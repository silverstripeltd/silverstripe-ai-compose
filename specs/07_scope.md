# Scope

## In scope

- AI-powered page content generation via a CMS modal
- Two free-form input fields (objective and substance) for directing the AI
- Single AI call returning structured JSON with title and content
- Read-only preview of generated title and content before apply
- Copy-to-clipboard (plain text) for both generated title and content
- Contextual overwrite/append warning in the modal based on page type
- Apply writes title to `Title` and content to `Content` (non-Elemental) or a new content block (Elemental)
- Configurable Elemental block class via YML for sites with custom allowed block types
- Validation that the configured block class is allowed in the target ElementalArea, with developer-facing error when misconfigured
- Soft writing style and tone rules integration - reads `SiteConfig.RefineDefinition` if present, via field introspection with no hard dependency
- Regeneration - editor can tweak inputs and regenerate without losing input state
- FQCN validation on all endpoints, supporting future expansion to other DataObject types
- Provider abstraction supporting Gemini, OpenAI, and Anthropic
- HTML sanitisation via `HTMLEditorSanitiser` and `XssSanitiser` before writing to Draft
- Draft-only writes, never publishes

## Out of scope

- **Persisted results** - generated content is cached on the Entwine instance only. No DataObject storage.
- **Background job** - not needed. This is a manual, on-demand tool.
- **CMS report** - no reporting on compose usage or generated content.
- **Selective per-field apply** - compose applies all generated content at once. Copy-to-clipboard provides a manual alternative for partial use.
- **Diff preview** - no comparison against existing content. This is a creation tool, not a review tool.
- **Inline editing of generated content** - editors cannot modify generated content in the modal. They apply and edit in the CMS, or copy and paste.
- **Auto-compose on page creation** - explicitly avoided. Composition is editor-initiated.
- **Publish cascade** - applying content writes to Draft only. Publishing is the editor's responsibility.
- **Content templates or presets** - no saved prompt templates. The input fields are free-form.
- **Image generation** - text content only. No images, media, or file attachments.
- **Multi-page generation** - one page at a time. No bulk generation.
- **Dirty-state protection** - compose does not interact with the page form's dirty state since it generates new content rather than evaluating existing content.
