# AI Providers

## Provider abstraction

The module includes a provider abstraction layer supporting multiple AI providers. One provider is active at a time, selected via environment variable. The module ships with three built-in providers:

- **Gemini** - primary provider (default). Calls the v1beta `generateContent` endpoint and includes `thinkingConfig.thinkingLevel` when `AI_COMPOSE_THINKING_LEVEL` is not `none`.
- **OpenAI** - Chat Completions API provider
- **Anthropic** - Messages API provider
- **Custom providers** - registered by name in the ai-core `ProviderFactory.providers` YAML map.

The providers live in the shared `silverstripeltd/silverstripe-ai-core` package, which every AI module uses.

## Provider interface

`ComposeGenerationService` calls ai-core's `SimpleCompletion`, bound to `EnvProviderSettings::forModule('COMPOSE')`:

```php
public function complete(string $system, string $user, ?CompletionOptions $options = null): string
```

Returns the model's text reply. The compose module constructs its own prompts (see `specs/03_prompts.md`) and parses the response as JSON in `ComposeResponseParser`.

## Configuration

Environment variables follow the same naming convention as the other AI modules. Each one falls back to the shared `AI_*` variable of the same name (for example `AI_API_KEY`), and the defaults below are YAML under `SilverstripeLtd\AiCore\Settings\EnvProviderSettings.modules.COMPOSE`:

| Environment variable | Description | Default |
|---|---|---|
| `AI_COMPOSE_PROVIDER` | Active provider (`gemini`, `openai`, `anthropic`) | `gemini` |
| `AI_COMPOSE_API_KEY` | API key for the active provider | (required) |
| `AI_COMPOSE_MODEL` | Model to use | `gemini-3.1-flash-lite`, `gpt-5-mini` or `claude-haiku-4-5` |
| `AI_COMPOSE_THINKING_LEVEL` | Thinking level, sent to the active vendor | `low` for Gemini only |
| `AI_COMPOSE_TEMPERATURE` | Temperature for generation | `1.0` |
| `AI_COMPOSE_MAX_TOKENS` | Max tokens in response | `4000` |
| `AI_COMPOSE_REQUEST_TIMEOUT` | Request timeout in seconds | `30` |

**Note on temperature:** Defaults to `1.0` because this is a creative content generation tool. Compose benefits from natural variation so editors can regenerate for different phrasings.

**Note on max tokens:** Defaults to `4000` because generated page content can be substantial. This is higher than the default for translation or metadata modules since compose returns a full page body.

**Note on request timeout:** Defaults to `30` seconds rather than the `15` used by evaluation modules, because content generation can produce longer responses.

## Error handling

- **Transient failures** (network timeout, rate limit, 5xx): ai-core `ProviderException` with `isTransient()`
- **Blocking failures** (missing or invalid API key, 401, 403): `ProviderException` with `isBlocking()`
- **Permanent failures** (other 4xx): `ProviderException`
- **Malformed response** (invalid JSON, missing required keys): `ProviderException`
- **Callers** (controller) catch the exception and return an error response for toast display in the modal
