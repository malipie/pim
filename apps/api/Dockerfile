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
        zip

COPY frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY frankenphp/php.ini /usr/local/etc/php/conf.d/app.ini
COPY frankenphp/worker.Caddyfile /etc/frankenphp/worker.Caddyfile

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
