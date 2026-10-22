# Deployment

Openlink runs as a container with PostgreSQL and Redis. The image serves HTTP
on port `8080` through Laravel Octane and FrankenPHP.

For a first installation, use the [Docker Compose guide](../docker/README.md).
This guide explains image selection and the requirements for production or
another container platform.

## Choose an image

The image is published to `ghcr.io/rchrdkvcs/openlink` for `linux/amd64` and
`linux/arm64`. All application services must use the same version.

- **Stable:** `ghcr.io/rchrdkvcs/openlink:latest` follows stable releases.
- **Beta:** `ghcr.io/rchrdkvcs/openlink:beta` follows beta prerelease publications.
- **Pinned:** an exact release tag, for example `ghcr.io/rchrdkvcs/openlink:v1.2.0-beta.1`, stays on that release. Use a tag that exists in the registry; this version is an example.

Exact tags preserve the release tag, including its `v` prefix when present.
Alpha and release-candidate prereleases have exact tags only. Prereleases never
update `latest`, and stable releases never update `beta`. For a deployment
pinned to specific image content, use the published `sha256` digest.

The GitHub package page can feature a prerelease as **Latest**. That badge is
separate from Docker's `latest` tag: select `:latest` explicitly for stable
releases or `:beta` for the beta channel.

