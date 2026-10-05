# AI compose module for Silverstripe CMS

AI-assisted page composition for Silverstripe CMS.

This module solves the editor "blank page" problem with a lightweight, on-demand workflow. Editors open a modal from the CMS page edit screen, provide a purpose and supporting facts, generate a draft title and body in one AI call, then preview, copy, or apply the result to Draft content. Generated results are cached only for the current CMS session and are never persisted to a module-owned table.

![AI compose modal](docs/ai-compose-modal.png)

## Installation

This module is currently not listed on Packagist. To install it, add the following to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:silverstripeltd/silverstripe-ai-compose.git"
        }
    ],
    "require": {
        "silverstripeltd/ai-compose": "*"
    }
}
```

Then run `composer install` followed by `vendor/bin/sake dev/build flush=1` to register the module's routes, extensions, and config.

### Prerequisites

- Silverstripe CMS 6
- A saved page the current editor can edit
- A valid API key for one of the supported AI providers: Gemini, OpenAI, or Anthropic
- Optional: Elemental if pages should append a new content block instead of overwriting `Content`
- Optional: ai-refine if you want `SiteConfig.RefineDefinition` writing style and tone rules injected into prompts

## Usage

1. Save the page in the CMS so it has an ID.
2. Click the "Compose" button on the page edit form.
3. Enter one or both modal inputs:
   - `Purpose & Format` for the audience, style, and intent
   - `Facts & Background` for the source facts that must stay accurate
4. Click "Generate" to request a draft page title and HTML body.
5. Review the read-only preview, then either copy the output or apply it to the page.

### Draft apply behaviour

Compose always writes to `Draft` only. It never publishes content.

- Non-Elemental pages: applying overwrites `Title` and `Content`
- Elemental pages: applying overwrites `Title` and appends one new configured content block to the first `ElementalArea`

The module sanitises generated HTML server-side before any Draft write and strips all HTML from titles.

## Configuration

All configuration is via environment variables, for example in your webserver environment or `.env`. Restart the webserver after changing any values.

Provider calls go through the shared [`silverstripeltd/silverstripe-ai-core`](https://github.com/silverstripeltd/silverstripe-ai-core) package. Every `AI_COMPOSE_*` variable below falls back to the shared `AI_*` variable of the same name (`AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`, ...), so one key in `.env` can drive every AI module on the site. A compose variable always wins over the shared one. The shared `AI_API_KEY` and `AI_MODEL` are ignored while `AI_COMPOSE_PROVIDER` names a different provider than `AI_PROVIDER`.

### Provider

Set the active AI provider and API key. Gemini, OpenAI, and Anthropic are supported out of the box. Custom providers can be registered in the ai-core `ProviderFactory.providers` map.

```bash
AI_COMPOSE_PROVIDER=gemini                # gemini (default), openai, or anthropic
AI_COMPOSE_API_KEY=your-api-key           # API key for the chosen provider
```

Or, shared with the other AI modules:

```bash
AI_PROVIDER=anthropic
AI_API_KEY=your-api-key
```

### Model

Control which model is used and how it generates responses. All settings are optional and have sensible defaults.

```bash
AI_COMPOSE_MODEL=gemini-3.1-flash-lite    # Model identifier (provider-specific)
AI_COMPOSE_THINKING_LEVEL=low             # Thinking effort passed to the active vendor, or none
AI_COMPOSE_TEMPERATURE=1.0                # Sampling temperature
AI_COMPOSE_MAX_TOKENS=4000                # Max tokens in AI response
AI_COMPOSE_REQUEST_TIMEOUT=30             # Timeout per AI request in seconds
```

Compose returns a full title plus page body, so longer pages may need `AI_COMPOSE_MAX_TOKENS` increased.

The default models are `gemini-3.1-flash-lite` (Gemini), `gpt-5-mini` (OpenAI) and `claude-haiku-4-5` (Anthropic). Without `AI_COMPOSE_THINKING_LEVEL` only Gemini receives a thinking level (`low`); when the variable is set it is sent to whichever vendor is active (Anthropic effort, OpenAI `reasoning_effort`, Gemini `thinkingLevel`), so pick a value that model accepts. The defaults live in YAML and can be changed per project:

```yaml
SilverstripeLtd\AiCore\Settings\EnvProviderSettings:
  modules:
    COMPOSE:
      max_tokens: 6000
      providers:
        anthropic:
          model: claude-sonnet-5-5
```

---

## Development

### AI tooling

AI tools should be run from the project root, not from within this directory. The module's `CLAUDE.md` should be symlinked to the project root so that AI tools pick it up automatically:

```bash
cd path/to/project

if [ -f CLAUDE.md ] || [ -L CLAUDE.md ]; then rm -f CLAUDE.md; fi
ln -s vendor/silverstripeltd/ai-compose/CLAUDE.md CLAUDE.md
```

`CLAUDE.md` contains project identity, hard constraints, directory structure, and module-specific command conventions.

### Running tests and linting

All commands use the same Docker-over-SSH style as the sibling AI modules.

- PHP unit tests:
  - `ssh webserver "cd /var/www && rm -rf /tmp/pu-cache && mkdir -p /tmp/pu-cache && SS_TEMP_PATH=/tmp/pu-cache nice -n 19 ionice -c 3 taskset -c 0 vendor/bin/phpunit vendor/silverstripeltd/ai-compose/tests/ --fail-on-warning"`
- JS prerequisite:
  - `ssh webserver "cd /var/www/vendor/silverstripe/admin && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn install"`
- Module JS install:
  - `ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn install"`
- JS tests:
  - `ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn test"`
- PHP linting:
  - `ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && nice -n 19 ionice -c 3 taskset -c 0 ../../bin/phpcs --ignore=*/thirdparty/*,*/node_modules/* --extensions=php ."`
- JS and SCSS linting:
  - `ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn lint"`

### Technical details

See `specs/` for the detailed architecture, prompt, provider, API, and CMS UX specifications.
