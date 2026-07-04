# Product Scope

## MVP

- Workspaces with members and workspace roles.
- Folders with folder permissions.
- Short links with generated or custom slugs.
- Generated slugs use a readable non-ambiguous alphabet, are random rather than incremental, and default to 6 characters. Custom slugs may contain path separators as part of the slug.
- Openlink maintains configurable reserved slugs and reserved slug prefixes that cannot be used as short URLs.
- Destination URLs must be valid HTTP or HTTPS URLs and must not create obvious resolution loops.
- Domains verified through DNS TXT records.
- Fast public redirection.
- Enabled and disabled links.
- Activation dates, expiration dates, visit limits, and fallback URLs.
- Protected links with visitor passwords.
- Named QR codes attached to short links.
- QR codes can be exported as PNG and SVG with configurable size, foreground color, background color, margin, and error correction level.
- Aggregated analytics for visits and scans.
- Analytics retention defaults to 12 months for daily aggregates, while lifetime totals remain available while the related link or QR code exists.
- Instance admin panel and workspace settings.
- Email and password authentication with two-factor authentication.

## Resolution Order

When a visitor opens a short URL, Openlink resolves it by finding the domain and slug, checking that the domain is verified and active, checking that the short link is enabled, checking lifecycle rules, checking the visit limit, validating the visitor password when required, attributing QR code scans when applicable, recording analytics, and then redirecting to the destination URL or fallback URL.

## Public Unavailable Pages

When a short URL cannot resolve and no fallback URL is available, visitors see a neutral unavailable page. Detailed reasons such as disabled, expired, scheduled, or visit limit reached remain visible only in the dashboard and analytics.

## Later

- Smart destinations and app link presets.
- Bulk import and export.
- Public API.
- Webhooks.
- SSO and OAuth login.
- Advanced QR code design templates.
- QR code logos.
- Alerts and monitoring.
- SaaS billing and commercial plans.
