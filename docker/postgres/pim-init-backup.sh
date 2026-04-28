#!/usr/bin/env bash
# One-off init: wait for postgres to accept connections, then idempotently
# create the pgBackRest stanza and trigger an initial full backup. Subsequent
# backups are handled by the hourly cron entry in /etc/crontabs/postgres.
#
# This runs as root from start-pim.sh and drops to postgres for any pgBackRest
# command (pgBackRest enforces "must run as the postgres user").

set -euo pipefail

STANZA="${PGBACKREST_STANZA:-pim}"
LOG=/var/log/pgbackrest/init.log
mkdir -p "$(dirname "${LOG}")"
chown postgres:postgres "$(dirname "${LOG}")" || true

log() {
    printf '[%s] pim-init-backup: %s\n' "$(date -u +%FT%TZ)" "$*" | tee -a "${LOG}"
}

log "waiting for postgres to accept connections"
for _ in $(seq 1 60); do
    if su -s /bin/sh postgres -c "pg_isready -h /var/run/postgresql -p 5432 -d ${POSTGRES_DB:-pim}" >/dev/null 2>&1; then
        log "postgres is ready"
        break
    fi
    sleep 2
done

if ! su -s /bin/sh postgres -c "pg_isready -h /var/run/postgresql -p 5432 -d ${POSTGRES_DB:-pim}" >/dev/null 2>&1; then
    log "postgres did not become ready within timeout — aborting init"
    exit 0
fi

# stanza-create is idempotent: returns 0 if the stanza already exists.
log "running pgbackrest stanza-create --stanza=${STANZA}"
if ! su -s /bin/sh postgres -c "pgbackrest --stanza=${STANZA} stanza-create" >>"${LOG}" 2>&1; then
    log "stanza-create failed — see ${LOG}; init aborted (cron will retry)"
    exit 0
fi

# Skip the initial full backup if the repo already has one. The retention
# policy will rotate older backups out automatically.
if su -s /bin/sh postgres -c "pgbackrest --stanza=${STANZA} info --output=json" 2>/dev/null \
        | grep -q '"backup":\[{'; then
    log "stanza already has at least one backup — skipping initial full"
    exit 0
fi

log "running initial full backup"
if su -s /bin/sh postgres -c "pgbackrest --stanza=${STANZA} --type=full backup" >>"${LOG}" 2>&1; then
    log "initial full backup completed"
else
    log "initial full backup failed — see ${LOG}; cron will retry hourly"
fi
