# HTTP API

Openlink exposes a token-based JSON API under `/api/v1` for the integration endpoints documented below. Some dashboard features, including Link Pages and webhook endpoint management, are not exposed through this API. It is designed for browser extensions, CLI tools, and other integrations. API access requires a verified email only when instance settings require email verification.

## Authentication

The API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access tokens. API tokens are user-owned credentials with the same permissions as the user and no separate scopes. Exchange the user's credentials for a token, or create one from the profile page, then send it as a bearer token on every request:

```
POST /api/v1/auth/token
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secret",
  "device_name": "chrome-extension",
  "one_time_password": "123456"
}
```

`one_time_password` is required only when two-factor authentication is enabled.

Response (`201`):

```json
{
  "token": "1|X9y...",
  "user": { "id": 1, "name": "Bear", "email": "user@example.com", "is_instance_admin": false }
}
```

Subsequent requests:

```
Authorization: Bearer 1|X9y...
Accept: application/json
```

Token management:

| Method | Path | Description |
| --- | --- | --- |
| `POST` | `/api/v1/auth/token` | Issue a token (rate limited, honours 2FA) |
| `GET` | `/api/v1/auth/tokens` | List the user's tokens |
| `DELETE` | `/api/v1/auth/token` | Revoke the token used for the request (logout) |
| `DELETE` | `/api/v1/auth/tokens/{id}` | Revoke a specific token |

When instance settings require email verification, issuing and using API tokens requires the user's current email to be verified. If a user changes their email while verification is required, existing tokens stop working for main API surfaces until the new email is verified. Minimal profile and token-revocation endpoints may remain available so the user can recover or revoke credentials.

Account registration and password reset are handled by the web interface; point users at the instance URL for onboarding.

## Workspace selection

The web app stores the active workspace in the session. API clients are stateless, so the active workspace is chosen per request with the `X-Workspace-Id` header:

```
GET /api/v1/links
Authorization: Bearer ...
X-Workspace-Id: 42
```

- Without the header, the user's first workspace is used.
- The header never falls back: an id the user is not a member of yields `403`, so a typo cannot silently target another workspace.
- `GET /api/v1/workspaces` lists the user's workspaces (with their role) to populate a workspace picker.

## Endpoints

All routes below require `Authorization: Bearer <token>`. They also require a verified email when instance settings require email verification, unless explicitly noted. Workspace routes operate in the workspace selected by `X-Workspace-Id`. Permissions match the web interface exactly (workspace roles Owner/Admin/Editor/Viewer).

### Profile

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/me` | Authenticated user, 2FA state, workspaces + roles |
| `PATCH` | `/api/v1/me` | Update name/email |
| `PUT` | `/api/v1/me/password` | Change password (`current_password`, `password`, `password_confirmation`) |
| `DELETE` | `/api/v1/me` | Delete account (requires `password`) |
| `POST` | `/api/v1/me/two-factor` | Start 2FA setup (returns secret + otpauth URL) |
| `POST` | `/api/v1/me/two-factor/confirm` | Confirm 2FA with `code` |
| `DELETE` | `/api/v1/me/two-factor` | Disable 2FA (requires `password`) |

### Workspaces

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/workspaces` | List workspaces with the user's role |
| `POST` | `/api/v1/workspaces` | Create a workspace (`name`) |
| `GET` | `/api/v1/workspaces/current` | Active workspace, role, and capabilities |
| `PATCH` | `/api/v1/workspaces/current` | Rename / set preferred domain (`name`, `preferred_domain_id`) |
| `DELETE` | `/api/v1/workspaces/{id}` | Delete a workspace (owner only) |

