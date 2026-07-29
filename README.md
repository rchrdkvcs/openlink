# Openlink

Openlink is a self-hosted URL management application for personal and team use. It manages short links, domains, QR codes, access rules, and analytics across multiple workspaces in one installation.

> [!NOTE]
> Openlink is under active development and does not have a stable release yet.

## Features

- Short links on the instance domain or verified custom domains
- Workspaces, members, folders, tags, and folder-level permissions
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

The example Compose stack runs Openlink, PostgreSQL, Redis, and a queue worker:

```bash
cp docker/.env.example docker/.env
key="base64:$(openssl rand -base64 32)"
sed -i.bak "s|^APP_KEY=.*|APP_KEY=$key|" docker/.env && rm docker/.env.bak

docker compose --env-file docker/.env -f docker/compose.yml up --build
```

Open `http://localhost:8080`. See [`docker/README.md`](./docker/README.md) for configuration and operational commands.

## Local Development

Start or provision PostgreSQL and Redis, then configure the connection values in `.env`.

Install dependencies and prepare the app:

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Run the Octane development server, queue worker, and Vite:

```bash
composer run dev
```

The app runs through Octane / FrankenPHP at `http://127.0.0.1:8000`. Use `http://localhost:8000/<slug>` when testing links created on the default `localhost` domain.

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
