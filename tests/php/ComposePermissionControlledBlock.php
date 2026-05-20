<?php

namespace SilverstripeLtd\AiCompose\Tests;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;

/**
 * Element fixture with configurable creation permissions.
 */
class ComposePermissionControlledBlock extends BaseElement implements TestOnly
{
    private static $table_name = 'AIC_PermBlock';

    private static $db = [
        'HTML' => 'HTMLText',
    ];

    private static $allow_create = true;

    private static $allow_create_element = true;

    /**
     * Allows tests to deny block creation through BaseElement permissions.
     */
    public function canCreate($member = null, $context = []): bool
    {
        return parent::canCreate($member, $context) && (bool) static::config()->get('allow_create');
    }

    /**
     * Allows tests to deny block creation through Elemental type filtering.
     */
    public function canCreateElement(): bool
    {
        return (bool) static::config()->get('allow_create_element');
    }
}
