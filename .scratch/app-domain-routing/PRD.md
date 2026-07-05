Status: ready-for-agent

# PRD: Application Domain and Redirect-Only Domains

## Problem Statement

Openlink currently treats every incoming hostname as a possible place to serve both the authenticated application and public short URL resolution. This makes reserved slugs and application routes leak into every domain. If a workspace adds a domain, paths such as `/login`, `/dashboard`, `/settings`, `/qr`, or `/profile` are blocked or risk being interpreted as application UI paths instead of normal short link slugs.

From the user perspective, this prevents workspaces from fully using their own domains for short URLs. A customer may want `go.example.com/login` or `links.example.com/dashboard` to redirect to a destination URL, but those slugs are currently reserved because Openlink needs them for the application UI. It also creates an unclear operational model: users and customers might discover application screens on workspace-owned domains, even though configuration should happen only through the Openlink application URL.

The desired behavior is that one configured domain serves the Openlink application, while every other verified domain serves only public short URL resolution. Workspace-owned domains should never render the authenticated UI, auth screens, settings screens, dashboard screens, or application management routes.

## Solution

Introduce a clear runtime distinction between the domain that serves the Openlink application and the domains that serve short URLs.

The application domain is the only hostname that renders the authenticated Inertia application, authentication screens, invitation management screens, workspace settings, the instance admin panel, and other product UI. All other domains known to Openlink are redirect-only domains. On redirect-only domains, every request path that can be interpreted as a slug should be resolved through public short URL resolution, including slugs that are reserved on the application domain.

This allows application-reserved slugs to remain protected where they matter while allowing workspace-owned domains to map those same path names to short links. Users configure links, domains, QR codes, members, and settings through the application domain. Visitors use short URLs on redirect-only domains and are either redirected to the destination URL, shown the protected link password form when applicable, or shown the neutral unavailable page.

## User Stories

1. As an instance admin, I want Openlink to have one explicit application domain, so that the authenticated product UI is served from one predictable URL.
2. As an instance admin, I want workspace-owned domains to be redirect-only, so that customer traffic cannot accidentally access application UI on those domains.
3. As an instance admin, I want the application domain to come from deployment configuration, so that infrastructure routing remains controlled outside workspace settings.
4. As an instance admin, I want the existing default domain concept to remain available for short URLs, so that new installations can still create short links before a workspace-owned domain is added.
5. As a workspace owner, I want to add a domain for short URLs without turning that domain into another application UI host, so that the domain behaves like a branded redirect surface.
6. As a workspace owner, I want `login` to be usable as a slug on my verified domain, so that I can create a short URL such as `go.example.com/login`.
7. As a workspace owner, I want `dashboard` to be usable as a slug on my verified domain, so that application route names do not limit my public URL strategy.
8. As a workspace owner, I want `settings`, `profile`, `register`, and other application-reserved paths to be usable as slugs on redirect-only domains, so that reserved words only apply where the application UI is served.
9. As a workspace owner, I want slugs with path separators to keep working on redirect-only domains, so that URLs such as `go.example.com/campaign/summer` still resolve correctly.
10. As a workspace owner, I want application-reserved slugs to remain blocked on the application domain, so that short URLs cannot shadow login, dashboard, settings, auth, QR, or asset routes there.
11. As a workspace owner, I want users to configure links only through the application domain, so that product operations are consistent and supportable.
12. As a workspace member, I want visiting a dashboard route on a redirect-only domain to resolve as a short URL when such a slug exists, so that domain behavior is predictable.
13. As a workspace member, I want visiting a dashboard route on a redirect-only domain with no matching slug to show the neutral unavailable page, so that visitors do not see application internals.
14. As a workspace member, I want protected links to continue showing the password form on redirect-only domains, so that existing protected link behavior is preserved.
15. As a workspace member, I want protected link password submission to work from redirect-only domains, so that visitors can complete the password flow without switching domains manually.
16. As a workspace member, I want QR code public resolution to keep working, so that QR codes attached to short links continue redirecting visitors correctly.
17. As a workspace member, I want QR code management and export screens to remain available only on the application domain, so that management routes are not exposed through redirect-only domains.
18. As a workspace member, I want unavailable links to continue using fallback URLs when configured, so that redirect-only domain routing does not change lifecycle behavior.
19. As a workspace member, I want analytics to preserve the visited domain and slug behavior, so that visits, scans, not found outcomes, unavailable outcomes, and password failures remain measurable.
20. As a visitor, I want a short URL on a redirect-only domain to redirect quickly to the destination URL, so that the public experience stays fast.
21. As a visitor, I want unavailable short URLs to show a neutral unavailable page, so that I am not exposed to workspace or application details.
22. As a visitor, I do not want to see Openlink login or dashboard UI on a workspace-owned domain, so that a branded redirect domain feels purpose-built.
23. As a self-hosting operator, I want deployment documentation to explain which domain should point to the application UI and which domains should point to public resolution, so that reverse proxy and DNS setup is clear.
24. As a developer, I want route handling to make the application-domain boundary obvious, so that future routes do not accidentally become available on redirect-only domains.
25. As a developer, I want slug reservation rules to depend on whether a domain can serve the application UI, so that the slug policy matches product behavior.
26. As a developer, I want feature tests for application-domain and redirect-only-domain routing, so that regressions are caught when new routes are added.
27. As a developer, I want the public resolution module to remain the single place that resolves domain and slug pairs, so that redirect behavior stays testable and consistent.
28. As a support operator, I want misrouted requests to fail neutrally on redirect-only domains, so that support does not need to explain why product routes appear on customer domains.

## Implementation Decisions

