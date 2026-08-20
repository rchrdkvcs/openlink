# Functional Reference

## Product Shape

Openlink is a self-hosted multi-workspace application. Each workspace contains its own members, domains, folders, short links, Bio Pages, QR codes, permissions, and settings. Every short link and Bio Page belongs to exactly one workspace.

The product is a focused operational tool: fast lists, clear status indicators, predictable forms, and enough analytics to understand link and QR code performance without visitor profiling.

## Users and Access

The first user created on an instance becomes an instance admin. The instance supports closed, invite-only, and open registration modes, with invite-only as the default.

Users authenticate with email and password or configured OAuth providers. Two-factor authentication is available for user accounts. Password reset is handled by email.

Email and password registration sends a verification email immediately. Users with an unverified email can access the profile page to resend verification, change their email, manage their password, or delete their account, but they cannot use Openlink's main authenticated surfaces until their email is verified. OAuth-created users are treated as verified only when the provider returns a verified email. Changing the primary email makes the user unverified until the new email is confirmed.

Users can manage connected identities from the profile page. A connected identity can be linked only when the provider returns a verified email matching the user's primary email, and it cannot be linked if that provider identity already belongs to another user. Users may unlink connected identities as long as they retain at least one valid sign-in method. If the user changes their primary email, connected identities with a provider email that no longer matches remain visible but stop being valid sign-in methods until reconnected with the verified primary email.

Users can select their profile avatar from the avatars supplied by valid connected identities. If the selected connected identity is removed or becomes invalid, Openlink automatically selects another available valid connected identity avatar or falls back to initials.

Users can create and revoke API tokens from the profile page. API tokens are user-owned credentials, are shown in clear text only once after creation, and act with the same workspace roles and folder permissions as the user. API tokens do not have configurable scopes. Creating or using an API token requires a verified email.

New users go through a short onboarding wizard: they name their first workspace (required when they have none), then can optionally create a first short link and generate an invite link for their team. Users who arrive through an invite link skip the wizard and land in the workspace they joined.

Workspace members have one workspace role:

- Owner: controls all workspace data, settings, members, domains, and ownership-level actions.
- Admin: manages members, domains, folders, links, QR codes, and settings without owning the workspace.
- Editor: creates and edits links and QR codes in accessible folders, and creates and edits Bio Page Draft Versions without publishing them.
- Viewer: reads links, Bio Pages, QR codes, and analytics available to the workspace.

Folder permissions are Can view, Can edit, and Can manage. Members do not see folders they cannot access, except Owner and Admin roles, which can see all folders in the workspace.

### Members and invite links

People join a workspace through invite links (see ADR 0008). Owners and admins create invite links, each carrying a role (Admin, Editor, or Viewer), an optional expiration date, and an optional usage limit; several links can be active at once and each can be revoked individually. Anyone who opens a usable link can join the workspace with the link's role — signed-in users join directly, and new visitors create an account first unless the instance registration mode is closed. Existing members who open a link keep their current role.

Owners and admins manage members: they can change any member's role and remove any member, except the Owner, who can only be changed through an explicit ownership transfer (the previous owner becomes an Admin). Any member except the Owner can leave a workspace. Removing a member revokes their folder permissions but keeps the links they created, which belong to the workspace.

## Workspaces

A user can belong to multiple workspaces. The app should make the current workspace obvious and allow switching between workspaces. Each workspace can configure a preferred domain used by default for new short links.

Workspace settings include defaults for new links, the preferred domain, member management, folder management, and workspace-level feature settings.

## Folders and Tags

Folders organise links and control access. A short link can belong to a folder and inherits access from that folder.

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

Disabled links do not resolve, regardless of lifecycle rules. Archived links are hidden from primary dashboard views, preserved for history and analytics, do not resolve, and keep their slug reserved.

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

## Bio Pages

A workspace can own multiple Bio Pages. They are managed in a flat `/biopages` area and never belong to Folders. Owner and Admin roles can create, edit, publish, unpublish, and permanently delete them. Editors can create and edit Draft Versions but cannot publish, unpublish, or delete. Viewers have read-only dashboard access.

Each Bio Page has a Bio URL composed from a Domain and Slug. Bio URLs and Short URLs share one uniqueness boundary per Domain. A Bio URL can be changed, but the change belongs to the Draft Version and takes effect atomically on publication; the old address then becomes unavailable without an automatic redirect. Permanent deletion releases the Slug.

