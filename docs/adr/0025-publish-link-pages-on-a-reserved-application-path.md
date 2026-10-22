# Publish Link Pages on a reserved application path

Some audiences need one public URL that offers several choices instead of an immediate redirect. We add the Link Page: a Workspace-owned object with an internal name, a public path, a title, a short description, an optional logo, and an ordered list of explicitly selected Short Links. A Link Page is never derived from a Workspace, Folder, or Tag, and it does not change the Short Link domain model: a Short Link cannot use a Link Page as its Destination URL, and a QR Code reaches a Link Page only by encoding its URL as a direct URL payload.

## Public surface

A Link Page is public only while it is published. It is served at `/p/{path}` on the application host and nowhere else. The path is lowercase letters, digits, and hyphens, is unique across the instance, and stays reserved until the Link Page is deleted. Draft, unpublished, and deleted Link Pages show the same neutral unavailable page as an unavailable Short URL.

The public response contains only the title, description, logo, display preferences, and the label, Short URL, and optional bundled icon identifier of each listed Short Link. It never contains the Workspace, its members, Destination URLs, destination hostnames, Short Link settings, or analytics. Each entry links to the Short URL, so a visit still goes through the normal lifecycle, password, Smart Routing, and analytics path; a Protected Link shows its password form and its Destination URL stays private.

A listed Short Link appears publicly only while it is active and its Domain is usable. Archived, disabled, scheduled, expired, and unreachable entries stay in the editor with a reason, and they are left out of the public page without explanation. A permanently deleted Short Link is removed from every Link Page. A Link Page with no public entries shows a neutral availability message.

## Collisions

Short Link slugs `p` and `p/…` are always reserved on the application host, whatever the instance reserved slug settings are. A path cannot be chosen when an existing application-host Short Link already uses `p/{path}`. A request for `/p/{path}` without a published Link Page continues through normal Short URL resolution, so Short Links created before this decision keep resolving. Stable QR routes (`/qr/{token}`) and application routes do not start with `/p/`.

## Content safety

Text is stored and rendered as plain text; no HTML, CSS, or JavaScript is accepted. A logo must be a PNG, JPEG, or WebP image. Openlink decodes it, scales it to at most 256 pixels, and re-encodes it as PNG before storing it, so metadata and appended content are discarded and SVG is never accepted.

## Display preferences and icons

Each Link Page persists whether to show icons, labels, and Short URLs. At least one must be visible. Icons without labels or URLs use a compact icons-only layout; each link retains its plain-text label as its accessible name and hover title. The editor preview and published page use the same renderer and settings. Hiding labels changes their visual presentation only: labels and Short URLs remain public content in the response.

Icons are bundled PNG favicons for a small catalogue of known services. The server matches exact destination hostnames to an icon identifier; the public response includes only that identifier, never a hostname, path, query, or remote image URL. This intentionally reveals the service brand for an unprotected link, not its exact destination. Protected Links always use the same generic icon as unknown sites, even if a recognised service is their destination. Changes to protection are reflected on the next request, without a stored icon snapshot. Smart Routing destinations are never consulted for icons.

Link Pages make no outbound HTTP or DNS requests for icons, including in the editor and picker. Unknown sites use a generic globe, not a purported custom favicon. This trades arbitrary-site favicon coverage for privacy, predictable rendering, and avoiding a public remote-fetch endpoint. Adding arbitrary favicon discovery requires a separate SSRF-safe design. Bundled icon provenance and the supported hosts are documented in [Link Page icons](../link-page-icons.md).

## Permissions

Every Member can list Link Pages and open the editor preview. Owner, Admin, and Editor can create, edit, sort entries, publish, unpublish, and delete Link Pages. These actions change only the Link Page and never change the selected Short Links.

## Deferred

Page view analytics, publication on workspace Domains, API endpoints, themes, and additional templates are out of scope. Page views must be recorded separately from Visits when they are added, and publication on workspace Domains requires a decision on address reservation and routing precedence first.
