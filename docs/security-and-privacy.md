# Security and Privacy

## Principles

Openlink should be useful without becoming a visitor profiling system. Analytics are aggregated, public errors are neutral, and sensitive configuration stays outside product settings.

## Authentication

Users authenticate with email and password. Two-factor authentication is available. Password reset uses email.

The first user created on an instance becomes an instance admin. The default registration mode is invite-only.

## Authorization

Workspace roles and folder permissions protect dashboard access. Tags are never an access boundary.

Members without access to a folder should not see that the folder exists. Owners and admins can see all folders in their workspace.

## Protected Links

Visitor passwords protect public resolution only. They do not affect dashboard permissions.

Visitor passwords must be hashed and never displayed in clear text after creation. Password failures are recorded as outcomes but do not consume visit limits.

## Public Resolution Safety

Destination URLs must use HTTP or HTTPS. Dangerous URL schemes are rejected. Obvious loops where a short URL targets itself are rejected.

Unavailable public pages are neutral. They should not reveal whether a private or expired link exists unless the visitor is in an explicit password flow.

## Analytics Privacy

Analytics are aggregated by link and QR code. They can include referrer, approximate country, device type, browser, operating system, daily counts, lifetime totals, and resolution outcomes.

Openlink does not create visitor profiles. IP addresses should not be stored long term in raw form. If request metadata is needed for anti-abuse, rate limiting, or deduplication, it should be temporary and minimal.

Daily aggregates are retained for 12 months by default. Lifetime totals remain while the related link or QR code exists.

## Secrets and Configuration

Infrastructure secrets remain outside the instance admin panel and workspace settings. This includes database credentials, Redis credentials, mail credentials, storage credentials, app keys, and boot-time Laravel configuration.

Product settings can be managed through the instance admin panel or workspace settings when they are safe to change at runtime.

## Abuse Controls

Redis-backed rate limiting should protect public resolution, password attempts, login, invite link joins, and domain verification checks.

The application should avoid making DNS verification or redirect behavior an amplification vector. Background jobs should have sensible retries and backoff.
