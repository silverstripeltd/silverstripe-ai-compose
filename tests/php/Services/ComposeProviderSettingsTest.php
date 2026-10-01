<?php

namespace SilverstripeLtd\AiCompose\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverstripeLtd\AiCompose\Services\ComposeGenerationService;
use SilverstripeLtd\AiCompose\Services\ComposeResponseParser;
use SilverstripeLtd\AiCompose\Services\PromptService;
use SilverstripeLtd\AiCompose\Tests\ComposeTestSiteConfig;
use SilverstripeLtd\AiCore\Completion\SimpleCompletion;
use SilverstripeLtd\AiCore\Provider\ProviderException;
use SilverstripeLtd\AiCore\Provider\ProviderFactory;
use SilverstripeLtd\AiCore\Settings\EnvProviderSettings;
use SilverstripeLtd\AiCore\Testing\ScriptedProvider;
use SilverstripeLtd\AiCore\Testing\StubProviderFactory;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers the compose provider defaults, environment overrides and failure handling.
 */
class ComposeProviderSettingsTest extends SapphireTest
{
    private const ENV_NAMES = [
        'PROVIDER',
        'API_KEY',
        'MODEL',
        'MAX_TOKENS',
        'REQUEST_TIMEOUT',
        'TEMPERATURE',
        'THINKING_LEVEL',
    ];

    protected $usesDatabase = false;

    /**
     * @var array<string, mixed>
     */
    private array $originalEnv = [];

