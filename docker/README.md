# Docker deployment

This Compose file runs a local evaluation stack with:

- the Openlink application on port `8080`;
- a Laravel queue worker;
- the Laravel scheduler;
- a one-shot migration container;
- PostgreSQL 17;
- Redis 7.

## Start

From the repository root, create `docker/.env` and generate a unique `APP_KEY`.
In Bash:

```bash
cp docker/.env.example docker/.env
key="base64:$(openssl rand -base64 32)"
sed -i.bak "s|^APP_KEY=.*|APP_KEY=$key|" docker/.env && rm docker/.env.bak
```

In PowerShell:

```powershell
Copy-Item docker/.env.example docker/.env
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
$key = [Convert]::ToBase64String($bytes)
$content = Get-Content docker/.env -Raw
$content = [regex]::Replace($content, '(?m)^APP_KEY=.*$', "APP_KEY=base64:$key")
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText((Resolve-Path docker/.env).Path, $content, $utf8NoBom)
$rng.Dispose()
```

Then start the stack from the public `ghcr.io/rchrdkvcs/openlink:latest` image:

```bash
docker compose --env-file docker/.env -f docker/compose.yml up -d
```

Open `http://localhost:8080`. The first registered account becomes the instance
administrator. The example uses Laravel's `log` mailer; retrieve email
verification and password-reset links from the application logs:

```bash
docker compose --env-file docker/.env -f docker/compose.yml logs app
```

The stack exposes PostgreSQL and Redis to the host for local inspection. Remove
those `ports` entries before using this example on an internet-facing host.
The local development services are defined separately in
[`compose.dev.yml`](./compose.dev.yml). Stop them before starting this full
stack because both files publish PostgreSQL and Redis on ports `5432` and
`6379`.

## Updates

The instance administrator can see the installed version and latest stable
GitHub release in Settings. To enable the **Update now** button on this Compose
installation, set `OPENLINK_UPDATER_ENABLED=true` and keep
`OPENLINK_IMAGE_TAG=latest` in `docker/.env`, then start the optional updater:

```bash
docker compose --env-file docker/.env -f docker/compose.yml --profile updates up -d
```

The updater pulls the latest application image, runs the migration service, and
recreates the application, worker, and scheduler containers. Follow progress with:

```bash
docker compose --env-file docker/.env -f docker/compose.yml logs -f updater
```

The updater has access to the Docker socket and therefore has host-level Docker
privileges. Only enable it on a trusted host. The application container itself
does not receive the socket. The updater uses this Compose file and does not
modify it; apply changes to deployment files separately when upgrading across
releases that require them. Back up PostgreSQL and `app-storage` before updating.

For manual updates, leave the updater disabled and run:

```bash
docker compose --env-file docker/.env -f docker/compose.yml pull app worker scheduler migrate
docker compose --env-file docker/.env -f docker/compose.yml up -d --wait app worker scheduler
```

## Common commands

```bash
# Run an Artisan command
docker compose --env-file docker/.env -f docker/compose.yml run --rm \
  --entrypoint php app artisan about

# Follow application, worker, and scheduler logs
docker compose --env-file docker/.env -f docker/compose.yml logs -f app worker scheduler

# Stop containers without deleting data
docker compose --env-file docker/.env -f docker/compose.yml down

# Stop containers and delete all local data
docker compose --env-file docker/.env -f docker/compose.yml down --volumes
```

## Production use

This is a starting example, not a complete hardened deployment. Before
production use:

- replace all example credentials and keep `APP_DEBUG=false`;
- configure a transactional mail provider;
- terminate HTTPS at a reverse proxy;
- back up PostgreSQL and the `app-storage` volume;
- remove the published PostgreSQL and Redis ports;
- configure monitoring and container updates.

See [`docs/deployment.md`](../docs/deployment.md) for the deployment contract.
