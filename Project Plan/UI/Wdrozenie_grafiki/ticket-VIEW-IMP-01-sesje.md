# VIEW-IMP-01 — Sesje: overhaul `ImportSessionsView` (KPI strip + LiveSessionCard + HistoryTable)

Epik: **UI-11 — Importy redesign**. Status: in progress (start: 2026-05-11).

## 1. Kontekst i cel widoku

Zastępuje istniejący `ImportsListView` (data-table z 5 kolumnami) pełnym widokiem operacyjnego huba dla sesji importu wg designu `importy-sessions.jsx`: header z eksportem CSV + "Nowy import", hero `LiveSessionCard` dla aktywnej sesji ze `StagePipeline` + `ProgressBar` + throughput, historyTable 30 dni z filtrami statusu + search po pliku/profilu.

KPI strip (4 karty) dostarcza glance-view: aktywne sesje, dzisiejsze runs (OK/warn/err), success rate 30d z sparkline, top 3 błędy.

## 2. Mockup / źródło designu

- Plik JSX: [Zrodla/Front_Claude_Design/PIM-nowoczesny/integracje/importy-sessions.jsx](../../Zrodla/Front_Claude_Design/PIM-nowoczesny/integracje/importy-sessions.jsx) (366 linii).
- Komponenty: `ImportSessionsView`, `KpiStrip`, `LiveSessionCard` (hero + compact variants), `HistoryRow`.
- Mock data referencyjne: `data.jsx` (HISTORY[]/ACTIVE_SESSIONS[]/KPI). Operator wymaga: usunąć z produkcyjnej kopii nazwy firm.

## 3. Zakres frontend (FE)

### 3.1 Routing
- `/integrations/imports/sessions` → `<ImportSessionsView>` (zastępuje `ImportsListView` w App.tsx).

### 3.2 Komponenty
| Komponent | Plik | Główne propsy |
|---|---|---|
| `ImportSessionsView` | `sessions/ImportSessionsView.tsx` | brak |
| `KpiStrip` | `sessions/KpiStrip.tsx` | `sessions: ImportSessionRow[]`, `throughput?: ThroughputData` |
| `LiveSessionCard` | `sessions/LiveSessionCard.tsx` | `session: ActiveSession`, `throughput?: ThroughputData` |
| `HistoryTable` | `sessions/HistoryTable.tsx` | `rows: ImportSessionRow[]`, `filter`, `query`, `onSelect` |
| `HistoryRow` | `sessions/HistoryRow.tsx` | `row: ImportSessionRow` |
| `SessionsFilterPills` | `sessions/SessionsFilterPills.tsx` | `value: FilterValue`, `onChange` |

### 3.3 Data
- Refine `useList({ resource: 'import-sessions' })` — używa `/api/import-sessions` GET (NOWY endpoint, patrz §4).
- Throughput: `useCustom({ url: '/api/import-sessions/throughput?windowMin=5' })`.
- KPI calculated FE-side z listy sessions (sesje 30d → today filter, success/warn/err proporcje, top error types z `errorCount > 0` rows).

### 3.4 i18n keys
- `imports.sessions.{title, subtitle, kpi.*, live.*, history.*, filter.*, export_csv, new_import}` — dorzucam do `pl.json` + `en.json`.

### 3.5 a11y
- Filter pills jako `role="radiogroup"`, każda jako `role="radio" aria-checked`.
- HistoryTable jako natywna `<table>` z `<th scope="col">`.
- Live progress `aria-live="polite"` na text counts.
- LiveSessionCard "Anuluj" button confirms via shadcn AlertDialog.

## 4. Zakres backend (BE)

### 4.1 Nowe endpointy
| Method | Path | Request | Response | Permissions |
|---|---|---|---|---|
| GET | `/api/import-sessions` | query: `status?`, `q?`, `page?`, `pageSize?` (default 50) | Hydra: `{member: ImportSessionListItem[], totalItems: int}` | `ROLE_IMPORT_VIEW`, tenant-scoped |
| GET | `/api/import-sessions/throughput?windowMin=5` | query: `windowMin?` (default 5, max 60) | `{rowsPerSec: float, sampledAt: ISO, windowMin: int}` | `ROLE_IMPORT_VIEW`, tenant-scoped |

