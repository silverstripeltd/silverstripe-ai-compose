<?php

namespace SilverstripeLtd\AiCompose\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;

/**
 * Adds compose toolbar context to editable saved records.
 */
class AiComposeExtension extends Extension
{
    /**
     * Adds the hidden record-class field used by the CMS button adapter.
     */
    public function updateCMSFields(FieldList $fields): void
    {
        if (!$this->owner->exists() || !$this->owner->canEdit()) {
            return;
        }

        if ($fields->dataFieldByName('AiComposeRecordClass')) {
            return;
        }

        $fields->push(HiddenField::create(
            'AiComposeRecordClass',
            null,
            $this->owner->ClassName
        ));
    }
}
