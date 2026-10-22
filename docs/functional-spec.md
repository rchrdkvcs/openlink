# Functional Reference

## Product Shape

Openlink is a self-hosted multi-workspace application. Each workspace contains its own members, domains, folders, short links, QR codes, and settings. Every short link belongs to exactly one workspace.

The product is a focused operational tool: fast lists, clear status indicators, predictable forms, and enough analytics to understand link and QR code performance without visitor profiling.

## Users and Access

The first user created on an instance becomes an instance admin. The instance supports closed, invite-only, and open registration modes, with invite-only as the default.

Users authenticate with email and password or configured OAuth providers. Two-factor authentication is available for user accounts. Password reset is handled by email.

Email and password registration sends a verification email immediately. Email verification is optional by default, so a new user can use Openlink even before mail is configured. An Instance Admin can require verification in instance settings. When required, users with an unverified email can access the profile page to resend verification, change their email, manage their password, or delete their account, but cannot use Openlink's main authenticated surfaces until their email is verified. Instance settings remain accessible to an unverified Instance Admin with a workspace so the requirement can be turned off again. OAuth-created users are treated as verified only when the provider returns a verified email. Changing the primary email makes the user unverified until the new email is confirmed.

Opening a valid email verification link redirects to the intended page, or the dashboard by default, and displays an email verification confirmation modal. The confirmation also appears after an onboarding redirect when the user has no workspace. Users can dismiss it to continue; it does not reappear on refresh or ordinary navigation.

Users can manage connected identities from the profile page. A connected identity can be linked only when the provider returns a verified email matching the user's primary email, and it cannot be linked if that provider identity already belongs to another user. Users may unlink connected identities as long as they retain at least one valid sign-in method. If the user changes their primary email, connected identities with a provider email that no longer matches remain visible but stop being valid sign-in methods until reconnected with the verified primary email.

Users can select their profile avatar from the avatars supplied by valid connected identities. If the selected connected identity is removed or becomes invalid, Openlink automatically selects another available valid connected identity avatar or falls back to initials.

Users can create and revoke API tokens from the profile page. API tokens are user-owned credentials, are shown in clear text only once after creation, and act with the same workspace roles as the user. API tokens do not have configurable scopes. Creating or using an API token requires a verified email when instance settings require email verification.

New users go through a short onboarding wizard: they name their first workspace (required when they have none), then can optionally create a first short link and generate an invite link for their team. Users who arrive through an invite link skip the wizard and land in the workspace they joined.

Workspace members have one workspace role:

- Owner: controls all workspace data, settings, members, domains, and ownership-level actions.
- Admin: manages members, domains, folders, links, QR codes, and settings without owning the workspace.
- Editor: creates and edits links and QR codes.
- Viewer: reads all links, QR codes, and analytics in the workspace. Cannot create or change them.

Access is the workspace role. Folders organise links and do not grant or restrict access.

### Members and invite links

People join a workspace through invite links (see ADR 0008). Owners and admins create invite links, each carrying a role (Admin, Editor, or Viewer), an optional expiration date, and an optional usage limit; several links can be active at once and each can be revoked individually. Anyone who opens a usable link can join the workspace with the link's role — signed-in users join directly, and new visitors create an account first unless the instance registration mode is closed. Existing members who open a link keep their current role.

Owners and admins manage members: they can change any member's role and remove any member, except the Owner, who can only be changed through an explicit ownership transfer (the previous owner becomes an Admin). Any member except the Owner can leave a workspace. Removing a member keeps the links they created, which belong to the workspace.

## Workspaces

A user can belong to multiple workspaces. The app should make the current workspace obvious and allow switching between workspaces. Each workspace can configure a preferred domain used by default for new short links.

Workspace settings include defaults for new links, the preferred domain, member management, folder management, and workspace-level feature settings.

## Folders and Tags

Folders organise links. A short link can belong to a folder. Folders do not grant or restrict access.

Tags classify links for filtering and search. Tags do not grant access and must not be used as a permission boundary.

## Domains

