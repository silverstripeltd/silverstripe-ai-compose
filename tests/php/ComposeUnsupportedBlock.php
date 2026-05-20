<?php

namespace SilverstripeLtd\AiCompose\Tests;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;

/**
 * Element fixture without any supported content field.
 */
class ComposeUnsupportedBlock extends BaseElement implements TestOnly
{
    private static $table_name = 'AIC_BadBlock';
}
