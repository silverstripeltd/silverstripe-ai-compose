<?php

namespace SilverstripeLtd\AiCompose\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverstripeLtd\AiCompose\Exceptions\AIProviderException;
use SilverstripeLtd\AiCompose\Services\ComposeGenerationService;
use SilverstripeLtd\AiCompose\Services\ComposeResponseParser;
use SilverstripeLtd\AiCompose\Services\PromptService;
use SilverstripeLtd\AiCompose\Tests\ComposeTestSiteConfig;
use SilverstripeLtd\AiCompose\Tests\Providers\StubProviderFactory;
use SilverstripeLtd\AiCompose\Tests\Providers\TestAIProvider;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers generation-time prompt wiring and response parsing.
 */
class ComposeGenerationServiceTest extends SapphireTest
{
    /**
     * Configure provider auth for generation tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Environment::setEnv('AI_COMPOSE_API_KEY', 'test-key');
    }

    /**
     * Reset provider auth after generation tests.
     */
    protected function tearDown(): void
    {
        Environment::setEnv('AI_COMPOSE_API_KEY', null);
        parent::tearDown();
    }

    /**
     * Confirms generation returns a structured title and content pair.
     */
    public function testGenerateReturnsStructuredResult(): void
    {
        $provider = new TestAIProvider([
            [
                'status' => 200,
                'body' => '{"title":"Generated title","content":"<p>Generated content</p>"}',
            ],
        ]);
        $service = new ComposeGenerationService(
            new StubProviderFactory($provider),
            new PromptService(new ComposeTestSiteConfig()),
            new ComposeResponseParser()
        );

        $result = $service->generate('Write a council notice', 'Date: 15 March');

        $this->assertSame('Generated title', $result->getTitle());
        $this->assertSame('<p>Generated content</p>', $result->getContent());
        $this->assertStringContainsString('Write a council notice', (string) $provider->getLastUserPrompt());
        $this->assertStringContainsString('Date: 15 March', (string) $provider->getLastUserPrompt());
    }

    /**
     * Supplies malformed provider responses that should be rejected.
     *
     * @return array<string, array{body: string, message: string}>
     */
    public static function provideGenerateRejectsMalformedProviderResponses(): array
    {
        return [
            'invalid-json' => [
                'body' => '{broken',
                'message' => 'not valid JSON',
            ],
            'non-object' => [
                'body' => '[]',
                'message' => 'not a JSON object',
            ],
            'missing-title' => [
                'body' => '{"content":"<p>Generated content</p>"}',
                'message' => 'missing title',
            ],
            'empty-title' => [
                'body' => '{"title":" ","content":"<p>Generated content</p>"}',
                'message' => 'missing title',
            ],
            'missing-content' => [
                'body' => '{"title":"Generated title"}',
                'message' => 'missing content',
            ],
        ];
    }

    /**
     * Confirms malformed provider responses are rejected.
     */
    #[DataProvider('provideGenerateRejectsMalformedProviderResponses')]
    public function testGenerateRejectsMalformedProviderResponses(string $body, string $message): void
    {
        $provider = new TestAIProvider([
            ['status' => 200, 'body' => $body],
        ]);
        $service = new ComposeGenerationService(
            new StubProviderFactory($provider),
            new PromptService(new ComposeTestSiteConfig()),
            new ComposeResponseParser()
        );

        $this->expectException(AIProviderException::class);
        $this->expectExceptionMessage($message);
        $service->generate('Write a council notice', 'Date: 15 March');
    }
}