- Treat the application domain as a separate concept from Domain, Default Domain, and Preferred Domain.
- The application domain should be read from boot-time Laravel configuration, derived from the configured application URL or an explicit host setting. It should not be editable from workspace settings.
- Keep Domain as the workspace-owned or default hostname used to publish short URLs. A Domain can serve short links only after verification and while usable.
- Keep Default Domain as the instance-level Domain available for creating short URLs. Do not rename or overload it to mean the application domain.
- Route the authenticated Inertia application, auth routes, workspace management routes, instance settings routes, invitation management, QR code management/export routes, and other product UI routes only on the application domain.
- Route redirect-only domain requests through public resolution. On these domains, paths matching application route names should be treated as slugs before they are treated as missing application routes.
- Preserve public resolution order: domain and slug lookup, domain availability, short link existence, enabled state, lifecycle rules, visit limit, password validation, QR attribution, analytics recording, and destination URL or fallback URL response.
- Preserve neutral unavailable pages for redirect-only domain misses and unavailable short links. Do not expose whether an unavailable path was an application route name, an unknown slug, or a private workspace object.
- Change slug reservation policy so application-reserved slugs and prefixes apply to the application domain only.
- Continue enforcing slug uniqueness by domain plus slug.
- Continue allowing generated and custom slugs on redirect-only domains, including slugs that overlap with application route names.
- Generated slugs should still avoid reserved application slugs when generating for the application domain, but they do not need to avoid those names for redirect-only domains unless the domain-specific policy says so.
- Preserve loop protection when a destination URL points back to the same domain and slug.
- Ensure public password routes remain usable for protected links on redirect-only domains without making general application auth routes available there.
- Ensure QR code scan routes remain public-resolution routes, while QR code management and export remain application-domain routes.
- Keep DNS TXT verification unchanged. Ownership verification remains separate from traffic routing.
- Add a small, deep routing or host classification module that answers whether an incoming request host is the application domain. This module should hide normalization details such as scheme removal, port handling, case folding, and configured URL parsing.
- Add a small, deep slug policy module or extend the existing slug service so reservation checks can be evaluated in the context of a specific Domain.
- Keep public resolution in the existing resolution module rather than spreading domain lookup and slug resolution across controllers.
- Avoid adding a separate frontend application or API boundary. The project remains a Laravel, Inertia, and Vue application.
- The first implementation can assume a single application domain. Multiple application UI domains, wildcard application domains, and per-workspace application domains are out of scope.
- Deployment documentation should clarify that reverse proxies may still route all relevant hostnames to the same Laravel app process; Laravel decides whether to render UI or resolve public short URLs based on the request host.

## Testing Decisions

- Tests should assert external behavior through HTTP requests and model-visible outcomes rather than implementation details of route grouping or helper classes.
- Add feature tests proving the application domain renders application routes such as login and dashboard according to the normal auth rules.
- Add feature tests proving redirect-only domains do not render login, register, dashboard, settings, profile, workspace, domain, link, member, or instance settings UI routes.
- Add feature tests proving redirect-only domains can resolve short links whose slugs match application route names.
- Add feature tests proving redirect-only domains return the neutral unavailable page for application route names when no matching short link exists.
- Add feature tests proving reserved slugs are rejected for the application domain.
- Add feature tests proving the same reserved slug names are accepted for verified redirect-only domains.
- Add feature tests proving reserved prefixes are rejected for the application domain but accepted for redirect-only domains.
- Add feature tests proving custom slugs with path separators resolve on redirect-only domains.
- Add feature tests proving short link uniqueness remains scoped to domain plus slug.
- Add feature tests proving disabled, archived, scheduled, expired, and visit-limit-reached links still follow the existing public resolution rules on redirect-only domains.
- Add feature tests proving fallback URLs still work on redirect-only domains.
- Add feature tests proving protected links still show the password form on redirect-only domains and redirect after a correct password.
- Add feature tests proving failed password attempts on redirect-only domains do not consume visit limits.
- Add feature tests proving QR scan attribution still works when the QR code resolves a short link attached to a redirect-only domain.
- Add feature tests proving analytics outcomes for success, not found, domain unavailable, password failed, and unavailable states are still queued or recorded as expected.
- Use existing Openlink MVP feature tests as prior art for domain verification gating, slug reservation, protected links, public resolution, QR code flows, and analytics behavior.
- Unit test the host classification module because it should encapsulate normalization rules behind a small interface.
- Unit or focused feature test the contextual slug policy because it is a compact business rule with high regression risk.

## Out of Scope

- Multiple application UI domains.
- Wildcard application UI domains.
- Per-workspace application UI domains.
- Moving the authenticated application to a subpath instead of a dedicated domain.
- Changes to DNS TXT ownership verification.
- Automated provisioning of DNS records, TLS certificates, or reverse proxy configuration.
- Public API changes.
- Smart destinations and app link presets.
- Billing, commercial plans, or SaaS tenant isolation.
- Reworking analytics storage beyond preserving the existing outcomes and attribution.
- Changing workspace roles, folder permissions, or dashboard authorization rules.
- Changing the visual design of the domain management UI except where copy is needed to explain redirect-only behavior.

## Further Notes

- The domain language currently defines Domain, Default Domain, and Preferred Domain, but it does not yet define the application-domain concept. Before implementation, the team should either add a canonical glossary term or explicitly keep this as an implementation concept.
- "Application Domain" is used in this PRD to mean the configured hostname that is allowed to render Openlink UI.
- The current codebase already resolves public short URLs by request host and slug, so the implementation should be evolutionary rather than a rewrite.
- The highest-risk behavior change is slug reservation becoming context-aware. Tests should pin this down before broad route changes are made.
- This PRD aligns with the existing ADRs: Domain ownership remains verified with DNS TXT records, and the product remains a single Laravel, Inertia, and Vue application.
