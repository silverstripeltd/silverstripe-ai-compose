Use the editorial brief below to generate a page title and HTML content for a CMS-managed page.

Objective guidance:
{objectiveGuidance}

Substance guidance:
{substanceGuidance}

Requirements:
- Generate a concise, descriptive page title in plain text.
- Generate page content as clean HTML suitable for a CMS rich text editor.
- Use semantic HTML elements: <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a> (only if URLs are provided in the substance).
- Do not use <h1>.
- Do not include <html>, <head>, <body>, or <div> wrapper elements.
- Do not include inline styles, classes, or data attributes.
- Structure the content with appropriate headings and paragraphs for scannability.
- If the substance includes dates, names, figures, or specific details, these must appear accurately in the generated content.

=== OBJECTIVE_START ===
{objective}
=== OBJECTIVE_END ===

=== SUBSTANCE_START ===
{substance}
=== SUBSTANCE_END ===

Return a JSON object using exactly this schema:
{
  "title": "Concise page title",
  "content": "<p>HTML body content</p>"
}
