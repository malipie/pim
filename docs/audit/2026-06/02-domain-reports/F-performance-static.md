# Domena F — Wydajność i skala (część STATYCZNA)

> Audyt adwersarski PIM przed wypuszczeniem SaaS. Cel skali: 50k SKU / 200+ atrybutów / 3 locale / 5 kanałów, sufit 200k.
> Data: 2026-06-16. Tryb: READ-ONLY. Benchmarki empiryczne p50/p95 na 50k = osobny przebieg (patrz „Luki audytu").

## Metodyka — co i jak sprawdzono

1. **Indeksy DB** — `raw/db-indexes.txt` (400 wpisów) + `raw/db-analysis-summary.txt`. Weryfikacja na żywej bazie:
   - `docker compose exec -T database psql -U pim -d pim -At -c "SELECT ... information_schema.columns WHERE table_name='objects'"` — potwierdzenie istnienia kolumn `attributes_indexed jsonb`, `completeness jsonb`, `completeness_pct smallint`, `path ltree`.
   - `SELECT indexname,indexdef FROM pg_indexes WHERE tablename='objects'` — potwierdzenie BRAKU indeksu na `objects.path` (ltree) i BRAKU GIN na `objects.attributes_indexed`.
   - `EXPLAIN SELECT id FROM objects WHERE attributes_indexed @> '{...}'::jsonb AND tenant_id=...` — potwierdzenie, że containment leci jako `Filter`, nie `Index Cond`.
   - `EXPLAIN` dla completeness w obu wariantach (JSONB path vs kolumna smallint).
2. **Geneza braku indeksów** — `grep` po `apps/api/migrations` na `gin/gist/attributes_indexed/path`. Znaleziono migrację `Version20260430092112.php` która w `up()` (linie 166–168) DROP-uje 3 indeksy `objects` i nie odtwarza ich; `down()` (linie 222–224) odtwarza, ale GIN błędnie jako btree.
3. **Paginacja** — `apps/api/config/packages/api_platform.yaml` + `CatalogObject.xml` (definicja operacji REST). Grep `paginationType/paginationViaCursor/setMaxResults/setFirstResult/OFFSET`.
4. **N+1 statycznie** — odczyt ścieżki eksportu (`SyncExportRunner`, `ExportBuilder`), repozytorium (`DoctrineCatalogObjectRepository`), completeness (`CompletenessFilter`, `RecalculateCompletenessCommand`), listener indeksu (`AttributesIndexedSyncListener`).
5. **Frontend** — `ls -lS apps/admin/dist/assets/*.js`, `vite.config.ts`, grep `React.lazy / react-window / react-virtual / tanstack-virtual / useVirtualizer`, odczyt `universal-list-page.tsx`, `excel-like-grid.tsx`, `pagination-bar.tsx`.
6. **Redis/cache** — `cache.yaml`, `framework.yaml`, grep `->get( / LockFactory / stampede / EarlyExpiration / redis`.
7. **Benchmarki** — odczyt `apps/api/src/Benchmark/BulkImportBenchmarkCommand.php` i `apps/api/src/Benchmark/Export/ExportBenchmarkCommand.php`.

## Stan danych w dev (raw/db-rowcounts.txt)
Wszystkie tabele domenowe = **0 wierszy** (objects, object_values, attributes, channels…). Jedyne dane: `audit_logs=2393`, `refresh_tokens=1`. **Konsekwencja: każdy EXPLAIN na żywej bazie pokazuje plan dla pustej tabeli — planner Postgresa wybiera dowolny indeks tenant/kind, bo koszt seq scan i tak ~0.** Statyczny audyt schematu + kodu jest miarodajny; wartości p50/p95 wymagają zaseedowania 50k (patrz luki).

---

## FINDING F-1 (CRITICAL) — Brak indeksu GIN na `objects.attributes_indexed`

**Dowód schematu:** `grep -i attributes_indexed raw/db-indexes.txt` → ZERO trafień. Na żywej bazie kolumna istnieje (`attributes_indexed | jsonb`), ale `pg_indexes` nie ma żadnego indeksu na niej.

**Dowód regresu (migracja):**
- `apps/api/migrations/Version20260428220053.php:83` — poprawnie: `CREATE INDEX objects_attributes_indexed_gin ON objects USING GIN (attributes_indexed)`.
- `apps/api/migrations/Version20260430092112.php:167` — `up()`: `$this->addSql('DROP INDEX objects_attributes_indexed_gin');` i **NIE odtwarza go w `up()`**.
- `Version20260430092112.php:223` — `down()`: `CREATE INDEX objects_attributes_indexed_gin ON objects (attributes_indexed)` — **bez `USING GIN`, czyli zwykły btree** (a btree na całym JSONB i tak nie obsługuje `@>`). To auto-generated migracja Doctrine; ORM nie zna `USING GIN`, więc diff uznał indeks za „obcy" i go usunął — nikt nie poprawił.

**Dowód planu zapytania (EXPLAIN, żywa baza):**
```
EXPLAIN SELECT id FROM objects WHERE attributes_indexed @> '{"brand":"Nike"}'::jsonb AND tenant_id='…';
Index Scan using objects_tenant_parent_idx on objects
  Index Cond: (tenant_id = '…'::uuid)
  Filter: (attributes_indexed @> '{"brand": "Nike"}'::jsonb)   <-- post-filtracja, NIE indeks
```

**Konsekwencja przy 50k:** każdy filtr atrybutowy w UI/API (`?attribute[brand]=Nike`) skanuje WSZYSTKIE wiersze tenanta (do 50k, sufit 200k) i odrzuca w pamięci. Kod aktywnie zakłada istnienie GIN:
- `AttributeFilter.php:18-22` komentarz: „a Postgres `jsonb @>` … the partial GIN index on objects.attributes_indexed answers in sub-50ms even at 50k rows (#34 benchmark target)".
- `JsonbContainsFunction.php:19` komentarz: „attributes_indexed is JSONB with a partial GIN index (#34)".
- `AttributeFilter.php:68` generuje `JSONB_CONTAINS(o.attributesIndexed, :p) = true` → `JsonbContainsFunction.php:48` → SQL `(... @> ...::jsonb)`.

To rdzeń wyróżnika produktowego (hybrid model atrybutów, CLAUDE.md pkt 4) — bez GIN search/filter atrybutowy nie skaluje.

---

## FINDING F-2 (CRITICAL) — Brak indeksu GiST na `objects.path` (ltree, drzewo kategorii)

**Dowód:** na żywej bazie `objects.path` jest typu `ltree`:
```
SELECT a.attname,t.typname … attname='path'  ->  path|ltree
```
ale `SELECT indexname,indexdef FROM pg_indexes WHERE tablename='objects' AND indexdef LIKE '%path%'` zwraca **pusty wynik** — żadnego indeksu (ani GiST, ani btree, ani partial).

**Dowód regresu:** `Version20260430092112.php:166` — `up()`: `DROP INDEX objects_path_gist_idx` oraz `:168` `DROP INDEX objects_path_btree_idx`. Odtworzone tylko w `down()` (linie 222, 224). Późniejsza migracja `Version20260606120000.php:35` dodaje GiST tylko dla NOWEJ tabeli `channel_category_nodes.path`, NIE dla `objects.path`.

**Kontrast:** `channel_category_nodes_path_gist_idx … USING gist (path)` istnieje (raw/db-indexes.txt). Drzewo kategorii produktowych (`objects` kind=category) zostało bez indeksu.

**Konsekwencja:** zapytania ltree na drzewie kategorii (`path <@ :ancestor`, `path ~ :lquery`) — np. „pokaż wszystkie produkty w gałęzi kategorii" / breadcrumbs / przenoszenie poddrzewa — wykonują seq scan po całej tabeli `objects`. Przy 50k+ obiektów i głębokim drzewie kategorii = liniowy koszt na każde rozwinięcie gałęzi.

---

## FINDING F-3 (HIGH) — Eksport: N+1 na `object_values` (i relacjach/kategoriach) per obiekt

**Dowód:** `apps/api/src/Export/Application/Builder/ExportBuilder.php`:
- `:150` `renderRow()` woła `:163 indexValuesByObject($object)`,
- `:166` `foreach ($this->values->findByObject($object) as $value)` — **jeden SELECT na `object_values` PER OBIEKT**.
- `:207` `foreach ($this->relations->findBySourceAndAttribute($object, $attribute) …)` — dodatkowy SELECT per obiekt per kolumna typu Relation/Reference.
- `:249` `resolveCategories($object)` — potencjalnie kolejny SELECT per obiekt.

**Konsekwencja przy celu PRD §11.2 (<30s, 50k SKU × 30 kolumn):** minimum 50 000 zapytań na `object_values` + do 50 000 na relacje + 50 000 na kategorie = 100k–150k roundtripów do Postgresa w jednym eksporcie. Indeks `object_values_object_idx` przyspiesza pojedyncze zapytanie, ale nie eliminuje narzutu liczby roundtripów. Builder jest świadomie repo-aware-agnostic (komentarz `:39-43`), ale brakuje batch-prefetch wartości dla całej strony keyset (`findByObjectIds(page)` zamiast `findByObject(object)`).

**Pozytyw odnotowany:** ścieżka „scope=All, masters-only" strumieniuje keysetem (`SyncExportRunner::runStreamingToFile` `:306`, `findRootObjectsAfter` `id > :afterId` + `em->clear()` co 200) i liczy total przez `COUNT(*)` (`countRootObjectsByType` `:130`) — to poprawny constant-memory wzorzec.

---

## FINDING F-4 (HIGH) — Eksport: ścieżki Selected / Filter / include_variants ładują CAŁY zbiór do pamięci

**Dowód:** `SyncExportRunner.php:104 canStream()` strumieniuje TYLKO gdy `scope=All && !includesVariants()`. Pozostałe ścieżki idą przez `resolveTargets()`:
- `:130` `ExportTargetScope::All` z włączonymi wariantami → `$this->objects->findByObjectType($objectType, $tenant)` = `DoctrineCatalogObjectRepository::findByObjectType` `:87` → `$this->findBy(['objectType'=>…])` — **ładuje WSZYSTKIE obiekty typu do tablicy w pamięci**.
- `applyVariantFanout` `:149` dodatkowo materializuje wszystkie dzieci (`findChildrenByParentIds`) i buduje `$result` jako tablicę wszystkich master+variant.

**Konsekwencja:** eksport 50k SKU z `include_variants=ON` (typowy use case przy synchronizacji do kanału z wariantami) hydratuje pełny graf encji w pamięci PHP — wprost odwrotnie do celu IMP2-2.6 (constant memory) i grozi OOM workera FrankenPHP (R-25, próg 256 MiB). Cel <30s realny tylko dla wąskiej ścieżki All+masters.

---

## FINDING F-5 (MEDIUM) — CompletenessFilter czyta z JSONB zamiast ze zindeksowanej kolumny smallint

**Dowód:** `apps/api/src/Catalog/Infrastructure/ApiPlatform/Filter/CompletenessFilter.php:72-73` generuje `JSONB_GET_NUMERIC(o.completeness, 'pct') <op> :p` — czyli czyta z JSONB `completeness->pct`.

Tymczasem schemat ma kolumnę `completeness_pct smallint` ORAZ pokrywający indeks `objects_tenant_kind_compl_idx ON objects (tenant_id, kind, completeness_pct)` (raw/db-indexes.txt). Filtr go nie wykorzystuje — funkcyjne wyrażenie na JSONB nie jest sargable na tym indeksie.

**EXPLAIN (żywa, pusta baza — oba warianty jako Filter):**
```
JSONB path:  Filter: (((completeness ->> 'pct'))::numeric > '80')
smallint:    Filter: (completeness_pct > 80)
```
Na pustej tabeli planner i tak nie sięga po composite indeks. **Confidence=needs-review**: dopiero przebieg na 50k pokaże, czy przepisanie filtra na `o.completenessPct` aktywuje range-scan po `objects_tenant_kind_compl_idx`. Rekomendacja stoi: filtr powinien czytać zindeksowaną kolumnę, nie JSONB.

---

## FINDING F-6 (MEDIUM) — Brak wirtualizacji wierszy i kolumn w gridzie list (ExcelLikeGrid)

**Dowód:** `grep react-window|react-virtual|tanstack-virtual|useVirtualizer apps/admin/src` → **ZERO trafień**; w `apps/admin/package.json` brak biblioteki wirtualizacji.
`apps/admin/src/components/catalog/excel-like-grid.tsx`:
- `:178` `<thead>` … `{columns.map(...)}` — wszystkie kolumny w DOM,
- `:190` `<tbody>` `{rows.map((row) => <tr> {columns.map(...)} </tr>)}` — pełny render wiersz × kolumna, bez windowing.

**Czynnik łagodzący (potwierdzony):** lista jest server-side paginowana — `universal-list-page.tsx` + `pagination-bar.tsx:18 PAGE_SIZE_OPTIONS = [20, 50, 100, 200]` (max 200 wierszy/stronę). Nie renderuje 50k naraz.

**Konsekwencja:** „universal list 100+ kolumn" × 200 wierszy = ~20 000+ komórek DOM na stronę, w pełni renderowanych. Przy szerokim ObjectType (200+ atrybutów) i max pageSize render/scroll/resize odczuwalnie zwalnia. Severity MEDIUM dzięki capowi 200; bez wirtualizacji kolumn pozostaje smell na szerokich typach.

---

## FINDING F-7 (LOW) — Pojedynczy chunk JS 777 KB (product-detail-page) i ciężki initial index 561 KB

**Dowód:** `ls -lS apps/admin/dist/assets/*.js`:
```
777.2 KB  product-detail-page-*.js   <-- > chunkSizeWarningLimit (700 KB, vite.config.ts:27)
560.9 KB  index-*.js                 (initial)
141.1 KB  refine-*.js
106.1 KB  radix-*.js
```
115 chunków, total 4.6 MB (suma WSZYSTKICH lazy chunków, nie initial). `vite.config.ts:35` ma `manualChunks` dla vendorów + lazy routes w `App.tsx` — code-splitting DZIAŁA.

**Konsekwencja:** initial load ≈ index 561 KB + vendory (refine 141 + radix 106 + i18n 61 + router 41 + react-query 32) ≈ ~940 KB uncompressed (gzip ~250–300 KB) — akceptowalne. Ale `product-detail-page` 777 KB to najcięższa pojedyncza trasa, przekracza próg ostrzeżenia — kandydat do dalszego podziału (np. lazy dla zakładek wariantów/relacji/asset-pickera). LOW, bo lazy-loaded i nie blokuje startu.

---

## Cache / Redis (bez findingu krytycznego — odnotowanie + needs-review)

- `apps/api/config/packages/cache.yaml`: dwa poole TagAware — `pim.modeling_cache` i `pim.permissions_cache`, oba `default_lifetime: 300`, `adapter: cache.app` (filesystem dev/test, Redis w prod przez env override). Inwalidacja tagami (ObjectFormSchemaCacheInvalidator, PermissionInvalidationListener).
- `framework.yaml:25-28`: rate-limitery na `cache.app` (in-memory/filesystem dev) — komentarz sam zaznacza „move to a shared Redis pool when multi-worker FrankenPHP exposes". W multi-worker prod filesystem/APCu rate-limit nie jest współdzielony między workerami → limity per-worker, nie globalne.
- **Stampede protection:** `grep '->get('` z callbackiem (sygnatura `CacheInterface::get` z beta/early-expiration) → ZERO trafień w `apps/api/src`. Jedyny lock to `BulkOperationLock.php` (operacje bulk, nie cache). Tag-aware poole używane prawdopodobnie przez `getItem/save` (PSR-6) bez wbudowanej ochrony przed stampede. Przy zimnym starcie / inwalidacji tagu wielu równoległych requestów może równocześnie przeliczyć ten sam form-schema/permission set. **needs-review** — wymaga potwierdzenia jak dokładnie poole są odpytywane (PSR-6 getItem vs Contracts get z callbackiem).

---

## Pozytywy zweryfikowane (chwalę tylko to co potwierdzone dowodem)

- **Cursor pagination dla list domenowych** — `CatalogObject.xml`: `paginationType="cursor"` + `paginationViaCursor` po `id` DESC dla `/api/products`, `/api/categories`, `/api/assets`, `/api/objects`; OrderById + RangeOnId zarejestrowane. Reguła architektury (cursor dla list >1000) spełniona dla głównych kolekcji. **Zero `setFirstResult/OFFSET`** na `objects` w kodzie aplikacji (poza poprawnym keyset eksportu).
- **Eksport All/masters** — keyset (`findRootObjectsAfter`) + `em->clear()` co 200 + `COUNT(*)` zamiast hydratacji do liczenia (`SyncExportRunner` `:312`, `countRootObjectsByType`).
- **RecalculateCompletenessCommand** — `toIterable()` + `em->clear()` co 200 (linie 107/119/126). Zgodne z guardrailem worker-mode.
- **AttributesIndexedSyncListener** — synchroniczny tylko dla single-edit (onFlush/postFlush), bulk opt-out na async `ObjectValuesChangedMessage`. Zgodne z CLAUDE.md pkt 4.
- **Indeksy gorących ścieżek** istnieją: `object_values_object_idx`, `object_values_attribute_idx`, `object_values_scope_uniq`, `objects_tenant_kind_idx`, `objects_tenant_type_idx`, `objects_tenant_parent_idx`, `objects_tenant_kind_compl_idx`, unikalne kody/SKU (`objects_tenant_kind_code_noncat_uniq`), `assets_tags_gin_idx` (GIN na tags), `messenger_messages` queue idx. Btree pokrycie FK/tenant jest kompletne.

---

## Luki audytu (czego NIE dało się sprawdzić statycznie i dlaczego)

1. **Liczby p50/p95 dla 50k SKU** — baza dev jest pusta (0 obiektów). EXPLAIN pokazuje plany dla pustej tabeli; rzeczywiste koszty (seq scan vs index) ujawnią się dopiero po zaseedowaniu. To osobny, empiryczny przebieg.
2. **Brak seedera 50k** — istnieją tylko `pim:benchmark:bulk-import` (insert syntetyczny, domyślnie 5000, sam czyści po sobie) i `pim:export:benchmark` (czyta istniejące dane, „demo dataset tops out around a few hundred"). NIE ma komendy generującej realistyczne 50k SKU × 200 atrybutów × 3 locale do EXPLAIN ANALYZE. Bez niego F-1/F-2/F-5 nie da się zmierzyć liczbowo.
3. **Realny rozmiar JSONB `attributes_indexed`** przy 200+ atrybutach — wpływa na koszt GIN i seq scan; nieznany bez danych.
4. **Stampede w poolach cache** — wymaga grep/odczytu konkretnych konsumentów (`EffectiveAttributeGroupResolver`, `PermissionResolver`) pod kątem PSR-6 vs Contracts get-with-callback; oznaczone needs-review.
5. **Frontend runtime** — czas renderu gridu 200×100, Lighthouse/TTI, realne czasy network nie mierzone (statyczny audyt). Wirtualizacja oceniona po kodzie, nie po profilu.
6. **Indeks composite a faktyczny plan** — czy przepisanie CompletenessFilter na kolumnę smallint aktywuje `objects_tenant_kind_compl_idx` (range) — do potwierdzenia EXPLAIN ANALYZE na 50k.

## Gotowe komendy benchmark (do uruchomienia w przebiegu empirycznym)

```bash
# Worker-memory + insert throughput (constant-memory pattern):
docker compose exec -T api php bin/console pim:benchmark:bulk-import --count=50000 --batch-size=200 --tenant=demo --keep
# (UWAGA: --keep zostawia 50k wierszy — wymaga ręcznego cleanup; bez --keep czyści po prefixie SKU)

# Export builder + repo hot path (per-row latency, ekstrapolacja do 50k):
docker compose exec -T api php bin/console pim:export:benchmark --tenant=demo --limit=50000 --chunk=1000

# Po zaseedowaniu 50k — EXPLAIN ANALYZE filtra atrybutowego (mierzy skutek braku GIN F-1):
docker compose exec -T database psql -U pim -d pim -c \
 "EXPLAIN (ANALYZE,BUFFERS) SELECT id FROM objects WHERE tenant_id=:t AND attributes_indexed @> '{\"brand\":\"Nike\"}'::jsonb;"

# EXPLAIN ANALYZE drzewa kategorii ltree (mierzy skutek braku GiST F-2):
# SELECT id FROM objects WHERE kind='category' AND path <@ 'root.electronics';
```

## Rekomendowane migracje (CREATE INDEX — do osobnego ticketu, forward migration)

```sql
-- F-1: przywrócić GIN (jsonb_path_ops jest mniejszy i wystarcza dla @>):
CREATE INDEX objects_attributes_indexed_gin ON objects USING GIN (attributes_indexed jsonb_path_ops);

-- F-2: przywrócić GiST na ltree drzewa kategorii (partial — tylko kind=category):
CREATE INDEX objects_path_gist_idx ON objects USING GIST (path) WHERE kind = 'category';
```
