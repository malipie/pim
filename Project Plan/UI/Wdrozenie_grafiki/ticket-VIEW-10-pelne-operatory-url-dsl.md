# VIEW-10 — Pełne operatory per typ atrybutu (Akeneo-grade) + URL filter DSL serializer + BE smart_preset w SearchController

## 1. Kontekst i cel widoku

Po VIEW-09 (#536 merged 2026-05-14, `4203d55`) lista produktów `/products` ma fundament cockpit operatora: 5 built-in Smart Filter Presets, push-down AdvancedFilterPanel (grid mode), FilterChipsBar + SaveAsSmartPresetModal. VIEW-09 FE resolver (`applyConditionsToFilters`) pokrywa tylko 6 known shape'ów (`brand=`, `family=`, `completeness_pct < n`, `enabled=`) i przy każdym przeciągnięciu większej liczby conditions surfaceuje toast *„partial apply (waiting for BE resolver in VIEW-10)"*.

VIEW-10 domyka tę lukę:
- **BE FilterDslResolver pełen** — 25 operatorów per typ atrybutu (PRD §5.5): `text` (8 ops), `number/metric` (8), `date` (7), `select` (6), `multiselect` (4), `boolean` (1), `relation` (5), `asset` (2). Każda condition w panelu / preset / URL działa end-to-end bez „partial apply" warningu.
- **BE UrlSerializer** — bi-directional URL params ↔ JSONB DSL: `?brand=Festo,Bosch&completeness_pct=lt:50&description.pl=empty:false` ↔ `{operator: AND, conditions: [...]}`. Single-level lossy + fallback hashowany blob `?q=<base64-json>` dla nested (przygotowuje grunt pod VIEW-09b query mode).
- **BE SearchController extension** — `?smart_preset=<slug-or-id>` + `?filter=<base64-or-flat>` query params → wczytuje preset / parsuje URL → resolver DSL → **Meilisearch filter expression** (string syntax: `brand IN [Festo, Bosch] AND completenessPct < 50`). RFC 7807 Problem Details na invalid operator/type combo + non-existent preset.
- **FE operator picker per typ** — FilterChip popover (inline edit) + AdvancedFilterPanel operator select pokazuje **tylko valid ops dla wybranego attribute type** (po VIEW-10 odblokowane: `STARTS WITH`, `CONTAINS`, `BETWEEN`, `AFTER/BEFORE` itd.).
- **FE URL serializer hook** — `useSearchParams` ↔ filter state, replace istniejący `applyConditionsToFilters` na pełen BE resolver flow.

Po VIEW-10 lista produktów ma pełen power-user filter UX bez VIEW-09b query mode (AND/OR brackets) — te wciąż w panelu jako disabled badge. VIEW-09b dochodzi w kolejnym tickecie.

## 2. Mockup / źródło designu

Baseline w VIEW-09 (PR #536) — pixel-perfect z `Produkty v2.html` zachowany. VIEW-10 dodaje funkcjonalność **bez zmian wizualnych** (operator picker w AdvancedFilterPanel używa już istniejącego `<select>`; FilterChip inline popover to upgrade z VIEW-09 chip body click → open panel).

**Pliki źródłowe pixel-perfect:**
- `Zrodla/Front_Claude_Design/PIM-nowoczesny/produkty/list-v2-overlays.jsx` l. 9-19 — `FILTER_OPS_BY_TYPE` mapa 8 typów ↔ ops list (single source of truth).
- `Zrodla/Front_Claude_Design/PIM-nowoczesny/produkty/list-v2-overlays.jsx` l. 95-101 — operator `<select>` z dynamic ops per attribute type.
- `Zrodla/Front_Claude_Design/PIM-nowoczesny/produkty/list-view-v2.jsx` l. 132-148 — FilterChip popover z operator section.

**PRD reference:** `Project Plan/PRD/PRD-PIM-list-advanced.md` §5.3 (Filter DSL JSONB format), §5.5 (walidacje per filter operator, tabela 25 ops), §7.4 (URL persistence DSL serializer), §11.2 (Meilisearch resolver).

## 3. Zakres frontend (FE)

### 3.1 Routing

Bez zmian — `/products` istnieje, push-down panel z VIEW-09 wystarcza. URL serializer rozszerza istniejący `useSearchParams` flow.

### 3.2 Komponenty (lista płaska)

#### Reuse (bez zmian)
| Komponent | Plik | Cel |
|---|---|---|
| `AdvancedFilterPanel` | `components/catalog/advanced-filter-panel.tsx` | Grid mode operator `<select>` używa nowego `FILTER_OPERATORS_BY_TYPE` enum z BE |
| `FilterChip` (TODO — w VIEW-09 chip otwiera panel; VIEW-10 dodaje inline popover) | `components/catalog/filter-chip.tsx` (**nowy**, zastępuje `filter-chips-bar.tsx` inline button) | Inline operator + value picker w popover |
| `FilterChipsBar` | `components/catalog/filter-chips-bar.tsx` | Rendering chipów (chip body click otwiera inline popover zamiast Advanced panel) |
| `SmartFilterPresetsRow` | (bez zmian) | Apply preset → pełny BE flow przez nowy URL serializer |
| `SaveAsSmartPresetModal` | (bez zmian) | Walidacja DSL pre-save delegowana do BE |
| `useCatalogSearch` | `features/catalog/search/use-catalog-search.ts` | Dodaj `smartPresetId` + `filterDsl` params, propaguj do `/api/search/products?smart_preset=...&filter=...` |
| `useSmartPresets` | `lib/filters/use-smart-presets.ts` | Bez zmian |

#### Nowe komponenty
| Komponent | Plik | LOC | Props |
|---|---|---|---|
| `FilterChip` (inline popover) | `components/catalog/filter-chip.tsx` | ~180 | `{label, type, op, value, options, onChange, onRemove}` |
| `FilterOperatorPicker` | `components/catalog/filter-operator-picker.tsx` | ~80 | `{type, value, onChange}` (renderuje valid ops jako buttony font-mono) |
| `FilterValueInput` | `components/catalog/filter-value-input.tsx` | ~120 | `{type, op, value, onChange, options}` (input wariant per type: text/number/date/select/multiselect/boolean/relation/asset) |

#### Nowe lib helpers
| Plik | LOC | Cel |
|---|---|---|
| `lib/filters/operators.ts` | ~80 | TS enum `FILTER_OPERATORS_BY_TYPE` (mirror BE), `validateOperatorForType()`, `requiresValue()`, `requiresArray()` |
| `lib/filters/url-serializer.ts` | ~140 | `dslToUrlParams(dsl): URLSearchParams`, `urlParamsToDsl(params): FilterDsl \| null`, `dslToBase64(dsl): string`, `base64ToDsl(blob): FilterDsl` |
| `lib/filters/use-filter-state.ts` | ~100 | `useFilterState({initialDsl?})` zwraca `{conditions, dsl, urlParams, setConditions, applyPreset}` z synchronizacją React Router `useSearchParams` |

#### Modyfikacje
| Plik | Zmiana |
|---|---|
| `features/catalog/products/list.tsx` | Wymień `applyConditionsToFilters` na `useFilterState` hook; `useCatalogSearch` dostaje `smart_preset` + `filter` props; usunięte FE-side mapping (BE robi) |
| `components/catalog/advanced-filter-panel.tsx` | Operator `<select>` używa `FILTER_OPERATORS_BY_TYPE[attr.type]` z `lib/filters/operators.ts`; dynamic opcje per chosen attribute |
| `components/catalog/filter-chips-bar.tsx` | Chip body click otwiera `FilterChip` inline popover (zamiast Advanced panel); usunięty `onEditChip` callback opening panel |
| `features/catalog/search/use-catalog-search.ts` | Dodaj `smartPresetId?: string`, `filterDsl?: FilterDsl` params; URL: `?smart_preset=<id>` lub `?filter=<base64>` |
| `locales/pl.json` + `en.json` | ~20 nowych kluczy dla operator names i value placeholders |

### 3.3 State management

`useFilterState` hook centralizuje:
- `conditions: FilterCondition[]` — flat array (1-level grouping w VIEW-10; nested → VIEW-09b).
- `dsl: FilterDsl | null` — derived z conditions.
- `urlParams: URLSearchParams` — derived z dsl przez `dslToUrlParams`.
- Synchronizacja z React Router `useSearchParams` (single source of truth: URL).
- `setConditions(next)` → updates URL → useCatalogSearch refetch.
- `applyPreset(preset)` → `urlParamsToDsl(preset.query)` → setConditions.

**Mutacje + invalidacje:**
- Każda zmiana conditions → URL update → `useCatalogSearch` refetch (z `?filter=<base64>` lub `?smart_preset=<id>`).
- Refetch używa nowego query key `[products, smart_preset, filter, query]`.

### 3.4 Struktura sekcji widoku (kolejność renderu)

Bez zmian z VIEW-09. Tylko zachowanie wewnętrzne komponentów się aktualizuje.

### 3.4a Mapping per typ atrybutu (BE+FE source of truth)

PRD §5.5 — pełna lista 25 ops:

| Typ | Operatory (UI labels) |
|---|---|
| `text` | `=`, `≠`, `IS EMPTY`, `IS NOT EMPTY`, `starts with`, `ends with`, `contains`, `not contains` |
| `number`, `metric` | `=`, `≠`, `>`, `<`, `≥` (`>=`), `≤` (`<=`), `between`, `IS EMPTY`, `IS NOT EMPTY` |
| `date`, `datetime` | `=`, `≠`, `after` (`>`), `before` (`<`), `between`, `IS EMPTY`, `IS NOT EMPTY` |
| `select` | `=`, `≠`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY` |
| `multiselect` | `contains` (any), `not contains`, `IS EMPTY`, `IS NOT EMPTY` |
| `boolean` | `= TRUE`, `= FALSE` |
| `relation` | `=`, `≠`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY` |
| `asset` | `IS EMPTY`, `IS NOT EMPTY` |

`FILTER_OPERATORS_BY_TYPE` BE enum + FE TS const — single source. OpenAPI snapshot eksportuje listę przy każdym BE build (FE generator typecheck w pre-merge).

### 3.5 i18n keys (~20 nowych)

```
products.advanced_filter.operators.equals
products.advanced_filter.operators.not_equals
products.advanced_filter.operators.starts_with
products.advanced_filter.operators.ends_with
products.advanced_filter.operators.contains
products.advanced_filter.operators.not_contains
products.advanced_filter.operators.between
products.advanced_filter.operators.after
products.advanced_filter.operators.before
products.advanced_filter.operators.gt
products.advanced_filter.operators.lt
products.advanced_filter.operators.gte
products.advanced_filter.operators.lte
products.advanced_filter.operators.in
products.advanced_filter.operators.not_in
products.advanced_filter.operators.is_empty
products.advanced_filter.operators.is_not_empty
products.advanced_filter.operators.is_true
products.advanced_filter.operators.is_false

products.filter_value.between_from_placeholder
products.filter_value.between_to_placeholder
products.filter_value.date_placeholder
products.filter_value.select_placeholder
```

### 3.6 a11y

- `FilterChip` inline popover → shadcn `<Popover>` daje focus trap + ESC close + aria-expanded.
- `FilterOperatorPicker` → buttons grid z `role="radiogroup"` + arrow keys navigation + Enter/Space select.
- `FilterValueInput` (between) → 2 `<input type="number">` z `aria-label="From"` + `aria-label="To"`.
- `FilterValueInput` (multiselect) → shadcn `<MultiSelect>` (istnieje) → focus + keyboard out-of-the-box.
- axe-core 0 violations serious/critical.

### 3.7 Locales (multilingual fields)

VIEW-10 dodaje **locale-scoped condition support** w UI: `description.pl IS NOT EMPTY` widoczne w chipie jako `Opis · PL IS NOT EMPTY`. FilterValueInput respektuje `attr.localizable=true` flag z BE (jeśli istnieje w attribute metadata; VIEW-10 zakłada że attribute repository wystawia tę informację).

### 3.8 Empty / loading / error states

- **No conditions**: Apply button disabled (jak w VIEW-09).
- **Invalid operator/type combo** (z BE 400 Problem Details): toast error z BE message + chip badge `INVALID` + Apply blocked.
- **Smart preset not found** (404 z BE): toast `Preset nie istnieje` + redirect do `/products` bez params.
- **URL truncated** (Caddy max 8KB): hashed blob `?q=<base64>` użyty automatycznie gdy single-level serializer przekroczy 2048 znaków.

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Request | Response | Permissions | Filtry |
|---|---|---|---|---|---|
| GET | `/api/search/products` | extended: `?smart_preset=<slug-or-id>` lub `?filter=<base64-encoded-FilterDsl>` lub `?filter[attr][op]=value` (flat) | unchanged (hits, totalHits, facetDistribution, processingTimeMs) | unchanged (`ROLE_USER`) | resolver DSL → Meilisearch filter expression |

**Errors w RFC 7807 Problem Details:**
- `400 invalid_filter_dsl` — DSL malformed (resolver validate exception, np. unsupported operator dla typu).
- `400 unsafe_identifier` — attr code zawiera unsafe znaki (potencjalny SQL/Meilisearch injection).
- `404 smart_preset_not_found` — `?smart_preset=<id>` zwraca nieistniejący ID.
- `413 url_too_long` — base64 blob > 4096 znaków (sanity limit).

### 4.2 Encje / schema / migracje

**Zero nowych encji + zero migracji.** VIEW-10 to refactor + extension istniejącej logiki.

### 4.3 Listenery / event subscribers

Bez zmian.

### 4.4 Permissions / RBAC

Bez zmian (MVP brak per-action gating per CLAUDE.md §11.5).

### 4.5 Provenance

N/A (read-only endpoint).

### 4.6 Worker / async

N/A (synchronous search).

### 4.7 Real-time (Mercure)

N/A.

## 5. Sub-tasks (checklist)

### Backend
- [ ] `FilterDslResolver` — rozszerzenie z 11 → 25 ops. Dodaj method `validateOperatorForType(string $attrCode, string $op): void` (sprawdza `FILTER_OPERATORS_BY_TYPE` lookup → throw `BadRequestHttpException` przy mismatch).
- [ ] `FilterDslResolver::toMeilisearchFilter(array $dsl): string` — kompiluje DSL do Meilisearch filter string syntax. Reuse existing `compile()` z SQL-flavored output → adapter dla Meilisearch.
- [ ] `AttributeMetadataResolver` (nowy service w `src/Catalog/Application/Filter/`) — `getAttributeType(string $code): string` — wczytuje atrybut z `attribute_repository` + zwraca `type` (text/number/select/...). Cache in-memory per request.
- [ ] `FilterUrlSerializer` (nowy service) — `fromUrlParams(array $params): FilterDsl`, `toUrlParams(FilterDsl $dsl): array`, `fromBase64(string $blob): FilterDsl`, `toBase64(FilterDsl $dsl): string`. Validate przez resolver.
- [ ] `SearchController::products()` — accept `smart_preset` + `filter` query params. Resolver:
  - `smart_preset=<slug-or-id>`: fetch preset (z SmartFilterPresetRepository), use preset.query.
  - `filter=<base64-json>`: decode + validate.
  - `filter[attr][op]=value` (flat): URL serializer parsuje.
  - DSL → `FilterDslResolver->toMeilisearchFilter()` → przekazane do `CatalogSearchService->search($filterExpression)`.
- [ ] `CatalogSearchService::search()` — accept optional `customFilterExpression` parameter, mergeuje z istniejącymi `filters` + `rangeFilters`.
- [ ] PHPUnit testy: `FilterDslResolverFullOperatorsTest` (per type × per op coverage), `FilterUrlSerializerTest` (bi-directional + edge cases), `AttributeMetadataResolverTest`.
- [ ] ApiTestCase: `SmartPresetSearchApiTest` — happy path GET `/api/search/products?smart_preset=red-low-completeness`, 400 invalid DSL, 404 unknown preset, 413 too long blob.
- [ ] OpenAPI snapshot regen.

### Frontend
- [ ] `lib/filters/operators.ts` — TS enum mirror z BE (regen z OpenAPI lub manual).
- [ ] `lib/filters/url-serializer.ts` — bi-directional helpers (jak BE, TS port).
- [ ] `lib/filters/use-filter-state.ts` — hook synchronizujący conditions z `useSearchParams`.
- [ ] `components/catalog/filter-operator-picker.tsx` — popover button grid per type.
- [ ] `components/catalog/filter-value-input.tsx` — input wariant per type (text, number z spinner, date z picker, select z dropdown, multiselect z shadcn MultiSelect, boolean toggle, relation autocomplete).
- [ ] `components/catalog/filter-chip.tsx` — refactor z `filter-chips-bar.tsx` button na shadcn `<Popover>` z inline operator + value editor.
- [ ] `components/catalog/advanced-filter-panel.tsx` — wymień `FILTER_OPERATORS_BY_TYPE` lokalny stałą na import z `lib/filters/operators.ts`; operator `<select>` dynamic.
- [ ] `components/catalog/filter-chips-bar.tsx` — chip body click otwiera `FilterChip` popover zamiast panel; usunięty `onEditChip` callback.
- [ ] `features/catalog/products/list.tsx` — wymień `applyConditionsToFilters` na `useFilterState`; usuń local DSL → searchFilters mapping; `useCatalogSearch` dostaje `smartPresetId` + `filter` props.
- [ ] `features/catalog/search/use-catalog-search.ts` — dodaj `smartPresetId` + `filter` props.
- [ ] `locales/pl.json` + `en.json` — ~20 kluczy.

### E2E + Integration
- [ ] `apps/admin/e2e/products-view-10.spec.ts`:
  - Scenariusz 1: text attr `Opis · PL` + operator `contains` + value `czujnik` → grid filtruje.
  - Scenariusz 2: number attr `Cena` + operator `between` + `100, 500` → grid filtruje.
  - Scenariusz 3: URL share — copy URL after apply → paste w new tab → conditions restored.
  - Scenariusz 4: Smart preset apply `red-low-completeness` → assert grid filtruje + URL `?smart_preset=red-low-completeness`.
  - Scenariusz 5: invalid operator (manually patch URL) → 400 toast error.

### Testy non-functional
- [ ] PHPStan max: 0 errors.
- [ ] Biome strict: 0 errors.
- [ ] TypeScript strict: 0 errors.
- [ ] PHPUnit: ≥80% nowej logiki, 25 ops × 8 typów = 200 test cases parametrized.
- [ ] ApiTestCase: pełen happy path + 400 + 404 + 413 + multi-tenancy.
- [ ] Playwright E2E: 5 scenariuszy zielone.
- [ ] axe-core: 0 violations serious/critical.
- [ ] Lighthouse: a11y =100, performance ≥85.
- [ ] Bundle size FE Δ <50KB gzip.
- [ ] composer + pnpm audit: 0 high/critical.
- [ ] **Performance gate (PRD §13.1)**: p95 `/api/search/products?smart_preset=<id>` < 300ms na seed 50k SKU. k6 raport w PR description (100 VU × 60s).
- [ ] OpenAPI snapshot zaktualizowany.

### Dokumentacja
- [ ] PR description: side-by-side mockup vs build (panel operator dropdown rozszerzony, chip popover inline).
- [ ] PR description: 25 ops × 8 typów coverage matrix.
- [ ] `agent/current_status.md` — update do VIEW-10 closure + VIEW-09b start.
- [ ] `agent/lessons.md` — Lessons z VIEW-10 (Meilisearch filter expression edge cases, BE↔FE operator enum sync).

### Manual smoke (operator po merge)
- [ ] Login + goto `/products`.
- [ ] Click `Filtruj zaawansowane` → add condition `Opis · PL contains "czujnik"` → Apply → grid filtruje + chip widoczny.
- [ ] Click chip body → popover z operator picker + value input → zmień operator na `not contains` → grid update.
- [ ] Apply preset `🔴 Czerwone (<50%)` → URL `?smart_preset=red-low-completeness` + grid filtruje.
- [ ] Copy URL → paste w nowym tabie → conditions restored.
- [ ] Manually patch URL z invalid operator (`?filter[brand][op]=STARTS_WITH`) — assert toast error 400.
- [ ] DevTools Network: GET `/api/search/products?smart_preset=...` < 300ms.
- [ ] DevTools Console: brak czerwonych errorów.

## 6. Acceptance criteria — funkcjonalne

- [x] BE `FilterDslResolver` wspiera 25 ops per typ atrybutu (PRD §5.5 tabela).
- [x] BE `FilterUrlSerializer` bi-directional URL params ↔ DSL.
- [x] BE `SearchController` accepts `?smart_preset=<id>` + `?filter=<base64>`.
- [x] BE resolver kompiluje DSL do Meilisearch filter expression.
- [x] FE `FilterOperatorPicker` pokazuje tylko valid ops per attribute type.
- [x] FE `FilterChip` inline popover (operator + value picker w jednym).
- [x] FE `useFilterState` hook synchronizuje conditions z URL.
- [x] Smart preset apply → URL update + grid refetch przez BE resolver (zamiast FE partial apply).
- [x] URL share — copy URL → paste w innym tabie → state restored.
- [x] Invalid operator/type combo → 400 Problem Details + toast error.

## 7. Acceptance criteria — non-functional (TWARDE GATES)

- p95 `/api/search/products?smart_preset=<id>` < 300ms na seed 50k SKU (k6 raport w PR).
- p95 `/api/search/products?filter=<base64>` < 300ms.
- EXPLAIN ANALYZE na resolver-generated Meilisearch query — zero N+1.
- PHPStan max: 0 errors.
- Biome strict: 0 errors.
- TypeScript strict: 0 errors.
- PHPUnit coverage: ≥80% nowej logiki (resolver + serializer + metadata cache).
- ApiTestCase: pełen happy path + 400 + 404 + 413 + multi-tenancy isolation.
- Playwright E2E: 5 scenariuszy zielone.
- axe-core: 0 violations serious/critical.
- composer + pnpm audit: 0 high/critical.
- Multi-tenancy: cross-tenant read test — preset z tenanta A nie widoczny dla tenanta B.
- OpenAPI snapshot zaktualizowany.

## 8. Smoke-test scenariusze (manualne)

1. Login `admin@demo.localhost / changeme` na `https://pim.localhost`.
2. Goto `/products`.
3. Click `Filtruj zaawansowane` button → push-down panel.
4. Dodaj condition: atrybut `Opis · PL`, operator `contains`, value `czujnik`. Apply.
5. Assert: chip `Opis · PL contains czujnik` w FilterChipsBar, URL `?filter[description.pl][op]=contains&filter[description.pl][value]=czujnik`, grid filtruje.
6. Click chip body → inline popover otwiera się z operator picker (8 ops dla `text`) + value input.
7. Zmień operator na `not contains`. Apply w popover. Grid update.
8. Dodaj 2-gą condition: `Cena` `between` `100, 500`. Apply.
9. URL: dwie conditions, grid pokazuje produkty z opisem nie zawierającym „czujnik" AND ceną 100-500 PLN.
10. *„Skopiuj URL z filtrami"*. Open w incognito → assert state restored.
11. Apply preset `🔴 Czerwone (<50%)`. URL: `?smart_preset=red-low-completeness`. Grid filtruje.
12. Patch URL manually: `?filter[brand][op]=STARTS_WITH&filter[brand][value]=F` — assert toast error 400 (BE resolver reject niewspieranego operatora dla typu `relation`).
13. DevTools Network: GET `/api/search/products?smart_preset=red-low-completeness` < 300ms.
14. DevTools Console: brak errorów.
15. Multi-tenancy: zaloguj jako inny tenant → preset z user-defined tenanta A nie widoczny.

## 9. Edge cases / poza zakresem

### Edge cases pokryte
- Empty conditions → URL bez params → useCatalogSearch bez extra filter.
- Single condition vs multi (1+) — DSL flatten do conditions array.
- Operator `IS EMPTY` / `IS NOT EMPTY` → no value required.
- Operator `IN` / `NOT IN` z array value (np. brand IN Festo, Bosch).
- Operator `between` z 2 values (np. price between 100, 500).
- Smart preset deletion mid-session → next apply → 404 → toast + URL clear.
- URL params > 4096 chars → auto-fallback do `?q=<base64-json>` (sanity limit).
- Cross-tenant preset access → 404 (information hiding, jak VIEW-09).

### Świadomie poza zakresem (deferred do follow-up VIEW-NN)
1. **Query mode AND/OR brackets** → VIEW-09b (tab disabled z badge `VIEW-09b` zachowany).
2. **Nested groups w DSL** (depth > 1) → VIEW-09b. URL serializer w VIEW-10 obsługuje single-level + base64 fallback dla nested (tylko apply, nie edit).
3. **Cmd+K palette** → VIEW-19. VIEW-10 nie integruje agent layer.
4. **Cross-page selection** → VIEW-11.
5. **Bulk actions wizard** → VIEW-12+.
6. **Per-attribute custom validation** (np. `voltage between 0, 480` z range constraint na encji Attribute) → VIEW-10 robi tylko type-based, not constraint-based.
7. **Real-time facet refresh** (po condition change pokazać tylko valid options w pickerze) → Faza 1 (wymaga Meilisearch facetDistribution + per-attr query).
8. **AttributeMetadataResolver cache invalidation** na attribute schema changes (po Schema-ops w epik 0.7) → VIEW-19 lub Faza 2.

### Edge cases pomijane (low-priority)
- 100+ conditions w URL — UI nie pozwoli (panel `+ Dodaj warunek` button), ale BE accept jeśli pre-validated.
- Concurrent edit różnych preset slug clash → MVP last-write-wins (jak VIEW-09).
- Mobile/tablet viewport — admin desktop-first.

## 10. Powiązane ADR / dokumenty

**Nowy ADR (po merge VIEW-10):**
- **ADR-015 — Filter DSL JSONB + URL serializer**: format flat conditions (VIEW-09) + nested AND/OR/NOT (VIEW-09b). Hashowany blob fallback. Resolver Meilisearch (VIEW-10) + Postgres `attributes_indexed @> ...` (VIEW-09 counts).

**Aktualizacje istniejących dokumentów (commit razem z PR):**
- `Project Plan/01-architektura-pim.md` — dodać sekcję dla Filter DSL Resolver w §5 model danych + §11.2 Meilisearch resolver integration.
- `Project Plan/02-plan-projektu-pim.md` — checkbox VIEW-10 + estymacja 26h.
- `agent/current_status.md` — sekcja *„2026-XX-XX: VIEW-10 marathon"*.
- `agent/lessons.md` — sekcja *„Lessons z VIEW-10"* po merge (BE↔FE operator enum sync, Meilisearch filter expression vs Postgres SQL fragment).
- `docs/api-spec/v0.json` — regenerowane.

**Memory updates:** brak nowych.
