# AI Compose Module for Silverstripe CMS

This current project is to build a Silverstripe CMS "ai-compose" module in `vendor/silverstripeltd/ai-compose`. No other work is being performed.

Do not modify any files outside of the `vendor/silverstripeltd/ai-compose` directory unless explicitly instructed to do so.

**Silverstripe CMS 6** module that generates AI-assisted page titles and content bodies on demand. Editors provide a short brief and factual notes, preview the result, optionally copy it, and can apply it back to Draft content only.

When implementing phases from a plan, implement all phases in one go. Do not stop to ask for review or confirmation between phases. Act as an autonomous developer - use your best judgement and do not ask for clarifications.

Never attempt to use MCP (Model Context Protocol) - it is disabled at an organisation level.

## Writing style

Never use em dashes (-) in any files. Use a regular hyphen (-) instead.
PHP and JS files should give each class and method a short docblock explainer. Do not include param or return types in those docblocks.
PHP methods should not have blank lines between statements except within heredoc or nowdoc strings.

## Hard constraints

- **NEVER view or edit bundled or compiled files** - no `/dist/`, `bundle.js`, `vendor.js`, or `*.min.js`. These will blow out your context window.
- **Only change code directly related to the task** - no unrelated cleanup, lint fixes, or reformatting. Note anything worth fixing in `z-learnings.md` instead.

## Directory structure

- `client/src/` - React modal, boot code, and Entwine adapter sources
- `client/tests/` - JS tests and supporting test files, mirroring the relevant `client/src/` paths
- `client/dist/` - Webpack build output (JS and CSS bundles) - do not read or edit
- `src/` - PHP classes such as controllers, services, extensions, providers, and value objects
- `prompts/` - Prompt templates loaded by `PromptService`
- `_config/` - YAML configuration for routes, extensions, and requirements
- `specs/` - Technical specs used as the implementation source of truth. Start with `specs/00_overview.md`
- `tests/php/` - PHPUnit tests

Files in `specs/` are prefixed with `00_`, `01_`, and so on.

## Running commands

All commands run inside Docker via SSH. Never call `phpunit`, `phpcs`, `phpcbf`, `yarn`, `npm`, or `npx` directly. Always prefix the actual command with `nice -n 19 ionice -c 3 taskset -c 0` to keep CPU and IO usage low. For yarn and node commands also prepend `NODE_OPTIONS=--max-old-space-size=512` to cap memory.

### PHPUnit testing conventions

- Use `SapphireTest`, which provides a temporary database - do not mock dependencies like a traditional unit test
- Use fixtures or programmatically create data within tests
- Use `#[DataProvider('provideFoo')]` attribute syntax (PHP 8), not the legacy `@dataProvider` annotation
- Place the provider method directly above the test method it supplies
- Do not use the description argument in assertions

#### Running tests

`ssh webserver "cd /var/www && rm -rf /tmp/pu-cache && mkdir -p /tmp/pu-cache && SS_TEMP_PATH=/tmp/pu-cache nice -n 19 ionice -c 3 taskset -c 0 vendor/bin/phpunit vendor/silverstripeltd/ai-compose/tests/ --fail-on-warning [--filter={test-name}]"`

#### PHP linting

`ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && nice -n 19 ionice -c 3 taskset -c 0 ../../bin/{binary} --ignore=*/thirdparty/*,*/node_modules/* --extensions=php ."`

Run `phpcs` or `phpcbf` after changing PHP files.

Replace `{binary}` with `phpcs` or `phpcbf`.

### JavaScript testing conventions

- Flat `test()` blocks only - no `describe()` nesting
- Use RTL queries by accessibility role or text rather than `getByTestId`
- Place JS tests under `client/tests/`, mirroring the relevant `client/src/` path

#### JS dependency prerequisite

This module depends on `vendor/silverstripe/admin` for shared JS tooling. Before running `yarn test`, `yarn lint`, or `yarn build`, first run:

`ssh webserver "cd /var/www/vendor/silverstripe/admin && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn install"`

Then install this module's JS dependencies:

`ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn install"`

#### Running tests

`ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn test"`

#### Linting

`ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn lint"`

### Spec editing rules

- Files in `specs/` are prefixed with `00_`, `01_`, and so on. Numbering reflects the recommended implementation order

### Final step - JS build

If any `.js` or `.jsx` files were changed during the task, run `yarn build` as the very last code-related step, after tests and linting pass. Do not run it mid-implementation to check progress.

`ssh webserver "cd /var/www/vendor/silverstripeltd/ai-compose && NODE_OPTIONS=--max-old-space-size=512 nice -n 19 ionice -c 3 taskset -c 0 yarn build"`

## Other files

This `CLAUDE.md` file is intended to be symlinked to the project root as `CLAUDE.md`. Do not read it again directly if it is already loaded as pre-prompt.

## Key architectural decisions

- No persisted compose result DataObject. Inputs and generated results are cached on the Entwine instance for the current CMS session only
- A single AI call returns structured JSON with `title` and `content`
- Editors preview generated output, copy either field, or apply both fields together
- Applying content always writes to Draft only, then reloads the CMS
- Non-Elemental pages overwrite `Content`; Elemental pages append one configured content block to the first `ElementalArea`
- Writing style and tone rules integration is soft. If `SiteConfig` exposes `RefineDefinition`, it is normalised when possible and injected into the prompt without adding a hard dependency on ai-refine

## Gotchas

- **Apply writes both fields together** - there is no selective per-field apply, so partial use should happen through copy to clipboard
- **Generated results are session only** - the module never persists generated drafts outside the current editing session cache

## Environment variables

- `AI_COMPOSE_PROVIDER` (default `gemini`)
- `AI_COMPOSE_API_KEY`
- `AI_COMPOSE_MODEL`
- `AI_COMPOSE_THINKING_LEVEL` (default `low`)
- `AI_COMPOSE_TEMPERATURE` (default `1.0`)
- `AI_COMPOSE_MAX_TOKENS` (default `4000`)
- `AI_COMPOSE_REQUEST_TIMEOUT` (default `30`)

## Learnings

Read `/app/z-learnings.md` if it exists. Add only non-obvious discoveries that caused a real detour, prevented one, or are very likely to matter again. Prefix each entry with a category tag such as `[testing]`, `[config]`, or `[api]`, and add `[universal]` when it is general platform knowledge. Replace older entries that cover the same topic instead of keeping both. Keep entries to 1 to 2 sentences. Create the file with a `# Learnings` heading if it does not exist.

## z- file outputs

If you are asked to create any `z-*.md` files, always put them in `/app`, not `vendor/silverstripeltd/ai-compose`.
