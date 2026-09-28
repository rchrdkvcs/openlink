# Openlink

Openlink is a self-hosted URL management application for personal and team use. It manages short links, domains, QR codes, access rules, and analytics across multiple workspaces in one installation.

> [!NOTE]
> Openlink is under active development. Review each release before updating a production installation.

## Features

- Short links on the instance domain or verified custom domains
- Workspaces, members, folders, and tags
- Scheduled, expiring, password-protected, and visit-limited links
- Customizable, trackable QR codes
- Privacy-conscious link and QR code analytics
- Email/password and two-factor authentication, with optional Google and Discord sign-in
- User API tokens and an HTTP API

## Stack

- Laravel
- Laravel Octane with FrankenPHP
- Inertia.js
- Vue 3
- TypeScript
- Tailwind CSS
- shadcn-vue
- PostgreSQL
- Redis
- Docker production image

## Documentation

- [Domain language](./CONTEXT.md)
- [Product scope](./docs/product-scope.md)
- [Functional specification](./docs/functional-spec.md)
- [Technical specification](./docs/technical-spec.md)
- [Security and privacy](./docs/security-and-privacy.md)
- [HTTP API](./docs/api.md)
- [Deployment](./docs/deployment.md)
- [Architecture decisions](./docs/adr)

## Quick Start with Docker

The example Compose stack runs Openlink, PostgreSQL, Redis, and a queue worker.
Create `docker/.env` and generate its key using the instructions for your shell
in [`docker/README.md`](./docker/README.md), then start the stack:

```bash
docker compose --env-file docker/.env -f docker/compose.yml up -d
```

Open `http://localhost:8080`. See [`docker/README.md`](./docker/README.md) for configuration and operational commands.

## Local Development

Requirements: PHP 8.4+, Composer 2, Node.js 24, pnpm 12.6.0, and Docker
Compose v2.

Prepare `.env`, start local PostgreSQL and Redis containers, install
dependencies, run migrations, and build the frontend:

```bash
composer run setup
```

Setup preserves an existing `.env` and application key. PostgreSQL and Redis
data live in Docker volumes. To stop the containers without deleting data:

```bash
docker compose -f docker/compose.dev.yml down
```

If you already run PostgreSQL and Redis outside Docker, configure their
addresses in `.env` and follow the manual setup steps in
[`CONTRIBUTING.md`](./CONTRIBUTING.md) instead.

Run the local PHP development server, queue worker, and Vite:

```bash
composer run dev
```

The app runs at `http://localhost:8000`. Use `http://localhost:8000/<slug>` when testing links created on the default `localhost` domain. The local PHP server works with native Windows PHP; the production Docker image continues to use Octane / FrankenPHP.

The first registered account becomes the instance admin. With the default
`log` mailer, email verification and password reset links are written to
`storage/logs/laravel.log`.

Set `APP_HOST` to the hostname that should render the authenticated application UI. Domains added inside Openlink are redirect-only domains; they can point to the same Laravel app, but their paths are resolved as short URL slugs instead of app routes.

Run verification:

```bash
composer run lint
composer run test
pnpm run check
pnpm run build
```

## Contributing

Contributions are welcome. Read [`CONTRIBUTING.md`](./CONTRIBUTING.md) before opening an issue or pull request. Security issues must be reported according to [`SECURITY.md`](./SECURITY.md).

## License

Openlink is released under the [MIT License](./LICENSE).
