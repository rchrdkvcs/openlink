# Attribute conversions with short-lived single-use tokens

Status: accepted

Visits and split-test assignments tell a team where visitors went, not whether a visit led to the action the team cares about, such as a registration. Openlink attributes a Conversion to the exact Visit or Scan that caused it by handing the destination an opaque Attribution Token, which the destination's own server later redeems through an authenticated API call. Conversion tracking is opt-in per Short Link and stores only what aggregate goal counts need, so it does not become a visitor profile.

## Decision

- **Opt-in per Short Link.** A Short Link tracks conversions only while it has a Conversion Goal: a short name such as "Registration". There is no workspace or instance switch; links without a goal behave exactly as before, and linked QR Codes inherit the goal of their Short Link. Each link has at most one goal.
- **Issued only on a successful resolution.** An Attribution Token is minted after availability, visit limit, password, and Smart Routing have all succeeded, and only for traffic not classified as a bot. Password prompts, failed passwords, blocked or unavailable links, fallback redirects, and direct-payload QR Codes never receive one. The token is appended to the chosen Destination URL as the `openlink_attribution` query parameter.
- **Opaque, unguessable, stored hashed.** The token is 48 random characters. Only its SHA-256 hash is stored, so a database read cannot be replayed as a valid token.
- **Attribution Window of 24 hours.** The token expires 24 hours after it is issued and is the only window. The window is a fixed product constant, not a setting.
- **Scope-bound.** A token is redeemed through `POST /api/v1/conversions` with a user API token and the workspace chosen by `X-Workspace-Id`. The redeeming user needs a role that can edit content. A token is rejected with the same neutral error when it is unknown, forged, expired, belongs to another workspace, belongs to a deleted link, or belongs to a link whose goal has been removed.
- **Single use and idempotent.** A token converts at most once. The token is the idempotency key: a retry or replay of an already converted token is acknowledged as a duplicate and never increments the goal. Concurrent redemptions are resolved with a conditional update, so only one can win.
- **No customer data.** The conversion request accepts only the token. Any other field is rejected rather than ignored, so integrations cannot silently send emails, user IDs, order values, or other personal data.
- **Minimal record.** An attribution stores the workspace, Short Link, optional QR Code, matched Routing Rule and variant, metric, token hash, issue time, expiry, and conversion time. It stores no visitor hash, IP address, user agent, referrer, country, or Destination URL.
- **Retention and deletion.** Attributions that expire unconverted are deleted by the daily analytics pruning command. Converted attributions follow the instance analytics retention, measured from the issue time. Deleting a Short Link or Workspace deletes its attributions. Removing a goal keeps past conversions visible but stops new tokens and rejects outstanding ones.
- **Reporting without verdicts.** Analytics report, per Short Link and per Routing Rule and variant, the attributed visits issued in the period, how many converted, the conversion rate over attributed visits, and how many tokens are still inside their window. Rows below 30 attributed visits are marked as insufficient evidence. Openlink never names a winning variant.

## Reconciliation with existing privacy decisions

ADR 0007 and ADR 0012 rely on a visitor hash salted per day, so visitors are unique only within a day and split-test assignment is stable only within a day. Conversion attribution keeps that promise: the token is random per resolution, is never derived from the visitor hash, and is never stored next to it. Two visits from the same person produce two unrelated tokens, so attributions cannot be joined into a cross-day history. The cost is accepted: one person who visits twice and converts once counts as two attributed visits and one conversion.

The security and privacy principle that analytics do not identify individual visitors still holds. A token identifies one resolution for 24 hours, to the destination the visitor was already sent to; it carries no information about the visitor and becomes useless once converted or expired.

ADR 0007 keeps analytics writes out of the request path. Issuing a token is the one exception: the attribution row is written before the redirect, because the destination may redeem the token as soon as the visitor arrives. The write happens only for links with a Conversion Goal. If it fails, the error is reported and the visitor is redirected without a token.

## Assumptions

These choices were made without a product owner and should be revisited if they prove wrong:

- 24 hours covers the common "visit then register" flow; longer journeys are out of scope rather than a reason to keep tokens longer.
- Redeeming conversions with a user API token is acceptable. API tokens have no scopes yet, so the guidance is to issue the token from a dedicated Editor account.
- 30 attributed visits per row is a deliberately low floor that only warns against reading noise; it is not a significance test.
- Bot traffic is excluded from attribution in the same way it is excluded from visit counts.

## Considered options

- **Reuse the daily visitor hash as the attribution key.** Rejected: it would turn a deliberately ephemeral value into an identifier shared with third-party destinations.
- **Client-side pixel or JavaScript tag.** Rejected: it adds a browser tracking surface and cannot be authenticated.
- **Per-link conversion secrets.** Deferred: existing API tokens already carry workspace roles, rate limits, and revocation.
- **Separate idempotency keys.** Rejected for the first version: with one goal per link and single-use tokens, the token already identifies the conversion uniquely.

## Out of scope

Revenue, customer profiles, affiliate commissions, cross-device attribution, marketing pixels, automatic experimentation, multiple goals per link, and conversions on destinations that do not integrate with the API.
