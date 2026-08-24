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
- Optional queue workers for analytics writes, DNS verification tasks, and background maintenance.
- Scheduler for repeated DNS checks, analytics retention, and cleanup.

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
- InstanceSettings

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
- domains
- qr_codes
- analytics_events
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

## Analytics Pipeline

The public request captures every analytics dimension synchronously (headers are gone once the response is sent) and writes one row to analytics_events after the response is flushed, so recording works on every deployment without a queue worker. Instances that run a worker can set OPENLINK_ANALYTICS_VIA_QUEUE=true to move the write onto the queue instead.

Each event stores the metric (visit or scan), the resolution outcome, referrer host and channel, approximate country, language, device type, browser, operating system, UTM parameters, a bot flag, and a privacy-preserving visitor hash whose salt rotates daily. Reports aggregate these rows on demand with covering indexes; unique visitors are counted as distinct daily hashes.

The system should track resolution outcomes even when no visit is counted. Visit limits are consumed only for successful resolutions to the destination URL.

Raw visitor data should not be retained long term. If temporary request metadata is needed for abuse prevention or deduplication, store it briefly and avoid exposing it as visitor profiles.

## Domain Verification

Domain verification checks a DNS TXT record generated for the domain. The domain cannot serve short links until verified.

DNS checks can run on demand from the dashboard and through scheduled background jobs. Store status, expected token, last checked time, and failure reason.

Traffic routing records such as CNAME or A are deployment-specific and separate from ownership verification.

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
- Link detail with analytics and QR codes
- QR code create/edit/export flow
- Domain management and verification screen
- Workspace members and invite links
- Workspace settings
- Instance admin panel

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
