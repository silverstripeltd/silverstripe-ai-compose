<?php

namespace SilverstripeLtd\AiCompose\Controllers;

use Psr\Log\LoggerInterface;
use SilverstripeLtd\AiCompose\Exceptions\AIProviderException;
use SilverstripeLtd\AiCompose\Exceptions\ComposeApplyException;
use SilverstripeLtd\AiCompose\Extensions\AiComposeExtension;
use SilverstripeLtd\AiCompose\Forms\ComposeForm;
use SilverstripeLtd\AiCompose\Services\ComposeApplyService;
use SilverstripeLtd\AiCompose\Services\ComposeContentSanitisationService;
use SilverstripeLtd\AiCompose\Services\ComposeGenerationService;
use SilverStripe\Admin\FormSchemaController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Versioned\Versioned;

/**
 * Serves schema, generate, and apply responses for the CMS compose modal.
 */
class ComposeController extends FormSchemaController
{
    private const STALE_SECURITY_TOKEN_MESSAGE = 'Session timed out, please refresh and try again.';

    private static $url_segment = 'ai-compose';

    private static $menu_title = 'Compose';

    private static $menu_priority = -1;

    private static $url_handlers = [
        'GET schema/$ID' => 'schema',
        'POST generate/$ID' => 'generate',
        'POST apply/$ID' => 'apply',
    ];

    private static $allowed_actions = [
        'schema',
        'generate',
        'apply',
    ];

    /**
     * Returns the client-side endpoint and modal config consumed by the CMS boot code.
     */
    public function getClientConfig(): array
    {
        $config = parent::getClientConfig();
        $className = 'ai-compose-modal';
        $modalSelector = '.' . implode('.', preg_split('/\s+/', trim($className)));
        $config['form']['aiCompose'] = [
            'schemaUrl' => $this->Link('schema'),
            'generateUrl' => $this->Link('generate'),
            'applyUrl' => $this->Link('apply'),
            'className' => $className,
            'modalClassName' => $className,
            'modalSelector' => $modalSelector,
            'size' => 'xl',
        ];
        return $config;
    }

    /**
     * Returns the modal schema payload and compose metadata for a record.
     */
    public function schema(HTTPRequest $request): HTTPResponse
    {
        try {
            $record = $this->resolveRecordFromRequest($request);
            $form = ComposeForm::createForRecord($this, $record);
            return $this->getSchemaResponse(
                $request->getURL(),
                $form,
                null,
                ['meta' => $this->buildSchemaMeta($record, $form)]
            );
        } catch (HTTPResponse_Exception $exception) {
            return $exception->getResponse();
        }
    }

    /**
     * Generates page content from the supplied objective and substance.
     */
    public function generate(HTTPRequest $request): HTTPResponse
    {
        $record = null;
        try {
            $this->requireValidSecurityToken($request);
            $record = $this->resolveRecordFromRequest($request);
            $payload = $this->resolvePayload($request);
            $objective = $this->resolveStringPayloadValue($payload, 'objective');
            $substance = $this->resolveStringPayloadValue($payload, 'substance');
            if ($objective === '' && $substance === '') {
                $this->failRequest(400, ComposeForm::EMPTY_INPUT_MESSAGE);
            }
            $result = $this->getGenerationService()->generate($objective, $substance);
            $sanitisationService = $this->getContentSanitisationService();
            return $this->jsonResponse([
                'generatedTitle' => $sanitisationService->sanitiseTitle($result->getTitle()),
                'generatedContent' => $sanitisationService->sanitiseHtml($result->getContent()),
            ]);
        } catch (HTTPResponse_Exception $exception) {
            return $exception->getResponse();
        } catch (AIProviderException $exception) {
            $this->logProviderException($exception, $record ?? null);
            return $this->jsonResponse([
                'error' => $this->getProviderErrorMessage($exception),
            ], 500);
        }
    }

