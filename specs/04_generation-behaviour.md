# Generation Behaviour

## Two operations

The module supports two distinct operations:

1. **Generate** - send the editor's inputs to the AI provider, validate the response, return title and content to the modal
2. **Apply** - write the generated title and content to Draft page/element records

Neither operation persists results to the database. Generation returns content to the client, where it is cached on the Entwine instance. Apply writes directly to Draft records.

## Generate pipeline

1. **Read inputs** - receive the editor's `objective` and `substance` from the request.
2. **Validate inputs** - if both fields are empty, return an error. At least one must be non-empty.
3. **Check writing style and tone rules** - if `SiteConfig` has a non-empty `RefineDefinition` field, include it in the prompt context.
4. **Build prompts** - construct system and user prompts per `specs/03_prompts.md`.
5. **Call AI provider** - single API call.
6. **Parse response** - validate JSON structure, require non-empty `title` and `content` strings.
7. **Return to modal** - the structured result is returned to the frontend and cached on the Entwine instance alongside the inputs.

## Apply pipeline

1. **Receive payload** - the modal sends the generated `title` and `content`.
2. **Validate payload** - require both `title` and `content` to be non-empty strings.
3. **Sanitise content** - apply HTML sanitisation to the `content` value (see sanitisation section below).
4. **Strip tags from title** - apply `strip_tags()` to the `title` value.
5. **Write title** - write the sanitised title to the page's `Title` field on Draft.
6. **Determine content target** - check whether the page uses Elemental or a plain `Content` field:
   - **Elemental page:** Create a new block of the configured class (default `ElementContent`) and append it to the page's first ElementalArea. Set the block's content field and write it to Draft.
   - **Non-Elemental page:** Write the sanitised content directly to the page's `Content` field on Draft.
7. **Write page** - save the page to Draft.
8. **Return response** - return success with `reloadRequired: true` so the modal triggers a CMS reload.

### Elemental block creation

When creating a new Elemental block:

1. Load the configured block class from YML (default: `DNADesign\Elemental\Models\ElementContent`).
2. Verify the class exists and is a subclass of `BaseElement`.
3. Get the page's first ElementalArea (via the first `has_one` relation that points to an `ElementalArea`).
4. Check that the configured block class is allowed in that area (via the area's allowed elements configuration). If not allowed, return an error with guidance to configure the `default_content_block_class` setting.
5. Create a new instance of the block class.
6. Set its HTML/Content field to the generated content.
7. Set its `ParentID` to the ElementalArea ID.
8. Set its `Sort` value to append it after existing blocks.
9. Write the block to Draft.

### Content field detection for custom block classes

The apply service needs to know which field on the configured block class should receive the generated content. This is determined by:

1. If the class is `ElementContent` (or a subclass), use the `HTML` field.
2. Otherwise, look for the first `DBHTMLText` field on the class. If found, use that field.
3. If no `DBHTMLText` field exists, fall back to the first `DBText` field.
4. If no suitable field is found, return an error indicating the configured block class has no supported content field.

## Apply sanitisation

AI-generated content is sanitised before being written to Draft fields, replicating the server-side protections of a normal CMS save:

- **Content (HTML):** Run through Silverstripe's `HTMLEditorSanitiser` (using the active `HTMLEditorConfig` allowlist) followed by the framework's `XssSanitiser` with default settings. This strips dangerous elements (`script`, `embed`, `object`, `style`, `svg`), event handler attributes (`on*`), and dangerous URL schemes (`javascript:`, `data:text/html`, `vbscript:`).
- **Title (plain text):** All HTML tags stripped via `strip_tags()`.

## Regeneration

Clicking Generate again discards the previous cached result and replaces it with the new response. The Entwine cache holds only the latest result. The editor's input fields retain their values so the editor can tweak and regenerate without retyping.

## Permissions

Deferred to the parent DataObject - the editor must have `canEdit()` on the page.

## Error handling

- **Empty inputs:** Error message returned to the modal. No API call made.
- **Provider failure:** `AIProviderException` caught by the controller, error toast shown in the modal.
- **Malformed response:** `AIProviderException` thrown if the JSON is invalid or required fields are missing. Error toast shown.
- **Elemental block class not allowed:** Error returned with configuration guidance.
- **No suitable content field on custom block class:** Error returned with guidance.

## Concurrency

If two editors compose content for the same page simultaneously, each gets their own cached result on their Entwine instance. Apply writes are independent Draft writes - last write wins, same as normal CMS editing.