A workspace can add one or more domains. A domain must be verified before it can serve short links.

Domain verification uses a DNS TXT record. The UI should show the required TXT record, verification status, last check time, and any verification failure details visible to workspace admins.

Domain states:

- Pending verification
- Verified
- Failed verification
- Disabled

The instance has a default domain so new installations can create links before a workspace-owned domain is configured. A workspace can choose a preferred domain from its usable domains.

## Short Links

A short link connects a domain and slug to a destination URL. Destination URLs must be valid HTTP or HTTPS URLs and must not create obvious resolution loops.

Slugs are unique per domain and remain reserved until the short link is permanently deleted. Archived, disabled, scheduled, active, and expired links all keep their slug reserved.

Generated slugs use a readable non-ambiguous alphabet, are random rather than incremental, and default to 6 characters. Custom slugs can contain path separators as part of the slug. Openlink maintains configurable reserved slugs and reserved slug prefixes that cannot be used for short URLs.

Short links support:

- Generated or custom slugs
- Enabled and disabled states
- Archived state
- Activation date
- Expiration date
- Visit limit
- Optional fallback URL
- Optional visitor password
- Folder assignment
- Tags

### UTM builder and UTM Templates

The link editor includes a UTM builder for the default destination URL (see ADR 0016). It shows optional source, medium, campaign, term, and content fields, reads any UTM parameters already in the destination URL, and shows the exact final URL. Editing a field replaces that UTM parameter in place, so the destination keeps one value per UTM parameter; unrelated query parameters, repeated keys, fragments, Unicode, and existing percent-encoding are kept as written. Clearing a field removes that UTM parameter from the destination URL. A builder with no values leaves the destination URL unchanged. Values are kept exactly as typed; lowercase naming is suggested as a convention, never enforced.

Owners and admins manage UTM Templates in workspace settings: they can create, rename, update, and delete them. Templates belong to one workspace and hold parameter values only. Any member who can edit links can apply a template from the link editor: the editor lists the changes the template makes, applies them only when confirmed, and leaves every field editable before saving. Applying a template sets only the parameters the template defines. The resulting destination URL is saved like any other destination; links do not remember which template was applied, so updating or deleting a template does not change existing links.

Every successful change to a short link appends a link revision to its link history, recording when the change happened, who made it, whether it came from the dashboard, the API, or the system, and what changed. Every member can read the link history. Owners, Admins, and Editors can preview and restore an earlier link revision; restoring records a new link revision and keeps the intervening history. Restoring covers destination and fallback URLs, enabled and archived state, schedule, visit limit, folder, tags, and Smart Routing, but never the short URL, a visitor password, visit totals, analytics, or QR codes. See ADR 0015.

Every successful change to a short link appends a link revision to its link history, recording when the change happened, who made it, whether it came from the dashboard, the API, or the system, and what changed. Every member can read the link history. Owners, Admins, and Editors can preview and restore an earlier link revision; restoring records a new link revision and keeps the intervening history. Restoring covers destination and fallback URLs, enabled and archived state, schedule, visit limit, folder, tags, and Smart Routing, but never the short URL, a visitor password, visit totals, analytics, or QR codes. See ADR 0017.

Disabled links do not resolve, regardless of lifecycle rules. Archived links are hidden from primary dashboard views, preserved for history and analytics, do not resolve, and keep their slug reserved.

### Bulk actions

Owners, Admins, and Editors can select short links on the current page of the Links list and apply one action to the whole selection: move to a folder or Unfiled, add or remove tags, enable, disable, or archive. Viewers cannot select or change links.

- A selection holds explicitly chosen link IDs from the current page, up to 100 links. It is cleared when the workspace, a filter, or the page changes.
- Moving, enabling, disabling, and archiving preview the action with the affected count and ask for confirmation; tag changes are previewed in the tag dialog. Enabling does not override lifecycle rules: scheduled, expired, and archived links still do not resolve.
- The server validates and authorizes the complete selection before writing. A selection that contains a link from another workspace, or a link that no longer exists, is rejected with no changes applied, and the list reports that nothing was changed. The batch runs in one transaction.
- Each link is changed through the same mutation path as single-link edits, so folder and tag membership and cached resolutions behave identically. Archived links keep their slugs, analytics, and visit counts; archiving an already archived link keeps its original archive date.
- Permanent deletion and destination replacement are not available as bulk actions.