### Short links

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/links` | List accessible links (includes status, visits, scans, tags, QR codes) |
| `POST` | `/api/v1/links` | Create a link |
| `GET` | `/api/v1/links/export` | Download links as a Link CSV |
| `POST` | `/api/v1/links/import` | Preview or import a Link CSV (editors and above) |
| `GET` | `/api/v1/links/{id}` | Show one link |
| `PATCH` | `/api/v1/links/{id}` | Update destination, lifecycle rules, password, tags |
| `POST` | `/api/v1/links/{id}/move` | Move to a folder (`folder_id`, null to unfile) |
| `POST` | `/api/v1/links/{id}/archive` | Archive the link |
| `DELETE` | `/api/v1/links/{id}` | Delete permanently (managers only) |
| `GET` | `/api/v1/links/{id}/revisions` | List the link history, newest first (20 per page) |
| `GET` | `/api/v1/links/{id}/revisions/{number}` | Show one link revision with its snapshot and a restore preview |
| `POST` | `/api/v1/links/{id}/revisions/{number}/restore` | Restore a link revision (`expected_revision` or `If-Match` required) |

Create payload (only `destination_url` is required):

```json
{
  "destination_url": "https://example.com/very/long/path",
  "domain_id": 3,
  "slug": "launch",
  "folder_id": 7,
  "fallback_url": "https://example.com",
  "is_enabled": true,
  "activates_at": "2026-08-01T00:00:00Z",
  "expires_at": "2026-09-01T00:00:00Z",
  "visit_limit": 1000,
  "password": "s3cret",
  "tags": "marketing, launch",
  "preview_title": "Spring launch",
  "preview_description": "Everything new this season."

  "conversion_goal": "Registration"
}
```

Link responses include `preview` with `title`, `description`, and `image_url`. For API clients, `image_url` points to `GET /api/v1/links/{id}/preview-image`, which needs the same token and workspace as the link. To upload a preview image, send `multipart/form-data` with `preview_image` (PNG, JPG, or WebP up to 5 MB and 4096 × 4096 pixels). Updates sent this way must use `POST` with `_method=PATCH`. Send `remove_preview_image=true` to remove the image. Omitted preview fields are left unchanged on update.

When `domain_id` is omitted the API falls back to the workspace's preferred domain, then to the instance default domain — convenient for a "shorten current page" extension action. When `slug` is omitted a slug is generated.

The link list returns 50 items per page in `data` and pagination details in `meta` (`current_page`, `last_page`, `per_page`, `total`). Use `page` to select a page and optional `search`, `status` (`active`, `scheduled`, `expired`, `disabled`, `archived`), and `tag` filters. Without a status filter, the API includes archived links. The web Links page hides them by default. `PATCH /api/v1/links/{id}` accepts `tags` as a comma-separated string; send an empty string to remove all tags. `conversion_goal` (at most 80 characters) turns on conversion tracking for the link; omit it to keep the current goal, or send `null`/an empty string to stop tracking. See [Conversions](#conversions).

`GET /api/v1/links/export` accepts the same `search`, `status`, `tag`, and `folder` filters as the list and also includes archived links when no status is given. `POST /api/v1/links/import` takes a multipart `file` (UTF-8 CSV, at most 1,000 links and 1 MB), an optional `mapping` object from column name to zero-based header position, an optional `domain_id` for rows without a domain, and `dry_run`. With `dry_run=1` nothing is created and the response lists `headers`, the resolved `mapping`, each row with `status` `ready` or `invalid` and its `errors`, and a `summary`. Without it, rows are validated again, ready rows are created, and each row reports `created` or `skipped`. The format and the import rules are described in [`link-csv.md`](./link-csv.md).

Every link payload includes `revision`, the number of its latest link revision, and single-link responses carry it as an `ETag` header (`"3"`). To detect concurrent updates, send it back either as `If-Match: "3"` on `PATCH`, `move`, `archive`, and `restore`, which answers `412` when the link has moved on, or as `expected_revision` in the `PATCH` or `restore` body, which answers `422` on `expected_revision`. Restoring always requires one of the two. For backward compatibility, `PATCH`, `move`, and `archive` without either precondition are still accepted and overwrite whatever is current (last write wins); new clients should always send `If-Match`. A link created before link history existed reports revision `0` until its first change, which records a `baseline` revision first.

Each link revision lists `number`, `action` (`baseline`, `created`, `updated`, `moved`, `archived`, `restored`), `channel` (`dashboard`, `api`, `system`), `actor` (`null` once the member's account is deleted), `restored_from`, and `changes` as `{ field, before, after }` entries. URL query values and fragments appear as `•••`, snapshots carry a `url_digests` map of keyed hashes for those URLs, and a replaced password appears as a value-free `password_changed` entry. The detail endpoint adds `restore`: whether the revision is restorable, why not, the changes a restore would make, and notes about deleted folders, recreated tags or routing rules, passwords, and the short URL. Restoring never changes the short URL, visit totals, analytics, QR codes, or passwords. Routing rules and variants that analytics refer to are never deleted by a restore; they stay on the link turned off. A revision recorded in an unsupported format, or whose redacted URL the link no longer uses, answers `422` on `revision`.

### Domains

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/domains` | List domains (incl. default domain, expected TXT record, and pointing record) |
| `POST` | `/api/v1/domains` | Add a domain (`hostname`) |
| `POST` | `/api/v1/domains/{id}/verify` | Run DNS checks: TXT ownership verification and A/CNAME pointing |
| `POST` | `/api/v1/domains/{id}/disable` | Disable the domain |
| `POST` | `/api/v1/domains/{id}/transfer` | Transfer to another managed workspace (`workspace_id`) |
| `DELETE` | `/api/v1/domains/{id}` | Delete the domain |

