# Webhooks

Webhooks let external systems react to changes in a workspace without polling the [HTTP API](./api.md). A workspace Owner or Admin adds **webhook endpoints** from **Settings → Webhooks**. Each endpoint subscribes to selected event types and receives a signed `POST` request for every matching event.

Webhook endpoints are separate from [API tokens](./api.md#authentication): an API token lets a client act as a user, while a webhook endpoint only receives webhook events and grants no access to Openlink.

## Managing endpoints

Owners and Admins can:

- add an endpoint with an HTTPS URL and at least one event type, up to 10 endpoints per workspace;
- edit its URL and event subscriptions;
- enable or disable it; a disabled endpoint receives no new attempts, including scheduled retries;
- send a `webhook.test` event to check the receiver (up to 10 per minute);
- rotate its signing secret;
- retry a failed delivery (up to 30 retries per minute);
- delete it, which also deletes its delivery history.

Editors and Viewers cannot see or manage endpoints.

## Events

| Type | When it is sent |
| --- | --- |
| `short_link.created` | A short link is created from the dashboard or the API. |
| `short_link.updated` | A short link's address, destination, lifecycle rules, password, tags, routing rules, or folder changes. |
| `short_link.archived` | A short link is archived. |
| `webhook.test` | An Owner or Admin sends a test event. Always delivered to the selected endpoint, whatever its subscriptions. |

Events describe changes made to a short link itself from the dashboard or the API. Deleting a folder or a tag, which also removes it from its short links, does not send `short_link.updated` events. Visits and scans are not sent as webhook events.

## Payload

Every request body is a JSON envelope:

```json
{
  "id": "0199f0a4-7c1d-7b3a-9e55-2f3b8d1c4a10",
  "type": "short_link.created",
  "version": 1,
  "occurred_at": "2026-10-21T09:30:12.418Z",
  "workspace": { "id": 42 },
  "resource": { "type": "short_link", "id": 1337 },
  "data": {
    "id": 1337,
    "slug": "launch",
    "short_url": "https://go.example.com/launch",
    "destination_url": "https://example.com/launch",
    "fallback_url": null,
    "status": "active",
    "is_enabled": true,
    "has_password": false,
    "domain": { "id": 7, "hostname": "go.example.com" },
    "folder": { "id": 3, "name": "Campaigns" },
    "tags": [{ "id": 11, "name": "spring" }],
    "activates_at": null,
    "expires_at": null,
    "visit_limit": null,
    "archived_at": null,
    "created_at": "2026-10-21T09:30:12Z",
    "updated_at": "2026-10-21T09:30:12Z"
  }
}
```

- `id` identifies the event. It never changes across retries.
- `data` is a snapshot of the resource when the event happened, not when it is delivered.
- For `webhook.test`, `resource` is `{ "type": "webhook_endpoint", "id": <endpoint id> }` and `data` contains only a `message`.
- Payloads never contain passwords or password hashes, signing secrets, API tokens, visitor IP addresses or hashes, or visitor request headers.

### Versioning

`version` is the envelope version, currently `1`. Within a version, Openlink may add new fields to `data` and new event types, so receivers must ignore fields and event types they do not know. Removing or renaming a field, or changing its meaning, increments `version`.

## Request headers

| Header | Value |
| --- | --- |
| `Content-Type` | `application/json` |
| `User-Agent` | `Openlink-Webhooks/1` |
| `Openlink-Event-Id` | The event `id` |
| `Openlink-Event-Type` | The event `type` |
| `Openlink-Timestamp` | Unix time, in seconds, when this attempt was signed |
| `Openlink-Signature` | One or more comma-separated signatures, each `v1=` followed by a hex HMAC-SHA256 signature |

## Verifying signatures

Each endpoint has a signing secret that starts with `whsec_`. It is shown once when the endpoint is created and once each time it is rotated. Store it in your receiver's secret store; Openlink cannot show it again.

The signature is `HMAC-SHA256(secret, timestamp + "." + raw_body)`, encoded as lowercase hex. To verify a request:

1. Read the raw request body as bytes, before any JSON parsing or re-encoding.
2. Reject the request if `Openlink-Timestamp` is more than 5 minutes away from your current time. This limits replay of captured requests.
3. Compute the expected signature and compare it, with a constant-time comparison, to each comma-separated entry of `Openlink-Signature`. Accept the request if any entry matches.
4. Only then parse the JSON.

PHP:

```php
$secret = getenv('OPENLINK_WEBHOOK_SECRET');
$body = file_get_contents('php://input');
$timestamp = (int) ($_SERVER['HTTP_OPENLINK_TIMESTAMP'] ?? 0);
$signature = $_SERVER['HTTP_OPENLINK_SIGNATURE'] ?? '';

if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    exit;
}

$expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
$verified = false;

foreach (explode(',', $signature) as $candidate) {
    $verified = hash_equals($expected, trim($candidate)) || $verified;
}

if (! $verified) {
    http_response_code(401);
    exit;
}

$event = json_decode($body, true);
```

Node.js:

```js
import { createHmac, timingSafeEqual } from 'node:crypto';

export function verifyOpenlinkWebhook(rawBody, headers, secret, toleranceSeconds = 300) {
  const timestamp = Number(headers['openlink-timestamp']);
  const candidates = String(headers['openlink-signature'] ?? '')
    .split(',')
    .map((candidate) => Buffer.from(candidate.trim()));

  if (!Number.isInteger(timestamp) || Math.abs(Date.now() / 1000 - timestamp) > toleranceSeconds) {
    return false;
  }

  const expected = Buffer.from(
    'v1=' + createHmac('sha256', secret).update(`${timestamp}.`).update(rawBody).digest('hex'),
  );

  return candidates.some((candidate) => candidate.length === expected.length && timingSafeEqual(candidate, expected));
}
```

### Rotating the signing secret

Rotating a secret does not break deliveries that are already pending or retrying. For 24 hours after a rotation, every attempt carries two signatures: one made with the new secret, followed by one made with the previous secret.

```
Openlink-Signature: v1=5257a869e7ecebeda32affa62cdca3fa51cad7e77a0e56ff536d0ce8e108d8bd,v1=6ffbb59b2300aae63f272406069a9788598b792a944a07aba816edb039989a39
```

A receiver that still holds the previous secret keeps verifying deliveries during that window. Deploy the new secret within 24 hours. After the window, or after another rotation, only the current secret signs deliveries. Rotating again within the window replaces the previous secret with the one that was current until then.

## Delivery and retries

- Events are recorded in the same database transaction as the change. A change that is rolled back sends nothing.
- Deliveries are sent by the queue worker after the transaction commits, so a slow or failing receiver never delays link edits or public redirects.
- A delivery succeeds when the receiver answers with a `2xx` status within 10 seconds. Respond quickly and do heavy work asynchronously.
- Openlink reads at most 64 KB of the response body. A larger response aborts the transfer and counts as a failure without retry, so keep responses short or empty.
- `5xx`, `408`, `425`, and `429` responses, timeouts, connection errors, and hostnames that temporarily do not resolve are retried up to 6 attempts in total, waiting 1, 4, 16, 64, and 256 minutes between attempts.
- Other `4xx` responses mean the receiver rejected the request, so the delivery fails without retry.
- Redirects are not followed and count as a failure without retry. Point the endpoint at its final URL.
- After the last attempt, the delivery is marked as failed. An Owner or Admin can retry it from the delivery history, which sends the same event with the same `id` and body.

### Duplicates and ordering

Delivery is at least once. A receiver can see the same event more than once, for example after a manual retry or when a response is lost after the receiver processed the request. Store the `Openlink-Event-Id` of processed events and ignore events you have already handled.

Events are not guaranteed to arrive in order. Use `occurred_at` and the `data` snapshot to decide whether an event is newer than the state you already hold.

## Delivery history

Each endpoint shows its 20 most recent deliveries with the event type, status, attempt count, HTTP status, a short sanitized error, and the duration of the last attempt. Response bodies and headers are not stored. Delivery history older than 30 days is deleted daily by the scheduler; deleting an endpoint deletes its history immediately.

## Endpoint safety

Endpoint URLs must use HTTPS and must not contain credentials. Saving an endpoint rejects IP literals in blocked ranges and internal names such as `localhost` or `*.internal`, but does not look up DNS, so saving never waits on a slow resolver. Before every attempt, Openlink resolves the hostname and refuses to connect when any resolved address is loopback, private, link-local, carrier-grade NAT, multicast, documentation, or otherwise reserved, including cloud metadata addresses such as `169.254.169.254`. The connection is pinned to the address that passed this check, so DNS rebinding cannot redirect it, and TLS certificates are always verified.

## Operating webhooks

Webhook deliveries require a running queue worker (`php artisan queue:work`) on an asynchronous queue connection such as Redis, and the Laravel scheduler for history pruning. With `QUEUE_CONNECTION=sync`, deliveries are attempted inside the request that caused them and automatic retries do not run.

These environment variables tune delivery:

| Variable | Default | Purpose |
| --- | --- | --- |
| `OPENLINK_WEBHOOK_MAX_ATTEMPTS` | `6` | Attempts per delivery before it is marked as failed |
| `OPENLINK_WEBHOOK_TIMEOUT` | `10` | Seconds to wait for a response |
| `OPENLINK_WEBHOOK_CONNECT_TIMEOUT` | `3` | Seconds to wait for a connection |
| `OPENLINK_WEBHOOK_RETENTION_DAYS` | `30` | Days of delivery history to keep |
