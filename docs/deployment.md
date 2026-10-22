# Deployment

Openlink ships a production `Dockerfile`. The image runs Laravel Octane with
FrankenPHP on port `8080`. PostgreSQL and Redis are required.
PHP 8.5.12 or newer is required. The image build checks the PHP version and
refuses older FrankenPHP base images. Build with `docker build --pull` to fetch
the latest base images; CI and release builds do this automatically.
Stable releases publish versioned and `latest` images to
`ghcr.io/rchrdkvcs/openlink`. Prereleases publish an image tagged with the exact
release tag, such as `v1.2.0-beta.1`, without updating `latest`. Beta prereleases
also update the floating `beta` tag. The image embeds
its release version.

The example in [`docker/`](../docker/) is the quickest way to evaluate the full
stack. For production, use a container platform or an orchestrator that can:

- provide PostgreSQL and Redis;
- inject environment variables and secrets;
- persist Laravel's `storage` directory;
- run database migrations during deployment;
- run at least one queue worker, which delivers webhooks;
- run the Laravel scheduler, which also prunes webhook delivery history;
- terminate TLS and forward requests to port `8080`;
- restart unhealthy containers using the `/up` health endpoint.

## Required configuration

Start from [`.env.example`](../.env.example). At minimum, set a unique
`APP_KEY`, the public `APP_URL`, `APP_HOST`, PostgreSQL credentials, Redis
connection details, and mail credentials.

`APP_HOST` is the hostname that serves the dashboard and authentication routes.
Every domain configured in Openlink must also reach the application through the
reverse proxy so it can resolve short links. Forward
`/.well-known/apple-app-site-association` and `/.well-known/assetlinks.json` to
Openlink over HTTPS without redirects so App Associations work; see
[verified-app-links.md](verified-app-links.md).

Webhook delivery needs an asynchronous `QUEUE_CONNECTION` such as `redis`. Its
retry and retention limits are described in [`webhooks.md`](./webhooks.md#operating-webhooks).
Webhook deliveries connect directly to public internet addresses and ignore
HTTP proxy environment variables.

Keep `APP_DEBUG=false` in production. Do not commit `.env` files or place
infrastructure credentials in instance settings.

## Diagnostics

Instance administrators can open **Settings → Diagnostics** to see whether
PostgreSQL, Redis, the queue worker, the scheduler, mail configuration, storage,
and analytics recording are working. The screen is read-only: it never restarts
services, runs commands, or changes deployment configuration. It complements the
public `/up` endpoint, which stays a lightweight liveness check.

Each check reports one state:

- **Healthy**: the service answered or reported a fresh heartbeat.
- **Stale**: the service reported a heartbeat, but it is too old.
- **Missing**: the service never reported a heartbeat, or the check has not
  run yet.
- **Unavailable**: Openlink could not reach the service.
- **Disabled**: this installation does not use or require the service.
- **Not configured**: the service is needed but its configuration is unusable.

The scheduler and queue worker prove they are running by recording heartbeats
in PostgreSQL. The scheduler runs `openlink:heartbeat` and each queue worker
records a heartbeat from its polling loop and around each job, both every
`OPENLINK_HEARTBEAT_MINUTES` minutes (default `1`, at most `30`). A heartbeat is
fresh until it is older than three cadences (three minutes by default) and stale
afterwards. A stopped worker is therefore detected even while Redis is healthy,
and a stopped scheduler even while a worker is healthy. A single job that runs
longer than the stale threshold makes the worker look stale until it finishes;
keep worker job timeouts below three cadences. When `QUEUE_CONNECTION=sync`, no
worker is used and the worker check is disabled.

Analytics are recorded after the response by default and need no worker. With
`OPENLINK_ANALYTICS_VIA_QUEUE=true`, visits and scans wait in the queue until a
worker records them; this is currently the only feature that requires a worker.
When no feature requires a worker, a missing or stale worker is reported as
disabled rather than as a problem.

PostgreSQL, Redis, and storage are probed only when an administrator selects
**Recheck**, never when the page is opened. The PostgreSQL and Redis probes use
dedicated connections with two-second connect and query timeouts; PostgreSQL may
retry a lost connection once. Only a local default storage disk is probed; a
remote disk is reported as disabled. Rechecks are limited to
six per minute and test emails to three every ten minutes per administrator.
The mail check only reports whether a transport is configured; SMTP needs
`MAIL_HOST` or `MAIL_URL`. A test email is
sent to the administrator's own address with a ten-second transport timeout; a successful send means the mail server
accepted it, not that it reached the inbox. Failure details are written to the
application log and are never shown on the screen.

## Weekly Workspace Summaries

Workspace Summaries depend on three pieces of infrastructure. The Laravel
scheduler (`php artisan schedule:run` every minute, or `schedule:work`) runs
`openlink:send-workspace-summaries` every fifteen minutes. A queue worker
(`php artisan queue:work`) sends each queued summary and retries failures up to
three times. Mail credentials (`MAIL_MAILER` and the matching settings) must be
able to deliver to members' email addresses.

When a piece is missing, summaries never fail silently: without a worker,
deliveries stay pending; queue or mail errors are recorded with a sanitized
message on Settings → Weekly summary, and the full exception goes to the
application log. A summary that was missed for more than 24 hours after its
Monday 08:00 send time is skipped rather than sent late.

## Release images

Publishing the latest stable GitHub Release or a prerelease triggers
`.github/workflows/deploy.yml`, which publishes the multi-architecture GHCR
image. The package must be made public in GitHub package settings for anonymous
self-hosted pulls. Deployments are managed separately from image publication.

To publish a prerelease package, create a GitHub Release with a semantic version
tag such as `v1.2.0-alpha.1`, `v1.2.0-beta.1`, or `v1.2.0-rc.1`, select
**Set as a pre-release**, and publish it. The same workflow publishes the GHCR
image for `linux/amd64` and `linux/arm64` without updating `latest`.
For example, pull `ghcr.io/rchrdkvcs/openlink:v1.2.0-beta.1`, or set
`OPENLINK_IMAGE_TAG=v1.2.0-beta.1` in `docker/.env` when using the Compose stack.
The image tag preserves the release tag, including its optional `v` prefix.

Beta prereleases (`v1.2.0-beta`, `v1.2.0-beta.1`, or the same tags without `v`)
also publish `ghcr.io/rchrdkvcs/openlink:beta`. Use `OPENLINK_IMAGE_TAG=beta` to
follow beta publications without changing the Compose configuration each time.
Alpha and release-candidate prereleases keep only their exact release tag and
do not update `beta`. Stable releases update `latest` and do not update `beta`.

The GitHub package page can feature the most recently published image, including
a prerelease, as **Latest** and suggest its versioned pull command. That badge
is separate from the Docker `latest` tag. Pull
`ghcr.io/rchrdkvcs/openlink:latest` explicitly for the stable channel, or
`ghcr.io/rchrdkvcs/openlink:beta` for the beta channel.

The instance administrator sees the installed and latest stable versions in
Settings. The update button is available only with the optional Compose updater
and a `latest` image tag; deployments on other platforms should update through
their own deployment process. See [`docker/README.md`](../docker/README.md).

Drafts and stable releases that are not marked as latest do not publish images.
