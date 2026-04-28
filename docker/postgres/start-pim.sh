#!/usr/bin/env bash
# Custom entrypoint wrapper for the PIM database image.
#
# Responsibilities (in order):
#   1. Launch busybox dcron in the background so the hourly /etc/crontabs/postgres
#      schedule can run pgBackRest backups under the postgres user.
#   2. Launch pim-init-backup.sh in the background. It waits until postgres is
#      accepting connections, then idempotently creates the stanza and triggers
#      an initial full backup. Backgrounded so it never blocks postgres start-up.
#   3. Hand control over to the upstream `docker-entrypoint.sh postgres ...`
#      which performs initdb on first run and then exec's postgres.

set -euo pipefail

# dcron logs to stderr; -L /dev/stderr keeps everything visible in `docker logs`.
crond -b -L /dev/stderr

# Background init: wait + stanza-create + initial backup. Survives postgres
# restarts because of the cron-based hourly schedule, but on first run we want
# the repo populated immediately so the restore test can run any time.
/usr/local/bin/pim-init-backup.sh &

exec docker-entrypoint.sh "$@"
