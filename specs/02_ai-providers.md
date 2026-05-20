# AI Providers

## Provider abstraction

The module includes a provider abstraction layer supporting multiple AI providers. One provider is active at a time, selected via environment variable. The module ships with three built-in providers:

- **Gemini** - primary provider (default). Calls the v1beta `generateContent` endpoint and includes `thinkingConfig.thinkingLevel` when `AI_COMPOSE_THINKING_LEVEL` is not `none`.
- **OpenAI** - Chat Completions API provider
- **Anthropic** - Messages API provider
- **Custom providers** - the built-in factory supports `gemini`, `openai`, and `anthropic` only. To use a custom provider, projects must override the factory via Silverstripe's Injector.

These are standalone classes with no dependencies beyond Guzzle (bundled with Silverstripe framework).

## Provider interface

All providers extend `AbstractAIProvider`, which supplies the generation method and shared error handling. Concrete providers implement protected request hooks (`performRequest`, `extractResponseContent`, `isTransientStatus`, and `getDefaultModel`).

```php
public function generate(string $systemPrompt, string $userPrompt): string
```

Returns the raw string response from the AI provider. The compose module constructs its own prompts (see `specs/03_prompts.md`) and parses the response as JSON in the service layer.

## Configuration

Environment variables follow the same naming convention as the other AI modules:

| Environment variable | Description | Default |
|---|---|---|
| `AI_COMPOSE_PROVIDER` | Active provider (`gemini`, `openai`, `anthropic`) | `gemini` |
| `AI_COMPOSE_API_KEY` | API key for the active provider | (required) |
| `AI_COMPOSE_MODEL` | Model to use | Provider-specific default |
| `AI_COMPOSE_THINKING_LEVEL` | Thinking level for Gemini | `low` |
| `AI_COMPOSE_TEMPERATURE` | Temperature for generation | `1.0` |
| `AI_COMPOSE_MAX_TOKENS` | Max tokens in response | `4000` |
| `AI_COMPOSE_REQUEST_TIMEOUT` | Request timeout in seconds | `30` |

**Note on temperature:** Defaults to `1.0` because this is a creative content generation tool. Compose benefits from natural variation so editors can regenerate for different phrasings.

**Note on max tokens:** Defaults to `4000` because generated page content can be substantial. This is higher than the default for translation or metadata modules since compose returns a full page body.

**Note on request timeout:** Defaults to `30` seconds rather than the `15` used by evaluation modules, because content generation can produce longer responses.

## Error handling

- **Transient failures** (network timeout, rate limit, 5xx): `AIProviderException`
- **Permanent failures** (invalid API key, 4xx): `AIProviderException`
- **Malformed response** (invalid JSON, missing required keys): `AIProviderException`
- **Callers** (controller) catch the exception and return an error response for toast display in the modal
