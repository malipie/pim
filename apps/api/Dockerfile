# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4
ARG FRANKENPHP_VERSION=1
ARG COMPOSER_VERSION=2

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-alpine AS base

ENV APP_ENV=dev \
    SERVER_NAME=":80" \
    FRANKENPHP_CONFIG="worker /app/public/index.php"

# Worker-mode safety net: even with the disciplined `EntityManager::clear()`
# cadence enforced through Shared\Application\AbstractBatchHandler,
# long-running PHP processes inevitably leak (Dunglas: "PHP was not
# designed for long-running"). FrankenPHP recycles the worker after this
# many requests so unbounded growth cannot starve the box.
ENV MAX_REQUESTS=1000

WORKDIR /app

# System packages required by Symfony + Doctrine + Postgres + tooling.
#
# DAM MVP (#438) adds Imagick + Ghostscript for the thumbnail pipeline:
#   - imagemagick / imagemagick-dev → Imagick PHP ext build
#   - ghostscript → PDF rasterisation (first-page poster) via `pdf[0]`
# `install-php-extensions imagick` compiles the PECL extension against
# imagemagick-dev, then the runtime needs imagemagick + ghostscript at
# exec time.
RUN apk add --no-cache \
        bash \
        curl \
        ghostscript \
        git \
        icu-data-full \
        icu-libs \
        imagemagick \
        oniguruma \
        unzip \
    && install-php-extensions \
        @composer-${COMPOSER_VERSION} \
        gd \
        imagick \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        redis \
        zip

COPY frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY frankenphp/php.ini /usr/local/etc/php/conf.d/app.ini
COPY frankenphp/worker.Caddyfile /etc/frankenphp/worker.Caddyfile

# Custom entrypoint that auto-seeds the dev/test database on container
# start (idempotent; calls pim:dev:ensure-seeded). See ./docker-entrypoint.sh
# for the decision tree. Prod containers (APP_ENV=prod) skip the seed
# entirely so this hook never touches production data.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# Composer optimisations: cache layer for vendor before copying source
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-interaction --no-scripts --no-progress --prefer-dist

COPY . .
RUN composer dump-autoload --classmap-authoritative \
    && composer run-script post-install-cmd --no-interaction || true

EXPOSE 80
EXPOSE 443
EXPOSE 443/udp

HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=5 \
    CMD curl -fsS http://127.0.0.1/api -o /dev/null || exit 1

# ─── Production stage (AUD-044 / W2-9) ────────────────────────────────────
# The `base` stage above pins `APP_ENV=dev` so `pnpm stack:up` boots a dev
# stack out-of-the-box (Symfony profiler, Swagger UI, verbose traces). Before
# this stage the ONLY thing flipping the image to prod was the
# `docker-compose.prod.yml` overlay's `environment: APP_ENV=prod` — so any
# build/run of the image WITHOUT that overlay (a bare `docker build` push to a
# registry, a hand-rolled `docker run`) shipped the whole app in dev mode:
# stack traces + the exception FQCN exposed to clients, the web profiler and
# Swagger UI reachable, dev-only error pages. That is an information-disclosure
# defect baked into the artifact, not just a compose concern.
#
# This stage bakes prod posture INTO the image so it is correct regardless of
# how it is run:
#   - APP_ENV=prod + APP_DEBUG=0 (no profiler, no trace/FQCN leak, opaque 500s);
#   - composer re-resolved with --no-dev (drops dev tooling: phpunit, phpstan,
#     cs-fixer, the profiler bundle's dev deps) + --classmap-authoritative so
#     the worker never hits the filesystem to resolve a class;
#   - the prod container is warmed for APP_ENV=prod.
#
# `base` is left byte-for-byte untouched — dev keeps working. docker-compose.yml
# pins `build.target: base` (dev) and docker-compose.prod.yml pins
# `build.target: prod`, so adding this trailing stage does not change which
# image a dev `docker compose build` produces.
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

# Re-resolve the autoloader without dev dependencies. The `base` stage copied
# the full source + a dev vendor tree; --no-dev prunes dev-only packages and
# --classmap-authoritative makes the classmap exhaustive (no runtime fs probe).
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --no-interaction --no-scripts --no-progress --prefer-dist \
    && composer dump-autoload --no-dev --classmap-authoritative

# Compile the prod container so the first request does not pay the warmup cost
# and so a misconfiguration fails the build, not the first live request.
RUN php bin/console cache:clear --env=prod --no-debug \
    && php bin/console cache:warmup --env=prod

# Inherit EXPOSE / HEALTHCHECK / ENTRYPOINT / CMD from `base` (FrankenPHP
# worker config + the auto-seed entrypoint, which itself skips the dev seed
# when APP_ENV=prod).
