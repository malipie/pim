# PIM database image — postgres:16-alpine + pgBackRest stub for Sprint 0 (#15).
#
# Hosts both the PostgreSQL server and the pgBackRest CLI so:
#   - `archive_command` ships WAL segments straight to MinIO from inside the
#     server process (no SSH, no shared volume gymnastics).
#   - The hourly cron tick (busybox dcron) runs `pgbackrest backup --stanza=pim`
#     as the postgres user against the local data directory.
#   - The initial stanza-create + first full backup run in the background once
#     postgres is ready, so the repo is initialised the moment the stack is up.
#
# Production hardening (full + diff schedule, retention beyond 2/4, weekly
# automated restore test, cron via systemd/k8s) is deferred to ticket 0.11.11.
# See docs/runbook/restore.md and Project Plan/02-plan-projektu-pim.md §0.11.11.

FROM postgres:16-alpine

# pgBackRest pulls in libxml2/libssh; dcron is busybox-based and tiny; tini
# reaps zombies left behind by the background init helper.
RUN apk add --no-cache pgbackrest dcron tini bash

# Filesystem layout owned by the postgres user (UID 70 in alpine images).
RUN install -d -m 0750 -o postgres -g postgres /etc/pgbackrest \
 && install -d -m 0750 -o postgres -g postgres /var/log/pgbackrest \
 && install -d -m 0750 -o postgres -g postgres /var/spool/pgbackrest \
 && install -d -m 0755 -o postgres -g postgres /var/lib/pgbackrest

COPY pgbackrest.conf /etc/pgbackrest/pgbackrest.conf
RUN chown postgres:postgres /etc/pgbackrest/pgbackrest.conf \
 && chmod 0640 /etc/pgbackrest/pgbackrest.conf

COPY pim-init-backup.sh /usr/local/bin/pim-init-backup.sh
COPY pim-cron.sh /usr/local/bin/pim-cron.sh
COPY start-pim.sh /usr/local/bin/start-pim.sh
RUN chmod 0755 /usr/local/bin/pim-init-backup.sh \
                /usr/local/bin/pim-cron.sh \
                /usr/local/bin/start-pim.sh

# Hourly schedule: run pgBackRest as postgres at minute 0 of every hour. The
# dcron variant in busybox honours `/etc/crontabs/<user>` files.
RUN printf '%s\n' '0 * * * * /usr/local/bin/pim-cron.sh >> /var/log/pgbackrest/cron.log 2>&1' \
        > /etc/crontabs/postgres \
 && chmod 0600 /etc/crontabs/postgres \
 && chown postgres:postgres /etc/crontabs/postgres

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/start-pim.sh"]
CMD ["postgres"]
