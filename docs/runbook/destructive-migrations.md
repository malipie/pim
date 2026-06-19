# Destructive migrations — pre-dump requirement (AUD-041)

> **Audience:** the operator running a deploy that includes a database
> migration. Read this BEFORE applying any migration listed in the
> [irreversible register](#irreversible-migration-register) below.

## TL;DR

Some PIM migrations **destroy data on `up()` and cannot restore it on
`down()`**. Their `down()` deliberately throws
`Doctrine\Migrations\Exception\IrreversibleMigration` instead of pretending to
rewind — a `migrations:migrate prev` on one of them **fails loud** rather than
silently leaving the data gone.

The only recovery for these is a **database dump taken BEFORE the migration
runs**. There is no automatic backup you can assume exists:

- the pgBackRest cron was stale for ~49 days (AUD-017), and
- the legacy `backups/pre-imp2-1.2-*.dump` referenced in old code is **not**
  guaranteed to exist.

So: **take a pre-dump yourself, every time, before a destructive migration.**

This runbook complements:

- [`docs/runbook/restore.md`](restore.md) — pgBackRest PITR walkthrough.
- [`docs/runbook/disaster-recovery.md`](disaster-recovery.md) — broader DR.
- [`docs/audit/2026-06/02-domain-reports/G-data-integrity.md`](../audit/2026-06/02-domain-reports/G-data-integrity.md) — finding G-03 / AUD-041.

---

## Why irreversible (not a fake rewind)

A `down()` that recreates the *schema* but not the *data* is worse than no
`down()` at all: the migrator reports a **successful rollback** while the data
is silently lost. An operator who runs `migrations:migrate prev` after a failed
deploy believes they rolled back cleanly — and only discovers the missing
channel currencies / locale bindings / category roots / `label.en` / original
import modes later, in production.

The fix (AUD-041) is to make every lossy `down()` call
`throwIrreversibleMigrationException(...)` with a message that names exactly
what was lost and points here. The migration **refuses** to fake a rollback;
the operator is forced to restore from the pre-dump instead.

---

## Pre-dump — BEFORE applying a destructive migration

Run this on the deploy host before `doctrine:migrations:migrate`. The dump is
taken with the **owner** role (`POSTGRES_USER`, `pim`) — the same role
migrations run under (`config/packages/doctrine_migrations.yaml` → `connection:
owner`).

```bash
# 1. Stop write traffic so the dump is a clean point-in-time snapshot.
docker compose stop api worker

# 2. Take a compressed, timestamped logical dump of the whole database.
#    -Fc = custom format (restorable with pg_restore, selective if needed).
TS=$(date -u +%Y%m%dT%H%M%SZ)
docker compose exec -T database \
  pg_dump -U "${POSTGRES_USER:-pim}" -d "${POSTGRES_DB:-pim}" -Fc \
  > "backups/pre-migration-${TS}.dump"

# 3. Sanity-check the dump is non-empty and readable.
pg_restore --list "backups/pre-migration-${TS}.dump" | head

# 4. Now apply the migration.
docker compose start api
docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction

# 5. Resume the worker.
docker compose start worker
```

> On production prefer a **pgBackRest snapshot** (`pgbackrest --stanza=pim
> --type=full backup`) over a one-off `pg_dump` — it lands in the off-site repo
> and supports PITR. The `pg_dump` above is the portable minimum that works
> even when the cron repo is stale.

Keep the dump until the migration has run cleanly in production AND you are
confident no rollback is needed (at least one successful backup cycle later).

---

## Restore — if a destructive migration must be undone

`down()` will throw `IrreversibleMigration` for these — that is expected. Do NOT
try to force the rollback. Restore from the pre-dump instead.

```bash
# 1. Stop write traffic.
docker compose stop api worker

# 2. Restore the pre-dump over the current database (owner role).
#    --clean --if-exists drops + recreates objects; -j speeds parallel restore.
docker compose exec -T database \
  pg_restore -U "${POSTGRES_USER:-pim}" -d "${POSTGRES_DB:-pim}" \
  --clean --if-exists -j 4 < "backups/pre-migration-<TS>.dump"

# 3. Re-sync the migration metadata so Doctrine knows the rolled-back version
#    is no longer applied (the dump predates the migration, so its row is gone
#    — confirm status reflects that).
docker compose start api
docker compose exec -T api php bin/console doctrine:migrations:status

# 4. Smoke the surface.
curl -sk https://pim.localhost/api | jq .
docker compose start worker
```

> If only one table was damaged, a selective restore is possible:
> `pg_restore --table=<name> ...`. For JSONB canonicalisation
> (`Version20260612210000`) the legacy shapes live across `object_values` +
> `objects.attributes_indexed`, so a whole-DB restore is the safe default.

---

## Irreversible migration register

These migrations destroy data on `up()` and **throw on `down()`**. Take a
pre-dump before applying any of them.

| Migration | Ticket | `up()` destroys | What `down()` could NOT restore |
|---|---|---|---|
| `Version20260605100000` | #1282 | drops `currencies` + `channel_currencies` | per-channel currency links (`channel_currencies` rows) |
| `Version20260606120000` | CHC-01 / #1284 | nulls `channels.category_tree_root_object_id` for all channels | the prior root-object ids |
| `Version20260607130000` | #1316 | collapses `channels.label` `{pl,en,…}` → scalar `name` | every non-`pl` label key (`en`, …) |
| `Version20260607140000` | #1318 | drops `channel_locales` | channel↔locale bindings (`channel_locales` rows) |
| `Version20260612210000` | IMP2-1.2 / #1464 | rewrites legacy `{value}` JSONB → canon, in place | the legacy `{value}` envelopes (object_values + attributes_indexed) |
| `Version20260612230000` | IMP2-1.3 / #1465 | remaps per-row `import_profiles.mode` (MERGE/INCREMENT/DELETE → UPSERT, ADD → CREATE) | the original per-row import modes |

For each of these, `down()` calls `throwIrreversibleMigrationException(...)`
with a message naming the lost data and linking here. The structural part of
some reverses (e.g. dropping the new `channel_category_nodes` table) *would* be
reversible — but because `up()` also destroyed data the partial rewind would be
a false round-trip, so the whole `down()` is treated as irreversible.

### Reversible migrations (for contrast)

The vast majority of PIM migrations are pure-structure (add/drop column, add
index, create table with no data backfill) and round-trip cleanly. Their
`down()` is a real inverse and `migrations:migrate prev` is safe. Only the six
data-bearing migrations above require a pre-dump.

---

## CI guard

`apps/api/tests/Integration/Migration/DestructiveMigrationDownTest.php`
asserts, on a fresh schema-built test DB, that:

- each irreversible migration's `down()` throws `IrreversibleMigration` (it does
  NOT silently "succeed" while losing data), and
- a representative reversible migration's `down()` does NOT throw (so the test
  is not just asserting "everything throws").

This locks the AUD-041 contract: a future edit that turns one of these `down()`
methods back into a fake schema-only rewind fails CI.
