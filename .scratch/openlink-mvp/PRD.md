# Openlink MVP PRD

Status: ready-for-agent

## Problem Statement

Personal projects, events, and small teams need a clean, maintainable way to manage short links across multiple domains. Existing open source options are often dated, visually weak, narrowly scoped, or awkward to operate when several domains, teams, campaigns, and QR codes need to coexist.

The user needs a self-hosted alternative to Bitly or TinyURL that can manage personal and team links in the same installation without mixing access, ownership, or analytics. The product should be straightforward to deploy, pleasant to use, and designed around workspaces, verified domains, folder-based permissions, short links, QR codes, and aggregated analytics.

## Solution

Build Openlink as a self-hosted Laravel application with multiple workspaces inside one installation. Each workspace owns its members, folders, domains, short links, QR codes, permissions, settings, and analytics.

The MVP provides a complete URL management workflow: users create workspaces, invite members, verify domains through DNS TXT records, create generated or custom short links, organise links in folders, protect links with visitor passwords, define lifecycle rules, generate QR codes, and inspect aggregated visits and scans.

The implementation uses Laravel, Inertia.js, Vue 3, TypeScript, Tailwind CSS, shadcn-vue, PostgreSQL, Redis, and Docker for local development. PostgreSQL is used locally and in production. Redis is used for cache, rate limiting, and queues.

## User Stories

