<?php

namespace SilverstripeLtd\AiCompose\Tests\Providers;

use SilverstripeLtd\AiCompose\Providers\AbstractAIProvider;

/**
 * Test provider with a scripted response queue.
 */
class TestAIProvider extends AbstractAIProvider
{
    /**
     * @var array<int, array{status: int, body: string}>
     */
    private array $responses;

    private int $callCount = 0;

    private ?string $lastSystemPrompt = null;

    private ?string $lastUserPrompt = null;

    /**
     * @param array<int, array{status: int, body: string}> $responses
     */
    public function __construct(array $responses)
    {
        parent::__construct();
        $this->responses = $responses;
    }

    /**
     * Returns the number of generate calls made against this provider.
     */
    public function getCallCount(): int
    {
        return $this->callCount;
    }

    /**
     * Returns the last system prompt seen by the provider.
     */
    public function getLastSystemPrompt(): ?string
    {
        return $this->lastSystemPrompt;
    }

    /**
     * Returns the last user prompt seen by the provider.
     */
    public function getLastUserPrompt(): ?string
    {
        return $this->lastUserPrompt;
    }

    /**
     * Exposes the resolved timeout for tests.
     */
    public function getResolvedTimeout(): int
    {
        return $this->getTimeout();
    }

    /**
     * Exposes the resolved temperature for tests.
     */
    public function getResolvedTemperature(): float
    {
        return $this->getTemperature();
    }

    /**
     * Exposes the resolved thinking level for tests.
     */
    public function getResolvedThinkingLevel(): string
    {
        return $this->getThinkingLevel();
    }

    /**
     * Exposes the resolved max token limit for tests.
     */
    public function getResolvedMaxTokens(): int
    {
        return $this->getMaxTokens();
    }

    /**
     * {@inheritDoc}
     */
    protected function performRequest(string $systemPrompt, string $userPrompt): array
    {
        $this->callCount++;
        $this->lastSystemPrompt = $systemPrompt;
        $this->lastUserPrompt = $userPrompt;
        $response = array_shift($this->responses);
        if ($response) {
            return $response;
        }
        return [
            'status' => 200,
            'body' => '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function extractResponseContent(string $body): string
    {
        return $body;
    }

    /**
     * {@inheritDoc}
     */
    protected function isTransientStatus(int $statusCode): bool
    {
        return $statusCode === 429 || $statusCode >= 500;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultModel(): string
    {
        return 'test-model';
    }
}
