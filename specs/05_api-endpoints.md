# API Endpoints

## Controller

- Class: `ComposeController` (namespace: `SilverstripeLtd\AiCompose\Controllers\ComposeController`)
- Registered as an admin route using the standard Silverstripe admin controller pattern

## Endpoints

### GET `/admin/ai-compose/schema/{ID}?fqcn={FQCN}`

Fetch the FormSchema payload for the modal.

- **FQCN:** Fully qualified class name of the parent DataObject (URL-encoded). Validated - only classes with the compose extension applied are allowed.
- **ID:** DataObject ID
- **Auth:** CMS session
- **Behaviour:**
  1. Validate FQCN and ID. The FQCN must be a valid class with the compose extension applied.
  2. Validate the record exists and user has `canEdit()` permission.
  3. Return the FormSchema JSON describing the modal layout plus schema meta.
- **Schema meta:**
  - `generateUrl` - URL for the generate endpoint
  - `applyUrl` - URL for the apply endpoint
  - `supportsApply` - whether apply is available (true when the page supports content writes)
  - `hasElemental` - whether the page uses Elemental (informational for the modal)
  - `labels` - UI labels for the modal
  - `messages` - status and guidance messages including the appropriate overwrite/append warning text
- **Response:** Standard Silverstripe FormSchema response
- **Error responses:**
  - 400 - invalid request parameters or invalid FQCN
  - 403 - user cannot edit the record
  - 404 - record not found

The Entwine adapter fetches this schema when mounting the React component. The schema defines the modal metadata server-side so the React component remains a thin renderer.

### POST `/admin/ai-compose/generate/{ID}`

Generate page content from the editor's inputs.

- **FQCN:** Passed via request params, validated as above
- **ID:** DataObject ID
- **Auth:** CMS session + CSRF token
- **Request body:** JSON payload:
  ```json
  {
    "objective": "A community notice for local residents...",
    "substance": "Date: 15 March 2026\nLocation: Town Hall..."
  }
  ```
- **Behaviour:**
  1. Validate FQCN and record exists with `canEdit()` permission.
  2. Validate that at least one of `objective` or `substance` is non-empty.
  3. Check for writing style and tone rules integration (see `specs/03_prompts.md`).
  4. Build prompts and call the AI provider.
  5. Parse and validate the JSON response.
  6. Return the generated title and content.
- **Response:**
  ```json
  {
    "generatedTitle": "Council Meeting - 15 March 2026",
    "generatedContent": "<h2>Join us at the Town Hall</h2><p>Local residents are invited...</p>"
  }
  ```
- **Error responses:**
  - 400 - both inputs empty or invalid FQCN
  - 403 - user cannot edit the record or CSRF token invalid
  - 404 - record not found
  - 500 - AI provider failure

### POST `/admin/ai-compose/apply/{ID}`

Apply generated content to the page's Draft records.

- **FQCN:** Passed via request params, validated as above
- **ID:** DataObject ID
- **Auth:** CMS session + CSRF token
- **Request body:** JSON payload:
  ```json
  {
    "title": "Council Meeting - 15 March 2026",
    "content": "<h2>Join us at the Town Hall</h2><p>Local residents are invited...</p>"
  }
  ```
- **Behaviour:**
  1. Validate FQCN and record exists with `canEdit()` permission.
  2. Validate that `title` and `content` are non-empty strings.
  3. Sanitise content (HTML sanitisation) and title (strip tags).
  4. Write `title` to the page's `Title` field.
  5. Determine content target:
     - **Elemental page:** Create a new block of the configured class, set its content field, append to the page's first ElementalArea, write to Draft.
     - **Non-Elemental page:** Write content to the page's `Content` field.
  6. Save the page to Draft.
  7. Return success.
- **Response:**
  ```json
  {
    "applied": true,
    "reloadRequired": true
  }
  ```
- **Error responses:**
  - 400 - missing or invalid payload, invalid FQCN, configured block class not allowed, no suitable content field on block class
  - 403 - user cannot edit the record or CSRF token invalid
  - 404 - record not found

Applying content writes to Draft records only. It never publishes content.

### Apply sanitisation

AI-generated content is sanitised before being written to Draft fields, replicating the server-side protections of a normal CMS save:

- **Content (HTML):** Run through Silverstripe's `HTMLEditorSanitiser` (using the active `HTMLEditorConfig` allowlist) followed by the framework's `XssSanitiser` with default settings. This strips dangerous elements (`script`, `embed`, `object`, `style`, `svg`), event handler attributes (`on*`), and dangerous URL schemes (`javascript:`, `data:text/html`, `vbscript:`).
- **Title (plain text):** All HTML tags stripped via `strip_tags()`.

## FQCN validation

1. Must be a valid, existing class
2. Must have the compose extension applied
3. Current user must have `canEdit()` on the specific record

This validation supports future expansion to DataObject types beyond SiteTree without controller changes.

## Error response format

```json
{
  "error": "Human-readable error message"
}
```

## CSRF protection

The `generate` and `apply` endpoints require a valid CSRF token, which is standard for Silverstripe admin controller POST requests. The React component includes the token in the XHR request header.

## FormSchema

The modal uses Silverstripe's FormSchema mechanism to define its layout server-side. This keeps the React component thin, and the returned schema meta carries the action URLs, labels, and messaging.

This module intentionally uses FormSchema only for schema meta, not as a full record-editing form. The real work happens through the JSON `generate` and `apply` controller endpoints.

## No GET endpoint for previous results

There is no GET endpoint to fetch previous results. Generated content is cached on the Entwine instance rather than persisted to DB. The modal does not need to load stored data from the server on open.
