# Openlink

Openlink is a self-hosted URL management application for personal and team use. It manages short links, domains, QR codes, access rules, and analytics across multiple workspaces in one installation.

## Product Direction

Openlink is built for people and teams who need a clean alternative to Bitly or TinyURL while keeping control of their own domains and data. The first version focuses on reliable short link management, verified domains, QR code tracking, folder-based permissions, and useful aggregated analytics.

## Stack

- Laravel
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
- [Roadmap](./docs/roadmap.md)
- [Architecture decisions](./docs/adr)

## Local Development

Start or provision PostgreSQL and Redis, then configure the connection values in `.env`.

Install dependencies and prepare the app:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Run the development server, queue worker, and Vite:

```bash
composer run dev
```

The app runs at `http://127.0.0.1:8000`. Use `http://localhost:8000/<slug>` when testing links created on the default `localhost` domain.

Set `APP_HOST` to the hostname that should render the authenticated application UI. Domains added inside Openlink are redirect-only domains; they can point to the same Laravel app, but their paths are resolved as short URL slugs instead of app routes.

Run verification:

```bash
php artisan test
npm run build
```

## Status

Openlink has an initial Laravel/Inertia MVP implementation in progress, including workspaces, domains, short links, public resolution, protected links, QR code export, aggregated analytics, and 2FA.
