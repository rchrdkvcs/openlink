# Technical Specification

## Architecture

Openlink is a Laravel application using Inertia.js for the primary authenticated app, Vue 3 with TypeScript for the frontend, Tailwind CSS for styling, and shadcn-vue for UI components.

The application is self-hosted first. It runs as a single Laravel application with PostgreSQL and Redis. Production deployment uses a Docker image for the application; PostgreSQL, Redis, HTTPS, and reverse proxy concerns are provided by the deployment platform.

The configured application host is the only hostname that serves the authenticated application, auth screens, workspace management, QR code management, and instance settings. Domains added inside Openlink are redirect-only domains: they may route to the same Laravel process at the reverse proxy layer, but Laravel treats their request paths as public short URL slugs rather than application UI routes.

## Main Runtime Components

- Laravel HTTP app for dashboard, public resolution, auth, and settings.
- Inertia/Vue frontend for authenticated product screens.
- PostgreSQL for durable application data.
- Redis for cache, rate limiting, and Laravel queues.
- Queue workers for analytics aggregation, DNS verification tasks, and background maintenance.
- Scheduler for repeated DNS checks, analytics retention, and cleanup.

## Suggested Domains in Code

The codebase should stay modular without over-engineering. Suggested Laravel areas:

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

Each area should own its actions, policies, requests, and tests where practical.

## Core Data Model

Expected primary records:

- users
- workspaces
- workspace_members
- invitations
- folders
- folder_permissions
- tags
- short_links
- short_link_tags
- domains
- qr_codes
- analytics_daily_aggregates
- analytics_totals
- instance_settings

The exact schema can evolve during implementation, but these concepts should remain visible.

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
- Dispatch analytics work to the queue.
- Return redirects quickly.

Cache entries should be invalidated when domains, short links, lifecycle fields, passwords, fallback URLs, or QR code routing metadata change.

## Analytics Pipeline

The public request records a lightweight analytics event through the queue. Queue workers aggregate events into daily aggregate rows and lifetime totals.

The system should track resolution outcomes even when no visit is counted. Visit limits are consumed only for successful resolutions to the destination URL.

Raw visitor data should not be retained long term. If temporary request metadata is needed for abuse prevention or deduplication, store it briefly and avoid exposing it as visitor profiles.

## Domain Verification

Domain verification checks a DNS TXT record generated for the domain. The domain cannot serve short links until verified.

DNS checks can run on demand from the dashboard and through scheduled background jobs. Store status, expected token, last checked time, and failure reason.

Traffic routing records such as CNAME or A are deployment-specific and separate from ownership verification.

## Authorization

Use Laravel policies for workspace, folder, domain, short link, QR code, and analytics access.

Workspace roles define baseline access. Folder permissions constrain access to folder-contained links for Editor and Viewer roles. Tags must never be used for authorization.

Owner and Admin can see all folders in a workspace. Other members only see folders granted to them.

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
- Folder permissions screen
- Workspace members and invitations
- Workspace settings
- Instance admin panel

Avoid a marketing-style landing page as the primary product surface. The first screen after login should be the working dashboard.

## Testing Strategy

Start with feature tests for the domain rules:

- Workspace membership and role authorization
- Folder permissions and invisibility
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
