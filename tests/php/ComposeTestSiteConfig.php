<?php

namespace SilverstripeLtd\AiCompose\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * SiteConfig stub without a normaliser helper for prompt tests.
 */
class ComposeTestSiteConfig extends SiteConfig implements TestOnly
{
    private static $table_name = 'AIC_SimpleCfg';

    private static $db = [
        'RefineDefinition' => 'Text',
    ];
}