Domain `status` is one of `pending_verification`, `failed_verification`, `ownership_verified` (TXT found, DNS not yet pointing to the server), `active` (serves short links), or `disabled`. An `ownership_verified` domain also becomes `active` when a real request reaches the server on that hostname. A `disabled` domain is never re-verified or re-activated by DNS checks, scheduled rechecks, or traffic. Payloads include `dns_record` (`type` + `value` the domain should point to, from the instance `dns_target` setting, falling back to the default domain) and `dns_check_error` when the pointing check fails.

### Folders and tags

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/folders` | List folders |
| `POST` | `/api/v1/folders` | Create a folder (`name`) |
| `PATCH` | `/api/v1/folders/{id}` | Rename |
| `DELETE` | `/api/v1/folders/{id}` | Delete (links become unfiled) |
| `GET` | `/api/v1/tags` | List tags |
| `POST` | `/api/v1/tags` | Create a tag (`name`) |

### QR codes

| Method | Path | Description |
| --- | --- | --- |
| `POST` | `/api/v1/qr-codes` | Create a QR Code with `name` and exactly one target: `short_link_id`, or `payload_type` plus `payload`; accepts optional appearance fields |
| `PATCH` | `/api/v1/qr-codes/{token}` | Update appearance or replace the target using `short_link_id`, or `payload_type` plus `payload` (`remove_logo` is also accepted). `template_id` applies a Workspace QR Code Template: its design and logo are copied server-side, then any appearance fields sent in the same request override it; an uploaded `logo` or `remove_logo` overrides the template logo |
| `DELETE` | `/api/v1/qr-codes/{token}` | Delete a QR code and its logo |
| `GET` | `/api/v1/qr-codes/{token}/preview` | Inline SVG preview; accepts the customization fields, `remove_logo`, and `template_id` as query overrides for live previews; `template_id` requires permission to edit the QR Code |
| `GET` | `/api/v1/qr-codes/{token}/export/{png\|svg}` | Download the QR code; accepts a `size` query override |

A linked QR image encodes `https://{link-domain}/qr/{token}`, so scans enter through the Domain selected for the Short Link. Direct-payload images encode their native content. When a logo is set, the effective error correction level is raised to at least quartile.

### Members and invite links

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/members` | List workspace members |
| `PATCH` | `/api/v1/members/{id}` | Change a member's role (`role`: `admin`, `editor`, `viewer`); the owner cannot be changed |
| `DELETE` | `/api/v1/members/{id}` | Remove a member from the workspace (not the owner, not yourself) |
| `GET` | `/api/v1/invite-links` | List active invite links (owner/admin only) |
| `POST` | `/api/v1/invite-links` | Create an invite link (`role`, optional `expires_in_days`, optional `max_uses`) |
| `DELETE` | `/api/v1/invite-links/{token}` | Revoke an invite link |
| `POST` | `/api/v1/invite-links/{token}/join` | Join the link's workspace as the authenticated user; existing members keep their role |

### Analytics

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/analytics` | Workspace analytics report: summary with previous-period deltas, time series, breakdowns (referrers, channels, countries, languages, devices, browsers, OS, UTM), outcomes, top links, and top QR codes. Accepts `range` (`24h`,`7d`,`14d`,`30d`,`90d`,`12m`,`custom` with `from`/`to`), `link`, `qr`, `domain`, `folder`, `tag`, and `metric` query parameters |

The report also includes `conversions`: `enabled`, `window_hours`, `minimum_attributed_visits`, `totals`, and per-link and per-routing-destination rows. Each row carries `attributed_visits`, `conversions`, `pending` (tokens still inside their window), `rate` (conversions divided by attributed visits, `null` without attributed visits), and `insufficient_evidence` (fewer than `minimum_attributed_visits` attributed visits). The report never names a winner.

