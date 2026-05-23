#!/usr/bin/env bash
#
# Fast local Postgres snapshot for the PIM dev stack.
#
# Writes `.local/snapshots/pim-YYYYmmdd-HHMMSS[-label].sql.gz` using
# `pg_dump` from inside the running `database` container. Use before
# any risky test session (Foundry tests, manual data mutations, agent
# experiments) so you can roll back in seconds with pim-db-restore.sh.
#
# Usage:
#   scripts/pim-db-snapshot.sh                # timestamped file
#   scripts/pim-db-snapshot.sh pre-rbac-test  # appends -pre-rbac-test
#   scripts/pim-db-snapshot.sh --list         # show existing snapshots
#   scripts/pim-db-snapshot.sh --prune 7      # delete snapshots older than 7d

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SNAP_DIR="$REPO_ROOT/.local/snapshots"

# Pull POSTGRES_USER / POSTGRES_DB from the running container so the
# script does not drift if the operator sets non-default values in
# docker-compose env. Falls back to the compose defaults otherwise.
container_env="$(docker compose exec -T database env 2>/dev/null || true)"
DB_USER="${POSTGRES_USER:-$(printf '%s' "$container_env" | sed -n 's/^POSTGRES_USER=//p' | head -1)}"
DB_NAME="${POSTGRES_DB:-$(printf '%s' "$container_env" | sed -n 's/^POSTGRES_DB=//p' | head -1)}"
DB_USER="${DB_USER:-app}"
DB_NAME="${DB_NAME:-app}"

mkdir -p "$SNAP_DIR"

if [ "${1:-}" = "--list" ]; then
  ls -lh "$SNAP_DIR"/*.sql.gz 2>/dev/null || echo "No snapshots yet."
  exit 0
fi

if [ "${1:-}" = "--prune" ]; then
  days="${2:-7}"
  find "$SNAP_DIR" -name '*.sql.gz' -type f -mtime "+$days" -print -delete
  exit 0
fi

label="${1:-}"
ts="$(date +%Y%m%d-%H%M%S)"
if [ -n "$label" ]; then
  fname="pim-${ts}-${label}.sql.gz"
else
  fname="pim-${ts}.sql.gz"
fi
out="$SNAP_DIR/$fname"

echo "[snapshot] pg_dump $DB_NAME -> $out"

# `--clean --if-exists` so the dump replays idempotently on a clean DB.
# Pipe through gzip to keep snapshots small (Postgres dumps compress ~5x).
docker compose exec -T database pg_dump \
  -U "$DB_USER" \
  --no-owner \
  --no-privileges \
  --clean --if-exists \
  "$DB_NAME" \
  | gzip -6 > "$out"

size="$(du -h "$out" | awk '{print $1}')"
echo "[snapshot] done — $size"
echo "[snapshot] restore with: pnpm db:restore $fname"
