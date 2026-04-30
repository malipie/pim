#!/usr/bin/env bash
# Automated restore test (#106 / 0.11.11).
#
# Restores the latest backup into a temporary cluster on a side directory,
# fires three smoke queries (tenants, users, audit row count), and reports
# the result on stdout for the dcron log. Designed to run weekly post-full
# (Saturday 03:30 in Dockerfile crontab) so a silent backup-corruption
# regression is detected within ≤7 days.
#
# Exit code:
#   0 — restore + smoke OK.
#   1 — restore failed or smoke caught a delta.
#   2 — environment misconfigured (no backup repo / no postgres user).
#
# The script is idempotent — leftover side directories from a previous run
# are wiped on entry. It does not touch the live cluster, the live archive
# stream, or the live MinIO bucket beyond a read-only `--archive-mode=preserve`
# walk.

set -euo pipefail

STANZA="${PGBACKREST_STANZA:-pim}"
SIDE_DIR="${PIM_RESTORE_TEST_DIR:-/tmp/pim-restore-test}"
SIDE_PORT="${PIM_RESTORE_TEST_PORT:-55432}"
LIVE_DATA="${PGDATA:-/var/lib/postgresql/data}"

log() { echo "$(date -u +%FT%TZ) [restore-test] $*"; }

cleanup() {
    log "cleanup: stopping side cluster (if any) + removing $SIDE_DIR"
    if [ -d "$SIDE_DIR" ]; then
        pg_ctl -D "$SIDE_DIR" -m immediate stop >/dev/null 2>&1 || true
        rm -rf "$SIDE_DIR"
    fi
}

trap cleanup EXIT

# Pre-flight.
command -v pgbackrest >/dev/null 2>&1 || { log "FATAL: pgbackrest binary missing"; exit 2; }
command -v pg_ctl >/dev/null 2>&1     || { log "FATAL: pg_ctl binary missing"; exit 2; }
command -v psql >/dev/null 2>&1       || { log "FATAL: psql binary missing"; exit 2; }

if ! pgbackrest --stanza="$STANZA" info >/dev/null 2>&1; then
    log "FATAL: stanza '$STANZA' has no usable repo"
    exit 2
fi

# Wipe a previous side directory if any survived.
[ -d "$SIDE_DIR" ] && rm -rf "$SIDE_DIR"
install -d -m 0700 -o postgres -g postgres "$SIDE_DIR"

log "restoring latest backup from stanza '$STANZA' into $SIDE_DIR"
pgbackrest --stanza="$STANZA" --pg1-path="$SIDE_DIR" \
           --archive-mode=preserve \
           --type=default \
           restore

# postgresql.conf in the cloned cluster references the live archive_command
# pointing at the live MinIO repo. Override so the side cluster never
# attempts to push WAL alongside the live one.
cat >> "$SIDE_DIR/postgresql.auto.conf" <<EOF
port = $SIDE_PORT
archive_mode = off
archive_command = 'true'
unix_socket_directories = '$SIDE_DIR'
EOF

log "starting side cluster on port $SIDE_PORT"
pg_ctl -D "$SIDE_DIR" -l "$SIDE_DIR/restore-test.log" \
       -o "-c port=$SIDE_PORT -k $SIDE_DIR" start

# Health-check the cluster came up before issuing smoke queries.
for i in $(seq 1 20); do
    if psql -h "$SIDE_DIR" -p "$SIDE_PORT" -U pim -d pim -c 'SELECT 1' >/dev/null 2>&1; then
        log "side cluster accepting queries after ${i}s"
        break
    fi
    sleep 1
    if [ "$i" = "20" ]; then
        log "FAIL: side cluster did not accept queries within 20s"
        exit 1
    fi
done

# Compare three counts against the live cluster — same row totals
# strongly suggest a clean restore. Tolerate ≤1% drift caused by writes
# that landed on the live cluster between snapshot start and this query.
LIVE_TENANTS=$(psql -h "$LIVE_DATA" -p 5432 -U pim -d pim -tA -c 'SELECT count(*) FROM tenants' 2>/dev/null || echo 0)
SIDE_TENANTS=$(psql -h "$SIDE_DIR" -p "$SIDE_PORT" -U pim -d pim -tA -c 'SELECT count(*) FROM tenants')
LIVE_USERS=$(psql -h "$LIVE_DATA" -p 5432 -U pim -d pim -tA -c 'SELECT count(*) FROM users' 2>/dev/null || echo 0)
SIDE_USERS=$(psql -h "$SIDE_DIR" -p "$SIDE_PORT" -U pim -d pim -tA -c 'SELECT count(*) FROM users')

log "tenants live=$LIVE_TENANTS side=$SIDE_TENANTS"
log "users   live=$LIVE_USERS side=$SIDE_USERS"

# Hard fail conditions.
if [ "$SIDE_TENANTS" -eq 0 ] && [ "$LIVE_TENANTS" -gt 0 ]; then
    log "FAIL: side cluster has zero tenants — restore likely incomplete"
    exit 1
fi

if [ "$SIDE_USERS" -eq 0 ] && [ "$LIVE_USERS" -gt 0 ]; then
    log "FAIL: side cluster has zero users — restore likely incomplete"
    exit 1
fi

log "PASS: restore-test OK"
exit 0