### Conversions

| Method | Path | Description |
| --- | --- | --- |
| `POST` | `/api/v1/conversions` | Record a Conversion for an Attribution Token (Owner, Admin, or Editor; rate limited) |

Conversion tracking is off by default. It starts when a short link has a `conversion_goal`. Each successful, non-bot visit or linked QR Code scan of that link then reaches the destination with a single-use `openlink_attribution` query parameter:

```
https://example.com/register?plan=pro&openlink_attribution=Q2x1Z...48 characters
```

Password prompts, failed passwords, blocked or unavailable links, fallback redirects, and direct-payload QR Codes never receive a token. The token is valid for 24 hours, the Attribution Window, and converts at most once.

Integrating a destination you control takes three steps:

1. When a visitor lands, read `openlink_attribution` from the URL and keep it server-side with the visitor's session, for example in the sign-up form state. Do not store it in analytics tools or share it with third parties.
2. When the visitor completes the goal, call Openlink from your server, never from the browser, so the API token stays secret:

   ```
   POST /api/v1/conversions
   Authorization: Bearer <api token of a dedicated Editor account>
   X-Workspace-Id: 42
   Content-Type: application/json

   { "token": "Q2x1Z..." }
   ```

3. Treat `201` (`"status": "recorded"`) and `200` (`"status": "duplicate"`) as success. A retry or replay of the same token returns `duplicate` and never counts twice, so retrying after a timeout is safe.

Response:

```json
{
  "data": {
    "status": "recorded",
    "short_link_id": 12,
    "conversion_goal": "Registration",
    "qr_code_id": null,
    "routing_rule_id": 4,
    "routing_variant_id": 9,
    "converted_at": "2026-10-21T09:30:00+00:00"
  }
}
```

The body must contain only `token`. Any other field, such as an email, user ID, or order value, is rejected with `422`, and nothing is stored. A token that is unknown, forged, expired, from another workspace, from a deleted link, or from a link whose goal was removed is rejected with the same `422` error on `token`. Viewers receive `403`.

Openlink stores the link, QR Code, Routing Rule and variant, and timestamps for each Attributed Visit. It never stores the visitor hash, IP address, user agent, or any data about the visitor's account on your site. Attributed Visits, converted or not, follow analytics retention from their issue time, so conversion rates for past periods keep their denominator. An expired token can no longer convert.

### Instance settings (instance admins)

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/instance-settings` | Read instance settings |
| `PATCH` | `/api/v1/instance-settings` | Update instance settings (same fields and validation as the web form) |

`registration_mode`, `require_email_verification`, `default_domain`, `slug_length`, `analytics_retention_days`, `public_unavailable_title`, and `public_unavailable_message` are required. `dns_target`, `reserved_slugs`, and `reserved_prefixes` are optional: omit them to keep their current values, or send `null`/an empty string to clear them. Saving makes `default_domain` the active Default Domain.

## Webhooks

Webhook endpoints are managed by workspace Owners and Admins from **Settings → Webhooks**, not through API tokens. Short link changes made through the API send the same webhook events as changes made in the dashboard. See [`webhooks.md`](./webhooks.md) for events, payloads, signature verification, and retries.

## Errors

Responses follow Laravel conventions, always as JSON on `/api/*`:

- `401` missing or invalid token
- `403` no accessible workspace, insufficient role, or a link, QR code, domain, folder, or invite link outside the active workspace
- `404` unknown resource, or a member outside the active workspace
- `410` invite link expired, revoked, or out of uses
- `412` `If-Match` does not name the link's current revision
- `429` rate limit reached
- `422` validation error, shape `{ "message": "...", "errors": { "field": ["..."] } }`

## CORS and browser extensions

CORS for `/api/*` allows any origin with credentials disabled (see `config/cors.php`), which is what a browser extension needs: authentication is a bearer token in the `Authorization` header, never a cookie. A minimal extension flow:

1. `POST /api/v1/auth/token` with the user's credentials (and OTP if enabled) and a `device_name` like `firefox-extension`; store the token in extension storage.
2. `GET /api/v1/me` and `GET /api/v1/workspaces` to build the workspace picker.
3. `POST /api/v1/links` with `{ "destination_url": <current tab URL> }` and the `X-Workspace-Id` header to shorten the current page.
4. `DELETE /api/v1/auth/token` on logout.