1. As an instance admin, I want the first created user to become the instance admin, so that a fresh self-hosted installation has an owner.
2. As an instance admin, I want to configure the registration mode, so that I can run Openlink as closed, invite-only, or open.
3. As an instance admin, I want invite-only registration by default, so that a new installation is private unless I choose otherwise.
4. As an instance admin, I want to configure the default domain, so that workspaces can create links before adding their own domains.
5. As an instance admin, I want to manage reserved slugs and prefixes, so that app routes do not collide with public short URLs.
6. As an instance admin, I want to configure slug generation defaults, so that generated slugs match the instance policy.
7. As an instance admin, I want to configure analytics retention, so that the database does not grow indefinitely.
8. As an instance admin, I want product settings in an instance admin panel, so that safe runtime settings do not require environment file edits.
9. As an instance admin, I want infrastructure secrets kept outside product settings, so that credentials cannot be exposed or changed accidentally from the UI.
10. As a user, I want to sign in with email and password, so that I can access my workspaces.
11. As a user, I want to reset my password by email, so that I can recover access to my account.
12. As a user, I want to enable two-factor authentication, so that my account is better protected.
13. As a user, I want to belong to multiple workspaces, so that I can separate personal, event, and client links.
14. As a user, I want to switch between workspaces clearly, so that I always know which context I am editing.
15. As a workspace owner, I want to manage workspace settings, so that each workspace has its own defaults and preferences.
16. As a workspace owner, I want to transfer or retain ownership-level control, so that workspace administration remains clear.
17. As a workspace owner, I want to invite members, so that other people can collaborate.
18. As a workspace owner, I want to assign workspace roles, so that members get the right baseline access.
19. As a workspace owner, I want an Owner role, so that one role controls all workspace data, settings, members, domains, and ownership-level actions.
20. As a workspace owner, I want an Admin role, so that trusted members can manage the workspace without owning it.
21. As a workspace owner, I want an Editor role, so that contributors can create and modify links in accessible folders.
22. As a workspace owner, I want a Viewer role, so that stakeholders can inspect links and analytics without changing them.
23. As a workspace member, I want only accessible folders to be visible, so that private work does not leak through navigation.
24. As a workspace owner, I want Owners and Admins to see all folders, so that they can administer the workspace.
25. As a workspace member, I want folders to organise short links, so that campaigns and projects stay tidy.
26. As a workspace owner, I want folder permissions, so that access can be granted to groups of links.
27. As a workspace owner, I want Can view, Can edit, and Can manage folder permissions, so that folder access remains simple and understandable.
28. As a workspace member, I want tags to classify links, so that I can filter and find them quickly.
29. As a workspace owner, I want tags to have no permission effect, so that classification and access control are not confused.
30. As a workspace admin, I want to add a domain, so that the workspace can publish branded short URLs.
31. As a workspace admin, I want to verify a domain with a DNS TXT record, so that Openlink can prove ownership before serving links.
32. As a workspace admin, I want to see domain verification status, so that I know whether a domain is usable.
33. As a workspace admin, I want to retry domain verification, so that DNS changes can be checked on demand.
34. As a workspace admin, I want to disable a domain, so that links on that domain stop resolving when needed.
35. As a workspace admin, I want to choose a preferred domain, so that new links default to the correct domain.
36. As a workspace member with edit access, I want to create a short link, so that I can publish a short URL.
37. As a workspace member with edit access, I want to choose a domain for a short link, so that links can use the correct brand or event domain.
38. As a workspace member with edit access, I want a generated slug, so that I can create links quickly.
39. As a workspace member with edit access, I want generated slugs to be random and readable, so that links are not easy to guess and are easy to share.
40. As a workspace member with edit access, I want to create a custom slug, so that the short URL is meaningful.
41. As a workspace member with edit access, I want custom slugs to support path separators, so that I can create URLs like event/2026/vip.
42. As a workspace member with edit access, I want slug uniqueness enforced per domain, so that the same slug can exist on different domains.
43. As a workspace member with edit access, I want slugs to remain reserved until permanent deletion, so that old materials do not unexpectedly point to new destinations.
44. As a workspace member with edit access, I want reserved slugs and prefixes blocked, so that I cannot accidentally override app routes.
45. As a workspace member with edit access, I want to set a destination URL, so that visitors are redirected to the intended page.
46. As a workspace member with edit access, I want destination URLs limited to HTTP and HTTPS, so that unsafe schemes are rejected.
47. As a workspace member with edit access, I want obvious resolution loops blocked, so that a short URL cannot point to itself.
48. As a workspace member with edit access, I want to enable or disable a short link manually, so that I can pause resolution without deleting it.
49. As a workspace member with edit access, I want to archive a short link, so that old campaigns leave primary views while preserving history.
50. As a workspace member with edit access, I want archived links to keep analytics, so that past performance remains inspectable.
51. As a workspace member with edit access, I want archived links to keep their slug reserved, so that old printed or shared links are not reused accidentally.
52. As a workspace member with edit access, I want activation dates, so that a link can become available later.
53. As a workspace member with edit access, I want expiration dates, so that a link can stop resolving after a campaign ends.
54. As a workspace member with edit access, I want visit limits, so that a link can stop resolving after a maximum number of successful visits.
55. As a workspace member with edit access, I want visit limits to count only successful destination redirects, so that failed attempts do not consume availability.
56. As a workspace member with edit access, I want a fallback URL, so that unavailable links can redirect somewhere useful.
57. As a workspace member with edit access, I want a protected link, so that visitors must provide a password before resolution.
58. As a visitor, I want a password challenge for protected links, so that I can access protected destinations when I know the password.
59. As a visitor, I want successful password entry remembered briefly, so that I am not challenged repeatedly during a short session.
60. As a workspace member with edit access, I want visitor passwords hidden after creation, so that they cannot be casually exposed from the dashboard.
61. As a visitor, I want unavailable links without fallback URLs to show a neutral page, so that the experience is understandable.
62. As a workspace owner, I want unavailable public pages not to reveal detailed states, so that private or expired link existence is not leaked.
63. As a workspace member, I want dashboard analytics to show detailed resolution outcomes, so that I can understand why links are or are not resolving.
64. As a visitor, I want fast redirection, so that short URLs feel reliable.
65. As a workspace owner, I want public redirection protected by rate limiting, so that abuse is reduced.
66. As a workspace member with edit access, I want to create a named QR code for a short link, so that I can track a specific printed or physical placement.
67. As a workspace member with edit access, I want multiple QR codes for one short link, so that flyers, posters, badges, and screens can be compared.
68. As a workspace member with edit access, I want QR codes to export as PNG, so that I can use them in common design tools.
69. As a workspace member with edit access, I want QR codes to export as SVG, so that I can use them in high-quality print contexts.
70. As a workspace member with edit access, I want QR export size, foreground color, background color, margin, and error correction options, so that QR codes fit different materials while remaining scannable.
71. As a workspace member, I want scans attributed to the QR code used, so that I can compare physical placements.
72. As a workspace member, I want lifetime visits and scans, so that I can understand total performance.
73. As a workspace member, I want daily visits and scans, so that I can understand campaign timing.
74. As a workspace member, I want referrer analytics, so that I can see where visitors came from.
75. As a workspace member, I want approximate country analytics, so that I can understand geographic distribution without identifying visitors.
76. As a workspace member, I want device type analytics, so that I can understand mobile, desktop, and tablet usage.
77. As a workspace member, I want browser and operating system analytics, so that I can diagnose platform patterns.
78. As a workspace member, I want resolution outcome analytics, so that failed, expired, disabled, scheduled, and successful attempts are visible.
79. As a privacy-conscious instance admin, I want analytics to avoid visitor profiles, so that Openlink remains lightweight and privacy-respectful.
80. As an instance admin, I want daily analytics aggregates retained for 12 months by default, so that useful history remains without indefinite growth.
81. As a workspace member, I want lifetime totals retained while the related link or QR code exists, so that high-level history remains available.
82. As a maintainer, I want PostgreSQL in development and production, so that schema, index, constraint, and JSON behavior remains consistent.
83. As a maintainer, I want Redis in development and production, so that cache, queues, and rate limits match the production architecture.
84. As a maintainer, I want analytics processed asynchronously, so that public redirection stays fast.
85. As a maintainer, I want DNS verification processed on demand and in background jobs, so that domain setup is responsive and reliable.
86. As a maintainer, I want settings stored in database-backed product configuration where safe, so that operators can manage Openlink without editing environment files.
87. As a maintainer, I want secrets and boot-time infrastructure config outside product settings, so that deployment remains predictable.
88. As a future product owner, I want smart destinations left for later, so that the MVP can ship without overpromising app deep-link behavior.
89. As a future product owner, I want public API and webhooks left for later, so that the MVP can focus on the dashboard and core resolution.
90. As a future product owner, I want SaaS billing left for later, so that the first version stays self-hosted and focused.

