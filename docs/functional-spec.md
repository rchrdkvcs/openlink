# Functional Reference

## Product Shape

Openlink is a self-hosted multi-workspace application. Each workspace contains its own members, domains, folders, short links, QR codes, permissions, and settings. Every short link belongs to exactly one workspace.

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
- Editor: creates and edits links and QR codes in accessible folders.
- Viewer: reads links, QR codes, and analytics in accessible folders.

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

## Protected Links

A protected link requires a visitor password before resolution. The password protects public resolution only; it does not affect workspace member access in the dashboard.

Visitor passwords are never shown in clear text after creation. Failed password attempts are recorded as analytics outcomes but do not consume the visit limit. A successful password entry can be remembered briefly for the visitor to avoid repeated prompts.

## Visit Limits

A visit limit counts successful resolutions to the destination URL. Failed password attempts, unavailable links, disabled links, scheduled links, expired links, and blocked attempts do not consume the visit limit.

When the visit limit is reached, the link becomes expired.

## QR Codes

A QR code is a named scannable entry point attached to a short link. Multiple QR codes can point to the same short link, and each QR code has its own scan analytics.

Each QR code encodes a trackable entry URL on the short link's domain (`https://{domain}/qr/{token}`), never on the application host.

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
