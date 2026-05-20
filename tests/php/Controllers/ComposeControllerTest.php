<?php

namespace SilverstripeLtd\AiCompose\Tests\Controllers;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use DNADesign\Elemental\Models\ElementContent;
use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use SilverstripeLtd\AiCompose\Controllers\ComposeController;
use SilverstripeLtd\AiCompose\Forms\ComposeForm;
use SilverstripeLtd\AiCompose\Providers\ProviderFactory;
use SilverstripeLtd\AiCompose\Services\ComposeApplyService;
use SilverstripeLtd\AiCompose\Tests\ComposeTestElementalPage;
use SilverstripeLtd\AiCompose\Tests\Providers\StubProviderFactory;
use SilverstripeLtd\AiCompose\Tests\Providers\TestAIProvider;
use SilverstripeLtd\AiCompose\Tests\RestrictedComposePage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Covers compose schema and endpoint behaviour.
 */
class ComposeControllerTest extends FunctionalTest
{
    protected static $extra_dataobjects = [
        RestrictedComposePage::class,
        ComposeTestElementalPage::class,
        ElementContent::class,
        SiteConfig::class,
    ];

    protected static $required_extensions = [
        ComposeTestElementalPage::class => [
            ElementalPageExtension::class,
        ],
    ];

    /**
     * Seed auth and CSRF state for controller tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
        SecurityToken::enable();
        Environment::setEnv('AI_COMPOSE_API_KEY', 'test-key');
        $this->session()->set(SecurityToken::inst()->getName(), SecurityToken::inst()->getValue());
    }

    /**
     * Restore injector and config state after controller tests.
     */
    protected function tearDown(): void
    {
        Injector::inst()->registerService(new ProviderFactory(), ProviderFactory::class);
        Environment::setEnv('AI_COMPOSE_API_KEY', null);
        Config::modify()->set(
            ComposeApplyService::class,
            'default_content_block_class',
            ElementContent::class
        );
        Config::modify()->remove(ComposeTestElementalPage::class, 'allowed_elements');
        Config::modify()->remove(ComposeTestElementalPage::class, 'disallowed_elements');
        parent::tearDown();
    }

    /**
     * Confirms boot config exposes the schema, generate, and apply URLs.
     */
    public function testClientConfigIncludesComposeUrls(): void
    {
        $controller = ComposeController::create();
        $config = $controller->getClientConfig();

        $this->assertSame('ai-compose-modal', $config['form']['aiCompose']['className']);
        $this->assertSame('admin/ai-compose/schema', $config['form']['aiCompose']['schemaUrl']);
        $this->assertSame('admin/ai-compose/generate', $config['form']['aiCompose']['generateUrl']);
        $this->assertSame('admin/ai-compose/apply', $config['form']['aiCompose']['applyUrl']);
    }

    /**
     * Confirms the schema endpoint returns modal schema and non-Elemental metadata.
     */
    public function testSchemaEndpointReturnsSchemaAndMeta(): void
    {
        $page = SiteTree::create([
            'Title' => 'Compose page',
            'Content' => '<p>Existing content</p>',
        ]);
        $page->write();

        $response = $this->get(
            '/admin/ai-compose/schema/' . $page->ID . '?fqcn=' . rawurlencode(SiteTree::class),
            null,
            ['X-FormSchema-Request' => 'schema,state']
        );

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(ComposeForm::MODAL_TITLE, $payload['meta']['aiCompose']['title'] ?? null);
        $this->assertSame(
            'admin/ai-compose/generate/' . $page->ID . '?fqcn=' . rawurlencode(SiteTree::class),
            $payload['meta']['aiCompose']['generateUrl'] ?? null
        );
        $this->assertSame(
            'admin/ai-compose/apply/' . $page->ID . '?fqcn=' . rawurlencode(SiteTree::class),
            $payload['meta']['aiCompose']['applyUrl'] ?? null
        );
        $this->assertArrayNotHasKey('actions', $payload['meta']['aiCompose']);
        $this->assertSame(
            ComposeApplyService::CONTENT_WARNING_MESSAGE,
            $payload['meta']['aiCompose']['messages']['warning'] ?? null
        );
        $this->assertTrue($payload['meta']['aiCompose']['state']['supportsApply'] ?? false);
        $this->assertFalse($payload['meta']['aiCompose']['state']['hasElemental'] ?? true);

        $fieldNames = array_map(
            static fn(array $field): ?string => $field['name'] ?? null,
            $payload['schema']['fields'] ?? []
        );
        $actionNames = array_map(
            static fn(array $action): ?string => $action['name'] ?? null,
            $payload['schema']['actions'] ?? []
        );

        $this->assertContains('Objective', $fieldNames);
        $this->assertContains('Substance', $fieldNames);
        $this->assertContains('action_AiComposeGenerateAction', $actionNames);
    }

