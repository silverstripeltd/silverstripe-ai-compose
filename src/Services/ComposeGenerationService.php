<?php

namespace SilverstripeLtd\AiCompose\Services;

use SilverstripeLtd\AiCompose\ValueObjects\ComposeGenerationResult;
use SilverstripeLtd\AiCore\Completion\SimpleCompletion;
use SilverstripeLtd\AiCore\Provider\ProviderException;
use SilverstripeLtd\AiCore\Settings\EnvProviderSettings;
use SilverStripe\Core\Injector\Injector;

/**
 * Coordinates prompt building, the provider call, and response parsing.
 */
class ComposeGenerationService
{
    /**
     * Module part of the AI_COMPOSE_* provider environment variables and the YAML defaults key.
     */
    public const SETTINGS_PREFIX = 'COMPOSE';

    private SimpleCompletion $completion;

    private PromptService $promptService;

    private ComposeResponseParser $responseParser;

    /**
     * Builds the generation service with injectable dependencies.
     */
    public function __construct(
        ?SimpleCompletion $completion = null,
        ?PromptService $promptService = null,
        ?ComposeResponseParser $responseParser = null
    ) {
        $this->completion = $completion ?: SimpleCompletion::create(
            EnvProviderSettings::forModule(self::SETTINGS_PREFIX)
        );
        $this->promptService = $promptService ?: Injector::inst()->get(PromptService::class);
        $this->responseParser = $responseParser ?: Injector::inst()->get(ComposeResponseParser::class);
    }

    /**
     * Generates structured compose output for one objective and substance pair.
     *
     * @throws ProviderException
     */
    public function generate(string $objective, string $substance): ComposeGenerationResult
    {
        [$systemPrompt, $userPrompt] = $this->promptService->buildPrompts($objective, $substance);
        $providerResponse = $this->completion->complete($systemPrompt, $userPrompt);
        return $this->responseParser->parse($providerResponse);
    }

    /**
     * Returns the completion helper bound to the compose provider settings.
     */
    public function getCompletion(): SimpleCompletion
    {
        return $this->completion;
    }
}