### 4.2 Listing DTO
`ImportSessionListItem` (Hydra resource):
- `id` (UUID), `status` (enum), `file_name`, `total_rows`, `success_count`, `error_count`, `started_at`, `completed_at`, `duration_sec` (computed), `profile_name` (joined, nullable), `profile_code` (joined, nullable), `user_email` (joined), `mode` (z ImportProfile.mode jeśli istnieje, fallback "UPSERT"), `target_object_type_code`, `rollback_until`.

### 4.3 Application services
- `ImportThroughputCalculator` — rolling window aggregation. Pobiera aktywne sesje (`status IN (running, paused)`), sumuje `successCount + errorCount` delta z `startedAt`. Throughput = `total_processed / elapsed_sec`.
- Brak schema migration — używa istniejących encji.

### 4.4 Voter / permissions
- `ImportSessionVoter` istniejący — pokrywa list + throughput (tenant + ownership).

### 4.5 NIE robimy w V01
- KPI endpoint (`/kpi`) — kalkulacja na FE z listy w MVP.
- Active sessions endpoint — używamy listing z filter `status=running`.
- WebSocket dla live throughput — używamy 5s polling.

## 5. Sub-tasks

**Backend**:
- [ ] `ListImportSessionsController` + Hydra wrapping
- [ ] `ImportThroughputController` + `ImportThroughputCalculator` service
- [ ] PHPUnit `ImportThroughputCalculatorTest` (rolling window math)
- [ ] ApiTestCase `ListImportSessionsApiTest` + `ImportThroughputApiTest`

**Frontend**:
- [ ] `ImportSessionsView` zastępuje `ImportsListView` (App.tsx route update)
- [ ] `KpiStrip` z 4 cards (active / today / rate30 / topErrors)
- [ ] `LiveSessionCard` hero variant + StagePipeline + ProgressBar
- [ ] `HistoryTable` + `HistoryRow` (9-col grid)
- [ ] `SessionsFilterPills` (5 filters: all/success/warning/error/cancelled)
- [ ] Eksport CSV button (link do `/api/import-sessions/{id}/report.csv` — per session)
- [ ] i18n keys pl + en
- [ ] Playwright spec `imports-sessions.spec.ts` (load, filter, search, klik wiersz)

**Quality gates**:
- PHPStan max, PHPUnit ≥80%, ApiTestCase
- Biome strict, TS noEmit, Vite build, Playwright zielony
- axe-core 0 violations
- composer + pnpm audit

## 6. Acceptance — funkcjonalne
- Layout pixel-perfect z designem (<2% diff).
- KPI strip pokazuje 4 cards z poprawnymi liczbami.
- LiveSessionCard renderuje dla `status=running`, ukrywa się jeśli brak aktywnych.
- HistoryTable filtruje po status + search po file_name/profile_name (debounce 300ms).
- Klik wiersz → `/integrations/imports/:id` (show page).

## 7. Acceptance — non-functional
- p95 `/api/import-sessions?page=1&pageSize=50` <300ms.
- Indeks `(tenant_id, started_at DESC)` na sessions — sprawdzę EXPLAIN ANALYZE.
- Bundle size delta <20KB gzip.
- Lighthouse a11y =100.

## 8. Smoke test scenariusze

1. Login → /integrations/imports → redirect /sessions.
2. KPI strip: 4 cards widoczne, liczby spójne z DevTools Network.
3. Filter "sukces" → tabela filtruje na status=success.
4. Search "demo" → debounce, lista się filtruje.
5. Klik wiersz → go to show page.
6. DevTools Console: brak czerwonych errorów.

## 9. Edge cases / poza zakresem
- Real-time Mercure update LiveSessionCard — używamy polling 5s w V01.
- Multi-active hero (jeśli >1 active session jednocześnie) — pokazujemy pierwszy hero + compact list pod nim (V01 minimum: tylko hero).
- Pagination cursor → MVP z page/pageSize offset.

## 10. Powiązane dokumenty
- Plan epiku UI-11: `~/.claude/plans/nifty-exploring-dolphin.md`.
- VIEW-IMP-00 (foundation): primitives już dostępne.