## Implementation Decisions

- Build Openlink as a self-hosted first application with multiple workspaces inside a single instance.
- Use Laravel, Inertia.js, Vue 3, TypeScript, Tailwind CSS, and shadcn-vue for the primary application.
- Use PostgreSQL in production and local development through Docker.
- Use Redis for resolution cache, public rate limiting, Laravel queues, and background analytics processing.
- Keep infrastructure secrets and boot-time configuration outside the instance admin panel and workspace settings.
- Create an instance admin panel for safe product-level settings such as registration mode, default domain, reserved slugs, slug generation defaults, analytics retention, feature flags, and branding basics.
- Use workspaces as the ownership boundary for members, domains, folders, short links, QR codes, settings, and analytics.
- Use folders as the primary sharing and permission boundary inside a workspace.
- Use tags only for classification and filtering, never for authorization.
- Implement workspace roles as Owner, Admin, Editor, and Viewer.
- Implement folder permissions as Can view, Can edit, and Can manage.
- Hide inaccessible folders from members who lack access, while allowing Owners and Admins to see all folders in the workspace.
- Verify domains with DNS TXT records before they can serve short links.
- Keep ownership verification separate from traffic routing records such as CNAME or A records.
- Provide a default domain at the instance level and a preferred domain at the workspace level.
- Enforce slug uniqueness by domain and slug.
- Keep slugs reserved until the short link is permanently deleted.
- Generate slugs randomly from a readable non-ambiguous alphabet, with a default length of 6 characters.
- Allow custom slugs to contain path separators as part of the slug.
- Maintain configurable reserved slugs and reserved prefixes.
- Validate destination URLs as HTTP or HTTPS only.
- Reject obvious resolution loops where a short URL targets itself.
- Separate manual enabled/disabled state from calculated lifecycle status such as scheduled, active, and expired.
- Add archived links as a distinct state from disabled and deleted; archived links do not resolve, stay out of primary views, preserve analytics, and keep slugs reserved.
- Use fallback URLs when a short link cannot resolve and a fallback is configured.
- Show neutral public unavailable pages when no fallback URL exists.
- Keep detailed unavailability reasons visible in the dashboard and analytics, not on public unavailable pages.
- Apply public resolution rules in this order: domain and slug lookup, domain availability, link existence and enabled state, lifecycle rules, visit limit, password validation, QR attribution, analytics recording, and final redirect or fallback.
- Validate protected link passwords after availability checks, so unavailable links do not show password prompts.
- Hash visitor passwords and never display them in clear text after creation.
- Count visit limits only when a visitor successfully reaches the destination URL.
- Model QR codes as named objects attached to short links, not just generated images.
- Allow multiple QR codes to point to the same short link.
- Track scans separately per QR code.
- Export QR codes as PNG and SVG with configurable size, foreground color, background color, margin, and error correction level.
- Store analytics as aggregated measurements rather than visitor profiles.
- Track lifetime totals, daily aggregates, referrer, approximate country, device type, browser, operating system, and resolution outcome.
- Retain daily analytics aggregates for 12 months by default and keep lifetime totals while the related link or QR code exists.
- Use queues for analytics aggregation to avoid slowing public resolution.
- Use cache invalidation when domains, short links, lifecycle fields, passwords, fallback URLs, or QR code routing metadata change.
- Organise implementation around the following major modules: Auth & Instance Admin, Workspaces & Members, Folders & Permissions, Domains & DNS Verification, Short Links & Slugs, Public Resolution, Protected Links, QR Codes, Analytics, Settings, and Security/Abuse Controls.
- Extract deep, testable modules around public resolution, slug policy, authorization, DNS verification, QR generation, and analytics aggregation because these areas contain meaningful business rules behind relatively stable interfaces.

