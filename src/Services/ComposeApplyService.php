<?php

namespace SilverstripeLtd\AiCompose\Services;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementContent;
use DNADesign\Elemental\Models\ElementalArea;
use SilverstripeLtd\AiCompose\Exceptions\ComposeApplyException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBText;

/**
 * Applies generated compose output to draft records.
 */
class ComposeApplyService
{
    use Configurable;

    public const CONTENT_WARNING_MESSAGE = 'Applying will overwrite the page title and content with the'
        . ' generated text.';
    public const ELEMENTAL_WARNING_MESSAGE = 'Applying will overwrite the page title and create a new'
        . ' content block.';
    public const BLOCK_NOT_ALLOWED_MESSAGE = 'The configured content block type is not allowed on this'
        . ' page. Update the default_content_block_class setting in your project YML configuration.';
    public const INVALID_BLOCK_CLASS_MESSAGE = 'The configured content block type is invalid. Update the'
        . ' default_content_block_class setting in your project YML configuration.';
    public const UNSUPPORTED_CONTENT_FIELD_MESSAGE = 'The configured content block type has no supported'
        . ' content field. Update the default_content_block_class setting in your project YML'
        . ' configuration.';

    private static $default_content_block_class = ElementContent::class;

    /**
     * Reports whether a record can receive generated content.
     */
    public function supportsApply(DataObject $record): bool
    {
        return $this->hasElementalTarget($record) || $record->hasField('Content');
    }

    /**
     * Reports whether a record should apply content via Elemental.
     */
    public function hasElementalTarget(DataObject $record): bool
    {
        return $this->getFirstElementalRelationName($record) !== '';
    }

    /**
     * Returns the contextual warning shown in the modal before apply.
     */
    public function getWarningMessage(DataObject $record): string
    {
        return $this->hasElementalTarget($record)
            ? ComposeApplyService::ELEMENTAL_WARNING_MESSAGE
            : ComposeApplyService::CONTENT_WARNING_MESSAGE;
    }

    /**
     * Applies generated title and content to the record's draft fields.
     */
    public function apply(DataObject $record, string $title, string $content): void
    {
        $sanitisationService = $this->getContentSanitisationService();
        $sanitisedTitle = $sanitisationService->sanitiseTitle($title);
        $sanitisedContent = $sanitisationService->sanitiseHtml($content);
        if ($sanitisedTitle === '' || trim(strip_tags($sanitisedContent)) === '') {
            throw new ComposeApplyException('Invalid apply request payload');
        }
        $record->setField('Title', $sanitisedTitle);
        if ($this->hasElementalTarget($record)) {
            $this->applyToElementalArea($record, $sanitisedContent);
        } elseif ($record->hasField('Content')) {
            $record->setField('Content', $sanitisedContent);
        } else {
            throw new ComposeApplyException('This record does not support AI compose apply.');
        }
        $record->write();
    }

    /**
     * Applies generated content by appending a new block to the first Elemental area.
     */
    private function applyToElementalArea(DataObject $record, string $content): void
    {
        $relation = $this->getFirstElementalRelationName($record);
        if ($relation === '') {
            throw new ComposeApplyException('This record does not support AI compose apply.');
        }

        $area = $this->resolveElementalArea($record, $relation);
        if (!$area || !$area->exists()) {
            throw new ComposeApplyException(ComposeApplyService::BLOCK_NOT_ALLOWED_MESSAGE);
        }

        $blockClass = $this->getConfiguredContentBlockClass();
        $this->validateConfiguredBlockClass($record, $blockClass);
        $contentField = $this->resolveContentField($blockClass);
        if ($contentField === '') {
            throw new ComposeApplyException(ComposeApplyService::UNSUPPORTED_CONTENT_FIELD_MESSAGE);
        }

        /** @var BaseElement $block */
        $block = $blockClass::create();
        if ($block->hasField('Title')) {
            $block->setField('Title', (string) $record->getField('Title'));
        }
        $block->setField($contentField, $content);
        $block->ParentID = $area->ID;
        $block->Sort = $this->getNextSortValue($area);
        $block->write();
    }

    /**
     * Resolves the configured content block class.
     */
    private function getConfiguredContentBlockClass(): string
    {
        $configured = ComposeApplyService::config()->get('default_content_block_class');
        return is_string($configured) && $configured !== ''
            ? $configured
            : ElementContent::class;
    }