    /**
     * Confirms Elemental pages receive the append-style warning metadata.
     */
    public function testSchemaEndpointReturnsElementalWarningForElementalPages(): void
    {
        $page = ComposeTestElementalPage::create([
            'Title' => 'Compose Elemental page',
            'Content' => '<p>Existing content</p>',
        ]);
        $page->write();

        $response = $this->get(
            '/admin/ai-compose/schema/' . $page->ID . '?fqcn=' . rawurlencode(ComposeTestElementalPage::class),
            null,
            ['X-FormSchema-Request' => 'schema,state']
        );

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['meta']['aiCompose']['state']['hasElemental'] ?? false);
        $this->assertSame(
            ComposeApplyService::ELEMENTAL_WARNING_MESSAGE,
            $payload['meta']['aiCompose']['messages']['warning'] ?? null
        );
    }

    /**
     * Confirms schema rejects valid DataObject classes that do not have the compose extension.
     */
    public function testSchemaEndpointRejectsInvalidRecordClass(): void
    {
        $siteConfig = SiteConfig::current_site_config();
        $response = $this->get(
            '/admin/ai-compose/schema/' . $siteConfig->ID . '?fqcn=' . rawurlencode(SiteConfig::class),
            null,
            ['X-FormSchema-Request' => 'schema,state']
        );

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('Invalid record class', $payload['error'] ?? null);
    }

    /**
     * Confirms schema rejects records the current member cannot edit.
     */
    public function testSchemaEndpointRejectsAccessDenied(): void
    {
        $page = RestrictedComposePage::create([
            'Title' => 'Restricted compose page',
        ]);
        $page->write();

        $response = $this->get(
            '/admin/ai-compose/schema/' . $page->ID . '?fqcn=' . rawurlencode(RestrictedComposePage::class),
            null,
            ['X-FormSchema-Request' => 'schema,state']
        );

        $this->assertSame(403, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('Access denied', $payload['error'] ?? null);
    }

    /**
     * Confirms generate rejects empty inputs before any provider call is made.
     */
    public function testGenerateEndpointRejectsEmptyInputsWithoutCallingProvider(): void
    {
        $provider = new TestAIProvider([
            ['status' => 200, 'body' => '{"title":"Ignored","content":"<p>Ignored</p>"}'],
        ]);
        Injector::inst()->registerService(new StubProviderFactory($provider), ProviderFactory::class);
        $page = SiteTree::create(['Title' => 'Compose page']);
        $page->write();

        $response = $this->post(
            '/admin/ai-compose/generate/' . $page->ID . '?fqcn=' . rawurlencode(SiteTree::class),
            [
                'objective' => '',
                'substance' => '',
                SecurityToken::inst()->getName() => SecurityToken::inst()->getValue(),
            ],
            ['X-SecurityID' => SecurityToken::inst()->getValue()]
        );

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(ComposeForm::EMPTY_INPUT_MESSAGE, $payload['error'] ?? null);
        $this->assertSame(0, $provider->getCallCount());
    }

    /**
     * Confirms generate returns sanitised title and content for safe preview rendering.
     */
    public function testGenerateEndpointReturnsSanitisedStructuredResult(): void
    {
        $provider = new TestAIProvider([
            [
                'status' => 200,
                'body' => '{"title":"<strong>Generated title</strong>",'
                    . '"content":"<script>alert(1)</script><p onclick=\"evil()\">Generated content</p>"}',
            ],
        ]);
        Injector::inst()->registerService(new StubProviderFactory($provider), ProviderFactory::class);
        $page = SiteTree::create(['Title' => 'Compose page']);
        $page->write();

        $response = $this->post(
            '/admin/ai-compose/generate/' . $page->ID . '?fqcn=' . rawurlencode(SiteTree::class),
            [
                'objective' => 'Create a public update',
                'substance' => 'Date: 15 March',
                SecurityToken::inst()->getName() => SecurityToken::inst()->getValue(),
            ],
            ['X-SecurityID' => SecurityToken::inst()->getValue()]
        );

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('Generated title', $payload['generatedTitle'] ?? null);
        $this->assertSame('<p>Generated content</p>', $payload['generatedContent'] ?? null);
    }

    /**
     * Confirms malformed provider responses are surfaced as generic provider failures in tests.
     */
    public function testGenerateEndpointReturnsGenericProviderErrorForMalformedResponse(): void
    {
        $provider = new TestAIProvider([
            ['status' => 200, 'body' => '{broken'],
        ]);
        Injector::inst()->registerService(new StubProviderFactory($provider), ProviderFactory::class);
        $page = SiteTree::create(['Title' => 'Compose page']);
        $page->write();

        $response = $this->post(
            '/admin/ai-compose/generate/' . $page->ID . '?fqcn=' . rawurlencode(SiteTree::class),
            [
                'objective' => 'Create a public update',
                'substance' => 'Date: 15 March',
                SecurityToken::inst()->getName() => SecurityToken::inst()->getValue(),
            ],
            ['X-SecurityID' => SecurityToken::inst()->getValue()]
        );

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(ComposeForm::PROVIDER_ERROR_MESSAGE, $payload['error'] ?? null);
    }
}
