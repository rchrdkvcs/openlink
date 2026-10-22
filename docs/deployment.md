# Deployment

Openlink ships a production `Dockerfile`. The image runs Laravel Octane with
FrankenPHP on port `8080`. PostgreSQL and Redis are required.
PHP 8.5.12 or newer is required. The image build checks the PHP version and
refuses older FrankenPHP base images. Build with `docker build --pull` to fetch
the latest base images; CI and release builds do this automatically.
Stable releases publish versioned and `latest` images to
`ghcr.io/rchrdkvcs/openlink`. The image embeds its release version.

The example in [`docker/`](../docker/) is the quickest way to evaluate the full
stack. For production, use a container platform or an orchestrator that can:

- provide PostgreSQL and Redis;
- inject environment variables and secrets;
- persist Laravel's `storage` directory;
- run database migrations during deployment;
- run at least one queue worker;
- run the Laravel scheduler;
- terminate TLS and forward requests to port `8080`;
- restart unhealthy containers using the `/up` health endpoint.

## Required configuration

Start from [`.env.example`](../.env.example). At minimum, set a unique
`APP_KEY`, the public `APP_URL`, `APP_HOST`, PostgreSQL credentials, Redis
connection details, and mail credentials.

`APP_HOST` is the hostname that serves the dashboard and authentication routes.
Every domain configured in Openlink must also reach the application through the
reverse proxy so it can resolve short links.

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

## Release deployment

This repository currently uses Coolify for its hosted instance. Publishing the
latest stable GitHub Release triggers `.github/workflows/deploy.yml`, which
publishes the multi-architecture GHCR image and then calls a Coolify deployment
webhook. The package must be made public in GitHub package settings for
anonymous self-hosted pulls.

The instance administrator sees the installed and latest stable versions in
Settings. The update button is available only with the optional Compose updater
and a `latest` image tag; deployments on other platforms should update through
their own deployment process. See [`docker/README.md`](../docker/README.md).

Configure these repository secrets:

- `COOLIFY_WEBHOOK`
- `COOLIFY_TOKEN`

Drafts, prereleases, and releases that are not marked as latest are not deployed.
