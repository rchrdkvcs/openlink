# HTTP API

Openlink exposes a token-based JSON API under `/api/v1` that mirrors the functionality of the web interface. It is designed for browser extensions, CLI tools, and other integrations.

## Authentication

The API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access tokens. Exchange the user's credentials for a token, then send it as a bearer token on every request:

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

All routes below require `Authorization: Bearer <token>` and operate in the workspace selected by `X-Workspace-Id`. Permissions match the web interface exactly (workspace roles Owner/Admin/Editor/Viewer plus folder permissions).

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
| `GET` | `/api/v1/domains` | List domains (incl. default domain and expected TXT record) |
| `POST` | `/api/v1/domains` | Add a domain (`hostname`) |
| `POST` | `/api/v1/domains/{id}/verify` | Run DNS TXT verification |
| `POST` | `/api/v1/domains/{id}/disable` | Disable the domain |
| `POST` | `/api/v1/domains/{id}/transfer` | Transfer to another managed workspace (`workspace_id`) |
| `DELETE` | `/api/v1/domains/{id}` | Delete the domain |

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
| `POST` | `/api/v1/links/{id}/qr-codes` | Create a QR code for a link (`name`, optional size/colors/margin/error_correction) |
| `GET` | `/api/v1/qr-codes/{token}/preview` | Inline SVG preview |
| `GET` | `/api/v1/qr-codes/{token}/export/{png\|svg}` | Download the QR code |

### Members and invitations

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/members` | List workspace members |
| `GET` | `/api/v1/invitations` | List invitations |
| `POST` | `/api/v1/invitations` | Invite by email (`email`, `role`); existing users are added immediately |
| `POST` | `/api/v1/invitations/{token}/accept` | Accept an invitation for the authenticated user |

### Analytics

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/v1/analytics` | Aggregated workspace analytics (daily series, outcomes, devices, countries, browsers, operating systems, referrers) |

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
- `410` invitation expired or already used
- `422` validation error, shape `{ "message": "...", "errors": { "field": ["..."] } }`

## CORS and browser extensions

CORS for `/api/*` allows any origin with credentials disabled (see `config/cors.php`), which is what a browser extension needs: authentication is a bearer token in the `Authorization` header, never a cookie. A minimal extension flow:

1. `POST /api/v1/auth/token` with the user's credentials (and OTP if enabled) and a `device_name` like `firefox-extension`; store the token in extension storage.
2. `GET /api/v1/me` and `GET /api/v1/workspaces` to build the workspace picker.
3. `POST /api/v1/links` with `{ "destination_url": <current tab URL> }` and the `X-Workspace-Id` header to shorten the current page.
4. `DELETE /api/v1/auth/token` on logout.
