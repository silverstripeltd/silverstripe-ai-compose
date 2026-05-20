<?php

namespace SilverstripeLtd\AiCompose\Services;

use JsonException;
use SilverstripeLtd\AiCompose\Exceptions\AIProviderException;
use SilverstripeLtd\AiCompose\ValueObjects\ComposeGenerationResult;

/**
 * Parses and validates structured compose responses from AI providers.
 */
class ComposeResponseParser
{
    /**
     * Parses the raw JSON response from the AI provider.
     */
    public function parse(string $providerResponse): ComposeGenerationResult
    {
        try {
            $decodedResponse = json_decode($providerResponse, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AIProviderException('AI provider response was not valid JSON', false, false, 0, $exception);
        }

        if (!is_array($decodedResponse) || array_is_list($decodedResponse)) {
            throw new AIProviderException('AI provider response was not a JSON object');
        }

        $title = $decodedResponse['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            throw new AIProviderException('AI provider response missing title');
        }

        $content = $decodedResponse['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new AIProviderException('AI provider response missing content');
        }
        return new ComposeGenerationResult(trim($title), trim($content));
    }
}