### Link CSV import and export

The Links page exports the links in the current view as a versioned Link CSV, and imports a Link CSV after a preview and an explicit confirmation. Imports are create-only: they never change or overwrite existing links, keep short URLs only on domains the workspace controls, and never reset visit counts or analytics. Visitor passwords, Smart Routing rules, QR Codes, and analytics are not exported or imported. The format and the import rules are described in [`link-csv.md`](./link-csv.md).

### Routing editor

Smart Routing is edited on a dedicated page for each short link, opened from the link inspector with "Open routing editor". The inspector keeps a compact summary of the first rules.

The page shows the routing as a left-to-right graph. An incoming visit enters the first rule. Each rule node lists its conditions; its "Match" output leads to its destination or its split-test variants, and its "No match" output leads to the next rule. The chain ends at the default destination. The graph is laid out automatically from the ordered rules; dragging a rule up or down changes its priority, and node positions are not saved.

Selecting a node opens it in the side panel, where members who can edit content change, duplicate, reorder, disable, or delete the rule, or change the default destination. Validation errors are shown on the affected node and in the panel. A List view shows the same rules as an ordered list for keyboard and screen reader users. Viewers can open the page and run a Routing Preview, but every editing control is read-only.

### Routing Preview

The routing editor's Test panel runs a Routing Preview. After a preview, the graph highlights the path the simulated visit took and the chosen destination, and dims the other branches. It accepts a synthetic country, language, device, browser, OS, referrer URL, UTM parameters, date and time with an explicit timezone, and a visitor seed. Members who can edit content test the current editor state, including unsaved changes, after the same validation used on save. Viewers test the saved routing.

The result lists every routing rule with its status (matched, no match, disabled, skipped, or not checked), each condition result, configured variant weights and shares, the selected variant, and the final or default destination. The seed replaces the daily visitor hash, so the same seed always picks the same variant; real visitors keep a variant for one day. The short link's public availability (lifecycle status, domain status, password protection) is shown separately from the simulated destination.

A Routing Preview never records visits or analytics, unlocks a protected link, activates a domain, saves drafts, or requests destination URLs.

## Public Resolution

When a visitor opens a short URL, Openlink resolves it in this order:

1. Find the domain and slug.
2. Check that the domain is verified and active.
3. Check that the short link exists and is enabled.
4. Check lifecycle rules.
5. Check the visit limit.
6. Validate the visitor password when required.
7. Attribute QR code scans when applicable.
8. Record analytics.
9. Redirect to the destination URL or fallback URL.

Availability rules run before password validation. If a link is expired, disabled, scheduled, or over its visit limit, visitors should not be shown a password form.

When a short URL cannot resolve and no fallback URL is available, visitors see a neutral unavailable page. Detailed reasons remain visible only in the dashboard and analytics.

## Destination Health

Editors and above can check every destination of a short link from the link inspector: the default destination, the fallback URL, and enabled routing rule and variant destinations. Each destination shows its latest Destination Health: not checked, reachable, an HTTP failure, a DNS, TLS, or connection failure, a timeout, inconclusive (for example a 403 or 429 from bot protection), or not checkable because it points to a private address.

Two consecutive failures open a Destination Incident. Incidents appear on the workspace Issues page with the affected short links and their QR codes, ordered by visits and scans in the last 7 days, and Editors can retry them. The next reachable result recovers the incident, which stays listed as recently recovered for 7 days. Results never change how a short link resolves, and Openlink never replaces a destination or switches to the fallback URL automatically.

The Instance Admin can enable Destination Monitoring to run these checks in the background.

## Protected Links

A protected link requires a visitor password before resolution. The password protects public resolution only; it does not affect workspace member access in the dashboard.

