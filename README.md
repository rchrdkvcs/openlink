# Openlink

Openlink is a self-hosted application for creating, sharing, and tracking short
links and QR codes. Organise your work across personal and team workspaces while
keeping control of your domains and data.

> [!NOTE]
> Openlink is under active development. Review release notes before updating a production installation.

## What you can do

- Create short links with your own domains, passwords, schedules, and visit limits.
- Organise links with folders and tags, import/export CSV files, and manage team access.
- Route visitors with Smart Routing, test rules before saving, and restore earlier link changes.
- Design QR codes with reusable templates, check readability, and export PNG or SVG files.
- Publish pages of selected links and customise social sharing previews.
- Measure visits, scans, and conversions, save reports, and receive weekly summaries.
- Connect your tools through the HTTP API, signed webhooks, and verified mobile app links.

See the [product overview](docs/product-scope.md) for the full feature list.

## Run with Docker

You need Docker with Compose v2. The stack uses the published
`ghcr.io/rchrdkvcs/openlink` image, with PostgreSQL, Redis, a queue worker,
a scheduler, and migrations included. Images support AMD64 and ARM64.

1. Clone this repository and open its directory.
2. Copy `docker/.env.example` to `docker/.env` and set a unique `APP_KEY` using
   the [Bash or PowerShell instructions](docker/README.md#start).
3. Start the stack:

   ```bash
   docker compose --env-file docker/.env -f docker/compose.yml up -d
   ```

Open **http://localhost:8080** and create your account. The first account becomes
the instance administrator. This example writes emails to the application logs.

The default image tag, `latest`, follows stable releases. Set
`OPENLINK_IMAGE_TAG=beta` in `docker/.env` to follow beta releases, or use an exact
release tag to pin a version. Prereleases never replace `latest`.

For production, follow the [deployment guide](docs/deployment.md) to configure
HTTPS, mail, persistent storage, and backups. For operational commands and
updates, see the [Docker guide](docker/README.md).

## Local development

Openlink uses Laravel, Inertia, Vue 3, and TypeScript, with PostgreSQL and Redis.
The production image runs Octane with FrankenPHP.

Requirements: PHP 8.5.12+, Composer 2, Node.js 24, pnpm 12.14.0, and Docker
Compose v2.

```bash
composer run setup
composer run dev
```

Open **http://localhost:8000**. Setup preserves an existing `.env` and application
key. Start `php artisan schedule:work` in another terminal when working on
scheduled tasks. See [CONTRIBUTING.md](CONTRIBUTING.md) for manual setup and checks.

## Documentation

- [Documentation index](docs/README.md)
- [Product and features](docs/product-scope.md)
- [Deployment and GHCR images](docs/deployment.md)
- [Docker setup and updates](docker/README.md)
- [API and conversions](docs/api.md)
- [Contributing](CONTRIBUTING.md) · [Security reporting](SECURITY.md)

## License

[MIT](LICENSE)
