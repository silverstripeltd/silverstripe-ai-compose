<?php

namespace SilverstripeLtd\AiCompose\Tests\Providers;

use SilverstripeLtd\AiCompose\Exceptions\AIProviderException;
use SilverstripeLtd\AiCompose\Providers\AnthropicProvider;
use SilverstripeLtd\AiCompose\Providers\GeminiProvider;
use SilverstripeLtd\AiCompose\Providers\OpenAIProvider;
use SilverstripeLtd\AiCompose\Providers\ProviderFactory;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Ensures compose provider resolution respects environment configuration.
 */
class ProviderFactoryTest extends SapphireTest
{
    private TestAIProvider $geminiProvider;

    private TestAIProvider $openAiProvider;

    private TestAIProvider $anthropicProvider;

    /**
     * Register stub providers for provider factory tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->geminiProvider = new TestAIProvider([]);
        $this->openAiProvider = new TestAIProvider([]);
        $this->anthropicProvider = new TestAIProvider([]);

        Injector::inst()->registerService($this->geminiProvider, GeminiProvider::class);
        Injector::inst()->registerService($this->openAiProvider, OpenAIProvider::class);
        Injector::inst()->registerService($this->anthropicProvider, AnthropicProvider::class);
    }

    /**
     * Reset environment after provider tests.
     */
    protected function tearDown(): void
    {
        Environment::setEnv('AI_COMPOSE_PROVIDER', null);
        parent::tearDown();
    }

    /**
     * Confirms the factory defaults to Gemini when env is empty.
     */
    public function testDefaultsToGeminiWhenEnvEmpty(): void
    {
        Environment::setEnv('AI_COMPOSE_PROVIDER', '');
        $factory = new ProviderFactory();

        $this->assertSame($this->geminiProvider, $factory->getProvider());
    }

    /**
     * Confirms the factory returns OpenAI when configured.
     */
    public function testSelectsOpenAiProvider(): void
    {
        Environment::setEnv('AI_COMPOSE_PROVIDER', 'openai');
        $factory = new ProviderFactory();

        $this->assertSame($this->openAiProvider, $factory->getProvider());
    }

    /**
     * Confirms the factory returns Anthropic when configured.
     */
    public function testSelectsAnthropicProvider(): void
    {
        Environment::setEnv('AI_COMPOSE_PROVIDER', 'anthropic');
        $factory = new ProviderFactory();

        $this->assertSame($this->anthropicProvider, $factory->getProvider());
    }

    /**
     * Confirms unknown providers throw a provider exception.
     */
    public function testThrowsForUnknownProvider(): void
    {
        Environment::setEnv('AI_COMPOSE_PROVIDER', 'unknown');
        $factory = new ProviderFactory();

        $this->expectException(AIProviderException::class);
        $factory->getProvider();
    }
}
