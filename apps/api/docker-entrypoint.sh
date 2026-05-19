#!/bin/sh
# PIM api container entrypoint.
#
# Wraps the upstream FrankenPHP entrypoint with a dev-only auto-seed step
# so a fresh database (post `pim:db:reset`, post `docker compose down -v`,
# post big-rebase that wiped the volume) auto-loads the admin user before
# the operator hits the login form. Without this, every "DB empty" boot
# surfaces as a misleading "Nieprawidłowy e-mail lub hasło" toast.
#
# Decisions:
# - dev/test only. APP_ENV=prod skips entirely (safety: never touch prod
#   data from an entrypoint hook).
# - Best-effort: a seed failure logs a warning but does NOT block the
#   container from coming up. Operator still gets a working API and can
#   diagnose interactively.
# - DB readiness is gated by compose `depends_on: database:
#   service_healthy`, so the seed runs against an up DB. The Symfony
#   command also has its own retry-on-DBAL-error path.
#
# This file is COPYed into /usr/local/bin/docker-entrypoint.sh in the
# Dockerfile and made the container ENTRYPOINT.

set -e

# Mirror the upstream behaviour from /usr/local/bin/docker-php-entrypoint:
# expand `-f flag` style args into a full `frankenphp run …` invocation.
if [ "${1#-}" != "$1" ]; then
    set -- frankenphp run "$@"
fi

# Run the seed guard only on dev / test, and never in CI — Playwright's
# workflow drives migrations + fixtures explicitly via `bin/console
# doctrine:migrations:migrate` after the container is up, and the seed
# guard's `pim:db:reset --with-fixtures` would race against that step
# (it creates the schema first, then the workflow's `migrations:migrate`
# trips "relation already exists"). GitHub Actions exports CI=true on
# every job; honour it as the universal "don't auto-mutate the DB" flag.
if [ "${CI:-}" != "true" ] && { [ "${APP_ENV:-dev}" = "dev" ] || [ "${APP_ENV:-dev}" = "test" ]; }; then
    if [ -x /app/bin/console ]; then
        echo "[entrypoint] Running pim:dev:ensure-seeded (env=${APP_ENV:-dev})"
        php /app/bin/console pim:dev:ensure-seeded --quiet-when-noop --no-interaction \
            || echo "[entrypoint] WARN: ensure-seeded failed; api will still start. Run 'docker compose exec api bin/console pim:dev:ensure-seeded' manually to diagnose."
    fi
fi

exec "$@"
