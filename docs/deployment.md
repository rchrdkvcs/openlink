# Deployment

Openlink ships a production `Dockerfile`. The image runs Laravel Octane with
FrankenPHP on port `8080`. PostgreSQL and Redis are required.

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

## Release deployment

This repository currently uses Coolify for its hosted instance. Publishing the
latest stable GitHub Release triggers `.github/workflows/deploy.yml`, which
calls a Coolify deployment webhook.

Configure these repository secrets:

- `COOLIFY_WEBHOOK`
- `COOLIFY_TOKEN`

Drafts, prereleases, and releases that are not marked as latest are not deployed.