A Bio Page contains a display name, optional public handle, optional biography, optional uploaded profile image, and up to 50 ordered Bio Elements. Profile images fall back to initials. Bio Element types are destination, social destination, section heading, and short text. Destinations may reference a Short Link or directly use HTTP, HTTPS, email, or telephone through validated dedicated types. Social services are detected from an integrated list and can be corrected manually. Social destinations can appear as compact icons or full destination buttons. Content is plain text only; HTML, Markdown, embeds, and scripts are not accepted.

The Draft Version autosaves and drives a live mobile or desktop preview in a two-panel editor. Bio Elements are reordered through drag and drop, including an accessible keyboard drag interaction. The latest successful save wins when members edit concurrently; the editor warns when another member is present but does not lock editing. Failed saves remain visibly failed, preserve recoverable local changes, and prevent publication.

Publishing explicitly replaces the Published Version. A page can have a Published Version and later draft changes at the same time. Unpublish removes public availability while retaining the Draft Version and reserving the Slug. There is no scheduled publication, password protection, shared external draft preview, duplication, version history, rollback, import, export, or public API management in the first version.

Publication requires a display name, a valid Bio URL on an active Domain, at least one visible usable Bio Element, valid destinations, and accessible color contrast. Drafts can be prepared on inactive Domains. If a Domain later becomes inactive, the Published Version remains published but unavailable until the Domain is active again. Unavailable Bio URLs use the neutral public unavailable page; deleted pages may return HTTP 410 while retained as tombstones, otherwise HTTP 404.

Themes are controlled rather than free-form: light, dark, or automatic appearance; color or gradient background; optional uploaded background image with fallback and readability overlay; text and destination colors; destination treatment and radius; optional shadow; profile-image shape; and a small set of self-hosted fonts. Critical contrast failures block publication and offer a conforming nearby color. Custom CSS, custom fonts, video, and animated backgrounds are not supported.

Published pages include native sharing with copy fallback, generated Open Graph metadata, adjustable share title and description, and search indexing enabled by default but configurable per page. Open Graph imagery is generated from the profile content and theme. Openlink branding is shown by default and can be disabled. Destinations open in the same tab by default and can opt into an accessibly announced new tab.

Hidden Bio Elements are absent from public HTML and public analytics. A destination referencing an unavailable Short Link remains visible and lets that Short Link apply its normal lifecycle and fallback behavior. Direct destinations pass through a lean Openlink activation route before redirecting. Email and telephone activations record intent only, not whether communication occurred.

Display names and destination labels are limited to 80 characters, public handles to 30, biographies to 160, section headings to 80, and short text elements to 300. A Bio Page has one editorial language; Openlink does not translate its content automatically.

## Protected Links

A protected link requires a visitor password before resolution. The password protects public resolution only; it does not affect workspace member access in the dashboard.

Visitor passwords are never shown in clear text after creation. Failed password attempts are recorded as analytics outcomes but do not consume the visit limit. A successful password entry can be remembered briefly for the visitor to avoid repeated prompts.

## Visit Limits

A visit limit counts successful resolutions to the destination URL. Failed password attempts, unavailable links, disabled links, scheduled links, expired links, and blocked attempts do not consume the visit limit.

When the visit limit is reached, the link becomes expired.

## QR Codes

A QR code is a named scannable entry point attached to a Short Link or Bio Page. Multiple QR codes can point to the same resource, and each QR code has its own scan analytics.

Each attached QR code encodes a trackable entry URL on its Short Link or Bio Page Domain (`https://{domain}/qr/{token}`), never on the application host.

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
- Bio Views and Bio Activations, kept distinct from Scans and Visits

A Bio Page dashboard reports its aggregated Bio Views and Bio Activations. Opening a Bio Page through its QR Code records a Scan and a Bio View. Following a Bio Element records a Bio Activation. If that element references a Short Link, its successful resolution separately records a Visit. Public pages never display analytics counters. Permanently deleting a Bio Page deletes its Bio Page analytics but does not delete Visits belonging to referenced Short Links.

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
- Feature flags for optional product capabilities
- Public unavailable page basics

Secrets, database credentials, Redis credentials, mail credentials, storage credentials, and boot-time Laravel configuration remain outside the panel.
