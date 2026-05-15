# Feature — Eksport produktów (UI marathon notatka)

> **Status:** MVP zaimplementowane 2026-05-15 w marathonie EXP-01..EXP-16.
> **PRD source of truth:** [`../PRD/PRD-PIM-exports.md`](../PRD/PRD-PIM-exports.md).
> **Sibling features:** [`feature-imports.md`](feature-imports.md) (round-trip target), [`feature-list-advanced.md`](feature-list-advanced.md) (selection state + filter snapshot DSL).

## Co dostarczyliśmy w MVP

### Backend (EXP-01..EXP-08 + fix #607)

- **EXP-01 (#580)** — Schema (3 tabele) + entities + ORM mapping + MinIO bucket. *Already shipped by drugi agent przed marathonem; closed jako "implemented via PR #578".*
- **EXP-02 (#581, PR #597)** — POC audit IMP kontraktu (4/4 FAIL — IMP-16..19 follow-ups #602–#605).
- **fix #607** — TenantAuditCommand whitelist `export_logs` (unblock PHPUnit).
- **EXP-03 (#582, PR #606)** — `ExportBuilder` service + `ColumnResolver` + `ValueSerializer` (pipe-separated multi-value, blank cell, locale scoping).
- **EXP-04 (#583, PR #608)** — `pim:export:benchmark` Console + report.
- **EXP-05 (#584, PR #609)** — `POST /api/products/export` sync endpoint + OpenSpout XLSX + native CSV.
- **EXP-06 (#585, PR #610)** — Async `ExportJobHandler` + Mercure SSE + MinIO upload.
- **EXP-07 (#586, PR #611)** — Profiles CRUD API (5 endpoints).
- **EXP-08 (#587, PR #612)** — Sessions API (7 endpoints) + run-from-profile + download stream + rerun.

### Frontend (EXP-09..EXP-14)

- **EXP-09 (#588, PR #613)** — Foundation routes + `ExportsLayout` (tabs) + integration-hub enable.
- **EXP-10 (#589, PR #616)** — Two-pane `ColumnPicker` MVP (built-in columns; no dnd-kit yet).
- **EXP-11 (#590, PR #617)** — `ExportModal` z 4 sekcjami (kolumny, format, encoding, scope).
- **EXP-12 (#591, PR #618)** — Full-page form `/integrations/exports/new` reusing modal.
- **EXP-13 (#592, PR #614)** — Recent exports grid + 5s polling + Download/Rerun/Delete.
- **EXP-14 (#593, PR #615)** — Saved profiles grid + Run-now + Delete.

### Testy + docs (EXP-15..EXP-16)

- **EXP-15 (#594, PR #619)** — Hub smoke spec + dogfooding follow-up plan (full 5-scenariusz E2E deferred).
- **EXP-16 (#595, ten PR)** — Plan/PRD/lessons docs update.

## Follow-ups na które czekamy

- **IMP-16..IMP-19 (#602–#605)** — 4 round-trip kontrakty z IMP pipeline. Blokujące pełny E2E reimport (PRD §3.5 killer scenario).
- **EXP-11 BulkActionsToolbar integration** — dodać "Eksport" button na liście produktów.
- **EXP-11 "Save as profile" submit handler** — backend gotowy, FE incremental.
- **EXP-13 Mercure SSE wiring** — backend już publishes, FE swap z 5s polling na EventSource.
- **EXP-10 drag-and-drop reorder** — dnd-kit po a11y review.
- **Locale + channel toggles w modalu** — wymaga `useCustom(/api/tenant/locales)` fetcha.
- **target_scope=filter** — wymaga `FilterDslResolver` integration na sync/async path.
- **Cross-user audit dla Owner** — PRD §14 R-45 → Faza 1.
- **Presigned URLs dla download** — PRD §11.6 → Faza 1.

## Co warto wiedzieć przed dotykaniem

- **PRD source of truth:** `Project Plan/PRD/PRD-PIM-exports.md` (782 lines, drukarka 5-falowego brainstormingu).
- **EXP-02 audit raport:** `agent/exp-02-imp-audit.md` — 4 IMP gap'y wymagające IMP-16..19.
- **EXP-04 benchmark log:** `apps/api/agent/exp-04-perf-benchmark.md` — append-only run log z perf metrics.
- **EXP-15 dogfooding plan:** `agent/exp-15-smoke-report.md` — 5-scenariuszowy E2E plan + scope shipped w marathonie.
