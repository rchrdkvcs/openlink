# Product

Openlink is a self-hosted URL management application for individuals and teams
that want to retain control of their domains and data.

## Features

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
- QR codes can be exported as PNG and SVG with configurable size, foreground color, background color, margin (quiet zone in modules), error correction level, module style (squares, rounded, dots), eye style (square, rounded, circle), transparent background, and an optional centered logo.
- Aggregated analytics for visits and scans.
- Analytics retention defaults to 12 months for daily aggregates, while lifetime totals remain available while the related link or QR code exists.
- Instance admin panel and workspace settings.
- Email and password authentication with email verification and two-factor authentication.
- OAuth sign-in with connected identity management from the profile page.
- Profile avatars selected from connected identity avatars.
- Token-based public API access through user-owned API tokens.
- Workspace-owned Bio Pages with shared Domain and Slug addressing, draft and published versions, profile-style content, ordered destinations, controlled visual customization, and aggregated analytics.
- Named QR codes attached to Bio Pages.

## Bio Pages

A workspace can own multiple Bio Pages, managed independently from Folders in a dedicated dashboard area. A Bio Page combines a profile header with ordered destination, social destination, section-heading, and short-text elements. Destinations can reference a Short Link or use a direct external destination.

A Bio URL uses a Domain and Slug from the same namespace as Short URLs. A Bio Page has an editable Draft Version and, after explicit publication, a Published Version. Draft changes do not affect visitors until published. Bio Pages support uploaded profile and background images, a controlled theme system, public sharing metadata, optional search indexing, and optional Openlink branding.

## Resolution Order

When a visitor opens a short URL, Openlink resolves it by finding the domain and slug, checking that the domain is verified and active, checking that the short link is enabled, checking lifecycle rules, checking the visit limit, validating the visitor password when required, attributing QR code scans when applicable, recording analytics, and then redirecting to the destination URL or fallback URL.

## Public Unavailable Pages

When a short URL cannot resolve and no fallback URL is available, visitors see a neutral unavailable page. Detailed reasons such as disabled, expired, scheduled, or visit limit reached remain visible only in the dashboard and analytics.
