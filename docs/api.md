# HTTP API

Openlink exposes a token-based JSON API under `/api/v1` that mirrors the functionality of the web interface. It is designed for browser extensions, CLI tools, and other integrations. API access requires a verified email.

## Authentication

The API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access tokens. API tokens are user-owned credentials with the same permissions as the user and no separate scopes. Exchange the user's credentials for a token, or create one from the profile page, then send it as a bearer token on every request:

```
POST /api/v1/auth/token
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "secret",
  "device_name": "chrome-extension",
  "one_time_password": "123456"   // required only when 2FA is enabled
}
```

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

Issuing and using API tokens requires the user's current email to be verified. If a user changes their email, existing tokens stop working for main API surfaces until the new email is verified. Minimal profile and token-revocation endpoints may remain available so the user can recover or revoke credentials.

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

All routes below require `Authorization: Bearer <token>` and a verified email unless explicitly noted. Workspace routes operate in the workspace selected by `X-Workspace-Id`. Permissions match the web interface exactly (workspace roles Owner/Admin/Editor/Viewer plus folder permissions).

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
| `GET` | `/api/v1/links/{id}` | Show one link |
| `PATCH` | `/api/v1/links/{id}` | Update destination, lifecycle rules, password |
| `POST` | `/api/v1/links/{id}/move` | Move to a folder (`folder_id`, null to unfile) |
| `POST` | `/api/v1/links/{id}/archive` | Archive the link |
| `DELETE` | `/api/v1/links/{id}` | Delete permanently (managers only) |

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
  "tags": "marketing, launch"
}
```

When `domain_id` is omitted the API falls back to the workspace's preferred domain, then to the instance default domain — convenient for a "shorten current page" extension action. When `slug` is omitted a slug is generated.

### Domains

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/domains` | List domains (incl. default domain, expected TXT record, and pointing record) |
| `POST` | `/api/v1/domains` | Add a domain (`hostname`) |
| `POST` | `/api/v1/domains/{id}/verify` | Run DNS checks: TXT ownership verification and A/CNAME pointing |
| `POST` | `/api/v1/domains/{id}/disable` | Disable the domain |
| `POST` | `/api/v1/domains/{id}/transfer` | Transfer to another managed workspace (`workspace_id`) |
| `DELETE` | `/api/v1/domains/{id}` | Delete the domain |

Domain `status` is one of `pending_verification`, `failed_verification`, `ownership_verified` (TXT found, DNS not yet pointing to the server), `active` (serves short links), or `disabled`. An `ownership_verified` domain also becomes `active` when a real request reaches the server on that hostname. Payloads include `dns_record` (`type` + `value` the domain should point to, from the instance `dns_target` setting, falling back to the default domain) and `dns_check_error` when the pointing check fails.

### Folders, tags, permissions

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/folders` | List accessible folders (with permissions) |
| `POST` | `/api/v1/folders` | Create a folder (`name`) |
| `PATCH` | `/api/v1/folders/{id}` | Rename |
| `DELETE` | `/api/v1/folders/{id}` | Delete (links become unfiled) |
| `POST` | `/api/v1/folders/{id}/permissions` | Grant a member access (`user_id`, `permission`: `can_view`/`can_edit`/`can_manage`) |
| `GET` | `/api/v1/tags` | List tags |
| `POST` | `/api/v1/tags` | Create a tag (`name`) |

### QR codes

| Method | Path | Description |
| --- | --- | --- |
| `POST` | `/api/v1/links/{id}/qr-codes` | Create a QR code for a link (`name`, optional `size`, `foreground_color`, `background_color`, `margin`, `error_correction`, `style` (`square`/`rounded`/`dot`), `eye_style` (`square`/`rounded`/`circle`), `background_transparent`, `logo` file upload) |
| `PATCH` | `/api/v1/qr-codes/{token}` | Update a QR code (same fields as create, plus `remove_logo`) |
| `DELETE` | `/api/v1/qr-codes/{token}` | Delete a QR code and its logo |
| `GET` | `/api/v1/qr-codes/{token}/preview` | Inline SVG preview; accepts the customization fields as query overrides for live previews |
| `GET` | `/api/v1/qr-codes/{token}/export/{png\|svg}` | Download the QR code; accepts a `size` query override |

The QR image encodes `https://{link-domain}/qr/{token}`, so scans enter through the domain selected for the link. When a logo is set, the effective error correction level is raised to at least quartile.

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

### Instance settings (instance admins)

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/instance-settings` | Read instance settings |
| `PATCH` | `/api/v1/instance-settings` | Update instance settings (same fields as the web form) |

## Errors

Responses follow Laravel conventions, always as JSON on `/api/*`:

- `401` missing or invalid token
- `403` no accessible workspace, insufficient role, or folder permission denied
- `404` unknown resource (or resource outside the active workspace)
- `410` invite link expired, revoked, or out of uses
- `422` validation error, shape `{ "message": "...", "errors": { "field": ["..."] } }`

## CORS and browser extensions

CORS for `/api/*` allows any origin with credentials disabled (see `config/cors.php`), which is what a browser extension needs: authentication is a bearer token in the `Authorization` header, never a cookie. A minimal extension flow:

1. `POST /api/v1/auth/token` with the user's credentials (and OTP if enabled) and a `device_name` like `firefox-extension`; store the token in extension storage.
2. `GET /api/v1/me` and `GET /api/v1/workspaces` to build the workspace picker.
3. `POST /api/v1/links` with `{ "destination_url": <current tab URL> }` and the `X-Workspace-Id` header to shorten the current page.
4. `DELETE /api/v1/auth/token` on logout.