Visitor passwords are never shown in clear text after creation. Failed password attempts are recorded as analytics outcomes but do not consume the visit limit. A successful password entry can be remembered briefly for the visitor to avoid repeated prompts.

## Visit Limits

A visit limit counts successful resolutions to the destination URL. Failed password attempts, unavailable links, disabled links, scheduled links, expired links, and blocked attempts do not consume the visit limit.

When the visit limit is reached, the link becomes expired.

## QR Codes

A QR Code is a Workspace-owned named scannable entry point managed from the QR Codes area. It can link to one Short Link or carry a native direct payload. Multiple QR Codes can link to the same Short Link, and each linked QR Code has its own scan analytics.

Each linked QR Code encodes a trackable entry URL on the Short Link's Domain (`https://{domain}/qr/{token}`). Direct-payload QR Codes encode their native payload and must be re-exported after their content changes.

A direct-payload QR code is a named QR code that carries a native payload such as URL, text, email, phone, SMS, Wi-Fi, vCard, calendar event, location, or a raw QR payload. Exported direct-payload QR code images encode that native payload directly so device scanners can act on it without opening an Openlink page.

QR code features:

- PNG export
- SVG export
- Configurable export size
- Configurable foreground color
- Configurable background color
- Configurable margin (quiet zone, in modules)
- Configurable error correction level
- Module style: squares, rounded, or dots
- Eye style: square, rounded, or circle
- Transparent background
- Optional centered logo (error correction is raised to at least quartile when a logo is set)

Each QR code has a dedicated studio page with a live preview of unsaved settings, copyable entry URL, and PNG/SVG downloads at a chosen size.

Before exporting, any workspace member can run a Readability Check from the studio. It decodes the PNG export of the current draft at the chosen export size (rendered at up to 1024 px for decoding) and reports, per format, whether the code decodes to the expected content, decodes to other content, cannot be decoded, uses an unsupported format, or cannot be checked. The SVG verdict is derived from the PNG decode because both exports draw the same module matrix and design. The check flags weak or inverted contrast, transparent backgrounds, missing or narrow quiet zones, small modules, estimated logo coverage that error correction may not recover, and, when an optional print width is entered, modules smaller than 0.4 mm on paper. It summarises the encoded content, linked Short URL, output and suggested minimum print dimensions, domain availability, and lifecycle restrictions. The draft is sent in a POST body so native payloads never appear in URLs. Results are discarded as out of date as soon as the payload, design, logo, export size, or print width changes. Members who can edit may apply readable settings to the draft when that changes something; nothing is saved until they save. Unsaved logo or target changes must be saved before they can be checked. The check makes no visits or scans, its warnings are guidance, and it always recommends a physical print test. See ADR 0020.

## Analytics

Analytics are aggregated and do not identify individual visitors.

Openlink tracks:

- Lifetime visits and scans
- Visits and scans over time (hourly, daily, or monthly depending on the selected range)
- Unique visitors (privacy-preserving daily hash, never a profile)
- Referrer host and acquisition channel (direct, search, social, video, email, messaging, AI, referral)
- UTM source, medium, campaign, term, and content
- Approximate country and browser language
- Device type, browser, and operating system
- Bot and crawler traffic, flagged and excluded from headline figures
- Resolution outcome

Resolution outcomes include success, password failed, expired, disabled, scheduled, not found, domain unavailable, and visit limit reached.

Analytics events are retained for 12 months by default; retention is configurable in the instance admin panel. The analytics page filters by date range, link, domain, folder, tag, and metric, compares each figure to the previous period, and exports the filtered events as CSV.

## Instance Admin Panel

The instance admin panel manages product settings for the whole installation. It does not manage infrastructure secrets or boot-time server configuration.

Instance settings include:

- Instance name and branding basics
- Registration mode
- Default domain
- Reserved slugs and prefixes
- Slug generation defaults
- Analytics retention
- Destination Monitoring
- Feature flags for optional product capabilities
- Public unavailable page basics

Secrets, database credentials, Redis credentials, mail credentials, storage credentials, and boot-time Laravel configuration remain outside the panel.
