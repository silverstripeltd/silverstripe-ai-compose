<?php

namespace SilverstripeLtd\AiCompose\Tests;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;

/**
 * Element fixture with both HTML and text fields.
 */
class ComposeCustomHtmlBlock extends BaseElement implements TestOnly
{
    private static $table_name = 'AIC_HtmlBlock';

    private static $db = [
        'PrimaryHTML' => 'HTMLText',
        'SummaryText' => 'Text',
    ];
}
