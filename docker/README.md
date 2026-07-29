# Docker example

This Compose file runs a local evaluation stack with:

- the Openlink application on port `8080`;
- a Laravel queue worker;
- the Laravel scheduler;
- a one-shot migration container;
- PostgreSQL 17;
- Redis 7.

## Start

From the repository root:

```bash
cp docker/.env.example docker/.env
key="base64:$(openssl rand -base64 32)"
sed -i.bak "s|^APP_KEY=.*|APP_KEY=$key|" docker/.env && rm docker/.env.bak

docker compose --env-file docker/.env -f docker/compose.yml up --build
```

Open `http://localhost:8080`. The first registered account becomes the instance
administrator. The example uses Laravel's `log` mailer; retrieve email
verification and password-reset links from the application logs:

```bash
docker compose --env-file docker/.env -f docker/compose.yml logs app
```

The stack exposes PostgreSQL and Redis to the host for local inspection. Remove
those `ports` entries before using this example on an internet-facing host.

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
