# System Overview

One-page summary of the AI compose module architecture. Read this first, then dive into individual specs.

## What it does

Solves the "blank page" problem for CMS editors. Provides a modal where editors describe what they want to write and supply the facts, then AI generates a page Title and Content body that can be applied to the current page. The module writes to Draft only and never publishes.

This is a lightweight, on-demand content generation tool - no background job, no persisted results, no CMS report, no selective per-field apply. Just generate, preview, copy or apply.

## Architecture

```
+-----------------------------------------------------------------+
| CMS (Editor)                                                    |
|                                                                 |
|  Page Edit Form --> [Compose] button --> Modal (React)          |
|                      (preview toolbar)                          |
|                                                                 |
|                         +-----------------------------------+   |
|                         | Purpose & Format (textarea)       |   |
|                         | Facts & Background (textarea)     |   |
|                         |                                   |   |
|                         | [Generate]                        |   |
|                         |                                   |   |
|                         | Generated Title     [copy]        |   |
|                         | Generated Content   [copy]        |   |
|                         |                                   |   |
|                         | [Regenerate]    [Apply to Page]   |   |
|                         +-----------------------------------+   |
|                         | Cached on Entwine instance        |   |
+-----------------------------------------------------------------+
                            |
          Schema / Generate / Apply XHR (specs/05)
                            v
+-----------------------------------------------------------------+
| ComposeController (specs/05)                                    |
|                                                                 |
|  GET  /admin/ai-compose/schema/{ID}?fqcn=...                   |
|  POST /admin/ai-compose/generate/{ID}                           |
|  POST /admin/ai-compose/apply/{ID}                              |
+-----------------------------------------------------------------+
                |
                v
+----------------------------+   +---------------------------------+
| Content Write-back         |   | AI Provider (specs/02)          |
| (specs/04)                 |   |                                 |
|                            |   | Prompt (specs/03)               |
| Page.Title overwrite       |   | -> Gemini / OpenAI /            |
| Page.Content overwrite     |   |    Anthropic                    |
| OR new ElementContent      |   |                                 |
| block appended             |   | -> Structured JSON              |
|                            |   |    (title + content)            |
+----------------------------+   +---------------------------------+

+-----------------------------------------------------------------+
| Optional integration                                            |
|                                                                 |
| If SiteConfig has a RefineDefinition field (from ai-refine):    |
|   Its value is injected into the AI prompt as tone/style        |
|   guidance. No hard dependency - checked via field               |
|   introspection at runtime.                                     |
+-----------------------------------------------------------------+
```

## Spec index

| # | Spec | What it covers |
|---|------|---------------|
| 00 | This file | System overview and architecture |
| 01 | `data-architecture` | Extension, Entwine cache shape, no persisted DataObject, Elemental block class config |
| 02 | `ai-providers` | Provider abstraction, env vars, error handling |
| 03 | `prompts` | System/user prompt templates, output format, writing style and tone rules integration |
| 04 | `generation-behaviour` | Generate and apply pipelines, Elemental vs Content field logic, sanitisation |
| 05 | `api-endpoints` | Controller endpoints for schema, generate, and apply |
| 06 | `cms-ux` | Modal layout, input fields, preview, copy-to-clipboard, apply flow, overwrite warnings |
| 07 | `scope` | In scope, out of scope |

## Key design decisions

- **No persisted results** - generated content is cached on the Entwine instance for the editing session only. Once applied, the content lives on the page/element records. No module-owned DataObject.
- **No selective apply** - the editor applies all generated content (title + body) at once. No per-field checkboxes. Copy-to-clipboard buttons give editors a manual alternative for partial use.
- **No diff preview** - this is a creation tool, not a comparison tool. The editor sees the generated title and content as read-only previews before applying.
- **Elemental-aware write-back** - detects whether the page uses Elemental. For Elemental pages, creates a new content block. For non-Elemental pages, overwrites the `Content` field. Title always writes to `Title`.
- **Configurable default block class** - the Elemental content block class is configurable via YML for sites that do not allow `ElementContent`.
- **Soft writing style and tone rules integration** - if `SiteConfig` has a `RefineDefinition` field, reads it and injects it into the prompt. No composer dependency. Checked via field introspection at runtime.
- **Two input fields, not database-backed** - the "Purpose & Format" and "Facts & Background" textareas exist only in the modal. They are cached on the Entwine instance alongside the result so editors can tweak and regenerate.
- **FQCN validation** - endpoints accept a fully qualified class name parameter and validate it against the compose extension. This supports future expansion to other DataObject types.
- **Provider abstraction** - supports Gemini, OpenAI, and Anthropic via a common interface, configured through environment variables.