    /**
     * Validates the configured block class against Elemental availability rules.
     */
    private function validateConfiguredBlockClass(DataObject $record, string $blockClass): void
    {
        if (!class_exists($blockClass) || !is_a($blockClass, BaseElement::class, true)) {
            throw new ComposeApplyException(ComposeApplyService::INVALID_BLOCK_CLASS_MESSAGE);
        }

        if (!$this->isBlockClassAllowed($record, $blockClass)) {
            throw new ComposeApplyException(ComposeApplyService::BLOCK_NOT_ALLOWED_MESSAGE);
        }
    }

    /**
     * Checks whether the configured block class can be created on this record.
     */
    private function isBlockClassAllowed(DataObject $record, string $blockClass): bool
    {
        if ($record->hasMethod('getElementalTypes')) {
            $availableTypes = $record->getElementalTypes();
            if (is_array($availableTypes)) {
                return array_key_exists($blockClass, $availableTypes);
            }
        }
        if (!$this->isBlockClassAllowedByConfig($record, $blockClass)) {
            return false;
        }
        return $this->passesBlockCreateChecks($blockClass);
    }

    /**
     * Checks the page-level Elemental allow and deny lists for one block class.
     */
    private function isBlockClassAllowedByConfig(DataObject $record, string $blockClass): bool
    {
        $config = Config::forClass($record->ClassName);
        $allowedElements = $config->get('allowed_elements');
        if (is_array($allowedElements) && !in_array($blockClass, $allowedElements, true)) {
            return false;
        }

        $disallowedElements = $config->get('disallowed_elements');
        if (is_array($disallowedElements) && in_array($blockClass, $disallowedElements, true)) {
            return false;
        }
        return true;
    }

    /**
     * Checks whether the configured block passes Elemental creation permission hooks.
     */
    private function passesBlockCreateChecks(string $blockClass): bool
    {
        /** @var BaseElement $block */
        $block = singleton($blockClass);
        if (!$block->canCreate()) {
            return false;
        }
        if ($block->hasMethod('canCreateElement') && !$block->canCreateElement()) {
            return false;
        }
        return true;
    }

    /**
     * Returns the first Elemental relation name, if the record has one.
     */
    private function getFirstElementalRelationName(DataObject $record): string
    {
        if ($record->hasMethod('getElementalRelations')) {
            $relations = $record->getElementalRelations();
            if (is_array($relations)) {
                foreach ($relations as $relation) {
                    if (is_string($relation) && $relation !== '') {
                        return $relation;
                    }
                }
            }
        }

        foreach ($record->hasOne() as $relation => $className) {
            if (is_string($relation) && is_a($className, ElementalArea::class, true)) {
                return $relation;
            }
        }
        return '';
    }

    /**
     * Resolves the first Elemental area, creating it through the owning extension when needed.
     */
    private function resolveElementalArea(DataObject $record, string $relation): ?ElementalArea
    {
        $field = $relation . 'ID';
        $area = $record->$relation();
        if ($area instanceof ElementalArea && $area->exists()) {
            return $area;
        }

        $relationClass = $record->hasOne()[$relation] ?? ElementalArea::class;
        if (!is_a($relationClass, ElementalArea::class, true)) {
            return null;
        }

        /** @var ElementalArea $area */
        $area = $relationClass::create();
        $area->OwnerClassName = $record->ClassName;
        $area->write();
        $record->setField($field, $area->ID);
        return $area;
    }

    /**
     * Determines which block field should receive generated content.
     */
    private function resolveContentField(string $blockClass): string
    {
        $singleton = singleton($blockClass);
        if (is_a($blockClass, ElementContent::class, true) && $singleton->hasField('HTML')) {
            return 'HTML';
        }

        $databaseFields = DataObject::getSchema()->databaseFields($blockClass);
        foreach ($databaseFields as $fieldName => $fieldSpec) {
            $field = DBField::create_field($fieldSpec, '', $fieldName);
            if ($field instanceof DBHTMLText) {
                return $fieldName;
            }
        }
        foreach ($databaseFields as $fieldName => $fieldSpec) {
            $field = DBField::create_field($fieldSpec, '', $fieldName);
            if ($field instanceof DBText) {
                return $fieldName;
            }
        }
        return '';
    }

    /**
     * Calculates the sort value needed to append a new block after existing ones.
     */
    private function getNextSortValue(ElementalArea $area): int
    {
        return ((int) $area->Elements()->max('Sort')) + 1;
    }

    /**
     * Resolves the shared sanitisation service for preview and apply flows.
     */
    private function getContentSanitisationService(): ComposeContentSanitisationService
    {
        return ComposeContentSanitisationService::create();
    }
}
