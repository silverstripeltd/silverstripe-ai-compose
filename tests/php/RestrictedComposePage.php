<?php

namespace SilverstripeLtd\AiCompose\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Simple page type that denies edit access for compose tests.
 */
class RestrictedComposePage extends SiteTree implements TestOnly
{
    private static $table_name = 'AIC_RestrPg';

    /**
     * Ensure compose access is hidden when editing is not allowed.
     */
    public function canEdit($member = null): bool
    {
        return false;
    }
}