    /**
     * Clear the compose and shared provider variables before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        foreach ($this->getEnvVariables() as $name) {
            $this->originalEnv[$name] = Environment::getEnv($name);
            Environment::setEnv($name, null);
        }
    }

    /**
     * Restore the provider variables and the provider factory after each test.
     */
    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            Environment::setEnv($name, $value);
        }
        Injector::inst()->unregisterNamedObject(ProviderFactory::class);
        parent::tearDown();
    }

    /**
     * Confirms compose keeps its own creative defaults and defaults to Gemini.
     */
    public function testUsesComposeDefaults(): void
    {
        $settings = $this->getSettings();
        $this->assertSame('gemini', $settings->getProviderName());
        $this->assertSame('gemini-3.1-flash-lite', $settings->getModel());
        $this->assertSame(4000, $settings->getMaxTokens());
        $this->assertSame(30, $settings->getTimeoutSeconds());
        $this->assertSame(1.0, $settings->getTemperature());
        $this->assertSame('low', $settings->getThinkingLevel());
        $this->assertSame('gemini', $this->createService()->getCompletion()->getProvider()->getName());
    }

    /**
     * Supplies each provider with the default model and thinking level compose uses for it.
     *
     * @return array<string, array{string, string, string|null}>
     */
    public static function provideProviderDefaults(): array
    {
        return [
            'anthropic' => ['anthropic', 'claude-haiku-4-5', null],
            'openai' => ['openai', 'gpt-5-mini', null],
            'gemini' => ['gemini', 'gemini-3.1-flash-lite', 'low'],
        ];
    }

    /**
     * Confirms each provider receives its own default model and only Gemini a thinking level.
     */
    #[DataProvider('provideProviderDefaults')]
    public function testUsesPerProviderDefaults(string $provider, string $model, ?string $thinkingLevel): void
    {
        Environment::setEnv('AI_COMPOSE_PROVIDER', $provider);
        $options = $this->createService()->getCompletion()->getProvider()->getDefaultOptions();
        $this->assertSame($model, $options->model);
        $this->assertSame(4000, $options->maxTokens);
        $this->assertSame(30, $options->timeoutSeconds);
        $this->assertSame($thinkingLevel, $options->reasoningEffort);
    }

    /**
     * Confirms env overrides are honoured when they are provided.
     */
    public function testUsesConfiguredOverrides(): void
    {
        Environment::setEnv('AI_COMPOSE_MODEL', 'custom-model');
        Environment::setEnv('AI_COMPOSE_REQUEST_TIMEOUT', '45');
        Environment::setEnv('AI_COMPOSE_TEMPERATURE', '0.6');
        Environment::setEnv('AI_COMPOSE_THINKING_LEVEL', 'none');
        Environment::setEnv('AI_COMPOSE_MAX_TOKENS', '1234');
        $settings = $this->getSettings();
        $this->assertSame('custom-model', $settings->getModel());
        $this->assertSame(45, $settings->getTimeoutSeconds());
        $this->assertSame(0.6, $settings->getTemperature());
        $this->assertSame('none', $settings->getThinkingLevel());
        $this->assertSame(1234, $settings->getMaxTokens());
    }

    /**
     * Confirms the shared AI_* variables apply when no compose variable is set.
     */
    public function testFallsBackToSharedVariables(): void
    {
        Environment::setEnv('AI_PROVIDER', 'anthropic');
        Environment::setEnv('AI_API_KEY', 'shared-key');
        Environment::setEnv('AI_MODEL', 'shared-model');
        $settings = $this->getSettings();
        $this->assertSame('anthropic', $settings->getProviderName());
        $this->assertSame('shared-key', $settings->getApiKey());
        $this->assertSame('shared-model', $settings->getModel());
        $this->assertSame(4000, $settings->getMaxTokens());
    }

    /**
     * Confirms compose variables win over the shared ones.
     */
    public function testComposeVariablesWinOverSharedVariables(): void
    {
        Environment::setEnv('AI_PROVIDER', 'anthropic');
        Environment::setEnv('AI_API_KEY', 'shared-key');
        Environment::setEnv('AI_COMPOSE_PROVIDER', 'anthropic');
        Environment::setEnv('AI_COMPOSE_API_KEY', 'compose-key');
        $this->assertSame('compose-key', $this->getSettings()->getApiKey());
    }

    /**
     * Confirms a missing API key is blocking and raised before any request is sent.
     */
    public function testMissingApiKeyIsBlocking(): void
    {
        try {
            $this->createService()->generate('Write a council notice', 'Date: 15 March');
            $this->fail('Expected a provider exception');
        } catch (ProviderException $exception) {
            $this->assertTrue($exception->isBlocking());
            $this->assertFalse($exception->isTransient());
            $this->assertStringContainsString('AI_COMPOSE_API_KEY', $exception->getMessage());
        }
    }

    /**
     * Confirms unknown providers throw a blocking provider exception.
     */
    public function testThrowsForUnknownProvider(): void
    {
        Environment::setEnv('AI_COMPOSE_PROVIDER', 'unknown');
        try {
            $this->createService()->generate('Write a council notice', 'Date: 15 March');
            $this->fail('Expected a provider exception');
        } catch (ProviderException $exception) {
            $this->assertTrue($exception->isBlocking());
            $this->assertStringContainsString('unknown', $exception->getMessage());
        }
    }

    /**
     * Confirms an empty provider reply is a permanent failure.
     */
    public function testEmptyReplyThrows(): void
    {
        $this->registerProvider(new ScriptedProvider([ScriptedProvider::text('  ')]));
        try {
            $this->createService()->generate('Write a council notice', 'Date: 15 March');
            $this->fail('Expected a provider exception');
        } catch (ProviderException $exception) {
            $this->assertFalse($exception->isTransient());
            $this->assertFalse($exception->isBlocking());
        }
    }

    /**
     * Confirms transient failures are raised after a single request without retrying.
     */
    public function testTransientFailureDoesNotRetry(): void
    {
        $provider = new ScriptedProvider([
            static function (): never {
                throw ProviderException::transient('Rate limited', 429);
            },
            ScriptedProvider::text('{"title":"Unused","content":"<p>Unused</p>"}'),
        ]);
        $this->registerProvider($provider);
        try {
            $this->createService()->generate('Write a council notice', 'Date: 15 March');
            $this->fail('Expected a provider exception');
        } catch (ProviderException $exception) {
            $this->assertTrue($exception->isTransient());
        }
        $this->assertCount(1, $provider->getRequests());
        $this->assertSame(1, $provider->getRemainingCount());
    }

    /**
     * Returns the compose provider settings.
     */
    private function getSettings(): EnvProviderSettings
    {
        return EnvProviderSettings::forModule(ComposeGenerationService::SETTINGS_PREFIX);
    }

    /**
     * Builds a generation service using the compose settings and test prompts.
     */
    private function createService(): ComposeGenerationService
    {
        return new ComposeGenerationService(
            SimpleCompletion::create($this->getSettings()),
            new PromptService(new ComposeTestSiteConfig()),
            new ComposeResponseParser()
        );
    }

    /**
     * Routes every provider lookup to the given scripted provider.
     */
    private function registerProvider(ScriptedProvider $provider): void
    {
        Injector::inst()->registerService(new StubProviderFactory($provider), ProviderFactory::class);
    }

    /**
     * Returns the compose and shared provider variable names.
     *
     * @return array<int, string>
     */
    private function getEnvVariables(): array
    {
        $names = [];
        foreach (self::ENV_NAMES as $name) {
            $names[] = 'AI_COMPOSE_' . $name;
            $names[] = 'AI_' . $name;
        }
        return $names;
    }
}
