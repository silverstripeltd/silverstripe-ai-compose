# CMS UX

## JS framework

The modal is rendered as a custom React component with an Entwine adapter for integration into the CMS.

## Compose button

A "Compose" button in the CMS preview toolbar, rendered in the same toolbar area as the other AI module buttons. The Entwine adapter checks for existing AI module button placeholders and positions the Compose button alongside them, falling back to the Share Draft Content placeholder or the view-mode selector area. It uses the same secondary toolbar button styling pattern as adjacent CMS utility actions.

### Button visibility

- **Shown** when the user has `canEdit()` permission on the page and the page has been saved at least once
- **Hidden** when the page does not yet exist or the user cannot edit it

## Modal behaviour

### Opening the modal

- Clicking the button opens a custom React modal (not FormBuilderModal)
- The Entwine adapter mounts the React component and passes schema meta from the server
- If previous inputs and/or a result exist from this editing session, the modal restores them from the Entwine cache
- If no previous session data exists, the modal opens with empty input fields and no result

### Modal layout

Top to bottom:

1. **Header** - "Compose page content"
2. **Overwrite warning** (contextual, always visible before and after generation):
   - **Non-Elemental pages:** Informational warning banner: "Applying will overwrite the page title and content with the generated text."
   - **Elemental pages:** Informational banner: "Applying will overwrite the page title and create a new content block."
   - The server provides the appropriate message text via schema meta based on whether the page uses Elemental, so the React component just renders whichever message it receives.
3. **Input section:**
   - **Purpose & Format** (textarea)
     - Label: "Purpose & Format"
     - Placeholder: "Describe what you want to create, who the target audience is, and the style of the page (e.g. a community notice, an event summary, or an internal policy update)."
     - Not database-backed. Cached on Entwine instance.
   - **Facts & Background** (textarea)
     - Label: "Facts & Background"
     - Placeholder: "Supply the raw data, bullet points, dates, and core details that must be included. The AI will use this as its single source of truth to ensure accuracy."
     - Not database-backed. Cached on Entwine instance.
4. **Generate button** - "Generate" on first use, "Regenerate" after a result exists. Uses the CMS info button style. Disabled while a request is in flight.
5. **Result section** (shown only after successful generation):
   - **Generated Title** - read-only text display of the generated title, with a copy-to-clipboard button
   - **Generated Content** - read-only rendered HTML preview of the generated content, with a copy-to-clipboard button
6. **Apply to Page button** (shown only after successful generation) - writes the generated title and content to the page's Draft records. Uses the CMS primary button style. Disabled while requests are in flight.

### Copy to clipboard

Each generated output field (title and content) has a small copy-to-clipboard icon button adjacent to its heading:

- **Title copy:** Copies the plain text title to the clipboard.
- **Content copy:** Copies the plain text content to the clipboard (HTML tags stripped). This gives editors clean text they can paste into other fields, documents, or tools without markup artifacts.
- On click, the button shows brief visual feedback (e.g. a checkmark or "Copied" tooltip) before reverting.

### No per-field checkboxes

Compose does not offer selective per-field apply. The "Apply to Page" button writes both the title and content at once. If the editor only wants one of them, they can use the copy-to-clipboard button and paste manually.

## Generate flow

1. Editor fills in one or both input fields.
2. Editor clicks "Generate" or "Regenerate".
3. Loading spinner shown in the result area while the XHR is in progress.
4. Generate button disabled during the request.
5. On success, the generated title and content are displayed in the result section. The generate button label changes to "Regenerate".
6. On failure, error toast is shown. Any previous result remains displayed. Input values are preserved.

## Apply flow

1. Editor reviews the generated title and content.
2. Editor clicks "Apply to Page".
3. Loading state shown while the apply request is in flight.
4. On success, the browser reloads the CMS so the page edit form shows the updated Draft content.
5. On failure, error toast is shown and the modal remains open with the result still displayed.

### Toast persistence across reload

The apply-success toast must survive the forced CMS reload. The Entwine adapter writes a pending toast descriptor to `sessionStorage` before triggering the reload. On the next page load, the adapter reads `sessionStorage`, replays the toast via the CMS toast API, and clears the stored entry.

## Result lifecycle

- Both inputs and results are cached on the **Entwine instance** rather than React state so they survive modal close and reopen.
- Cache is **lost** when the editor navigates to a different page (Entwine reinitialises) or when the CMS reloads after apply.
- Cache does **not** survive browser refresh or page navigation.
- Compose does **not** flush the cache when the page edit form becomes dirty. Generated content is a creation artifact unrelated to existing page content.

## Toast notifications

- **Generation success** - "Content generated successfully"
- **Generation failure** - error toast with message (development: provider error detail; production: generic message with server-side logging)
- **Apply success** - "Content applied to draft page"
- **Apply failure** - "Unable to apply generated content"
- **Empty inputs** - "Enter a purpose or facts before generating"
- **Block class error** - "The configured content block type is not allowed on this page. Update the default_content_block_class setting in your project YML configuration."

## Loading states

- **Schema load in progress:** Loading indicator shown while the modal fetches its schema metadata on open. Action buttons disabled.
- **Generation in progress:** Loading spinner in the result area. Generate button disabled.
- **Apply in progress:** Loading state with "Applying content..." while the Draft write request is in flight.

## No dirty-state interaction

Compose does not disable actions when the page form is dirty. This module generates new content rather than evaluating or translating existing saved content, so the current form state is irrelevant.

## No rating or diff

The compose modal does not display a compliance rating, reasoning summary, or diff preview. The generated content is displayed as a clean read-only preview.

## Modal actions

The modal has:

- A close control (standard modal close button and escape key)
- A generate or regenerate action (single button, label changes based on state)
- An apply to page action
- Copy-to-clipboard buttons on each generated output field

It does **not** support editing generated content inline. If the editor wants to modify the output, they apply it and edit in the CMS, or copy it and paste where needed.
