# Sync engine benchmark — consumer inbound/outbound (APIC-P5-04)

> ADR-0022, epik APIC, M5 Hardening. Profil pamięci + zapytań silnika
> synchronizacji konektora (Integration/Generic). Cel: worker mieści się w
> budżecie 256 MiB FrankenPHP dla 50k rekordów, profil O(batch) nie O(rekord),
> brak N+1 na ścieżce gorącej.

## Metodyka

`apps/api/tests/Integration/Integration/Generic/SyncMemoryBenchmarkTest.php`
(grupa `import-benchmark`, ten sam dedykowany krok CI co import/export memory
gate — `APP_DEBUG=0`, podniesiony `memory_limit`, poza domyślnym suitem).

- **inbound**: syntetyczny remote stronicuje N rekordów po `PAGE_SIZE=500`
  (O(strona) pamięci po stronie fixture), przepuszczone przez prawdziwy
  `InboundSyncRunner` → `PaginatedFetcher` → `RecordMapper` →
  `InboundRecordWriter` (Provenance::Integration) do realnego Postgresa.
- **outbound**: N obiektów katalogu z wartościami global, przepuszczone przez
  `OutboundSyncRunner` → `ExportOutboundRecordReader` → `PayloadBuilder` →
  fake `RemoteRequester` (każdy push = 201).
- Liczba rekordów: `SYNC_BENCH_ROWS` (domyślnie 2 000 — dowodzi płaskiego
  profilu przy szybkim kroku CI; podnieś do 50 000 dla pełnego przebiegu).
- Mierzone: `memory_get_peak_usage(true)` po `memory_reset_peak_usage()` tuż
  przed przebiegiem (fixture build nie zanieczyszcza pomiaru — EM czyszczony
  przed startem). Bramka: peak < 256 MiB **i** delta < 200 MiB.

## Wynik — pamięć

| Leg | Przed P5-04 | Po P5-04 |
| --- | --- | --- |
| inbound | O(rekord) — `SyncRunLog` + upsertowane `CatalogObject`/`ObjectValue` akumulowane w identity map przez cały przebieg | **O(strona)** — `flush()`+`clear()` po każdej stronie, reload `SyncRun`/`SyncBinding` + re-point `TenantContext` |
| outbound | O(rekord) — `SyncRunLog` + odpytane wartości każdego obiektu akumulowane | **O(batch)** — `clear()` co `CLEAR_EVERY=200` rekordów |

**Root cause** (naprawiony w P5-04): oba runnery robiły `flush()` per rekord, ale
**nigdy `clear()`** — wbrew intencji udokumentowanej w docbloku `PaginatedFetcher`
(*„the sync handler consumes one page at a time and clears the Doctrine unit of
work between batches"*). Bez czyszczenia każdy long-running pull/push 50k SKU
zabiłby worker na OOM (ta sama klasa błędu co `#import-oom`). Czyszczenie między
batchami przywraca higienę worker-mode (sekcja „Memory management" w `CLAUDE.md`).

## Znana pozostałość — outbound read seam (follow-up, Export context)

`ExportOutboundRecordReader::read` (kontekst **Export**, nie Integration):

1. **Materializuje pełny zbiór obiektów** per przebieg (`findByObjectType` zwraca
   tablicę, nie generator/`iterate()`) — pik proporcjonalny do liczby obiektów
   ObjectType. Dla 50k+ to dominujący koszt pamięci, którego per-chunk clear w
   runnerze nie usuwa (tablica trzyma referencje, choć clear zwalnia snapshoty
   UoW + odpytane `ObjectValue` + logi).
2. **N+1**: `globalValues()` robi `findBy(['object' => $object])` osobno dla
   każdego obiektu → 1 zapytanie/obiekt.

Docblock readera sam to flaguje („*keyset paging for 50k+ catalogs is a
follow-up*"). **Rekomendacja**: osobny ticket Export — keyset paging
(`WHERE id > :lastId ORDER BY id LIMIT N`) + batched value fetch (jedno
`WHERE object_id IN (...)` na stronę). To zdejmuje zarówno pik pełnego zbioru,
jak i N+1, dopełniając profil O(batch) po stronie outbound read. Cross-context
(Export) — poza zakresem P5-04 (Integration).

## EXPLAIN ANALYZE — kluczowe zapytania

Uruchom na realnym wolumenie (`SYNC_BENCH_ROWS=50000`, potem `psql`):

- **inbound match lookup** (`CatalogInboundRecordWriter::resolveObjectId`, native
  SQL): `objects` po `object_type_id` + `tenant_id` + JSONB match na
  `attributes_indexed`. Wymaga indeksu GIN na `attributes_indexed` (jest) —
  potwierdź `Index Scan`, nie `Seq Scan`, na powtarzanym match key.
- **outbound object scan** (`findByObjectType`): po keyset refaktorze — `Index
  Scan` po `(object_type_id, id)`; dziś `Seq Scan` całego ObjectType.
- **outbound value fetch** (`globalValues`): dziś N× `Index Scan` po `object_id`;
  po batched fetch — jedno `object_id IN (...)`.

## p95 endpointów (<300 ms)

Endpointy read silnika sync są cursor/paginated i scope'owane per-tenant
(RLS + TenantFilter); mierz pod obciążeniem (k6/ab) na 50k przebiegach:

- `GET /api/sync_runs` (+ filtry connection/binding) — lista historii.
- `GET /api/sync_run_logs` (drill-down per run) — paginowane.
- `GET /api/connections`, `GET /api/sync_bindings` — CRUD list.

Cel p95 < 300 ms wymaga indeksów na `sync_runs(binding_id, started_at)` i
`sync_run_logs(run_id)` (migracje encji P3-02/P4-01) — potwierdź keyset
pagination, nie OFFSET, dla list > 1000 (reguła „Cursor-based pagination" w
`CLAUDE.md`).

## Powiązane

- `docs/operations/connection-credentials-rotation-runbook.md` (P5-03).
- `apps/api/tests/Integration/Import/ImportMemoryBenchmarkTest.php` — wzorzec
  memory-gate, ten sam krok CI.
