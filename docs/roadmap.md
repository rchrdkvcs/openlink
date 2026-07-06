# Roadmap

## Phase 0: Project Foundation

- Scaffold Laravel, Inertia.js, Vue 3, TypeScript, Tailwind CSS, and shadcn-vue.
- Add a production Docker image for the Laravel application.
- Configure Laravel queues, scheduler, cache, sessions, and testing.
- Add authentication with email/password, password reset, and two-factor authentication.
- Create the first-user instance admin setup flow.

## Phase 1: Workspace Core

- Create workspaces and workspace switching.
- Add workspace members, invitations, and workspace roles.
- Add folders and folder permissions.
- Add tags as classification only.
- Add workspace settings.

## Phase 2: Domains and Slugs

- Add instance default domain settings.
- Add workspace domains.
- Implement DNS TXT verification.
- Add preferred domain per workspace.
- Add slug generation defaults.
- Enforce slug uniqueness per domain.
- Add reserved slugs and reserved prefixes.

## Phase 3: Short Links and Resolution

- Create and edit short links.
- Support generated and custom slugs.
- Validate destination URLs.
- Add enabled, disabled, and archived states.
- Add activation dates, expiration dates, visit limits, and fallback URLs.
- Implement public resolution order.
- Add Redis-backed resolution cache and invalidation.
- Add neutral unavailable pages.

## Phase 4: Protected Links

- Add visitor password support.
- Add public password challenge page.
- Hash protected link passwords.
- Track password failed outcomes.
- Ensure password failures do not consume visit limits.

## Phase 5: QR Codes

- Add named QR codes attached to short links.
- Generate trackable QR entry URLs.
- Export PNG and SVG.
- Add export options for size, colors, margin, and error correction level.
- Attribute scans to QR codes during resolution.
- QR code studio page with live preview, module styles, eye styles, transparent background, and centered logos.

## Phase 6: Analytics

- Queue public resolution events.
- Aggregate daily visits and scans.
- Store lifetime totals.
- Add referrer, approximate country, device type, browser, operating system, and outcome breakdowns.
- Add dashboard analytics views.
- Add 12-month default retention for daily aggregates.

## Phase 7: Instance Admin Panel

- Add registration mode settings.
- Add default domain settings.
- Add instance branding basics.
- Add reserved slug management.
- Add analytics retention settings.
- Add feature flags for later capabilities.

## Phase 8: Later Capabilities

- Smart destinations and app link presets.
- Bulk import and export.
- Public API.
- Webhooks.
- SSO and OAuth login.
- Alerts and monitoring.
- SaaS billing and commercial plans.
