# Security and Privacy

## Principles

Openlink should be useful without becoming a visitor profiling system. Analytics are aggregated, public errors are neutral, and sensitive configuration stays outside product settings.

## Authentication

Users authenticate with email and password or configured OAuth providers. Two-factor authentication is available. Password reset uses email.

Email verification is optional by default and can be required by an Instance Admin in instance settings. When required, a verified email is needed for the main authenticated web and API surfaces. The profile page remains available to unverified users so they can resend verification, change their email, manage their password, revoke tokens, or delete their account. An unverified Instance Admin with a workspace can still open instance settings to disable the requirement.

Connected identities require a provider-verified email matching the user's verified email when linked from the profile page. Removing a connected identity must not leave the user without a valid sign-in method. Connected identities whose provider email no longer matches the user's verified email remain visible but are not valid sign-in methods.

API tokens are user-owned credentials. They are shown in clear text only once after creation, can be revoked individually, and require a verified email to create or use when email verification is required by instance settings.

The first user created on an instance becomes an instance admin. The default registration mode is invite-only.

## Authorization

Workspace roles protect dashboard access. A Viewer can read every folder, short link, QR code, and analytics report in the workspace, and cannot create or change them. Folders and tags are never an access boundary.

Owner, Admin, Editor, and Viewer can see all folders in their workspace.

## Protected Links

Visitor passwords protect public resolution only. They do not affect dashboard permissions.

Visitor passwords must be hashed and never displayed in clear text after creation. Password failures are recorded as outcomes but do not consume visit limits.

## Public Resolution Safety

Destination URLs must use HTTP or HTTPS. Dangerous URL schemes are rejected. Obvious loops where a short URL targets itself are rejected.

Unavailable public pages are neutral. They should not reveal whether a private or expired link exists unless the visitor is in an explicit password flow.

## Destination Checks

Destination Checks run outside public resolution and cannot block or change a redirect. Outbound requests only reach public addresses: loopback, private, reserved, link-local, metadata-service, and alternate numeric host forms are rejected for IPv4 and IPv6, DNS resolution is pinned to the connection to prevent rebinding, and every redirect hop is revalidated. Requests are anonymous and bounded in duration, redirects, and response size; page bodies are never stored.

## Analytics Privacy

Analytics are aggregated by link and QR code. They can include referrer, approximate country, device type, browser, operating system, daily counts, lifetime totals, and resolution outcomes.

Openlink does not create visitor profiles. IP addresses should not be stored long term in raw form. If request metadata is needed for anti-abuse, rate limiting, or deduplication, it should be temporary and minimal.

Daily aggregates are retained for 12 months by default. Lifetime totals remain while the related link or QR code exists.

## Link History

Link history is operational workspace data about who changed a short link, not visitor data. Snapshots and diffs never contain visitor passwords, password hashes, API tokens, QR Code tokens, or infrastructure secrets; they record only whether a link was protected and, as a value-free entry, when its password was replaced. Restoring checks the member's current workspace role and revalidates destinations and routing rules.

Link history is kept for as long as its short link exists and is deleted with the short link or its workspace. Revisions by a deleted member remain without the member's name. Query values and fragments of every URL are replaced by `•••` before a snapshot or diff is stored, so tokens carried in URLs never reach history. A keyed HMAC of the full URL, derived from the application key, lets a restore reuse such a URL only while the link still holds it; otherwise the restore is refused rather than writing a masked value. Path segments are stored as they are, so tokens must not be placed in URL paths if they should stay out of history.

## Secrets and Configuration

Infrastructure secrets remain outside the instance admin panel and workspace settings. This includes database credentials, Redis credentials, mail credentials, storage credentials, app keys, and boot-time Laravel configuration.

Product settings can be managed through the instance admin panel or workspace settings when they are safe to change at runtime.

Webhook signing secrets are integration credentials, not infrastructure secrets. Openlink generates them per webhook endpoint, encrypts them at rest with the application key, shows them only when they are created or rotated, keeps a rotated-out secret only for a 24-hour signing overlap, and keeps them out of payloads, queued jobs, and delivery history. See ADR 0021.

## Webhooks

Webhook endpoints are managed only by workspace Owners and Admins. Event payloads contain the workspace's own short link data and never include passwords or password hashes, API tokens, visitor IP addresses or hashes, or visitor request headers. Outbound requests require HTTPS, verify TLS certificates, do not follow redirects, and refuse to connect to loopback, private, link-local, reserved, or metadata addresses; the connection is pinned to the address checked before each attempt to prevent DNS rebinding. Delivery history stores only status codes, timing, and short sanitized errors.

## Abuse Controls

Redis-backed rate limiting should protect public resolution, password attempts, login, invite link joins, and domain verification checks.

The application should avoid making DNS verification or redirect behavior an amplification vector. Background jobs should have sensible retries and backoff.
