<?php

namespace SilverstripeLtd\AiCompose\Services;

use SilverStripe\Core\Extensible;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Builds compose prompts from prompt templates and current site guidance.
 */
class PromptService
{
    use Extensible;

    private ?SiteConfig $siteConfig;

    /**
     * Builds the service with an optional SiteConfig dependency for tests.
     */
    public function __construct(?SiteConfig $siteConfig = null)
    {
        $this->siteConfig = $siteConfig;
    }

    /**
     * Builds the system and user prompts for one compose request.
     *
     * @return array{0: string, 1: string}
     */
    public function buildPrompts(string $objective, string $substance): array
    {
        $objective = trim($objective);
        $substance = trim($substance);
        $systemPrompt = $this->renderSystemPrompt($this->getRefineDefinition());
        $userPrompt = $this->renderUserPrompt($objective, $substance);
        $this->extend('updateComposePrompts', $systemPrompt, $userPrompt, $objective, $substance);
        return [trim($systemPrompt), trim($userPrompt)];
    }

    /**
     * Loads the system prompt template from disk.
     */
    public function getSystemPromptTemplate(): string
    {
        return trim((string) file_get_contents($this->getPromptsDirectory() . '/system.md'));
    }

    /**
     * Loads the user prompt template from disk.
     */
    public function getUserPromptTemplate(): string
    {
        return trim((string) file_get_contents($this->getPromptsDirectory() . '/user.md'));
    }

    /**
     * Renders the system prompt, including optional writing style and tone rules guidance.
     */
    private function renderSystemPrompt(string $refineDefinition): string
    {
        $writingStyleAndToneRulesSection = '';
        if ($refineDefinition !== '') {
            $writingStyleAndToneRulesSection = "Follow the writing style and tone rules below when writing.\n\n"
                . "=== WRITING_STYLE_AND_TONE_RULES_START ===\n"
                . $refineDefinition . "\n"
                . "=== WRITING_STYLE_AND_TONE_RULES_END ===\n\n";
        }
        return trim(str_replace(
            '{writingStyleAndToneRulesSection}',
            $writingStyleAndToneRulesSection,
            $this->getSystemPromptTemplate()
        ));
    }

    /**
     * Renders the user prompt with input-specific fallback guidance.
     */
    private function renderUserPrompt(string $objective, string $substance): string
    {
        return trim(str_replace(
            [
                '{objectiveGuidance}',
                '{substanceGuidance}',
                '{objective}',
                '{substance}',
            ],
            [
                $this->getObjectiveGuidance($objective, $substance),
                $this->getSubstanceGuidance($objective, $substance),
                $objective,
                $substance,
            ],
            $this->getUserPromptTemplate()
        ));
    }

    /**
     * Returns the current site-wide writing style and tone rules when available.
     */
    private function getRefineDefinition(): string
    {
        $siteConfig = $this->siteConfig ?: SiteConfig::current_site_config();
        if (!$siteConfig || !$siteConfig->hasField('RefineDefinition')) {
            return '';
        }

        $definition = (string) $siteConfig->getField('RefineDefinition');
        if ($siteConfig->hasMethod('normaliseRefineDefinition')) {
            return trim((string) $siteConfig->normaliseRefineDefinition($definition));
        }
        return trim($definition);
    }

    /**
     * Explains how the objective should be interpreted for the current request.
     */
    private function getObjectiveGuidance(string $objective, string $substance): string
    {
        if ($objective === '') {
            return 'No explicit objective was provided. Infer the page purpose, audience, and suitable'
                . ' format from the supplied substance.';
        }
        if ($substance === '') {
            return 'Use the objective to understand the requested purpose, audience, and preferred'
                . ' format.';
        }
        return 'Use the objective to understand the requested purpose, audience, and preferred format.';
    }

    /**
     * Explains how the substance should be interpreted for the current request.
     */
    private function getSubstanceGuidance(string $objective, string $substance): string
    {
        if ($substance === '') {
            return 'No specific facts were provided. Write useful general content based on the objective'
                . ' alone and do not invent concrete dates, names, figures, URLs, or claims.';
        }
        if ($objective === '') {
            return 'Treat the substance as the single source of truth for facts and infer the most'
                . ' sensible page purpose from it.';
        }
        return 'Treat the substance as the single source of truth for facts, dates, figures, URLs,'
            . ' names, and required details.';
    }

    /**
     * Resolves the module prompt template directory.
     */
    private function getPromptsDirectory(): string
    {
        return dirname(__DIR__, 2) . '/prompts';
    }
}
