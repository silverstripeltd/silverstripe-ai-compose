# Prompts

## Approach

A single AI call generates both the page title and content body. The editor provides two inputs - the objective (what to write, audience, style) and the substance (facts, data, details). The prompt instructs the AI to produce structured JSON output.

Prompt templates live in the module-root `prompts/` directory and are loaded by `PromptService`.

## Prompt structure

### System prompt

Role statement establishing the AI as a content writer:

```
You are a professional web content writer for a CMS-managed website. You create clear, well-structured page content from editorial briefs. Return only valid JSON, no markdown fences or commentary.
```

When writing style and tone rules are available (see below), the system prompt is extended:

```
You are a professional web content writer for a CMS-managed website. You create clear, well-structured page content from editorial briefs. Follow the writing style and tone rules below when writing.

=== WRITING_STYLE_AND_TONE_RULES_START ===
{refineDefinition}
=== WRITING_STYLE_AND_TONE_RULES_END ===

Return only valid JSON, no markdown fences or commentary.
```

### User prompt

Contains:

1. The editor's objective (purpose, audience, format)
2. The editor's substance (facts, data, details)
3. Content format instructions
4. Output format specification

Key instructions:

- The `objective` field describes what the page is about, who the target audience is, and the desired style or format
- The `substance` field contains the raw facts, bullet points, dates, and core details that must be included. Treat this as the single source of truth for accuracy - do not invent facts beyond what is provided
- Generate a concise, descriptive page title
- Generate page content as clean HTML suitable for a CMS rich text editor
- Use semantic HTML elements: `<p>`, `<h2>`, `<h3>`, `<ul>`, `<ol>`, `<li>`, `<strong>`, `<em>`, `<a>` (only if URLs are provided in the substance)
- Do not use `<h1>` (the page title serves as the primary heading)
- Do not include `<html>`, `<head>`, `<body>`, or `<div>` wrapper elements
- Do not include inline styles, classes, or data attributes
- Structure the content with appropriate headings and paragraphs for scannability
- If the substance includes dates, names, figures, or specific details, these must appear accurately in the generated content
- Content delimiters: `=== OBJECTIVE_START/END ===` and `=== SUBSTANCE_START/END ===` to separate prompt instructions from editor input

### Expected JSON output

```json
{
  "title": "Council Meeting - 15 March 2026",
  "content": "<h2>Join us at the Town Hall</h2><p>Local residents are invited to attend the upcoming council meeting on 15 March 2026 at the Town Hall.</p><h3>Agenda</h3><ul><li>Infrastructure update</li><li>Community funding proposals</li></ul><p>All residents are welcome. No registration is required.</p>"
}
```

## Output parsing

The provider response is parsed as JSON. The parser requires:

- A non-empty string `title` field
- A non-empty string `content` field

If either field is missing or empty, the response is treated as malformed and an `AIProviderException` is thrown.

## Writing style and tone rules integration

If `SiteConfig` has a `RefineDefinition` field (added by the ai-refine module when installed):

1. The prompt service checks whether `SiteConfig` has a `RefineDefinition` field (via `hasField()`)
2. If the field exists and is non-empty, its value is normalised before injection. If `SiteConfig` exposes a `normaliseRefineDefinition()` method (via `hasMethod()`), use it - this is the ai-refine module's own whitespace/line-break normaliser and produces the cleanest input. If the method is not available, fall back to trimming the raw field value.
3. The normalised value is injected into the system prompt between `WRITING_STYLE_AND_TONE_RULES_START` and `WRITING_STYLE_AND_TONE_RULES_END` delimiters
4. If the field does not exist or is empty after normalisation, the writing style and tone rules section is omitted entirely

This is a soft integration - no composer `require`, no class imports. The checks use Silverstripe's DataObject field and method introspection which is safe even when the field or method does not exist.

## Extension hook

```php
$this->extend('updateComposePrompts', $systemPrompt, $userPrompt, $objective, $substance);
```

Allows projects to:
- Add site-specific context (e.g. "This is a government website, use formal language")
- Adjust the tone or format instructions
- Add boilerplate content requirements

Projects must not change the output format. The JSON response schema is fixed.

## Empty input handling

- If both `objective` and `substance` are empty, the generate endpoint returns an error without calling the AI
- If only `objective` is provided (no substance), the AI is called but instructed to write general content based on the objective alone, with a note that no specific facts were provided
- If only `substance` is provided (no objective), the AI is called and infers the page purpose from the supplied facts
