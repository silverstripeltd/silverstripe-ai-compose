<?php

namespace SilverstripeLtd\AiCompose\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverstripeLtd\AiCompose\Services\PromptService;
use SilverstripeLtd\AiCompose\Tests\ComposeTestNormalisingSiteConfig;
use SilverstripeLtd\AiCompose\Tests\ComposeTestSiteConfig;
use SilverStripe\Dev\SapphireTest;

/**
 * Covers prompt-template loading, empty-input guidance, and writing style and tone rules injection.
 */
class PromptServiceTest extends SapphireTest
{
    /**
     * Confirms writing style and tone rules are injected using normaliseRefineDefinition() when available.
     */
    public function testBuildPromptsInjectsNormalisedWritingStyleAndToneRules(): void
    {
        $siteConfig = new ComposeTestNormalisingSiteConfig();
        $siteConfig->RefineDefinition = "  Calm\n  helpful   and clear  ";
        $service = new PromptService($siteConfig);

        [$systemPrompt, $userPrompt] = $service->buildPrompts(
            'Create a community notice',
            'Date: 15 March 2026'
        );

        $this->assertTrue($siteConfig->wasNormaliseCalled());
        $this->assertStringContainsString('=== WRITING_STYLE_AND_TONE_RULES_START ===', $systemPrompt);
        $this->assertStringContainsString('Calm helpful and clear', $systemPrompt);
        $this->assertStringContainsString('=== OBJECTIVE_START ===', $userPrompt);
        $this->assertStringContainsString('=== SUBSTANCE_START ===', $userPrompt);
        $this->assertStringContainsString('"title"', $userPrompt);
        $this->assertStringContainsString('"content"', $userPrompt);
    }

    /**
     * Confirms prompt building falls back to trimming raw writing style and tone rules text when no normaliser exists.
     */
    public function testBuildPromptsFallsBackToTrimmedWritingStyleAndToneRulesWithoutNormaliser(): void
    {
        $siteConfig = new ComposeTestSiteConfig();
        $siteConfig->RefineDefinition = '  Helpful and clear  ';
        $service = new PromptService($siteConfig);

        [$systemPrompt] = $service->buildPrompts('Create a public update', 'Date: 15 March');

        $this->assertStringContainsString('Helpful and clear', $systemPrompt);
    }

    /**
     * Confirms the writing style and tone rules section is omitted entirely when the definition is empty.
     */
    public function testBuildPromptsOmitsWritingStyleAndToneRulesWhenDefinitionEmpty(): void
    {
        $service = new PromptService(new ComposeTestSiteConfig());

        [$systemPrompt] = $service->buildPrompts('Create a public update', 'Date: 15 March');

        $this->assertStringNotContainsString('=== WRITING_STYLE_AND_TONE_RULES_START ===', $systemPrompt);
    }

    /**
     * Supplies prompt inputs that should trigger the empty-input guidance rules.
     *
     * @return array<string, array{objective: string, substance: string, expected: string}>
     */
    public static function provideBuildPromptsIncludesFallbackGuidance(): array
    {
        return [
            'objective-only' => [
                'objective' => 'Create a public update',
                'substance' => '',
                'expected' => 'No specific facts were provided.',
            ],
            'substance-only' => [
                'objective' => '',
                'substance' => 'Date: 15 March',
                'expected' => 'No explicit objective was provided.',
            ],
        ];
    }

    /**
     * Confirms prompt building includes the expected fallback guidance when one input is empty.
     */
    #[DataProvider('provideBuildPromptsIncludesFallbackGuidance')]
    public function testBuildPromptsIncludesFallbackGuidance(
        string $objective,
        string $substance,
        string $expected
    ): void {
        $service = new PromptService(new ComposeTestSiteConfig());

        [, $userPrompt] = $service->buildPrompts($objective, $substance);

        $this->assertStringContainsString($expected, $userPrompt);
    }
}
