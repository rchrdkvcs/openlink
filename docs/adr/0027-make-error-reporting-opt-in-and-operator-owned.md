# Make error reporting opt-in and operator-owned

The hosted Openlink instance needs exception tracking for the server and the dashboard, while self-hosted instances must never send data to a destination their operator did not choose (ADR 0003). Openlink therefore ships the Sentry SDKs but no DSN. Reporting starts only when an operator sets `SENTRY_LARAVEL_DSN` or `SENTRY_BROWSER_DSN` in the environment, and events go to that operator's backend: Sentry SaaS, self-hosted Sentry, or a compatible service such as GlitchTip.

## Decisions

- **Environment only.** DSNs and sample rates are boot-time infrastructure configuration, so they stay out of instance settings, as ADR 0002 requires.
- **No visitor data.** The server SDK uses an explicit data collection policy instead of relying on `send_default_pii`. User identity, IP addresses, cookies, query strings, bodies, SQL bindings, queue payloads, and frame variables are never collected, and request headers are limited to an allow list without `User-Agent` or `Referer`. The browser SDK uses the same policy and a `beforeSend` scrub. This keeps reporting consistent with the analytics privacy rules.
- **Public resolution stays untouched.** Tracing is disabled unless a sample rate is set. When it is, a sampler drops every request outside the application host and every route named `public.*` or `app-associations.*`, so redirects pay no tracing cost. The browser configuration is shared only with dashboard pages on the application host, never with Link Pages or workspace domains.
- **Runtime browser configuration.** Release images are built once and published for everyone, so a DSN cannot be baked in at build time. The server shares the browser DSN as an Inertia prop, and the dashboard imports `@sentry/vue` dynamically only when the prop is present. Instances without a browser DSN never download the SDK.
- **Release from the image.** `SENTRY_RELEASE` defaults to `OPENLINK_VERSION`, which release images already set to their tag.

## Considered options

- **Keep reporting in a private fork or deployment overlay of the hosted instance.** Rejected: it would drift from the published image, and self-hosters would lose an easy way to report errors to their own backend.
- **Laravel Nightwatch only.** Not chosen as the integration point because it covers the server only and is not self-hostable. Operators can still add it themselves.
- **Build-time `VITE_SENTRY_DSN`.** Rejected because published images would carry either no DSN or the hosted instance's DSN.
