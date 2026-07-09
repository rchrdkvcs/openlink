FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan ./artisan
RUN mkdir -p bootstrap/cache \
    && composer dump-autoload --no-dev --classmap-authoritative


FROM node:24-alpine AS assets

WORKDIR /app

RUN corepack enable && corepack prepare pnpm@11.7.0 --activate

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile --ignore-scripts

COPY app ./app
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
COPY resources ./resources
COPY public ./public
COPY components.json postcss.config.js tailwind.config.js tsconfig.json vite.config.js ./
RUN pnpm run build


FROM dunglas/frankenphp:1-php8.4-alpine AS production

WORKDIR /app

RUN install-php-extensions \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    zip

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_STACK=stderr \
    SERVER_NAME=:8080

COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources/views ./resources/views
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data storage ./storage
COPY --chown=www-data:www-data artisan composer.json composer.lock ./
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /app /data/caddy /config/caddy

USER www-data

EXPOSE 8080

ENTRYPOINT ["php", "artisan", "octane:frankenphp"]
CMD ["--host=0.0.0.0", "--port=8080"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/up || exit 1
