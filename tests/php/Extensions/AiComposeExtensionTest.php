<?php

namespace SilverstripeLtd\AiCompose\Tests\Extensions;

use SilverstripeLtd\AiCompose\Tests\RestrictedComposePage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\HiddenField;

/**
 * Covers SiteTree compose extension behaviour.
 */
class AiComposeExtensionTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        RestrictedComposePage::class,
    ];

    /**
     * Logs in an admin so CMS field assertions can run.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
    }

    /**
     * Confirms saved editable records expose the hidden toolbar context field.
     */
    public function testUpdateCmsFieldsAddsToolbarContextForEditableSavedRecord(): void
    {
        $page = SiteTree::create(['Title' => 'Compose page']);
        $page->write();

        $fields = $page->getCMSFields();
        $recordClass = $fields->dataFieldByName('AiComposeRecordClass');

        $this->assertInstanceOf(HiddenField::class, $recordClass);
        $this->assertSame($page->ClassName, $recordClass->dataValue());
    }

    /**
     * Confirms unsaved records do not expose toolbar context.
     */
    public function testUpdateCmsFieldsSkipsUnsavedRecords(): void
    {
        $page = SiteTree::create(['Title' => 'Unsaved compose page']);

        $fields = $page->getCMSFields();

        $this->assertNull($fields->dataFieldByName('AiComposeRecordClass'));
    }

    /**
     * Confirms non-editable records do not expose toolbar context.
     */
    public function testUpdateCmsFieldsSkipsRestrictedRecords(): void
    {
        $page = RestrictedComposePage::create(['Title' => 'Restricted compose page']);
        $page->write();

        $fields = $page->getCMSFields();

        $this->assertNull($fields->dataFieldByName('AiComposeRecordClass'));
    }

    /**
     * Confirms the extension avoids pushing duplicate toolbar context fields.
     */
    public function testUpdateCmsFieldsAvoidsDuplicateToolbarContextField(): void
    {
        $page = SiteTree::create(['Title' => 'Compose page']);
        $page->write();

        $fields = $page->getCMSFields();
        $page->extend('updateCMSFields', $fields);
        $matches = array_filter(
            $fields->dataFields(),
            static fn($field): bool => $field->getName() === 'AiComposeRecordClass'
        );

        $this->assertCount(1, $matches);
    }
}
