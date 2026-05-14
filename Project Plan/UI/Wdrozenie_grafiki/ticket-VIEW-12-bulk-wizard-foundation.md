# VIEW-12 — Bulk wizard fundament + bulk_sessions + bulk_logs + set_attribute E2E

## 1. Kontekst i cel

Foundation ticket dla wszystkich bulk operations (VIEW-13..VIEW-17). Pierwsza akcja `set_attribute` wired end-to-end: 3-step wizard (Action → Configure → Preview diff) → `BulkSession` zapisana w DB → sync handler (`<100` produktów) lub async via Symfony Messenger (`>100`) → Mercure SSE progress → `bulk_logs` zapisane per object change (rollback recipe).

PRD §5.1, §11.3, §13.1. Wzorzec PR #534 (FrankenPHP worker memory + `EntityManager::clear()` per chunk).

## 2. Mockup

`list-v2-overlays.jsx` l. 151-360 — `BulkWizard` 3-step modal:
- Step 1: attribute picker + mode (Set/Clear/Append/Remove) + value input + locked attrs warning
- Step 2: locale + channels + async info
- Step 3: stat grid (target/change/skip lock/error) + sample 5 + value distribution before/after

## 3. Zakres FE

**Nowe komponenty:**
- `components/catalog/bulk-wizard/wizard.tsx` (~200 LOC) — 3-step shell.
- `components/catalog/bulk-wizard/step-1-action.tsx` (~120 LOC).
- `components/catalog/bulk-wizard/step-2-config.tsx` (~80 LOC).
- `components/catalog/bulk-wizard/step-3-preview.tsx` (~160 LOC).
- `lib/mercure/use-bulk-progress.ts` (~80 LOC) — SSE subscription.
- `components/catalog/bulk-progress-banner.tsx` (~60 LOC) — sticky progress.

## 4. Zakres BE

**Nowe encje:**
- `BulkSession` (`tenant_id`, `user_id`, `action_type`, `target_object_ids UUID[]`, `target_count/success_count/skipped_count/error_count`, `action_payload JSONB`, `rollback_available_until`, `rolled_back_at`, `source` enum manual|cmd_k_agent, `cmd_k_command TEXT`, timestamps).
- `BulkLog` (`bulk_session_id`, `object_id`, `attribute_id` nullable, `old_value JSONB`, `new_value JSONB`, `level` info|warning|error, `message`).

**Migracja:**
- CREATE TABLE `bulk_sessions` + `bulk_logs` z indeksami.
- ALTER `catalog_objects` ADD `bulk_session_id UUID REFERENCES bulk_sessions(id)` + partial index.

**Endpointy:**
- `POST /api/products/bulk-actions/preview` — sync, returns sample 5 + aggregate (target/success/skipped/error counts) + value distribution.
- `POST /api/products/bulk-actions/{action_type}` — sync <100, async Messenger >100. Returns `{session_id}`. Mercure topic `bulk-operations.{session_id}` + `bulk-operations.{user_id}`.

**Worker:**
- `BulkSetAttributeHandler` — chunk N=200, `EntityManager::clear()` per chunk (FrankenPHP worker memory rule).
- Provenance: każdy write `provenance='bulk'` + `provenance_meta.bulk_session_id`.

## 5. Sub-tasks (najkrótszy MVP path)

### Minimum viable (cuts deferred do follow-ups):
- [ ] Migracja bulk_sessions + bulk_logs + catalog_objects.bulk_session_id (idempotent)
- [ ] Encje BulkSession + BulkLog + repository (Doctrine)
- [ ] Sync handler dla `set_attribute` (<100 SKU pierwsze; >100 async w follow-up VIEW-12.1)
- [ ] Preview endpoint (sample 5 + counts)
- [ ] FE wizard shell + 3 steps z basic UX
- [ ] Tests: PHPUnit BulkSession entity + ApiTestCase preview + happy path

### Deferred to follow-ups (VIEW-12.1+):
- [ ] Async Messenger handler (sync wystarczy dla MVP < 100 SKU; >100 follow-up)
- [ ] Mercure SSE progress (follow-up)
- [ ] Per-attribute lock check (VIEW-18)
- [ ] Rollback executor (VIEW-17)

## 6-10. ADR-014 (bulk_sessions + rollback semantics) — dorzucony razem z VIEW-17.
