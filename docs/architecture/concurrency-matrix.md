# Concurrency matrix — bulk write paths (IMP2-2.9, ADR-0019 D11)

> Authoritative map of which write paths contend for the per-tenant
> [`BulkOperationLock`](../../apps/api/src/Shared/Application/BulkOperationLock.php)
> (PROD-05) and how a collision surfaces. Every new bulk-write entry point
> MUST be added here with its surface, or carry the lock — a CI/code-review
> gate enforced by the IMP2-2.9 ticket (#1485).

## Why one lock per tenant

A bulk job holds the Postgres connection pool, the Meilisearch index, and the
FrankenPHP worker memory budget for its whole duration. Two concurrent bulk
jobs for the **same tenant** multiply that footprint for no benefit and race on
the per-row `attributes_indexed` write path (the denormalised JSONB the GIN
index reads). The lock is keyed `bulk-op:{tenantId}` — different tenants never
contend; the same tenant gets at-most-one in-flight bulk job.

The lock is **non-blocking**: a contender does not queue, it learns the lock is
held *now* and the caller decides what to do (see "Surface" column).

## Matrix

| Write path | Entry point | Lock acquired in | Surface on collision |
|---|---|---|---|
| Import run | `ImportRunMessage` → `ImportRunHandler` | `ImportRunHandler::run()` | `BulkOperationInProgressException` → Messenger retry (5×, 30s→300s backoff) → exhaustion → `failed` transport → `ImportRunDeadLetterListener` flips `ImportSession` to **failed** with a re-run hint |
| Import rollback | `ImportRollbackService::rollback()` | service body | `BulkOperationInProgressException` (caller-translated) |
| Bulk edit (UI-02.3) | `POST /api/products/bulk-edit` → `BulkEditController::bulkEdit` | controller | **409** `ConflictHttpException` (RFC 7807) |
| Bulk actions (VIEW-12) | `POST /api/{products,objects}/bulk-actions/{actionType}` → `BulkActionsController::apply` | controller | **409** `ConflictHttpException` |
| Bulk rollback (VIEW-17) | `POST /api/bulk-sessions/{id}/rollback` → `BulkSessionsController::rollback` | controller | **409** `ConflictHttpException` |
| Backup snapshot | `BackupSnapshotMessage` → `BackupSnapshotHandler` | see note ‡ | n/a (read-consistent snapshot; does not mutate catalog rows) |

‡ The pgBackRest snapshot reads the DB at a consistent point and writes to
object storage, not to catalog rows. It does not contend for the
`attributes_indexed` write path, so it intentionally does **not** take the bulk
lock — running a backup while an import streams is safe (PITR captures the
in-progress state and replays cleanly). Listed here so the omission is a
documented decision, not an oversight.

## Handler-level note — `Catalog\Application\Bulk\*`

The 12 `Bulk*Handler` services (`BulkSetAttributeHandler`,
`BulkMultiAttributeEditHandler`, `BulkAddCategoryHandler`,
`BulkPublishChannelsHandler`, `BulkDeleteHandler`, `BulkDuplicateHandler`, …)
**do not** take the lock themselves. They are pure write executors invoked
**only** from `BulkActionsController::apply`, which already holds the lock for
the whole `match` dispatch. Pushing the lock into each handler would either
double-acquire (self-deadlock with a non-reentrant lock) or scatter the 409
surface across 12 call sites. The lock lives at the **single HTTP entry point**;
the handlers are documented here as covered-by-caller.

If a future ticket invokes any `Bulk*Handler` from a **new** entry point (an
agent tool, a scheduled job, a second controller), that entry point MUST
acquire the lock — add a row to the matrix above.

## Per-id optimistic-lock retry (rebuild path)

`RebuildAttributesIndexedHandler` (the async `attributes_indexed` + completeness
rebuild) runs **outside** the bulk lock — it is dispatched *after* the import
request returns, on the worker. A concurrent single-object UI edit can bump
`objects.version` between the rebuild's `find` and `flush`, raising
`OptimisticLockException`. The handler rebuilds + flushes **per id** (not
per-batch): on conflict it clears the unit of work, re-reads, and retries up to
3×; after exhaustion it logs a warning and skips that one object (its next
`ObjectValuesChanged` event re-queues it) — so one conflicting object never
dead-letters the whole batch (including the objects already rebuilt).

## Links

- ADR-0019 §4.4 D11 — `docs/adr/0019-import-v2-engine-contracts.md`
- `BulkOperationLock` (PROD-05) — `apps/api/src/Shared/Application/BulkOperationLock.php`
- Messenger retry policy — `apps/api/config/packages/messenger.yaml` (`async` + `import` `retry_strategy`)
