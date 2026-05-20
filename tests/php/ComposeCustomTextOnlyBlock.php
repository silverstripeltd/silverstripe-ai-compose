<?php

namespace SilverstripeLtd\AiCompose\Tests;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;

/**
 * Element fixture with only plain text content storage.
 */
class ComposeCustomTextOnlyBlock extends BaseElement implements TestOnly
{
    private static $table_name = 'AIC_TextBlock';

    private static $db = [
        'BodyText' => 'Text',
    ];
}
