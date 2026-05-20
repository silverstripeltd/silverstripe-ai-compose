<?php

namespace SilverstripeLtd\AiCompose\Forms;

use SilverstripeLtd\AiCompose\Controllers\ComposeController;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataObject;

/**
 * Builds the server-side schema for the compose modal.
 */
class ComposeForm extends Form
{
    public const FORM_NAME_TEMPLATE = 'ComposeForm_%s';
    public const MODAL_TITLE = 'Compose page content with AI';
    public const OBJECTIVE_LABEL = 'Purpose & Format';
    public const OBJECTIVE_PLACEHOLDER = 'Describe what you want to create, who the target audience is,'
        . ' and the style of the page (e.g. a community notice, an event summary, or an internal'
        . ' policy update).';
    public const SUBSTANCE_LABEL = 'Facts & Background';
    public const SUBSTANCE_PLACEHOLDER = 'Supply the raw data, bullet points, dates, and core details'
        . ' that must be included. The AI will use this as its single source of truth to ensure'
        . ' accuracy.';
    public const GENERATED_TITLE_LABEL = 'Generated Title';
    public const GENERATED_CONTENT_LABEL = 'Generated Content';
    public const GENERATE_BUTTON_LABEL = 'Generate';
    public const REGENERATE_BUTTON_LABEL = 'Regenerate';
    public const APPLY_BUTTON_LABEL = 'Apply to page';
    public const COPY_BUTTON_LABEL = 'Copy to clipboard';
    public const GENERATE_SUCCESS_MESSAGE = 'Content generated successfully';
    public const GENERATE_FAILURE_MESSAGE = 'Unable to generate content';
    public const APPLY_SUCCESS_MESSAGE = 'Content applied to draft page';
    public const APPLY_FAILURE_MESSAGE = 'Unable to apply generated content';
    public const EMPTY_INPUT_MESSAGE = 'Enter a purpose or facts before generating';
    public const INVALID_APPLY_PAYLOAD_MESSAGE = 'Invalid apply request payload';
    public const PROVIDER_ERROR_MESSAGE = 'There was an error connecting to the AI provider';
    public const OBJECTIVE_ROWS = 6;
    public const SUBSTANCE_ROWS = 8;

    /**
     * Creates the modal form schema for one CMS record.
     */
    public static function createForRecord(ComposeController $controller, DataObject $record): ComposeForm
    {
        $fields = FieldList::create(
            TextareaField::create('Objective', ComposeForm::OBJECTIVE_LABEL)
                ->setRows(ComposeForm::OBJECTIVE_ROWS)
                ->setAttribute('placeholder', ComposeForm::OBJECTIVE_PLACEHOLDER)
                ->addExtraClass('ai-compose-modal__objective-field'),
            TextareaField::create('Substance', ComposeForm::SUBSTANCE_LABEL)
                ->setRows(ComposeForm::SUBSTANCE_ROWS)
                ->setAttribute('placeholder', ComposeForm::SUBSTANCE_PLACEHOLDER)
                ->addExtraClass('ai-compose-modal__substance-field')
        );

        $actions = FieldList::create(
            FormAction::create('AiComposeGenerateAction', ComposeForm::GENERATE_BUTTON_LABEL)
                ->setAttribute('type', 'button')
                ->setAttribute('data-schema-only', 'true')
        );

        /** @var ComposeForm $form */
        $form = ComposeForm::create(
            $controller,
            sprintf(ComposeForm::FORM_NAME_TEMPLATE, $record->ID),
            $fields,
            $actions
        );
        $form->setFormAction($controller->Link(sprintf(
            'generate/%d?fqcn=%s',
            $record->ID,
            rawurlencode($record->ClassName)
        )));
        $form->addExtraClass('form--no-dividers ai-compose-modal__schema');
        $form->loadDataFrom([
            'Objective' => '',
            'Substance' => '',
        ]);
        return $form;
    }
}