A public package needs no registry login. For a private package, authenticate
the deployment host to `ghcr.io` with credentials that can read the package.
See [Release image publication](#release-image-publication) for how images are
built and made available.

## Production requirements

Your container platform must provide:

- PostgreSQL and Redis, reachable by every application process.
- Environment variables and secrets, including a persistent `APP_KEY`.
- Persistent storage mounted at `/app/storage`, shared by the app and background processes.
- A one-shot database migration before the new app and workers start.
- A queue worker and scheduler that restart when they exit.
- HTTPS through a reverse proxy forwarding to port `8080`.
- Monitoring, database and file backups, and a tested restore process.

The image runs as `www-data`. Its storage and `bootstrap/cache` directories must
be writable. Uploaded QR logos, template logos, Link Page logos, and social
preview images live in storage; preserve them alongside PostgreSQL and the
application key. Changing the key can make encrypted integration secrets
unreadable.

The image's `/up` health endpoint is a lightweight liveness check. Configure
container restarts and use Diagnostics and service logs to investigate failures.

## Required configuration

Start from [`.env.example`](../.env.example). At minimum, set a unique
`APP_KEY`, the public `APP_URL`, `APP_HOST`, PostgreSQL credentials, Redis
connection details, and mail credentials.

`APP_HOST` is the hostname that serves the dashboard and authentication routes.
Set `APP_URL` to the complete public HTTPS URL and `APP_HOST` to its hostname
only, without a scheme, port, or path. Link Pages are served at `/p/{path}` on
this application host. Workspace domains serve short links rather than the
application dashboard.

Every domain configured in Openlink must also reach the application through the
reverse proxy so it can resolve short links. Provision DNS and TLS for each
hostname; a TXT ownership record alone does not route traffic or issue a
certificate. Preserve the request host and forwarded HTTPS headers. Configure
`TRUSTED_PROXIES` for your proxy infrastructure and `OCTANE_HTTPS=true` when
Octane generates URLs for an HTTPS deployment. Forward
`/.well-known/apple-app-site-association` and `/.well-known/assetlinks.json` to
Openlink over HTTPS without redirects so App Associations work; see
[verified-app-links.md](verified-app-links.md).

Webhook delivery needs an asynchronous `QUEUE_CONNECTION` such as `redis`. Its
retry and retention limits are described in [`webhooks.md`](./webhooks.md#operating-webhooks).
Webhook deliveries connect directly to public internet addresses and ignore
HTTP proxy environment variables.

Keep `APP_DEBUG=false` in production. Do not commit `.env` files or place
infrastructure credentials in instance settings.

The Compose example defines its environment explicitly in
`x-app-environment`. Adding a variable to `docker/.env` alone does not pass it
to Laravel: add the matching entry there so app, worker, scheduler, and migration
services receive the same configuration. In particular, the example fixes
`MAIL_MAILER` to `log`; replace it with your mail transport and forward the
matching mail settings before expecting outgoing emails.

## Background processes

Keep these processes running with the same image and configuration as the app:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
php artisan schedule:work
```

These are separate long-running processes. Alternatively, run
`php artisan schedule:run` every minute through your platform's scheduler.
To invoke Artisan in the image, override its default web-server entrypoint;
the Compose file already does this for its background services.

The queue handles webhook deliveries, Destination Checks, weekly summary
emails, and analytics when `OPENLINK_ANALYTICS_VIA_QUEUE=true`. Keep
`QUEUE_CONNECTION=redis` for asynchronous execution.

The scheduler verifies pending domains hourly, checks due destinations and
workspace summaries every fifteen minutes, records its heartbeat, and prunes
analytics and webhook history daily. Destination Monitoring must also be enabled
by the instance administrator; starting the scheduler alone does not enable it.

## Operating Destination Checks

Manual Destination Checks use the same default queue as the worker. Keep
`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, and the same Redis connection,
queue name, and cache prefix in the web and worker processes. A persistent
shared cache is required for destination deduplication, host limits, and global
concurrency slots. The `database` queue is also supported; run the worker against
that connection if selected. Synchronous, deferred, and background queue drivers
are rejected for Destination Checks so outbound requests never run in the web
request. No scheduler is needed for a manual check.

The inspector reports **Queued** until a worker starts the job, **Running**
while probing, and **Completed** only when a normalized result is saved. The
latest health result is kept during a recheck and if a job fails. Completed can
mean an HTTP failure, Inconclusive, or Blocked; it does not imply Reachable.
The inspector and Issues page keep refreshing while a job is pending, including
when it waits more than a minute. An old queued request does not become Not
checked just because time passed.

If a check stays Queued, inspect the worker service and its logs, verify it
consumes the configured connection and queue, and check `php artisan queue:failed`.
Start or restart the worker after correcting configuration; pending jobs are
consumed without another click. A queue submission error or terminal worker
failure is shown as a failed check and can be retried. A queued job gets up to
30 minutes for host and global throttling; its unique lock lasts 32 minutes.
After 60 seconds in Queued, **Retry check** becomes available. It is an explicit
replacement, not an automatic expiry: leaving the request alone still lets the
original job run through its full queue waiting window. Replacing it creates a
new generation; an old queued job skips probing, and late results or failure
callbacks cannot overwrite the replacement. This also recovers pre-upgrade
pending rows whose original job is missing. Repeated replacements can delay
completion on a busy queue, so first verify that a worker is running.

When Destination Monitoring is enabled, the scheduler also replaces abandoned
queued requests after 32 minutes, beyond their 30-minute queue retry window,
and running requests whose lease has expired. Recovery uses the same generation
and execution-state comparison as manual retry, including when the previous
health result is recent. Valid queued backlogs and active running leases are
preserved. Recovery runs at the next monitoring tick, every fifteen minutes.

Running has a 120-second lease, longer than the job timeout. If a worker is killed
without reporting failure, the dashboard treats the expired lease as a failed
execution and offers **Check now** again. The previous health is preserved;
an expired lease never claims a successful probe. A redelivered job waits for an
active lease to expire, then queues a fresh generation before probing. Redelivery of a Completed or Failed generation does
nothing, so a lost queue acknowledgement cannot count one check twice.

Jobs time out after 90 seconds and report timeout failures. The Redis and
database queue reservation defaults are 120 seconds, longer than that timeout;
keep `REDIS_QUEUE_RETRY_AFTER` or `DB_QUEUE_RETRY_AFTER` above 90 if overridden.
Keep the worker running with the command above, including on installations
where queued analytics and scheduled Destination Monitoring are disabled.
Run migrations before restarting the web and worker processes when upgrading;
execution state is stored separately from Destination Health. Restart all old
workers before accepting new check requests so they use generation guards.

## Diagnostics

Instance administrators can open **Settings → Diagnostics** to see whether
PostgreSQL, Redis, the queue worker, the scheduler, mail configuration, storage,
and analytics recording are working. The screen is read-only: it never restarts
services, runs commands, or changes deployment configuration. It complements the
public `/up` endpoint, which stays a lightweight liveness check.

Checks report Healthy, Stale, Missing, Unavailable, Disabled, or Not configured.
The queue worker and scheduler record independent heartbeats in PostgreSQL.
Their default cadence is one minute, controlled by `OPENLINK_HEARTBEAT_MINUTES`
(up to 30); a heartbeat is stale after three cadences. Keep job timeouts below
that threshold to avoid reporting a busy worker as stale.

The current worker diagnostic bases its required/disabled state only on
`OPENLINK_ANALYTICS_VIA_QUEUE`. It can report a missing worker as disabled even
when webhooks, Destination Checks, or summary emails need one. Keep the worker
running for those features and inspect their delivery states and logs.

PostgreSQL, Redis, and local storage are probed only when an administrator
selects **Recheck**. A mail check confirms configuration; sending a test email
checks that the mail transport accepts it, not inbox delivery. Test emails go
to the administrator's own address. Detailed failures go to the application
log rather than the screen.

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

## Updates and recovery

Before an update, read the release notes, back up PostgreSQL and `/app/storage`,
and retain the current image reference, deployment files, and `APP_KEY` through
your normal secret backup process. Pull the selected image for every application
service, run migrations, and recreate the app, workers, and scheduler. Long-lived
processes must restart to load the new code and configuration.

The [Docker guide](../docker/README.md#updates) provides manual commands and the
optional updater. On another platform, run this once as a deployment job before
starting the new application processes:

```bash
docker run --rm --env-file /path/to/production.env --network openlink \
  --entrypoint php ghcr.io/rchrdkvcs/openlink:latest \
  artisan migrate --force --no-interaction
```

Replace the environment file, network, and image reference with your deployment
values; attach its storage volume too if your deployment requires it. The job
must reach PostgreSQL and Redis using the same configuration as the app.

After deployment, check `/up`, **Settings → Diagnostics**, the worker and
scheduler logs, and an existing Short URL. For enabled integrations, check a
webhook delivery or Destination Check and the weekly summary delivery state.

The **Update now** button requires the optional Compose updater and the `latest`
image tag. The `beta` channel and exact tags are updated manually or through
your deployment platform. Image publication never updates a running instance.

An older image may not be compatible with a newly migrated database. Recovery
may require restoring the matching database and storage backup as well as the
previous image and key; do not assume changing the image tag reverses migrations.

## Release image publication

Publishing a GitHub Release triggers
[`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml). It builds the
release tag on native AMD64 and ARM64 runners, pushes image digests, and combines
them into a multi-architecture manifest. The release tag is embedded as
`OPENLINK_VERSION` and in the image metadata.

- A stable release publishes its exact tag and `latest` only when it is GitHub's latest stable release.
- Every published prerelease gets its exact tag. Beta version tags such as `v1.2.0-beta`, `v1.2.0-beta.1`, and their equivalents without `v` also update `beta`.
- Alpha and release-candidate prereleases do not update `beta` or `latest`.
- Drafts, ordinary pushes, and stable releases not selected as latest do not publish images through this workflow.

For a prerelease, publish a GitHub Release with its version tag and **Set as a
pre-release** selected. Wait for the workflow to finish before pulling it. The
workflow uses `GITHUB_TOKEN` with package-write permission; it does not deploy
to Coolify or any other host.

The registry path comes from `github.repository`. A fork therefore publishes
to its own GHCR namespace; change the image reference in your Compose file if
you want to use that package. Make the package public in GitHub package settings
for anonymous pulls.

## Build from source

The production image includes PHP and frontend assets; a Docker host does not
need Composer or Node.js. The build requires PHP 8.5.12 or newer in its
FrankenPHP base and refuses older versions. To build locally from a checkout:

```bash
docker build --pull --build-arg OPENLINK_VERSION=dev -t openlink:local .
```

`--pull` refreshes the base images; CI and release builds also enable it. Point
all application services at your built image when using a local build.
