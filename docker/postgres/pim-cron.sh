#!/usr/bin/env bash
# Hourly cron entry — runs as the postgres user via /etc/crontabs/postgres.
#
# Sprint 0 stub uses pgBackRest's default backup type (incremental → first
# call escalates to full). Production schedule (weekly full + daily diff +
# 5-min WAL) lands in 0.11.11.

set -euo pipefail

STANZA="${PGBACKREST_STANZA:-pim}"

# Defensive: if init never completed, run stanza-create here too. Idempotent.
pgbackrest --stanza="${STANZA}" stanza-create >/dev/null 2>&1 || true

pgbackrest --stanza="${STANZA}" backup
