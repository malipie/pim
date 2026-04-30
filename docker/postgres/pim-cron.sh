#!/usr/bin/env bash
# Production backup cron entry (#106 / 0.11.11).
#
# Invoked by /etc/crontabs/postgres with one argument — `full` or `diff` —
# matching the production schedule baked into the Dockerfile:
#   * Sundays  02:00 — full backup
#   * Mon-Sat  02:00 — differential backup
#
# The first ever run (cold cluster, no prior backups) gets escalated to
# `--type=full` regardless of the argument so the repo is bootstrapped
# even if the operator brings the database up on a Wednesday.

set -euo pipefail

STANZA="${PGBACKREST_STANZA:-pim}"
TYPE="${1:-incr}"

case "$TYPE" in
    full|diff|incr) ;;
    *)
        echo "ERROR: backup type must be one of full|diff|incr (got: $TYPE)" >&2
        exit 2
        ;;
esac

# Defensive: if init never completed, run stanza-create here too. Idempotent.
pgbackrest --stanza="${STANZA}" stanza-create >/dev/null 2>&1 || true

# Cold-start protection — if no full backup exists yet, escalate.
if ! pgbackrest --stanza="${STANZA}" --output=json info 2>/dev/null \
        | grep -q '"type":"full"'; then
    echo "$(date -u +%FT%TZ) cold repo, escalating to full backup"
    TYPE=full
fi

echo "$(date -u +%FT%TZ) starting ${TYPE} backup"
pgbackrest --stanza="${STANZA}" --type="${TYPE}" backup
echo "$(date -u +%FT%TZ) ${TYPE} backup complete"
