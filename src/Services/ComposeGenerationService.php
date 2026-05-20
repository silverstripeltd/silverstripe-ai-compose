<?php

namespace SilverstripeLtd\AiCompose\Services;

use SilverstripeLtd\AiCompose\Providers\ProviderFactory;
use SilverstripeLtd\AiCompose\ValueObjects\ComposeGenerationResult;
use SilverStripe\Core\Injector\Injector;

/**
 * Coordinates prompt building, provider selection, and response parsing.
 */
class ComposeGenerationService
{
    private ProviderFactory $providerFactory;

    private PromptService $promptService;

    private ComposeResponseParser $responseParser;

    /**
     * Builds the generation service with injectable dependencies.
     */
    public function __construct(
        ?ProviderFactory $providerFactory = null,
        ?PromptService $promptService = null,
        ?ComposeResponseParser $responseParser = null
    ) {
        $this->providerFactory = $providerFactory ?: Injector::inst()->get(ProviderFactory::class);
        $this->promptService = $promptService ?: Injector::inst()->get(PromptService::class);
        $this->responseParser = $responseParser ?: Injector::inst()->get(ComposeResponseParser::class);
    }

    /**
     * Generates structured compose output for one objective and substance pair.
     */
    public function generate(string $objective, string $substance): ComposeGenerationResult
    {
        [$systemPrompt, $userPrompt] = $this->promptService->buildPrompts($objective, $substance);
        $providerResponse = $this->providerFactory->getProvider()->generate($systemPrompt, $userPrompt);
        return $this->responseParser->parse($providerResponse);
    }
}
