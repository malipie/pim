# VIEW-05 — Produkty · Lista (delta-alignment do pixel-perfect z mockupu)

## 1. Kontekst i cel widoku

Widok `/products` to centralny ekran katalogu produktów: lista z saved views, filtrami, search, Excel-like grid, sticky bulk bar, tree wariantów. Używany przez merchandisera/managera produktu w codziennej pracy (przegląd, zaznaczenie, edit-in-grid, segregacja po marka/rodzina/kanał/status, szybkie zlecanie bulk akcji).

Lista istnieje w ~75-80% (PR-y UI-02 #336–#342 + UI-03.3 #358). VIEW-05 dokleja **delta** do pixel-perfect mockupu: poziomy SavedViewsRail (zamiast Dropdownu), refactor toolbara (search flex-1 + 4 FilterPill + Płasko/Drzewo segmented + Import/Nowy produkt), 12-kolumnowy ProductsGrid, pixel-perfect BulkBar z 4 placeholder akcjami (toast „W przygotowaniu"). Detail (`/products/:id`), wizard (`/products/new`), bulk modale = OUT OF SCOPE.

Backend zero zmian — wszystkie BE gaps idą do follow-up tickets VIEW-05.1–VIEW-05.7.

## 2. Mockup / źródło designu

- **Plik źródłowy**: `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/produkty/list-view.jsx` (437 linii).
- **Pixel-perfect binding**: JSX prototyp jest single source of truth dla layoutu, klas Tailwind, paddingów, copy, animacji. Adaptacje stack-specific (shadcn zamiast hand-rolled `<DropdownMenu>`, lucide-react zamiast inline SVG `ILV.*`) dozwolone, ale wizualny rezultat <2% pixel mismatch.
- **Powiązane widoki niewchodzące w scope**:
  - `detail-view.jsx` → out of scope (operator: „bez szczegółów produktu").
  - `data.jsx` → mock seed (same file, only data fixture; nie używamy w produkcji).
- **Kluczowe linie mockupu** (do referencji):
  - Header: l. 406–415.
  - SavedViewsRail: l. 62–79.
  - Toolbar: l. 82–141 (z FilterPill l. 143–173).
  - BulkBar: l. 176–198.
  - QuickEdit popover: l. 201–218.
  - Grid 12 kolumn: l. 220–376 (kolumny zdef. l. 255–268).
  - ListView root: l. 378–433.

## 3. Zakres frontend (FE)

### 3.1 Routing

- `/products` (już istnieje, `apps/admin/src/app.tsx` l. 106). Zmiana: brak — używamy istniejącej trasy.
- Szczegóły produktu (klik SKU/name w gridzie) → `<Link to="/products/:id">` (`/products/:id` istnieje, out of scope).
- Wizard (klik „Nowy produkt") → `<Link to="/products/new">` (istnieje, out of scope).
- Auth gate: `<AuthedRoute>` (istnieje, bez zmian).

**Decyzja**: lista jest fullscreen-routed, NIE Sheet/Dialog. Zgodne z view-first regułą operatora.

### 3.2 Komponenty (lista płaska)

#### Reuse (bez zmian)
| Komponent | Plik | Cel |
|---|---|---|
| `CompletenessBadge` | `components/catalog/completeness-badge.tsx` | Progress bar w kolumnie `compl` |
| `SyncAggregateIcon` | `components/catalog/sync-aggregate-icon.tsx` | 1 ikona aggregate w kolumnie `channels` |
| `AdvancedFilterBuilder` | `components/catalog/advanced-filter-builder.tsx` | Sheet trigger „Więcej" w toolbarze |
| `SaveViewModal` | `components/catalog/save-view-modal.tsx` | Modal wywoływany z SavedViewsRail przez „+ Zapisz widok" |
| `ProductRowActions` | `components/catalog/product-row-actions.tsx` | Kebab menu w kolumnie `more` |
| `EmptyStateProducts` | `components/catalog/empty-state-products.tsx` | Empty state |
| `useCatalogSearch` | `features/catalog/search/use-catalog-search.ts` | Search hook |
| `jsonFetch` | `lib/http.ts` | HTTP client z JWT |

#### Nowe komponenty
| Komponent | Plik (nowy) | LOC | Props |
|---|---|---|---|
| `ToastProvider` + `useToast` + `toast.*` | `components/ui/toast.tsx` | ~120 | `<ToastProvider />`, `toast.info/error/success(text)` |
| `SavedViewsRail` | `components/catalog/saved-views-rail.tsx` | ~110 | `{ resource?, activeSlug, onApply, onSaveCurrent, currentTotal }` |
| `FilterPill` | `components/catalog/filter-pill.tsx` | ~80 | `{ label, value, options, onChange, allLabel? }` |
| `ProductsGrid` | `components/catalog/products-grid.tsx` | ~280 | `{ rows, selected, onToggleSelect, onToggleSelectAll, expandedMasters, onToggleExpand, variantsByMasterCount, onCommit, onToggleEnabled }` |
| `BulkBar` | `components/catalog/bulk-bar.tsx` | ~150 | `{ selectedIds, onClear, onToggleEnabled, showSelectedOnly, onToggleShowSelectedOnly }` |

#### Modyfikacje
| Plik | Zmiana |
|---|---|
| `features/catalog/products/list.tsx` | 671→~480 LOC: header refactor, toolbar refactor, drop dual-mode, swap Rail/BulkBar/Grid |
| `components/catalog/variants-toggle.tsx` | radio-fieldset → segmented control |
| `components/catalog/excel-like-grid.tsx` | dodać F2 alias do handleKeyDown (+2 LOC) |
| `app.tsx` | mount `<ToastProvider>` wokół Refine root |
| `locales/pl.json` + `en.json` | ~30 nowych kluczy |

#### Usuwane
| Plik | Powód |
|---|---|
| `components/catalog/saved-views-dropdown.tsx` (118 LOC) | Zastąpiony przez `SavedViewsRail` |

### 3.3 State management

- `query: string` — search input value.
- `filters: Record<string, string|string[]>` — pille Marka/Rodzina/Kanał/Status. Brak klucza = pill „wszystkie".
- `advancedFilters: Record<string, FilterValue>` — pochodzą z AdvancedFilterBuilder Sheet (Więcej).
- `selected: Set<string>` — zaznaczone wiersze.
- `showSelectedOnly: boolean` — toggle w counter row.
- `variantsMode: 'tree' | 'flat'` — segmented Płasko/Drzewo.
- `activeViewSlug: string | null` — aktywny saved view pill.
- `showSaveViewModal: boolean` — modal trigger.
- `expandedMasters: Set<string>` — które mastery rozwinięte w tree.

Mutacje + invalidacje:
- PATCH `/api/products/:id` (toggle enabled inline) → `refetch()` po sukcesie.
- POST `/api/products/bulk-edit` (overflow Włącz/Wyłącz) → `refetch()` + clear selected.
- POST `/api/saved-views` (z SaveViewModal) → re-fetch saved-views w Rail.

Cache: Refine `useList` + `useCatalogSearch` używają query keys `['products']`, `['catalog-search', kind, query, filters, ranges]`. Optymistycznych update'ów nie robimy w MVP.

### 3.4 Struktura sekcji widoku (kolejność renderu)

1. **Header** — breadcrumb-line „Workspace · katalog" + h1 32px „Produkty" (lewa kolumna), total SKU + last sync time (prawa kolumna).
2. **SavedViewsRail** — poziomy scrollable rail z pillami, „+ Zapisz widok" na końcu.
3. **Toolbar** — search flex-1 + 4 FilterPill (Marka/Rodzina/Kanał/Status) + „Więcej" button + segmented Płasko/Drzewo + Import button + „Nowy produkt" button.
4. **Counter row** — „N wyników · K zaznaczonych [+ Bulk action chip jeśli K>0] · ⌘C ⌘V ⇧↓ F2 hints".
5. **ProductsGrid** — 12-kolumnowy grid z header sticky + body scrollable.
6. **BulkBar** (sticky bottom-6, conditional na `selected.size > 0`).
7. **SaveViewModal** (conditional na `showSaveViewModal`).

### 3.4a Mapping element-po-elemencie z prototypu

#### Header (mockup l. 406–415)
- `<div class="flex items-baseline justify-between mb-3">`:
  - lewa: `<div class="text-[13px] text-zinc-500 font-medium">Workspace · katalog</div>` + `<h1 class="font-display text-[32px] font-semibold tracking-tight leading-none mt-1">Produkty</h1>`
  - prawa: `<div class="text-[12px] text-zinc-500 num"><span class="text-zinc-900 font-semibold">{total}</span> SKU · ostatnia synchronizacja {minutes} min temu</div>`
- `total` = `useCatalogSearch().result.totalHits ?? useList().result.total ?? 0`.
- `minutes` = FE heurystyka `Math.floor((Date.now() - Math.max(...rows.map(r => parseDate(r.updatedAt).getTime()))) / 60000)` lub `—` jeśli `rows.length === 0`.

#### SavedViewsRail (mockup l. 62–79)
- `<div role="tablist" aria-label="..." class="flex items-center gap-1.5 overflow-x-auto scrollbar-thin">`:
  - per view pill: `<button role="tab" aria-selected={active} class="shrink-0 inline-flex items-center gap-2 h-9 px-3 rounded-2xl text-[13px] font-medium transition ${active ? 'bg-zinc-900 text-white' : 'bg-white soft-shadow text-zinc-700 hover:bg-zinc-50'}">`. Eye icon dla `is_system`. Count: aktywny pokazuje `currentTotal`, pozostałe `—` (świadome odejście — operator zatwierdził).
  - na końcu: `<button class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-2xl text-[13px] text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 border border-dashed border-zinc-200">+ Zapisz widok</button>` → `onSaveCurrent()`.
- Keyboard: ←/→ arrow keys move focus między tabami. Enter/Space → apply.

#### Toolbar (mockup l. 82–141)
- Wrapping: `<div class="flex items-center gap-3 flex-wrap">`.
- Search: `<div class="flex-1 min-w-[280px] relative">` z lucide `Search` icon w `absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-400` + `<input type="search" class="w-full h-11 pl-10 pr-4 rounded-2xl bg-white soft-shadow text-[14px] placeholder:text-zinc-400 focus-ring" placeholder="Szukaj po SKU, nazwie, EAN, atrybucie…">`.
- 4 `<FilterPill label="Marka|Rodzina|Kanał|Status" ... />`.
- `<button class="h-11 px-3.5 rounded-2xl bg-white soft-shadow text-[13px] font-medium text-zinc-600 inline-flex items-center gap-2 hover:bg-zinc-50">` z lucide `SlidersHorizontal` icon + „Więcej" → otwiera AdvancedFilterBuilder Sheet.
- Segmented Płasko/Drzewo: `<div class="h-11 rounded-2xl bg-white soft-shadow inline-flex items-center p-1">` z 2 buttonami `h-9 px-3 rounded-xl text-[12.5px] font-medium`. Aktywny `bg-zinc-900 text-white`, nieaktywny `text-zinc-500`.
- `<button class="h-11 px-3.5 rounded-2xl bg-white soft-shadow text-[13px] text-zinc-600 inline-flex items-center gap-2">` z lucide `Upload` + „Import" — disabled (mock).
- `<Link to="/products/new" class="h-11 px-4 rounded-2xl bg-zinc-900 text-white text-[13px] font-medium inline-flex items-center gap-2 hover:bg-zinc-800">` z lucide `Plus` + „Nowy produkt".

#### Counter row (mockup l. 121–138)
- `<div class="mt-3 flex items-center gap-2 text-[12px] text-zinc-500">`:
  - „N wyników": `<span class="num"><span class="text-zinc-900 font-semibold">{total.toLocaleString('pl-PL')}</span> wyników</span>`
  - „K zaznaczonych" (conditional na `selected.size > 0`): `<span class="text-zinc-300">·</span><span class="num"><span class="text-zinc-900 font-semibold">{selected.size}</span> zaznaczonych</span>` + button „Bulk action" violet pill (toggle showSelectedOnly).
  - keyboard hints (`ml-auto`): `<span class="kbd font-mono">⌘C</span>kopiuj` itd. (4 pary).

#### Grid (mockup l. 220–376)
Patrz sekcja 3.4b (kolumna-po-kolumnie).

#### BulkBar (mockup l. 176–198)
- `<div class="sticky bottom-6 z-30 flex justify-center fade-in">`
- container: `<div class="bg-zinc-900 text-white rounded-3xl soft-shadow-lg px-6 py-3 flex items-center gap-5">`:
  - count badge: `<span class="h-7 w-7 rounded-xl bg-white/10 grid place-items-center text-[12px] font-semibold num">{count}</span>` + `<span class="text-[13px] font-medium">zaznaczonych produktów</span>`
  - dzielnik: `<span class="h-6 w-px bg-white/15">`
  - 4 buttony placeholderowe (Edytuj atrybut z lucide `Pencil`, Zmień kategorię z `FolderTree`, Eksport z `Upload`, Zleć agentowi z `Sparkles` violet bg) → klik = `toast.info(t('products.bulk.placeholder_in_progress'))`.
  - dzielnik
  - overflow `<DropdownMenu>` (lucide `MoreHorizontal`) → „Włącz" / „Wyłącz" (real PATCH bulk-edit).
  - „Wyczyść" → `onClear()`.

### 3.4b Grid 12 kolumn (mockup l. 255–268)

Grid template: `gridTemplateColumns: "44px 52px 130px minmax(220px,1.4fr) 120px 110px minmax(140px,0.9fr) 150px 150px 110px 70px 44px"`.

| # | id | width | Label | Render |
|---|----|-------|-------|--------|
| 1 | sel | 44px | (checkbox „all") | `<input type="checkbox" aria-label="Zaznacz wszystkie" checked={allSelected} onChange={onToggleSelectAll}>` |
| 2 | img | 52px | — | placeholder `<span class="grid place-items-center rounded-xl bg-zinc-100 text-[18px] h-9 w-9">▣</span>` (variant: h-8 w-8) |
| 3 | sku | 130px | SKU | `font-mono text-[12px] flex items-center gap-1.5`: chevron expand (jeśli master z variants) + `<Link to="/products/:id" class="font-medium text-zinc-700 hover:text-zinc-900">{sku}</Link>` |
| 4 | name | minmax(220px,1.4fr) | Nazwa | `<Link>` z `text-[13.5px] font-medium tracking-tight truncate` + (master) badge `<span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-violet-50 text-violet-700">{count} wariantów</span>` + (variant) axis label `<span class="text-[10.5px] text-zinc-500 font-mono">{axis}</span>` |
| 5 | brand | 120px | Marka | `truncate` text |
| 6 | family | 110px | Rodzina | `text-zinc-600 truncate text-[12.5px]` lub `—` |
| 7 | cats | minmax(140px,0.9fr) | Kategorie | pills `<span class="text-[10.5px] px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-700">` + `+N` overflow. **MVP: `—`** (BE gap, follow-up VIEW-05.1). |
| 8 | compl | 150px | Completeness | `<CompletenessBadge pct={...}>` |
| 9 | channels | 150px | Kanały | `<SyncAggregateIcon state={syncStatusAggregate}>` + label tekstowy `text-[10.5px] font-medium ${color-by-state}` (OK/Częściowo/Błąd/—). **NIE 3 dots** (świadome odejście). |
| 10 | price | 110px | Cena | `num text-[13px] font-medium tabular-nums {price.toLocaleString('pl-PL')}<span class="text-zinc-400 ml-1 text-[11px]">PLN</span>`. **MVP: `—`** (BE gap). |
| 11 | enabled | 70px | Aktywny | toggle pill `<button class="inline-flex items-center h-5 w-9 rounded-full p-0.5 transition ${enabled ? 'bg-emerald-500' : 'bg-zinc-200'}"><span class="h-4 w-4 bg-white rounded-full shadow transition ${enabled ? 'translate-x-4' : ''}">` + `aria-label`. Klik = PATCH. |
| 12 | more | 44px | — | kebab `<button>` z lucide `MoreHorizontal` opening `<ProductRowActions>` popover. Pokazuje się na hover (`opacity-0 group-hover:opacity-100`). |

Header row: `<div role="row" class="grid items-center text-[11px] uppercase tracking-wider text-zinc-500 font-semibold border-b border-zinc-100 bg-zinc-50/60">`.

Row: `<div role="row" class="group relative grid items-center text-[13px] border-b border-zinc-50 last:border-b-0 transition ${isSelected ? 'bg-violet-50/60' : isActive ? 'bg-zinc-50/80' : 'hover:bg-zinc-50/60'} ${isVariant ? 'bg-zinc-50/40' : ''}">`.

Tree expand:
- chevron lucide `ChevronRight` z `class="transition-transform ${expanded ? 'rotate-90' : ''}"`.
- variant indent: `<span class="ml-1 text-zinc-300">└</span>` w cell `img`.

### 3.5 i18n keys

Dodaj do `locales/pl.json` i `locales/en.json` (~30 kluczy):

```
products.header.workspace
products.header.last_sync_minutes_ago (count param: minutes)
products.header.last_sync_unknown
products.header.total_skus (count param)

products.toolbar.search_placeholder
products.toolbar.filter_brand
products.toolbar.filter_family
products.toolbar.filter_channel
products.toolbar.filter_status
products.toolbar.filter_all
products.toolbar.more_filters
products.toolbar.import
products.toolbar.new_product

products.counter.results_one
products.counter.results_other
products.counter.selected_one
products.counter.selected_other
products.counter.shortcut_copy
products.counter.shortcut_paste
products.counter.shortcut_select
products.counter.shortcut_edit

products.bulk.edit_attribute
products.bulk.change_category
products.bulk.export
products.bulk.delegate_agent
products.bulk.placeholder_in_progress
products.bulk.selected_label

products.saved_views.save_view
products.saved_views.system_view_aria
products.saved_views.rail_aria

products.variants.flat
products.variants.tree
products.variants.count_one
products.variants.count_other

products.fields.family
products.fields.categories
products.fields.channels
products.fields.price
products.fields.enabled

products.row.toggle_enabled_aria
products.row.expand_variants_aria
products.row.collapse_variants_aria

toast.dismiss
```

**Ban literałów w JSX**: każdy user-facing tekst przez `t('...')`. Sprawdzone przez Biome rule + manual review.

### 3.6 a11y

- `axe-core 0 violations serious/critical` — sprawdzone w E2E scenariuszu 2.
- SavedViewsRail: `role="tablist"` + `role="tab" aria-selected` + ←/→ arrow keys + Enter/Space apply.
- FilterPill: shadcn `<DropdownMenu>` daje out-of-the-box (focus trap, aria-expanded, ESC close).
- ProductsGrid: `role="grid"` + `role="row"` + `role="gridcell"` + tabIndex management. Selection checkbox `aria-label`. Toggle enabled `aria-label`. Tree expand button `aria-expanded`.
- BulkBar: `role="region" aria-label="Bulk actions"`. Toast `role="status" aria-live="polite"`.
- Focus ring: `focus-visible:ring-2 focus-visible:ring-zinc-900` na wszystkich button/input/link.
- Color contrast: zinc-900 na zinc-50 = AA pass; violet-700 na violet-50 = AA pass; emerald-700 na zinc-50 = AA pass.

### 3.7 Locales (multilingual fields w produkcie)

Lista produktów nie pokazuje multilingual fields (name/description są mono — `attributesIndexed.name` w jednym lokalu, default tenant lokal). **N/A — locales obsługa pojawia się na detail/edit page**.

### 3.8 Empty / loading / error states

- **Empty (no products)**: `<EmptyStateProducts>` (istnieje). Render gdy `!isLoading && baseRows.length === 0 && !isSearchActive`.
- **Empty (search no results)**: render w gridzie pojedynczy row `<div role="row">{t('products.empty.no_search_results')}</div>` (lub oddzielny komponent — keep simple, inline).
- **Loading**: skeleton rows w gridzie (8 placeholder rows z `<div class="animate-pulse bg-zinc-100">`). Spinner globalny w header gdy `isLoading`.
- **Error**: `<div role="alert" class="rounded-2xl bg-rose-50 p-4 text-rose-700">{error}</div>` zamiast gridu jeśli fetch failed.

## 4. Zakres backend (BE)

### 4.1 Endpointy

**Zero zmian** w tym tickecie. Wszystkie używane endpointy istnieją:

| Method | Path | Cel | Status |
|--------|------|-----|--------|
| GET | `/api/products` | Lista + cursor pagination | ✅ |
| GET | `/api/search/products` | Meilisearch + facets | ✅ |
| GET | `/api/saved-views?resource=products` | SavedViews list | ✅ |
| POST | `/api/saved-views` | Save new view | ✅ |
| POST | `/api/products/bulk-edit` | Bulk toggle_enabled | ✅ |
| PATCH | `/api/products/{id}` | Inline edit (name, brand, enabled) | ✅ |

### 4.2 Encje / schema / migracje

**Zero zmian**. Encje istnieją:
- `App\Catalog\Domain\Entity\CatalogObject` (kind=product)
- `App\Catalog\Domain\Entity\ObjectValue`
- `App\Catalog\Domain\Entity\SavedView`

Migrations: brak nowych.

### 4.3 Listenery / event subscribers

**Zero nowych**. Istniejące triggery (regression sanity-check):
- `AttributesIndexedSyncListener` (na ObjectValue insert/update/delete)
- `TenantAssignmentListener` (prePersist)
- `CatalogIndexSubscriber` (Meilisearch)

### 4.4 Permissions / RBAC

**Zero zmian**. `CatalogObjectVoter` istnieje (READ/WRITE/DELETE per role). FE nie konsumuje voter w VIEW-05 (operator: „RBAC FE → epik 0.7+"). Endpointy mają voter mapping.

### 4.5 Provenance

**Zero zmian** na liście (nie pokazujemy provenance badges — to detail-page concern). Istniejący ProvenanceBadge nie jest renderowany w gridzie (cell `provenance` nie jest w 12 kolumnach mockupu).

### 4.6 Worker / async

**Zero nowych** (lista nie odpala asynchronicznych jobów).

### 4.7 Real-time (Mercure)

**Out of scope w VIEW-05**. Real-time refresh listy po publikacji innym tenancie/innej sesji = follow-up. Aktualnie `/.well-known/mercure?topic=/objects` jest publishowany, ale list page nie subskrybuje. Decyzja MVP: refresh listy = manual reload (F5) lub po lokalnej akcji (PATCH/bulk-edit) przez `refetch()`.

## 5. Sub-tasks (checklist)

### Backend
- [ ] Zero zmian — sanity-check że istniejące testy zielone (regression).

### Frontend (kolejność implementacji)
- [ ] `components/ui/toast.tsx` — in-house toast provider.
- [ ] Mount `<ToastProvider>` w `app.tsx`.
- [ ] `components/catalog/saved-views-rail.tsx` — nowy.
- [ ] `components/catalog/filter-pill.tsx` — nowy.
- [ ] `components/catalog/variants-toggle.tsx` — refactor radio→segmented.
- [ ] `components/catalog/products-grid.tsx` — nowy.
- [ ] `components/catalog/bulk-bar.tsx` — nowy.
- [ ] `components/catalog/excel-like-grid.tsx` — F2 alias (+2 LOC).
- [ ] `features/catalog/products/list.tsx` — główny refactor.
- [ ] DELETE `components/catalog/saved-views-dropdown.tsx`.
- [ ] `locales/pl.json` + `en.json` — ~30 kluczy.

### E2E + Integration
- [ ] `apps/admin/e2e/products-view-05.spec.ts` — happy path scenariusz 1.
- [ ] `apps/admin/e2e/products-view-05.spec.ts` — a11y scenariusz 2 (axe-core).

### Testy non-functional
- [ ] PHPStan max: 0 errors (BE bez zmian, sanity).
- [ ] Biome strict: 0 errors.
- [ ] TypeScript strict: 0 errors.
- [ ] PHPUnit + ApiTestCase: regression zielone.
- [ ] Playwright E2E: 2 scenariusze zielone.
- [ ] axe-core: 0 violations serious/critical.
- [ ] Lighthouse a11y = 100, performance ≥85.
- [ ] Bundle size FE Δ <50KB gzip.
- [ ] composer audit + pnpm audit: 0 high/critical.
- [ ] OpenAPI snapshot: diff = pusty (BE bez zmian).

### Dokumentacja
- [ ] PR description: side-by-side mockup vs build screenshots (header, toolbar, grid, BulkBar).
- [ ] PR description: lista 12 świadomych odejść od mockupu.
- [ ] PR description: link do follow-up tickets VIEW-05.1–VIEW-05.7.
- [ ] `agent/current_status.md` — dopisać sekcję VIEW-05.

### Manual smoke (operator po merge)
- [ ] Login → /products → assert header/toolbar/grid pixel-perfect.
- [ ] FilterPill „Marka" → Festo → assert czarna, lista filtruje.
- [ ] FilterPill „Kanał" → toast „epik 0.6".
- [ ] SavedView pill apply → assert filtry zmienione.
- [ ] Segmented Drzewo → tree expand chevron + badge wariantów.
- [ ] Zaznacz 3 → BulkBar widoczny → klik „Edytuj atrybut" → toast.
- [ ] Overflow „Wyłącz" → assert PATCH wired, status update.
- [ ] „Wyczyść" → BulkBar znika.
- [ ] F2 na komórce name → quick edit input.
- [ ] DevTools Console → brak errorów.

## 6. Acceptance criteria — funkcjonalne

- [x] Header pokazuje „Workspace · katalog" + h1 „Produkty" 32px + total SKU + last sync time. Pixel-perfect z mockupem (l. 406–415).
- [x] SavedViewsRail renderuje wszystkie views z `/api/saved-views?resource=products` jako poziomy rail z pillami; aktywny pill `bg-zinc-900 text-white`, system view ma `Eye` icon, na końcu „+ Zapisz widok" dashed border.
- [x] Toolbar layout: search flex-1 + 4 FilterPill + Więcej + segmented Płasko/Drzewo + Import + Nowy produkt — wszystko w jednym flex-wrap row (mockup l. 85).
- [x] FilterPill „Marka"/„Rodzina"/„Status" filtruje listę przez `searchFilters` + Meilisearch facets. „Kanał" pokazuje toast „epik 0.6".
- [x] Counter row pod toolbar: „N wyników · K zaznaczonych · keyboard hints".
- [x] ProductsGrid renderuje 12 kolumn z mockupu. Mockup-aligned typography (`font-mono text-[12px]`, `text-[13.5px] font-medium tracking-tight`).
- [x] Tree mode: chevron rotate-90 + badge „X wariantów" (violet-50/700) + variant indent `└`.
- [x] Inline toggle Aktywny: klik = PATCH `/api/products/:id` z `{enabled: !current}` → refetch → status zmieniony.
- [x] BulkBar pixel-perfect z mockupu: 4 buttony placeholder → toast „W przygotowaniu". Overflow `…` → Włącz/Wyłącz wired (PATCH bulk-edit). „Wyczyść" → `setSelected(new Set())`.
- [x] Saved view apply → filters i variantsMode hydratowane z `view.config`.
- [x] Klik „+ Zapisz widok" → `<SaveViewModal>` open → POST `/api/saved-views` → re-fetch Rail.
- [x] Search po SKU/nazwie/EAN/atrybucie filtruje listę przez Meilisearch.
- [x] F2 na aktywnej komórce w gridzie → quick edit input pojawia się.
- [x] i18n PL/EN toggluje wszystkie nowe stringi.
- [x] Empty state widoczny gdy 0 produktów. Loading skeleton gdy fetch in-flight. Error alert gdy fetch failed.

## 7. Acceptance criteria — non-functional (TWARDE GATES)

- **Performance**:
  - p95 `/api/products` < 300ms na seed 50k SKU. **k6 raport w PR description** (scenariusz: 100 VU × 60s, query: random z [pusty, „TST", brand=Festo]).
  - p95 `/api/search/products?q=...` < 300ms.
  - p95 `/api/saved-views?resource=products` < 100ms.
- **N+1 query check**: BE bez zmian → existing `EXPLAIN ANALYZE` wystarczy. Sprawdzić w PR że `SqlLogger` pokazuje 0 nowych queries.
- **Indeksy**: BE bez zmian.
- **Pagination**: cursor-based (już jest, default 30, max 200).
- **Memory FE**: brak leak po 5 min interakcji (heap stable w DevTools Memory profiler).
- **Bundle size FE Δ <50KB gzip**: `pnpm --filter admin build` przed/po, diff `dist/assets/*.gz` w PR.
- **Lighthouse**: performance ≥85, a11y =100, best-practices ≥90.
- **PHPStan max**: 0 errors.
- **Biome strict**: 0 errors.
- **TypeScript strict**: 0 errors.
- **PHPUnit coverage**: BE bez zmian → 0 nowych testów; existing pass (regression).
- **ApiTestCase**: brak nowych endpointów → N/A.
- **Playwright E2E**: 2 scenariusze zielone (happy path + axe-core).
- **axe-core**: 0 violations serious/critical na `/products`.
- **composer audit + pnpm audit**: 0 high/critical.
- **Multi-tenancy**: regression — saved-views fetch zwraca tylko views aktualnego tenanta.
- **RBAC**: regression — `/api/products` voter check pass.
- **Audit log**: BE bez zmian → N/A nowe writes.
- **Provenance**: BE bez zmian → N/A.
- **i18n coverage**: wszystkie nowe klucze w `pl.json` i `en.json`. Jeśli klucz nie istnieje, fallback na `defaultValue` w `t()` call.
- **OpenAPI snapshot**: BE bez zmian → diff = pusty po `bin/console api:openapi:export`.

## 8. Smoke-test scenariusze (manualne)

1. Login `admin@demo.localhost / changeme` na `https://pim.localhost`.
2. Goto `/products` → assert „Workspace · katalog" + h1 „Produkty" 32px + total SKU.
3. Klik aktywny pill SavedViewsRail (np. „Domyślny") → assert filtry załadowane.
4. Wpisz „TST" w search → assert lista filtruje, count update.
5. Klik FilterPill „Marka" → wybierz „Festo" → pill staje się czarny, lista pokazuje tylko Festo. Klik „wszystkie" → wraca pełna lista.
6. Klik FilterPill „Kanał" → wybierz „Shopify" → assert toast „Filtr per kanał czeka na epik 0.6", filter NIE zmieniony.
7. Klik segment „Drzewo" → tree expand chevron + badge „X wariantów" + variant rows widoczne z `└` indent.
8. Zaznacz 3 checkboxy w gridzie → BulkBar fade-in z bottom, count = 3.
9. Klik „Edytuj atrybut" w BulkBar → toast „W przygotowaniu — VIEW-05.2".
10. Klik overflow `…` w BulkBar → „Wyłącz" → assert PATCH wywołany, lista refresh, statusy disabled.
11. Klik „Wyczyść" w BulkBar → BulkBar znika, selected reset.
12. Inline toggle Aktywny w wierszu → assert PATCH wired, status zmieniony.
13. F2 na komórce „name" w gridzie → quick edit input pojawia się.
14. Klik SKU lub nazwę produktu → przekierowanie do `/products/:id` (out of scope — tylko sanity że link działa).
15. Lighthouse a11y w DevTools → 100.
16. DevTools Console → brak czerwonych errorów.
17. DevTools Network: GET `/api/products` < 300ms.
18. Multi-tenancy: zaloguj jako tenant B → `/products` → assert lista B, brak produktów A.

## 9. Edge cases / poza zakresem

### Edge cases pokryte
- Empty state (0 produktów) — `<EmptyStateProducts>`.
- Empty search results — inline message.
- Brak kategorii w `attributesIndexed` — render `—`.
- Brak ceny — render `—`.
- Brak `family` — render `—`.
- Master bez wariantów — brak chevron + brak badge.
- Variant bez axis — brak axis label.
- Toast error: shows `toast.error()` przy PATCH/bulk-edit fail.
- Multi-tenancy: TenantFilter aktywny na endpointach.

### Świadomie poza zakresem (deferred do follow-up VIEW-05.X)
1. **Per-channel sync state** (3 dots Shopify/BL/Allegro) → 1 ikona aggregate. Follow-up: VIEW-05.6 (post epik 0.6 `channel_publications` table).
2. **BulkBar 4 akcje wired**:
   - VIEW-05.2: Bulk edit attribute modal.
   - VIEW-05.3: Bulk change category modal.
   - VIEW-05.4: Bulk export CSV/XLSX endpoint + download.
   - VIEW-05.5: Bulk delegate to agent (epik 0.7 agentic).
3. **SavedView count per view** (rail counts) → only active view shows count. Follow-up: VIEW-05.7 (BE endpoint `/api/saved-views/counts`).
4. **Last sync time** → FE heurystyka. Real `MAX(channel_publications.last_sync_at)` po epik 0.6.
5. **Quick edit popover styling** (mockup floating popover) → ExcelLikeGrid inline `<input>`. F2 alias dodany. Popover styling deferred (low priority).
6. **Image cell** → fallback emoji `▣`. Real images po DAM (epik 0.7).
7. **`family`, `cats`, `price` columns** → `—` jeśli BE nie indexuje. Follow-up: VIEW-05.1 (rozszerzenie `/api/products` o categories[] + price w listingu, indexowanie family).
8. **Channel FilterPill** klik → toast „epik 0.6", state nie zmienia się.
9. **`viewMode: table | excel`** → drop, 1 grid (excel-like). Table mode wycofany.
10. **CatalogFacetList aside** → schowane w „Więcej" Sheet (AdvancedFilterBuilder).
11. **Provenance row** → schowane w „Więcej" Sheet.
12. **Bulk delete button** → wycofany. Single-row delete pozostaje w `<ProductRowActions>` kebab.

### Edge cases pomijane (low-priority)
- F2 trigger gdy focus na search input → search input ma own focus, F2 pomijany. OK, nie blokuje.
- Saved view config malformed (np. nie-object) → silent skip + console.warn (existing behavior).
- Race condition: wiele PATCH w locie (selected many + toggle enabled simultaneously) → MVP: sekwencyjnie (await), brak optimistic UI.
- Mobile/tablet layout — viewport <768px → out of scope (lista admin desktop-first).

## 10. Powiązane ADR / dokumenty

- **ADR-009**: ObjectType jako koncept pierwszej klasy (`product` jako built-in kind).
- **ADR-002**: Hybrid model atrybutów (`attributesIndexed JSONB` + GIN index).
- **`Project Plan/01-architektura-pim.md`** sekcja 3.10a (single-origin Caddy) — bez zmian.
- **`Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md`** — backlog modułu produktów (VIEW-05 dokleja delta-alignment listy).
- **`agent/lessons.md`** — patterny z marathonu UI-02 (#336–#342 bug fixy: SavedViewsDropdown JWT, AdvancedFilterBuilder state merge, ExcelLikeGrid swallowed errors). VIEW-05 trzyma się tych patternów.
- **`agent/current_status.md`** — dopisać sekcję „2026-05-03: VIEW-05 produkty-lista marathon" po merge.

### Brak nowego ADR
VIEW-05 nie wprowadza zmian architektonicznych (delta-alignment FE only, BE zero). Wszystkie przyszłe decyzje (per-channel sync, bulk edit modal, agent dispatch) idą do osobnych ADR-ów per follow-up ticket.
