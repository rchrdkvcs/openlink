# Technical Reference

## Architecture

Openlink is a Laravel application using Inertia.js for the primary authenticated app, Vue 3 with TypeScript for the frontend, Tailwind CSS for styling, and shadcn-vue for UI components.

The application is self-hosted first. It runs as a single Laravel application with PostgreSQL and Redis. Production deployment uses a Docker image for the application; PostgreSQL, Redis, HTTPS, and reverse proxy concerns are provided by the deployment platform.

The configured application host is the only hostname that serves the authenticated application, auth screens, workspace management, QR code management, and instance settings. Domains added inside Openlink are redirect-only domains: they may route to the same Laravel process at the reverse proxy layer, but Laravel treats their request paths as public short URL slugs rather than application UI routes.

## Main Runtime Components

- Laravel HTTP app for dashboard, public resolution, auth, and settings.
- Inertia/Vue frontend for authenticated product screens.
- PostgreSQL for durable application data.
- Redis for cache, rate limiting, and Laravel queues.
- Queue workers for webhook deliveries, Destination Checks, weekly summary emails, and optional queued analytics writes.
- Scheduler for domain verification, diagnostics heartbeats, Destination Monitoring, weekly summaries, analytics retention, and webhook delivery history retention.
- One-shot database migration process during deployment.

All application processes use the same image, application key, database, Redis,
and persistent storage. The web process uses port `8080`; worker, scheduler,
and migration processes override the image entrypoint to run Artisan. See
[Deployment](deployment.md) for GHCR tags and process commands.

## Application Areas

The codebase stays modular without over-engineering. Its main Laravel areas are:

- Auth
- Workspaces
- Members
- Folders
- Domains
- ShortLinks
- QrCodes
- Resolution
- Analytics
- Conversions
- LinkPages
- AppAssociations
- DestinationHealth
- Diagnostics
- InstanceSettings
- Webhooks

Each area owns its actions, policies, requests, and tests where practical.

## Core Data Model

Primary records:

- users
- workspaces
- workspace_members
- invite_links
- folders
- tags
- short_links
- short_link_tags
- short_link_revisions
- domains
- routing_rules
- routing_variants
- utm_templates
- qr_codes
- qr_code_templates
- link_pages
- link_page_items
- app_associations
- destination_checks
- diagnostic_observations
- analytics_events
- analytics_views
- analytics_summary_schedules
- analytics_summary_recipients
- analytics_summary_deliveries
- webhook_endpoints
- webhook_deliveries
- conversion_attributions
- instance_settings

## Short Link Constraints

The slug uniqueness boundary is domain plus slug. Slugs remain reserved until permanent deletion of the short link.

Use database constraints for domain and slug uniqueness. Application validation should mirror the constraint so users get friendly errors before a database exception.

Destination URLs accept only HTTP and HTTPS. Validation should reject dangerous schemes and obvious loops where the destination is the same short URL.

## Resolution Path

Public resolution should be a lean path. The resolver should:

- Resolve domain and slug from the request host and path.
- Prefer Redis cache for active resolution metadata.
- Fall back to PostgreSQL on cache miss.
- Avoid expensive analytics writes in the request path.
- Capture analytics dimensions synchronously, write the event after the response (or on the queue when configured).
- Return redirects quickly.

Cache entries should be invalidated when domains, short links, lifecycle fields, passwords, fallback URLs, or QR code routing metadata change.

Smart Routing evaluates ordered rules and weighted variants after availability
and password checks. The routing editor's preview uses the same routing logic
with synthetic inputs and makes no requests to destinations or analytics writes.
Link revisions record mutations from the dashboard, API, imports, and bulk
actions. Restores append a revision rather than rewriting history; API clients
can use `If-Match` to prevent concurrent changes from being overwritten.

Supported social preview crawlers use a separate response path that never
counts visits or scans. Link Pages are public only at `/p/{path}` on the
application host; entries open normal Short URLs. App Association JSON is
published only on eligible workspace Domains. See [Functional reference](functional-spec.md)
and [Security and privacy](security-and-privacy.md) for availability rules.

## Analytics Pipeline

The public request captures every analytics dimension synchronously (headers are gone once the response is sent) and writes one row to analytics_events after the response is flushed, so recording works on every deployment without a queue worker. Instances that run a worker can set OPENLINK_ANALYTICS_VIA_QUEUE=true to move the write onto the queue instead.

Conversion attribution is the one synchronous write: for short links with a conversion goal, the attribution row is inserted before the redirect so the destination can redeem the token immediately. A failed insert is reported and the visitor is redirected without a token. See ADR 0024.

Each event stores the metric (visit or scan), the resolution outcome, referrer host and channel, approximate country, language, device type, browser, operating system, UTM parameters, a bot flag, and a privacy-preserving visitor hash whose salt rotates daily. Reports aggregate these rows on demand with covering indexes; unique visitors are counted as distinct daily hashes.

