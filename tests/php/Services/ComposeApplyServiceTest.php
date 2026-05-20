<?php

namespace SilverstripeLtd\AiCompose\Tests\Services;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\ElementContent;
use DNADesign\Elemental\Models\ElementalArea;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverstripeLtd\AiCompose\Exceptions\ComposeApplyException;
use SilverstripeLtd\AiCompose\Services\ComposeApplyService;
use SilverstripeLtd\AiCompose\Tests\ComposeCustomHtmlBlock;
use SilverstripeLtd\AiCompose\Tests\ComposeCustomTextOnlyBlock;
use SilverstripeLtd\AiCompose\Tests\ComposePermissionControlledBlock;
use SilverstripeLtd\AiCompose\Tests\ComposeTestElementalPage;
use SilverstripeLtd\AiCompose\Tests\ComposeUnsupportedBlock;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * Covers non-Elemental and Elemental draft apply behaviour.
 */
class ComposeApplyServiceTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        ComposeTestElementalPage::class,
        ComposeCustomHtmlBlock::class,
        ComposeCustomTextOnlyBlock::class,
        ComposePermissionControlledBlock::class,
        ComposeUnsupportedBlock::class,
        ElementContent::class,
    ];

    protected static $required_extensions = [
        ComposeTestElementalPage::class => [
            ElementalPageExtension::class,
        ],
    ];

    /**
     * Logs in an admin so Elemental allowed-element checks can run.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
    }

    /**
     * Restores config state after apply tests.
     */
    protected function tearDown(): void
    {
        Config::modify()->set(
            ComposeApplyService::class,
            'default_content_block_class',
            ElementContent::class
        );
        Config::modify()->remove(ComposeTestElementalPage::class, 'allowed_elements');
        Config::modify()->remove(ComposeTestElementalPage::class, 'disallowed_elements');
        Config::modify()->set(ComposePermissionControlledBlock::class, 'allow_create', true);
        Config::modify()->set(ComposePermissionControlledBlock::class, 'allow_create_element', true);
        ElementalAreasExtension::reset();
        parent::tearDown();
    }

    /**
     * Confirms non-Elemental pages overwrite Title and Content with sanitised output.
     */
    public function testApplyOverwritesNonElementalContentAndSanitisesHtml(): void
    {
        $page = SiteTree::create([
            'Title' => 'Original title',
            'Content' => '<p>Original content</p>',
        ]);
        $page->write();

        $service = new ComposeApplyService();
        $service->apply(
            $page,
            '<strong>Updated title</strong>',
            '<script>alert(1)</script><p onclick="evil()">Safe copy</p>'
        );

        $page = DataObject::get(SiteTree::class)->byID($page->ID);

        $this->assertSame('Updated title', $page->Title);
        $this->assertStringContainsString('<p>Safe copy</p>', $page->Content);
        $this->assertStringNotContainsString('script', $page->Content);
        $this->assertStringNotContainsString('onclick', $page->Content);
    }

    /**
     * Confirms Elemental pages append a new block and still overwrite the page title.
     */
    public function testApplyAppendsElementalBlockAndWritesTitle(): void
    {
        $page = $this->createElementalPage();
        $page->ElementalArea()->Elements()->add(ElementContent::create([
            'HTML' => '<p>Existing block</p>',
        ]));

        $service = new ComposeApplyService();
        $service->apply($page, 'Updated Elemental title', '<h2>Heading</h2><p>Body copy</p>');

        /** @var ComposeTestElementalPage $page */
        $page = DataObject::get(ComposeTestElementalPage::class)->byID($page->ID);
        $elements = $page->ElementalArea()->Elements()->sort('Sort');
        /** @var ElementContent $newBlock */
        $newBlock = $elements->last();

        $this->assertSame('Updated Elemental title', $page->Title);
        $this->assertCount(2, $elements);
        $this->assertInstanceOf(ElementContent::class, $newBlock);
        $this->assertSame($page->ElementalAreaID, $newBlock->ParentID);
        $this->assertSame('Updated Elemental title', $newBlock->Title);
        $this->assertSame('<h2>Heading</h2><p>Body copy</p>', $newBlock->HTML);
        $this->assertGreaterThan(0, $newBlock->Sort);
    }

    /**
     * Confirms custom Elemental block classes prefer the first DBHTMLText field.
     */
    public function testApplyUsesFirstDbHtmlTextFieldOnCustomBlockClass(): void
    {
        $this->allowElementTypes([ComposeCustomHtmlBlock::class]);
        Config::modify()->set(
            ComposeApplyService::class,
            'default_content_block_class',
            ComposeCustomHtmlBlock::class
        );
        $page = $this->createElementalPage();

        $service = new ComposeApplyService();
        $service->apply($page, 'Updated title', '<p>HTML block body</p>');

        /** @var ComposeCustomHtmlBlock $block */
        $block = DataObject::get(ComposeCustomHtmlBlock::class)->first();

        $this->assertSame('Updated title', $block->Title);
        $this->assertSame('<p>HTML block body</p>', $block->PrimaryHTML);
        $this->assertSame('', (string) $block->SummaryText);
    }

    /**
     * Confirms custom Elemental block classes fall back to the first DBText field when needed.
     */
    public function testApplyFallsBackToFirstDbTextFieldWhenNoDbHtmlTextFieldExists(): void
    {
        $this->allowElementTypes([ComposeCustomTextOnlyBlock::class]);
        Config::modify()->set(
            ComposeApplyService::class,
            'default_content_block_class',
            ComposeCustomTextOnlyBlock::class
        );
        $page = $this->createElementalPage();

        $service = new ComposeApplyService();
        $service->apply($page, 'Updated title', '<p>Plain text block body</p>');

        /** @var ComposeCustomTextOnlyBlock $block */
        $block = DataObject::get(ComposeCustomTextOnlyBlock::class)->first();

        $this->assertSame('Updated title', $block->Title);
        $this->assertSame('<p>Plain text block body</p>', $block->BodyText);
    }

    /**
     * Supplies misconfigured Elemental block classes that should be rejected.
     *
     * @return array<string, array{className: string, allowed: array<int, string>, message: string}>
     */
    public static function provideApplyRejectsConfiguredBlockClassFailures(): array
    {
        return [
            'invalid-class' => [
                'className' => SiteTree::class,
                'allowed' => [ElementContent::class],
                'message' => ComposeApplyService::INVALID_BLOCK_CLASS_MESSAGE,
            ],
            'not-allowed' => [
                'className' => ComposeCustomHtmlBlock::class,
                'allowed' => [ElementContent::class],
                'message' => ComposeApplyService::BLOCK_NOT_ALLOWED_MESSAGE,
            ],
            'no-supported-field' => [
                'className' => ComposeUnsupportedBlock::class,
                'allowed' => [ComposeUnsupportedBlock::class],
                'message' => ComposeApplyService::UNSUPPORTED_CONTENT_FIELD_MESSAGE,
            ],
        ];
    }

    /**
     * Confirms configured block class validation failures return the expected guidance.
     *
     * @param array<int, string> $allowed
     */
    #[DataProvider('provideApplyRejectsConfiguredBlockClassFailures')]
    public function testApplyRejectsConfiguredBlockClassFailures(
        string $className,
        array $allowed,
        string $message
    ): void {
        $this->allowElementTypes($allowed);
        Config::modify()->set(
            ComposeApplyService::class,
            'default_content_block_class',
            $className
        );
        $page = $this->createElementalPage();

        $service = new ComposeApplyService();

        $this->expectException(ComposeApplyException::class);
        $this->expectExceptionMessage($message);
        $service->apply($page, 'Updated title', '<p>Generated content</p>');
    }

    /**
     * Supplies Elemental creation permission combinations that should block apply.
     *
     * @return array<string, array{allowCreate: bool, allowCreateElement: bool}>
     */
    public static function provideApplyRejectsConfiguredBlockClassWhenElementCreationPermissionsDenyIt(): array
    {
        return [
            'can-create-denied' => [
                'allowCreate' => false,
                'allowCreateElement' => true,
            ],
            'can-create-element-denied' => [
                'allowCreate' => true,
                'allowCreateElement' => false,
            ],
        ];
    }

    /**
     * Confirms apply honours Elemental block creation permissions for configured block classes.
     */
    #[DataProvider('provideApplyRejectsConfiguredBlockClassWhenElementCreationPermissionsDenyIt')]
    public function testApplyRejectsConfiguredBlockClassWhenElementCreationPermissionsDenyIt(
        bool $allowCreate,
        bool $allowCreateElement
    ): void {
        $this->allowElementTypes([ComposePermissionControlledBlock::class]);
        Config::modify()->set(
            ComposeApplyService::class,
            'default_content_block_class',
            ComposePermissionControlledBlock::class
        );
        $this->configurePermissionControlledBlock($allowCreate, $allowCreateElement);
        $page = $this->createElementalPage();
        $service = new ComposeApplyService();
        $this->expectException(ComposeApplyException::class);
        $this->expectExceptionMessage(ComposeApplyService::BLOCK_NOT_ALLOWED_MESSAGE);
        $service->apply($page, 'Updated title', '<p>Generated content</p>');
    }

    /**
     * Creates a saved Elemental page fixture.
     */
    private function createElementalPage(): ComposeTestElementalPage
    {
        $page = ComposeTestElementalPage::create([
            'Title' => 'Original title',
            'Content' => '<p>Original content</p>',
        ]);
        $page->write();
        if (!$page->ElementalAreaID) {
            $area = ElementalArea::create();
            $area->OwnerClassName = $page->ClassName;
            $area->write();
            $page->ElementalAreaID = $area->ID;
            $page->write();
        }
        return DataObject::get(ComposeTestElementalPage::class)->byID($page->ID);
    }

    /**
     * Restricts allowed Elemental types for the test page class.
     *
     * @param array<int, string> $allowed
     */
    private function allowElementTypes(array $allowed): void
    {
        Config::modify()->set(ComposeTestElementalPage::class, 'allowed_elements', $allowed);
        Config::modify()->set(ComposeTestElementalPage::class, 'disallowed_elements', []);
        ElementalAreasExtension::reset();
    }

    /**
     * Sets creation permission flags for the permission-controlled test block.
     */
    private function configurePermissionControlledBlock(bool $allowCreate, bool $allowCreateElement): void
    {
        Config::modify()->set(ComposePermissionControlledBlock::class, 'allow_create', $allowCreate);
        Config::modify()->set(ComposePermissionControlledBlock::class, 'allow_create_element', $allowCreateElement);
        ElementalAreasExtension::reset();
    }
}
