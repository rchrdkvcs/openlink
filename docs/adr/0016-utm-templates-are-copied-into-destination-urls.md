# UTM Templates are copied into destination URLs

The UTM builder edits a Short Link's Destination URL in the link editor, and a UTM Template is only a named set of UTM parameter values that a member applies explicitly. Applying a template copies its values into the builder; the saved Destination URL is the only record of the result, stored through the ordinary short link mutation path with its usual validation, loop checks, Smart Routing, and analytics. Short links keep no reference to the template, so renaming, updating, or deleting a template never rewrites existing destinations, and no Campaign aggregate is introduced: campaign grouping uses tags and the UTM dimensions already recorded by analytics.

## Considered Options

- **Linked templates that propagate changes**: rejected because silently rewriting destinations that are already shared or printed changes where visitors land without anyone editing the link, and it would require a background rewrite path that bypasses the per-link validation.
- **Server-side URL rewriting**: rejected for the MVP because the exact final URL must be visible before saving; the transformation runs in the editor and the server validates the resulting URL like any other destination.

## Consequences

- The builder preserves the rest of the Destination URL verbatim: unrelated query parameters, repeated keys, fragments, Unicode, and existing percent-encoding are not re-encoded. Only the supported UTM keys (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`) are rewritten, each to at most one occurrence.
- Clearing a builder field removes every occurrence of that key, including occurrences without a value. Applying a template also removes repeated occurrences of the keys it sets. Applying a template only sets the keys the template defines and leaves the other UTM keys as they are.
- The query is the part between the first `?` and the first `#`; UTM parameters inside a fragment, such as hash-based routes, are left untouched.
- Values are stored exactly as entered; case conventions are shown as guidance and never normalised.
- The MVP covers the default Destination URL only; routing rule destinations and automatic workspace-wide defaults are deferred.
