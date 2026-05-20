<?php

namespace SilverstripeLtd\AiCompose\Tests\Providers;

use SilverstripeLtd\AiCompose\Exceptions\AIProviderException;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

/**
 * Tests shared compose provider configuration logic.
 */
class AbstractAIProviderTest extends SapphireTest
{
    /**
     * Configure environment for provider tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Environment::setEnv('AI_COMPOSE_API_KEY', 'test-key');
    }

    /**
     * Reset environment after provider tests.
     */
    protected function tearDown(): void
    {
        Environment::setEnv('AI_COMPOSE_API_KEY', null);
        Environment::setEnv('AI_COMPOSE_REQUEST_TIMEOUT', null);
        Environment::setEnv('AI_COMPOSE_THINKING_LEVEL', null);
        Environment::setEnv('AI_COMPOSE_TEMPERATURE', null);
        Environment::setEnv('AI_COMPOSE_MAX_TOKENS', null);
        parent::tearDown();
    }

    /**
     * Confirms missing API keys throw provider exceptions.
     */
    public function testMissingApiKeyThrows(): void
    {
        Environment::setEnv('AI_COMPOSE_API_KEY', null);
        $provider = new TestAIProvider([
            ['status' => 200, 'body' => 'ok'],
        ]);

        $this->expectException(AIProviderException::class);
        $provider->generate('system', 'user');
    }

    /**
     * Confirms compose uses its own creative defaults when env overrides are absent.
     */
    public function testUsesComposeDefaults(): void
    {
        $provider = new TestAIProvider([
            ['status' => 200, 'body' => 'Generated output'],
        ]);

        $result = $provider->generate('system', 'user');

        $this->assertSame('Generated output', $result);
        $this->assertSame(30, $provider->getResolvedTimeout());
        $this->assertSame(1.0, $provider->getResolvedTemperature());
        $this->assertSame('low', $provider->getResolvedThinkingLevel());
        $this->assertSame(4000, $provider->getResolvedMaxTokens());
    }

    /**
     * Confirms env overrides are honoured when they are provided.
     */
    public function testUsesConfiguredOverrides(): void
    {
        Environment::setEnv('AI_COMPOSE_REQUEST_TIMEOUT', '45');
        Environment::setEnv('AI_COMPOSE_TEMPERATURE', '0.6');
        Environment::setEnv('AI_COMPOSE_THINKING_LEVEL', 'none');
        Environment::setEnv('AI_COMPOSE_MAX_TOKENS', '1234');

        $provider = new TestAIProvider([
            ['status' => 200, 'body' => 'Generated output'],
        ]);

        $provider->generate('system', 'user');

        $this->assertSame(45, $provider->getResolvedTimeout());
        $this->assertSame(0.6, $provider->getResolvedTemperature());
        $this->assertSame('none', $provider->getResolvedThinkingLevel());
        $this->assertSame(1234, $provider->getResolvedMaxTokens());
    }
}
