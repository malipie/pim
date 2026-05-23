#!/usr/bin/env bash
#
# Restore a PIM dev DB snapshot taken by pim-db-snapshot.sh.
#
# Usage:
#   scripts/pim-db-restore.sh                       # restore newest snapshot
#   scripts/pim-db-restore.sh pim-20260523-180000.sql.gz
#   scripts/pim-db-restore.sh /abs/path/to/dump.sql.gz
#
# The script:
#   1. Locates the snapshot file (newest if no arg).
#   2. Asks for confirmation — this OVERWRITES the live dev DB.
#   3. Streams the gzipped dump into psql inside the `database` container.
#   4. Reminds you to restart the api container so workers reload state.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SNAP_DIR="$REPO_ROOT/.local/snapshots"

# Pull POSTGRES_USER / POSTGRES_DB from the running container so the
# script stays in sync with whatever the operator configured.
container_env="$(docker compose exec -T database env 2>/dev/null || true)"
DB_USER="${POSTGRES_USER:-$(printf '%s' "$container_env" | sed -n 's/^POSTGRES_USER=//p' | head -1)}"
DB_NAME="${POSTGRES_DB:-$(printf '%s' "$container_env" | sed -n 's/^POSTGRES_DB=//p' | head -1)}"
DB_USER="${DB_USER:-app}"
DB_NAME="${DB_NAME:-app}"

arg="${1:-}"

resolve_snapshot() {
  local a="$1"
  if [ -z "$a" ]; then
    # No arg — pick newest snapshot.
    local newest
    newest="$(ls -1t "$SNAP_DIR"/*.sql.gz 2>/dev/null | head -1 || true)"
    if [ -z "$newest" ]; then
      echo "[restore] No snapshots found in $SNAP_DIR" >&2
      exit 1
    fi
    printf '%s' "$newest"
    return
  fi
  if [ -f "$a" ]; then
    printf '%s' "$a"
    return
  fi
  if [ -f "$SNAP_DIR/$a" ]; then
    printf '%s' "$SNAP_DIR/$a"
    return
  fi
  echo "[restore] Snapshot not found: $a" >&2
  exit 1
}

snapshot="$(resolve_snapshot "$arg")"
size="$(du -h "$snapshot" | awk '{print $1}')"

cat <<EOF
[restore] About to OVERWRITE the live dev DB.
          Snapshot: $snapshot ($size)
          Target:   container=database db=$DB_NAME user=$DB_USER

Type YES to continue, anything else to abort:
EOF

read -r answer
if [ "$answer" != "YES" ]; then
  echo "[restore] Aborted."
  exit 1
fi

# Drop active connections to allow restore to replay DROP/CREATE
# statements without "database in use" errors. We do NOT drop the DB
# itself — `pg_dump --clean --if-exists` handles object-level cleanup.
docker compose exec -T database psql -U "$DB_USER" -d postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();" \
  >/dev/null

echo "[restore] streaming $snapshot into psql..."
gunzip -c "$snapshot" | docker compose exec -T database psql -U "$DB_USER" -d "$DB_NAME" --quiet --single-transaction

echo "[restore] done."
echo "[restore] Restart api to drop stale Doctrine connections:"
echo "          docker compose restart api"
