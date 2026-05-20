<?php

namespace SilverstripeLtd\AiCompose\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Elemental-enabled page fixture used by compose tests.
 */
class ComposeTestElementalPage extends SiteTree implements TestOnly
{
    private static $table_name = 'AIC_ElPage';
}
