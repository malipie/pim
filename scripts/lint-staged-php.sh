#!/usr/bin/env sh
# PIM lint-staged helper for PHP files.
#
# Runs PHP-CS-Fixer (dry-run) over the api codebase inside the api container.
# We ignore the file paths lint-staged passes us — they are host paths and the
# config-bundled Finder already targets the right scope inside /app.
#
# Falls back to a one-shot `docker run` against the pim-api image when the
# stack is not running so the hook does not block when the operator merely
# wants to commit something quick.

set -e

if [ "$#" -eq 0 ]; then
    exit 0
fi

if docker compose ps api --format json 2>/dev/null | grep -q '"State":"running"'; then
    exec docker compose exec -T api vendor/bin/php-cs-fixer fix --dry-run --using-cache=no
fi

if docker image inspect pim-api:latest >/dev/null 2>&1; then
    REPO_ROOT="$(git rev-parse --show-toplevel)"
    exec docker run --rm \
        -v "$REPO_ROOT/apps/api:/app" \
        -w /app \
        -u "$(id -u):$(id -g)" \
        --entrypoint sh \
        pim-api:latest \
        -c "vendor/bin/php-cs-fixer fix --dry-run --using-cache=no"
fi

echo "lint-staged PHP: stack is down and pim-api image is missing — start the stack with 'pnpm stack:up' and try again."
exit 1
