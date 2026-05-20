<?php

namespace SilverstripeLtd\AiCompose\Services;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\XssSanitiser;
use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;
use SilverStripe\Forms\HTMLEditor\HTMLEditorSanitiser;
use SilverStripe\View\Parsers\HTMLValue;

/**
 * Shares compose preview and apply sanitisation behaviour.
 */
class ComposeContentSanitisationService
{
    use Injectable;

    /**
     * Sanitises generated titles down to plain text.
     */
    public function sanitiseTitle(string $title): string
    {
        return trim(strip_tags($title));
    }

    /**
     * Applies CMS-equivalent HTML sanitisation to generated content.
     */
    public function sanitiseHtml(string $content): string
    {
        $htmlValue = new HTMLValue($content);
        HTMLEditorSanitiser::create(HTMLEditorConfig::get_active())->sanitise($htmlValue);
        XssSanitiser::create()->sanitiseHtmlValue($htmlValue);
        return trim($htmlValue->getContent());
    }
}
