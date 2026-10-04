<?php

namespace SilverstripeLtd\AiCompose\Services;

use SilverstripeLtd\AiCompose\ValueObjects\ComposeGenerationResult;
use SilverstripeLtd\AiCore\Completion\JsonCompletion;
use SilverstripeLtd\AiCore\Provider\ProviderException;

/**
 * Parses and validates structured compose responses from AI providers.
 */
class ComposeResponseParser
{
    /**
     * Parses the JSON response from the AI provider, tolerating Markdown fences or surrounding prose.
     */
    public function parse(string $providerResponse): ComposeGenerationResult
    {
        $decodedResponse = JsonCompletion::decode($providerResponse);
        if ($decodedResponse === null) {
            throw new ProviderException('AI provider response was not valid JSON');
        }

        if (!is_array($decodedResponse) || array_is_list($decodedResponse)) {
            throw new ProviderException('AI provider response was not a JSON object');
        }

        $title = $decodedResponse['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            throw new ProviderException('AI provider response missing title');
        }

        $content = $decodedResponse['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new ProviderException('AI provider response missing content');
        }
        return new ComposeGenerationResult(trim($title), trim($content));
    }
}
