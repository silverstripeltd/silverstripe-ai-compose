<?php

namespace SilverstripeLtd\AiCompose\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverstripeLtd\AiCompose\Services\ComposeGenerationService;
use SilverstripeLtd\AiCompose\Services\ComposeResponseParser;
use SilverstripeLtd\AiCompose\Services\PromptService;
use SilverstripeLtd\AiCompose\Tests\ComposeTestSiteConfig;
use SilverstripeLtd\AiCore\Completion\SimpleCompletion;
use SilverstripeLtd\AiCore\Provider\ProviderException;
use SilverstripeLtd\AiCore\Settings\EnvProviderSettings;
use SilverstripeLtd\AiCore\Testing\ScriptedProvider;
use SilverstripeLtd\AiCore\Testing\StubProviderFactory;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers generation-time prompt wiring and response parsing.
 */
class ComposeGenerationServiceTest extends SapphireTest
{
    /**
     * Confirms generation returns a structured title and content pair.
     */
    public function testGenerateReturnsStructuredResult(): void
    {
        $provider = new ScriptedProvider([
            ScriptedProvider::text('{"title":"Generated title","content":"<p>Generated content</p>"}'),
        ]);
        $service = $this->createService($provider);

        $result = $service->generate('Write a council notice', 'Date: 15 March');

        $this->assertSame('Generated title', $result->getTitle());
        $this->assertSame('<p>Generated content</p>', $result->getContent());
        $userPrompt = $provider->getLastRequest()->messages[0]->getText();
        $this->assertCount(1, $provider->getRequests());
        $this->assertStringContainsString('Write a council notice', $userPrompt);
        $this->assertStringContainsString('Date: 15 March', $userPrompt);
        $this->assertNotSame('', $provider->getLastRequest()->system);
    }

    /**
     * Supplies provider replies that wrap the JSON object in ways models commonly do.
     *
     * @return array<string, array{body: string}>
     */
    public static function provideGenerateAcceptsWrappedJson(): array
    {
        $json = '{"title":"Generated title","content":"<p>Generated content</p>"}';
        return [
            'plain-json' => [
                'body' => $json,
            ],
            'fenced-json' => [
                'body' => "```json\n" . $json . "\n```",
            ],
            'leading-sentence' => [
                'body' => "Here is the page you asked for:\n\n" . $json,
            ],
        ];
    }

    /**
     * Confirms fenced or prose-wrapped JSON replies still produce a structured result.
     */
    #[DataProvider('provideGenerateAcceptsWrappedJson')]
    public function testGenerateAcceptsWrappedJson(string $body): void
    {
        $service = $this->createService(new ScriptedProvider([ScriptedProvider::text($body)]));
        $result = $service->generate('Write a council notice', 'Date: 15 March');
        $this->assertSame('Generated title', $result->getTitle());
        $this->assertSame('<p>Generated content</p>', $result->getContent());
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
            'plain-text' => [
                'body' => 'Sorry, I cannot write that page.',
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
        $service = $this->createService(new ScriptedProvider([ScriptedProvider::text($body)]));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage($message);
        $service->generate('Write a council notice', 'Date: 15 March');
    }

    /**
     * Builds a generation service whose provider calls go to the given scripted provider.
     */
    private function createService(ScriptedProvider $provider): ComposeGenerationService
    {
        return new ComposeGenerationService(
            SimpleCompletion::create(
                EnvProviderSettings::forModule(ComposeGenerationService::SETTINGS_PREFIX),
                new StubProviderFactory($provider)
            ),
            new PromptService(new ComposeTestSiteConfig()),
            new ComposeResponseParser()
        );
    }
}