    /**
     * Applies generated content to the record's draft fields.
     */
    public function apply(HTTPRequest $request): HTTPResponse
    {
        try {
            $this->requireValidSecurityToken($request);
            $record = $this->resolveRecordFromRequest($request);
            $payload = $this->resolvePayload($request);
            $title = $this->resolveStringPayloadValue($payload, 'title');
            $content = $this->resolveStringPayloadValue($payload, 'content');
            if ($title === '' || $content === '') {
                $this->failRequest(400, ComposeForm::INVALID_APPLY_PAYLOAD_MESSAGE);
            }
            $this->withDraftStage(
                $record,
                fn(DataObject $draftRecord): mixed => $this->getApplyService()->apply($draftRecord, $title, $content)
            );
            return $this->jsonResponse([
                'applied' => true,
                'reloadRequired' => true,
            ]);
        } catch (HTTPResponse_Exception $exception) {
            return $exception->getResponse();
        } catch (ComposeApplyException $exception) {
            return $this->jsonResponse([
                'error' => $exception->getMessage(),
            ], 400);
        }
    }

    /**
     * Builds the extra modal metadata that the React UI reads from the schema response.
     */
    private function buildSchemaMeta(DataObject $record, Form $form): array
    {
        $generateUrl = $this->Link(sprintf(
            'generate/%d?fqcn=%s',
            $record->ID,
            rawurlencode($record->ClassName)
        ));
        $applyUrl = $this->Link(sprintf(
            'apply/%d?fqcn=%s',
            $record->ID,
            rawurlencode($record->ClassName)
        ));
        $hasElemental = $this->getApplyService()->hasElementalTarget($record);
        $supportsApply = $this->getApplyService()->supportsApply($record);
        $warningMessage = $this->getApplyService()->getWarningMessage($record);
        return [
            'aiCompose' => [
                'title' => ComposeForm::MODAL_TITLE,
                'record' => [
                    'id' => $record->ID,
                    'fqcn' => $record->ClassName,
                ],
                'generateUrl' => $generateUrl,
                'applyUrl' => $applyUrl,
                'supportsApply' => $supportsApply,
                'hasElemental' => $hasElemental,
                'labels' => [
                    'generate' => ComposeForm::GENERATE_BUTTON_LABEL,
                    'regenerate' => ComposeForm::REGENERATE_BUTTON_LABEL,
                    'apply' => ComposeForm::APPLY_BUTTON_LABEL,
                    'objective' => ComposeForm::OBJECTIVE_LABEL,
                    'substance' => ComposeForm::SUBSTANCE_LABEL,
                    'generatedTitle' => ComposeForm::GENERATED_TITLE_LABEL,
                    'generatedContent' => ComposeForm::GENERATED_CONTENT_LABEL,
                    'copy' => ComposeForm::COPY_BUTTON_LABEL,
                ],
                'messages' => [
                    'warning' => $warningMessage,
                    'generateSuccess' => ComposeForm::GENERATE_SUCCESS_MESSAGE,
                    'generateFailure' => ComposeForm::GENERATE_FAILURE_MESSAGE,
                    'applySuccess' => ComposeForm::APPLY_SUCCESS_MESSAGE,
                    'applyFailure' => ComposeForm::APPLY_FAILURE_MESSAGE,
                    'emptyInputs' => ComposeForm::EMPTY_INPUT_MESSAGE,
                    'blockClassError' => ComposeApplyService::BLOCK_NOT_ALLOWED_MESSAGE,
                ],
                'form' => [
                    'name' => $form->getName(),
                    'action' => $form->FormAction(),
                    'fields' => [
                        'objective' => 'Objective',
                        'substance' => 'Substance',
                    ],
                ],
                'errors' => [
                    'provider' => [
                        'mode' => $this->shouldExposeProviderErrors() ? 'development' : 'generic',
                        'genericMessage' => ComposeForm::PROVIDER_ERROR_MESSAGE,
                    ],
                ],
                'state' => [
                    'supportsApply' => $supportsApply,
                    'hasElemental' => $hasElemental,
                    'storesResultsServerSide' => false,
                ],
            ],
        ];
    }

    /**
     * Resolves the shared sanitisation service for preview and apply flows.
     */
    private function getContentSanitisationService(): ComposeContentSanitisationService
    {
        return ComposeContentSanitisationService::create();
    }

