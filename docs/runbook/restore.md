# Database restore runbook (Sprint 0 stub)

> Scope: PITR (point-in-time recovery) procedure for the **dev/local** PIM stack
> using pgBackRest backups stored in MinIO. The full production runbook —
> off-site replication, weekly automated restore tests, alerting, escalation
> contacts — lives in `Project Plan/02-plan-projektu-pim.md` §0.11.8 (epik 0.11)
> and is delivered alongside the production-grade pgBackRest setup in §0.11.11.

## What's wired in Sprint 0

- Custom `database` image: `postgres:16-alpine` + `pgbackrest` + `dcron`
  (`docker/postgres/Dockerfile`).
- WAL archiving: `archive_mode=on`, `archive_command='pgbackrest --stanza=pim
  archive-push %p'`. Continuous, async via `archive-async=y`.
- Repository: MinIO bucket `pim-backups`, path `/pim`. Credentials reuse
  `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` via `PGBACKREST_REPO1_S3_KEY*` env
  vars in `docker-compose.yml`.
- Schedule: hourly cron (`/etc/crontabs/postgres` inside the database
  container) runs `pgbackrest backup --stanza=pim`. Initial full backup is
  triggered by `pim-init-backup.sh` once postgres is ready.
- Retention: 2 full + 4 differential. Older backups are pruned automatically.
- Restore orchestrator: `scripts/pim-backup-restore.sh` (host).
- Acceptance test: `scripts/test-pgbackrest-restore.sh` (host).

## Day-to-day operations

### Inspect the repository

```bash
docker compose exec database pgbackrest --stanza=pim info
```

Shows backup labels, sizes, WAL ranges and which type each backup is. Fresh
stack with one full backup looks like:

```
stanza: pim
    status: ok
    cipher: none

    db (current)
        wal archive min/max (16): 000000010000000000000001 / 000000010000000000000002

        full backup: 20260428-153018F
            timestamp start/stop: 2026-04-28 15:30:18+00 / 2026-04-28 15:30:23+00
            wal start/stop: 000000010000000000000002 / 000000010000000000000002
            database size: 49.4MB, database backup size: 49.4MB
            repo1: backup set size: 6.5MB, backup size: 6.5MB
```

### Trigger a manual backup

```bash
# Incremental (default, fastest after a full)
docker compose exec database \
    su -s /bin/sh postgres -c "pgbackrest --stanza=pim backup"

# Force a full backup (rotates retention)
docker compose exec database \
    su -s /bin/sh postgres -c "pgbackrest --stanza=pim --type=full backup"
```

### Tail the cron log

```bash
docker compose exec database tail -f /var/log/pgbackrest/cron.log
```

## PITR — restoring to a specific moment

> ⚠ This destroys the current `$PGDATA`. Take a manual backup first if there
> is anything you cannot afford to lose.

### Latest backup (no replay)

```bash
pnpm backup:restore -- --type latest --no-confirm
# or
scripts/pim-backup-restore.sh --type latest
```

### Stop replay as soon as the backup is consistent (fastest)

```bash
scripts/pim-backup-restore.sh --type immediate
```

### Replay WAL up to a specific timestamp (canonical PITR)

```bash
scripts/pim-backup-restore.sh \
    --type time \
    --target "2026-04-28 12:34:56+00"
```

The orchestrator will:

1. `docker compose stop api` — releases FrankenPHP's persistent connections.
2. `docker compose stop database` — clean shutdown so the WAL is consistent.
3. `docker compose run --rm --no-deps --entrypoint /bin/sh database -c …` —
   wipes `$PGDATA` and runs `pgbackrest restore` against the existing volume.
4. `docker compose up -d --wait database api` — postgres replays WAL from the
   repo on start-up; healthchecks block until everything is back.

### Verify

```bash
docker compose exec database \
    psql -U pim -d pim -c "SELECT count(*) FROM products;"

curl -k https://pim.localhost/api/products | head
```

## Sprint 0 acceptance test

```bash
pnpm backup:test
# or
scripts/test-pgbackrest-restore.sh
```

The script:

1. Reads the baseline product count via `/api/products`.
2. Inserts 3 marker products tagged with a random prefix.
3. Forces an `incr` backup + a `pg_switch_wal()` so the markers are durable in
   the repo.
4. Deletes the markers (simulated data loss).
5. Runs `scripts/pim-backup-restore.sh --type latest --no-confirm`.
6. Re-authenticates and re-counts. Pass = post-restore count equals the
   pre-delete count.

This is the test referenced in ticket 0.0.15 DoD ("restore test passing —
produkty po restore = produkty przed dropem").

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `stanza-create` fails with `unable to load info file` | MinIO bucket missing | `docker compose up -d minio-init` (check `pim-backups` exists in MinIO) |
| `archive-push` warnings in postgres log | Stanza not initialised yet | Wait for `pim-init-backup.sh` to finish, or run `pgbackrest --stanza=pim stanza-create` manually |
| `pgbackrest backup` complains about WAL not archived | `archive-async` queue full | Check `/var/spool/pgbackrest`, ensure MinIO is reachable from the database container |
| Restore fails with `unable to remove path` | API still holds connections | The orchestrator stops `api` first; if running pgbackrest manually, `docker compose stop api` before wiping `$PGDATA` |
| Healthcheck never goes green after restore | WAL replay still in progress | `docker compose logs -f database` and wait — large WAL ranges take longer |

## Production gaps (closed in 0.11.11)

- Schedule beyond hourly stub: weekly full + daily diff + 5-min WAL.
- Retention beyond 2 full / 4 diff: 4 weeks full, 30 days diff, 7 days WAL.
- Automated weekly restore test as a CI/cron job, with Slack/Sentry alert on
  failure.
- Off-site repo replication (or S3 bucket in a second region).
- Encryption at rest for the repo (`repo1-cipher-type=aes-256-cbc`).
- Hardened credentials (per-stanza S3 user, not the MinIO root).
- Full PITR runbook with role-based escalation and decision tree for the
  different failure scenarios (data corruption / hardware / human error).

See `Project Plan/02-plan-projektu-pim.md` §0.11.8 and §0.11.11.