The system should track resolution outcomes even when no visit is counted. Visit limits are consumed only for successful resolutions to the destination URL.

Raw visitor data should not be retained long term. If temporary request metadata is needed for abuse prevention or deduplication, store it briefly and avoid exposing it as visitor profiles.

Saved Views persist each member's report filters. Weekly summary schedules,
recipients, and deliveries are workspace-owned; delivery uniqueness is enforced
by schedule, recipient, and local week. The scheduler queues due summaries,
and a worker sends them through the configured mail transport. See ADR 0022.

## Domain Verification

Domain verification checks a DNS TXT record generated for the domain. The domain cannot serve short links until verified.

DNS checks can run on demand from the dashboard and through scheduled background jobs. Store status, expected token, last checked time, and failure reason.

Traffic routing records such as CNAME or A are deployment-specific and separate from ownership verification.

## Destination Checks

Destination Checks run on the queue (`CheckDestination`) and never during public resolution. A check is stored once per workspace and exact URL in `destination_checks`, together with the normalized result, HTTP status, a short detail, consecutive failures, and incident and recovery times. Links that reference a URL are derived from current short links, enabled routing rules, and enabled split-test variants, so a changed destination is shown as not checked and unreferenced checks are pruned by `openlink:monitor-destinations`.

Each request also stores an execution state (Queued, Running, Completed, or Failed) and a start time separately from its latest normalized health. Queue submission and terminal worker errors never manufacture a health result. Queued requests stay pending until completion, failure, or explicit replacement; running has a 120-second lease and an expired lease exposes a failed execution with retry. After 60 seconds in the queue a member can explicitly replace the request with a new generation. Jobs, result writes, and failure callbacks are guarded by that generation, so superseded jobs cannot overwrite a newer observation. The dashboard continues refreshing across backlogs longer than one minute. Checks require an asynchronous queue driver and a worker consuming the configured connection and queue.

The outbound client resolves the hostname itself, requires every address to be public (IPv4, IPv6, and embedded IPv4 forms), pins the connection to the validated address with `CURLOPT_RESOLVE`, follows at most five redirects manually while revalidating every hop, aborts after response headers, uses short connect and per-request timeouts, and gives up with a timeout result once a check has run for 30 seconds. Check jobs are deduplicated per destination, never run two checks against the same host at once, run at most 6 checks per host per minute, and share 4 global slots; a blocked job is released and retried for up to 30 minutes. Requests carry no cookies, credentials, or visitor data. Result classification is documented in ADR 0018.

`openlink:monitor-destinations` runs every 15 minutes. When the `destination_monitoring` instance setting is on, it prunes unreferenced checks, registers referenced destinations, and queues at most `--limit` due checks: never-checked destinations, results older than 6 hours, incidents older than 1 hour, and unconfirmed failures older than 10 minutes. Valid queued requests and active running leases are skipped. Queued requests abandoned for at least 32 minutes and expired running leases are recovered with a new generation, even when their previous health is recent. Recovery compares the stored generation, execution state, and lease start before replacing a request. Completed and failed generations cannot be claimed again by job redelivery; a redelivered running job waits for its lease or replaces an expired attempt before probing. When the setting is off, the command does nothing.

## Authorization

Use Laravel policies for workspace, folder, domain, short link, QR code, and analytics access.

Workspace roles define access. A Viewer can read every folder, short link, QR code, and analytics report in the workspace, and cannot create or change them. Folders organise links and are not an authorization boundary. Tags must never be used for authorization.

Owner, Admin, Editor, and Viewer can see all folders in a workspace.

## Settings

Instance settings and workspace settings should be stored in the database with typed accessors or dedicated settings objects. Infrastructure secrets remain in environment/server configuration.

Settings writes should be audited enough for operational clarity, especially registration mode, reserved slugs, analytics retention, and default domain changes.

## Frontend

The authenticated app should prioritize dense, clear workflows:

- Workspace switcher
- Links list with filters, search, status, folder, tags, domain, and analytics summary
- Link create/edit form
- Link inspector with analytics, history, Destination Health, and a dedicated visual routing editor
- QR code studio with design templates, Readability Checks, and PNG/SVG export
- Link Page editor with live preview and publication controls
- Domain management and verification screen
- Workspace members and invite links
- Workspace settings for UTM Templates, webhooks, and weekly summaries
- Instance admin panel with Diagnostics and release information

Avoid a marketing-style landing page as the primary product surface. The first screen after login should be the working dashboard.

## Testing Strategy

Start with feature tests for the domain rules:

- Workspace membership and role authorization
- Domain verification gating
- Slug uniqueness per domain
- Reserved slugs and prefixes
- Resolution order
- Protected links
- Visit limits
- Fallback behavior
- QR scan attribution
- Analytics aggregation

Add browser-level coverage for the most important Inertia workflows once the UI exists.