    /**
     * Resolves the current record from the request and checks edit access.
     */
    private function resolveRecordFromRequest(HTTPRequest $request): DataObject
    {
        $fqcn = urldecode((string) ($request->getVar('fqcn') ?: $request->param('FQCN')));
        $id = (int) ($request->param('ID') ?: $request->param('ItemID'));

        if ($fqcn === '' || $id <= 0) {
            $this->failRequest(400, 'Invalid request parameters');
        }

        if (!class_exists($fqcn)
            || !is_a($fqcn, DataObject::class, true)
            || !DataObject::has_extension($fqcn, AiComposeExtension::class)) {
            $this->failRequest(400, 'Invalid record class');
        }

        $record = DataObject::get($fqcn)->byID($id);
        if (!$record) {
            $this->failRequest(404, 'Record not found');
        }

        if (!$record->canEdit()) {
            $this->failRequest(403, 'Access denied');
        }
        return $record;
    }

    /**
     * Rejects stale or missing SecurityID values before any provider call or write runs.
     */
    private function requireValidSecurityToken(HTTPRequest $request): void
    {
        if (!SecurityToken::inst()->checkRequest($request)) {
            $this->failRequest(403, ComposeController::STALE_SECURITY_TOKEN_MESSAGE);
        }
    }

    /**
     * Resolves request payload data from either JSON or normal form posts.
     *
     * @return array<string, mixed>
     */
    private function resolvePayload(HTTPRequest $request): array
    {
        $contentType = strtolower((string) $request->getHeader('Content-Type'));
        $body = (string) $request->getBody();
        $looksLikeJson = str_contains($contentType, 'application/json')
            || preg_match('/^\s*[{[]/', $body) === 1;
        if ($looksLikeJson) {
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                $this->failRequest(400, 'Invalid request payload');
            }
            return $decoded;
        }
        return $request->postVars() ?: $request->requestVars();
    }

    /**
     * Resolves one string payload value and trims surrounding whitespace.
     *
     * @param array<string, mixed> $payload
     */
    private function resolveStringPayloadValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Returns the generation service used to build prompts and parse provider output.
     */
    private function getGenerationService(): ComposeGenerationService
    {
        return Injector::inst()->get(ComposeGenerationService::class);
    }

    /**
     * Returns the draft write-back service used by the apply endpoint.
     */
    private function getApplyService(): ComposeApplyService
    {
        return Injector::inst()->get(ComposeApplyService::class);
    }

    /**
     * Runs a callback against the draft stage version of a versioned record.
     */
    private function withDraftStage(DataObject $record, callable $callback): mixed
    {
        if (!$record->hasExtension(Versioned::class)) {
            return $callback($record);
        }
        return Versioned::withVersionedMode(function () use ($record, $callback): mixed {
            Versioned::set_stage(Versioned::DRAFT);

            $draftRecord = DataObject::get($record->ClassName)->byID($record->ID) ?: $record;
            return $callback($draftRecord);
        });
    }

    /**
     * Chooses the provider error message that is safe to expose to the current environment.
     */
    private function getProviderErrorMessage(AIProviderException $exception): string
    {
        if ($this->shouldExposeProviderErrors()) {
            return $exception->getMessage();
        }
        return ComposeForm::PROVIDER_ERROR_MESSAGE;
    }

    /**
     * Limits raw provider errors to development requests outside the PHPUnit runtime.
     */
    private function shouldExposeProviderErrors(): bool
    {
        $runningTests = defined('PHPUNIT_COMPOSER_INSTALL');
        return Director::isDev() && !$runningTests;
    }

    /**
     * Logs the original provider exception with record context for debugging.
     */
    private function logProviderException(AIProviderException $exception, ?DataObject $record): void
    {
        $this->getLogger()->error('Compose provider request failed', [
            'exception' => $exception,
            'recordClass' => $record?->ClassName,
            'recordId' => $record?->ID,
        ]);
    }

    /**
     * Returns the module logger used for provider diagnostics.
     */
    private function getLogger(): LoggerInterface
    {
        return Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * Builds the JSON response used by the modal schema, generate, and apply endpoints.
     *
     * @param array<string, mixed> $body
     */
    private function jsonResponse(array $body, int $code = 200): HTTPResponse
    {
        return HTTPResponse::create((string) json_encode($body, JSON_UNESCAPED_SLASHES), $code)
            ->addHeader('Content-Type', 'application/json');
    }

    /**
     * Throws a JSON HTTP error response.
     */
    private function failRequest(int $statusCode, string $message): never
    {
        throw new HTTPResponse_Exception($this->jsonResponse(['error' => $message], $statusCode));
    }
}
