# Contributing to Openlink

Thank you for helping improve Openlink.

## Before you start

- Search [existing issues](https://github.com/rchrdkvcs/openlink/issues) before
  opening a new one.
- Use the bug report or feature request form when creating an issue.
- For a substantial feature or behavior change, open an issue before writing
  code so the approach can be agreed on.
- Do not open public issues for vulnerabilities. Follow
  [`SECURITY.md`](./SECURITY.md) instead.
- Follow the [`CODE_OF_CONDUCT.md`](./CODE_OF_CONDUCT.md) in all project spaces.

## Development setup

Requirements:

- PHP 8.4 and Composer 2
- Node.js 24 and pnpm 11
- PostgreSQL 17
- Redis 7
- FrankenPHP, used through Laravel Octane

Install and configure the application:

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Set the PostgreSQL and Redis connection values in `.env`, then start the
application, queue worker, and Vite development server:

```bash
composer run dev
```

The application is available at `http://localhost:8000`.
With the default `log` mailer, verification and password-reset links are
written to `storage/logs/laravel.log`.

The Docker example can also run the entire stack. See
[`docker/README.md`](./docker/README.md).

## Making changes

- Keep pull requests focused on one problem.
- Follow the existing Laravel, Vue, and TypeScript conventions.
- Add or update tests for behavior changes.
- Update user, API, deployment, or domain documentation when behavior changes.
- Record durable architecture decisions as an ADR in [`docs/adr`](./docs/adr).
- Never commit secrets, generated assets, dependencies, IDE settings, or local
  environment files.

Run all checks before opening a pull request:

```bash
composer validate --strict
composer run lint
composer run test
pnpm run check
pnpm run build
```

PHP formatting can be applied with `composer run format`; frontend formatting
can be applied with `pnpm run format`.

## Commits and pull requests

Write concise, imperative commit messages. Conventional Commit prefixes such as
`feat:`, `fix:`, `docs:`, and `chore:` are encouraged.

In the pull request:

- explain the problem and the chosen solution;
- link the related issue;
- describe how the change was tested;
- include screenshots or recordings for visible UI changes;
- call out migrations, configuration changes, or breaking changes.

By contributing, you agree that your contribution is licensed under the
project's [MIT License](./LICENSE).
