<?php

namespace SilverstripeLtd\AiCompose\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * SiteConfig stub that tracks normalisation calls for prompt tests.
 */
class ComposeTestNormalisingSiteConfig extends SiteConfig implements TestOnly
{
    private static $table_name = 'AIC_NormCfg';

    private static $db = [
        'RefineDefinition' => 'Text',
    ];

    private bool $normaliseCalled = false;

    /**
     * Normalises writing style and tone rules input and records that the helper was used.
     */
    public function normaliseRefineDefinition(string $value): string
    {
        $this->normaliseCalled = true;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * Reports whether the normaliser hook was invoked.
     */
    public function wasNormaliseCalled(): bool
    {
        return $this->normaliseCalled;
    }
}
