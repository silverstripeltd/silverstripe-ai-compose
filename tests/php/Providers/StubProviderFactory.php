<?php

namespace SilverstripeLtd\AiCompose\Tests\Providers;

use SilverstripeLtd\AiCompose\Providers\AbstractAIProvider;
use SilverstripeLtd\AiCompose\Providers\ProviderFactory;

/**
 * Provider factory that always returns the supplied provider.
 */
class StubProviderFactory extends ProviderFactory
{
    /**
     * Stores the provider instance that should always be returned in tests.
     */
    public function __construct(private readonly AbstractAIProvider $provider)
    {
    }

    /**
     * Returns the preconfigured provider without consulting environment configuration.
     */
    public function getProvider(): AbstractAIProvider
    {
        return $this->provider;
    }
}