## Testing Decisions

- Tests should focus on external behavior and domain rules rather than implementation details.
- Authorization tests should cover workspace roles, folder permissions, and folder invisibility for unauthorized members.
- Domain tests should cover DNS TXT verification states, verified-domain gating, disabled domains, and retry behavior.
- Slug tests should cover uniqueness per domain, generated slug policy, custom slug path separators, reserved slugs, reserved prefixes, and slug reservation until permanent deletion.
- Short link tests should cover destination URL validation, obvious loop prevention, enabled and disabled states, archived links, activation dates, expiration dates, visit limits, and fallback URLs.
- Public resolution tests should cover the agreed resolution order and ensure availability checks happen before password validation.
- Protected link tests should cover password challenge behavior, hashed storage, failed password outcomes, remembered successful access, and visit limit behavior.
- Visit limit tests should prove that only successful destination redirects consume the limit.
- QR code tests should cover named QR codes, multiple QR codes per short link, PNG/SVG export behavior, configurable export options, and scan attribution.
- Analytics tests should cover queued event handling, daily aggregates, lifetime totals, resolution outcomes, scan versus visit attribution, and retention behavior.
- Settings tests should cover instance admin settings, workspace settings, and the boundary between product settings and infrastructure secrets.
- Security and abuse tests should cover HTTP/HTTPS destination restrictions, blocked dangerous schemes, rate limiting targets, neutral public unavailable pages, and non-disclosure of detailed link state to visitors.
- Frontend/browser tests should be added for the highest-value Inertia workflows once screens exist: login, workspace switcher, link creation, domain verification, QR export, folder permission management, and analytics viewing.

## Out of Scope

- Smart destinations and app link presets.
- Mobile native app deep-link guarantees.
- Bulk import and export.
- Public API.
- Webhooks.
- SSO and OAuth login.
- Advanced QR code design templates.
- QR code logos.
- Alerts and monitoring.
- SaaS billing and commercial plans.
- Visitor profiles, session replay, fingerprinting, heatmaps, or individual visitor tracking.
- Microservices or a split frontend/backend API architecture for the MVP.
- GitHub or GitLab issue publication while the project is still using local markdown tracking.

## Further Notes

Openlink should use the domain language defined in `CONTEXT.md`. Important terms include Workspace, Folder, Tag, Short Link, Destination URL, Slug, Short URL, Domain, Default Domain, Preferred Domain, Fallback URL, Protected Link, QR Code, Visit, Scan, Analytics, Workspace Role, Folder Permission, Instance Admin Panel, and Workspace Settings.

The current ADRs establish these constraints:

- Domain ownership is verified with DNS TXT records.
- Product settings are managed through panels only when safe; secrets stay outside product settings.
- The product is self-hosted first.
- The main app uses Laravel, Inertia, Vue, TypeScript, Tailwind, and shadcn-vue.
- PostgreSQL is used everywhere, including local development through Docker.
- Redis is used for cache, rate limits, and queues from the MVP.

This PRD is published to the local markdown issue tracker because the project does not yet have a git remote or hosted issue tracker.
