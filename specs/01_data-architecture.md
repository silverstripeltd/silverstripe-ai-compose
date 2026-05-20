# Data Architecture

## No persisted DataObject

Generated content is cached on the Entwine instance for the editing session only, not persisted to the database.

Rationale: compose is a one-shot creation tool. The editor generates content, previews it, and either applies it or copies it. Once applied, the content lives on the page `Title`, `Content`, or an Elemental block record - there is nothing useful to persist on a module-owned table.

## Extension on SiteTree

An Extension is applied to `SiteTree` that:

- Adds the "Compose" button to the CMS edit form (visible on all editable saved pages)
- Seeds the Entwine adapter with record context (page ID, FQCN) via a hidden `AiComposeRecordClass` field for the React modal

The extension is designed for SiteTree but uses FQCN validation in the controller layer so the module can be extended to other DataObject types in the future without architectural changes.

## Entwine cache shape

The Entwine adapter caches both the editor's input and the last generated result so they survive modal close and reopen within the same editing session:

```json
{
  "inputs": {
    "objective": "A community notice for local residents about the upcoming council meeting...",
    "substance": "Date: 15 March 2026\nLocation: Town Hall\nAgenda items: ..."
  },
  "result": {
    "generatedTitle": "Council Meeting - 15 March 2026",
    "generatedContent": "<p>Local residents are invited to attend...</p>"
  }
}
```

When no generation has been run yet, the cache is:

```json
{
  "inputs": {
    "objective": "",
    "substance": ""
  },
  "result": null
}
```

### Cache lifecycle

- Inputs are cached as the editor types (on change), so they survive modal close and reopen.
- Results are cached when the generate endpoint returns successfully.
- Both inputs and results are **lost** when the editor navigates to a different page (Entwine reinitialises) or when the CMS reloads after apply.
- The cache does not survive browser refresh or page navigation.
- Compose does **not** flush the cache when the page edit form becomes dirty. The generated content is a creation artifact, not a review of existing content, so draft changes do not invalidate it.

## Elemental block class configuration

The content block class used when applying to Elemental pages is configurable via YML:

```yaml
SilverstripeLtd\AiCompose\Services\ComposeApplyService:
  default_content_block_class: 'DNADesign\Elemental\Models\ElementContent'
```

This allows sites that have customised their allowed block types to specify which class should be used. The apply service validates that the configured class:

1. Exists and is a subclass of `BaseElement`
2. Is allowed in the target page's ElementalArea (checked via the area's allowed elements configuration)

If validation fails, the apply endpoint returns an error with guidance: "The configured content block type is not allowed on this page. Update the `default_content_block_class` setting in your project YML configuration."
