# VIEW-09 — Lista produktów v2 · fundament UI (smart filter presets + push-down advanced filter panel grid mode + filter chips edit popover)

## 1. Kontekst i cel widoku

Widok `/products` po marathonie UI-02 (#336–#342) i VIEW-05 (PR #412) jest pixel-perfect z mockupem **v1** (`design_handoff_modelowania/produkty/list-view.jsx`). Mockup **v2** (`Zrodla/Front_Claude_Design/PIM-nowoczesny/Produkty v2.html`) wraz z PRD `Project Plan/PRD/PRD-PIM-list-advanced.md` definiuje cockpit operatora ICP Kasi (60% czasu pracy) — gęstość BaseLinker + workflow Akeneo + Cmd+K agent.

VIEW-09 dostarcza **fundament UI** epiku UI-09: (a) pasek 5 built-in smart filter presets (PRD §8.2, rule-based — *NIE LLM*; PRD §11 krytyczna nota marketingowa) + user-defined CRUD; (b) push-down sticky-collapsible advanced filter panel w **grid mode only** (query mode → VIEW-09b); (c) filter chips area z edit popover (click chip body otwiera operator + value picker, ✕ kasuje, *„Wyczyść wszystkie"* + *„Skopiuj URL z filtrami"* buttony).

Query mode AND/OR brackets, pełne operatory per typ, cross-page selection, bulk wizard, rollback, locki, Cmd+K — następne tickety epiku (VIEW-09b, VIEW-10..VIEW-19). VIEW-09 to baseline UI/UX i model danych pod resztę feature'a.

## 2. Mockup / źródło designu

- **Plik źródłowy (HTML wrapper)**: `Zrodla/Front_Claude_Design/PIM-nowoczesny/Produkty v2.html` (loader trzech JSX scriptów).
- **Plik źródłowy (FE komponenty v2)**:
  - `Zrodla/Front_Claude_Design/PIM-nowoczesny/produkty/list-view-v2.jsx` (694 LOC, główny widok)
  - `Zrodla/Front_Claude_Design/PIM-nowoczesny/produkty/list-v2-overlays.jsx` (569 LOC — `AdvancedFilterPanel` + `BulkWizard` + `CmdKPalette` + `RollbackToast`)
  - `Zrodla/Front_Claude_Design/PIM-nowoczesny/produkty/data.jsx` (156 LOC, schema reference dla SAVED_VIEWS + PRODUCTS + SMART_PRESETS)
- **Pixel-perfect binding**: JSX prototyp v2 jest single source of truth dla layoutu, klas Tailwind, paddingów, copy, animacji. Adaptacje stack-specific (shadcn `<DropdownMenu>` zamiast hand-rolled, lucide-react zamiast inline SVG `IL2.*`) dozwolone, wizualny rezultat <2% pixel mismatch.
- **PRD master**: `Project Plan/PRD/PRD-PIM-list-advanced.md` (1214 LOC, sekcje §1, §3, §5, §7.1, §7.2, §7.4, §8.2, §11, §13.1, §14.1).

**Kluczowe linie mockupu (do referencji):**
- `list-view-v2.jsx` l. 17-24: `SMART_PRESETS` 5 built-in tablica.
- `list-view-v2.jsx` l. 49-69: `SavedViewsRail` (bez zmian — istnieje w `saved-views-rail.tsx`).
- `list-view-v2.jsx` l. 71-107: `SmartFilterRow` — nowy fundament VIEW-09.
- `list-view-v2.jsx` l. 109-151: `FilterChip` (upgrade z `FilterPill`).
- `list-view-v2.jsx` l. 153-223: `Toolbar` — refactor existing.
- `list-view-v2.jsx` l. 583-689: główna struktura widoku.
- `list-v2-overlays.jsx` l. 9-19: `FILTER_OPS_BY_TYPE` mapa (full operatorzy → VIEW-10).
- `list-v2-overlays.jsx` l. 21-38: `FILTER_ATTRS` baseline atrybutów z `star: true` (favorite top 10).
- `list-v2-overlays.jsx` l. 40-146: `AdvancedFilterPanel` (grid mode w VIEW-09; query mode tab disabled).

**Powiązane widoki niewchodzące w scope:**
- `BulkWizard`, `CmdKPalette`, `RollbackToast` (overlays) → VIEW-12, VIEW-19, VIEW-17.
- `SelectionToolbar` (`list-view-v2.jsx` l. 226-254) → VIEW-11.
- `BulkBar` z 14 akcjami (l. 419-482) → fundament w VIEW-12, rozszerzenia w VIEW-13..VIEW-16.
- Grid z 12 kolumnami (l. 260-396) — bez zmian, kolumna `lock` w VIEW-18.
- `RollbackToast` → VIEW-17.

**Klimas / inne nazwy w mockupie** to przykładowy tenant — nie używać w produkcyjnej kopii (feedback memory `feedback_view_scope_literal.md`).

## 3. Zakres frontend (FE)

### 3.1 Routing

- `/products` (już istnieje, `apps/admin/src/app.tsx`). **Bez zmian** — VIEW-09 modyfikuje wnętrze widoku, nie route.
- Auth gate: `<AuthedRoute>` (istnieje).
- Lista pozostaje fullscreen-routed (nie Sheet/Dialog).

### 3.2 Komponenty (lista płaska)

#### Reuse (bez zmian)
| Komponent | Plik | Cel w VIEW-09 |
|---|---|---|
| `SavedViewsRail` | `components/catalog/saved-views-rail.tsx` | Saved views rail pod headerem (bez zmian) |
| `CompletenessBadge` | `components/catalog/completeness-badge.tsx` | Bez zmian (grid kolumna) |
| `ProductsGrid` | `components/catalog/products-grid.tsx` | Bez zmian (kolumna lock → VIEW-18) |
| `SaveViewModal` | `components/catalog/save-view-modal.tsx` | Reuse + dodanie ikoną picker dla Smart Preset |
| `useCatalogSearch` | `features/catalog/search/use-catalog-search.ts` | Rozszerzenie o `smartPresetId` + filter DSL parametr |
| `jsonFetch` | `lib/http.ts` | JWT-aware HTTP (defensive po hotfix #525) |
| `ToastProvider` + `useToast` | `components/ui/toast.tsx` | Smart Preset save success toast |

#### Nowe komponenty
| Komponent | Plik | LOC | Props |
|---|---|---|---|
| `SmartFilterPresetsRow` | `components/catalog/smart-filter-presets-row.tsx` | ~140 | `{ activeId, presets, counts, onSelect, onCreate }` |
| `SaveAsSmartPresetModal` | `components/catalog/save-as-smart-preset-modal.tsx` | ~100 | `{ open, onClose, currentFilters, onSaved }` |
| `AdvancedFilterPanel` | `components/catalog/advanced-filter-panel.tsx` | ~280 | `{ open, mode, setMode, conditions, setConditions, onApply, onClose, onClear, onSaveAsView, onSaveAsPreset }` |
| `FilterChip` | `components/catalog/filter-chip.tsx` | ~140 | `{ label, op, value, attrType, options, onChange, onRemove }` (zastępuje `FilterPill`) |
| `FilterChipsBar` | `components/catalog/filter-chips-bar.tsx` | ~80 | `{ chips, onRemove, onClearAll, urlShareable }` |

#### Modyfikacje
| Plik | Zmiana |
|---|---|
| `features/catalog/products/list.tsx` | Topbar refactor: `SmartFilterPresetsRow` pod `SavedViewsRail` + toolbar bez 4 hardcoded `FilterPill` (zastąpione button *„Filtruj po atrybucie 3"* → otwiera Advanced panel) + `FilterChipsBar` pod toolbar + `AdvancedFilterPanel` renderowany conditionally |
| `features/catalog/search/use-catalog-search.ts` | Dodaj `smartPresetId?: string` + `filterDsl?: FilterDsl` parametry; resolver po stronie BE |
| `components/catalog/save-view-modal.tsx` | Dodaj `icon` picker (emoji set lub lucide IconPicker) — reuse w SaveAsSmartPresetModal |
| `locales/pl.json` + `en.json` | ~40 nowych kluczy |
| `app.tsx` | (Już zmounted ToastProvider w VIEW-05) — bez zmian |

#### Usuwane
| Plik | Powód |
|---|---|
| `components/catalog/advanced-filter-builder.tsx` (Sheet, 6KB) | Zastąpiony przez `advanced-filter-panel.tsx` (push-down sticky) |
| `components/catalog/filter-pill.tsx` (3KB) | Zastąpiony przez `filter-chip.tsx` z edit popover |
| `components/catalog/product-filter-chips.tsx` (2KB) | Zastąpiony przez `filter-chips-bar.tsx` |

### 3.3 State management

- `query: string` — quick search input value (debounce 800ms zgodnie z PRD §13.1, NIE 200ms jak teraz).
- `conditions: FilterCondition[]` — array `{attr, op, value}` (płaski grid mode).
- `activeSmartPresetId: string | null` — wybrany preset (clear gdy user edytuje conditions ręcznie).
- `advancedOpen: boolean` — push-down panel open/closed (default closed; persisted w localStorage `products.advancedOpen`).
- `chips: Chip[]` — derived state z `conditions` (1 chip = 1 condition).
- `urlShareable: string` — derived URL z aktualnymi conditions.
- `showSaveAsPresetModal: boolean` — modal trigger.

**Mutacje + invalidacje:**
- `GET /api/smart-filter-presets` (z `?counts=true` per preset) → `refetch()` po POST/DELETE.
- `POST /api/smart-filter-presets` z `{name, icon, query: FilterDsl}` → toast success + refetch.
- `DELETE /api/smart-filter-presets/{id}` → confirm + refetch.
- `GET /api/search/products?smart_preset=<id>` lub `?filter=<filterDsl>` → useCatalogSearch refetch.

**Cache:** Refine `useList` + `useCatalogSearch` query keys rozszerzone o `smartPresetId` + `filterDsl` hash.

### 3.4 Struktura sekcji widoku (kolejność renderu)

1. **Header** — breadcrumb-line *„Workspace · katalog"* + h1 *„Produkty"* 32px (lewa) + total SKU + last sync + Mercure live indicator (prawa). (Z VIEW-05 baseline, bez zmian).
2. **Saved views rail** — poziomy rail z pillami (`SavedViewsRail`, bez zmian).
3. **🆕 Smart filter presets row** — sticky pasek z 5 built-in + user-defined chipami.
4. **Toolbar** — search flex-1 + button *„Filtruj po atrybucie [N]"* (otwiera Advanced panel) + segmented Płasko/Drzewo + Cmd+K button gradient violet (VIEW-19 implementacja, w VIEW-09 disabled z badge *„VIEW-19"*) + Import + Nowy produkt.
5. **🆕 Filter chips bar** — *„Aktywne filtry"* label + chipy z edit popover + *„Wyczyść wszystkie"* + *„Skopiuj URL z filtrami"*.
6. **🆕 Advanced filter panel** (conditional na `advancedOpen`) — push-down sticky, grid mode only.
7. **ProductsGrid** — 12-col grid (bez zmian).
8. **Pagination footer** — total + per page + paginator (bez zmian).
9. **BulkBar** (sticky bottom-6, conditional, VIEW-12+ rozszerzenie z VIEW-05 placeholderów).

### 3.4a Mapping element-po-elemencie z prototypu

#### SmartFilterPresetsRow (mockup `list-view-v2.jsx` l. 71-107)

Wrapper: `<div class="rounded-3xl bg-white soft-shadow border border-zinc-100 px-3 py-2.5 flex items-center gap-2">`.

- Lewa sekcja (~120px): `<span class="h-7 w-7 rounded-xl bg-zinc-900 text-white grid place-items-center">` z `<Sparkles>` lucide ikoną (zamiast IL2.zap) + `<div class="leading-tight">` z `<div class="text-[11.5px] font-semibold tracking-tight">Smart filtry</div>` + `<div class="text-[10px] text-zinc-400 inline-flex items-center gap-1">` zawierające `<span class="font-mono px-1 rounded bg-zinc-100 text-zinc-500">reguły</span><span>· LLM od Fazy 1</span>` (**marketing-honest copy** — PRD §11 krytyczna nota).
- Vertical separator: `<span class="h-7 w-px bg-zinc-100" />`.
- Scrollable chip area (flex-1, `overflow-x-auto scrollbar-thin`):
  - Per preset chip (5 built-in + N user-defined): `<button onClick={...} title={preset.rule} class="group shrink-0 inline-flex items-center gap-2 h-9 px-3 rounded-2xl text-[12.5px] font-medium transition border ${isActive ? 'bg-zinc-900 text-white border-zinc-900' : 'bg-zinc-50 text-zinc-700 border-zinc-100 hover:bg-white hover:border-zinc-200'}">` zawierający emoji icon + name + count `font-mono num`.
- Prawa: `<button onClick={onCreate} class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-2xl text-[12px] text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50">` z `<Plus>` lucide + *„Własny preset"*.

**5 built-in presets** (PRD §8.2, seedowane w migracji BE):

| Slug | Icon | Name (PL) | Name (EN) | Query DSL |
|---|---|---|---|---|
| `inconsistent-translations` | 🌐 | Niespójne tłumaczenia | Inconsistent translations | `{op: "AND", conditions: [{attr: "description.pl", op: "IS NOT EMPTY"}, {attr: "description.en", op: "IS EMPTY"}]}` |
| `missing-images` | 📷 | Brakujące zdjęcia | Missing images | `{attr: "main_image", op: "IS EMPTY"}` |
| `weak-seo` | 🔍 | Niepełne SEO | Weak SEO | `{op: "AND", conditions: [{attr: "description", op: "IS NOT EMPTY"}, {attr: "meta_description", op: "IS EMPTY"}]}` |
| `red-low-completeness` | 🔴 | Czerwone (<50%) | Red (<50%) | `{attr: "completeness_pct", op: "<", value: 50}` |
| `no-category` | 📂 | Bez kategorii | No category | `{attr: "category", op: "IS EMPTY"}` |

#### SaveAsSmartPresetModal

shadcn `<Dialog>` z headerem *„Zapisz jako Smart Preset"* + body z `<Input>` (name) + `<EmojiPicker>` (icon, ~20 wybranych z lucide IconPicker) + read-only preview aktualnych conditions (DSL JSON pretty-printed) + footer Anuluj + Zapisz.

**Walidacja:**
- name min 3 znaki max 60.
- conditions array nie może być pusty.
- icon obowiązkowy.

#### AdvancedFilterPanel (mockup `list-v2-overlays.jsx` l. 40-146)

Wrapper: `<div class="rounded-3xl bg-white soft-shadow-lg border border-zinc-100 overflow-hidden fade-in">`.

**Header** (`px-5 h-12 flex items-center gap-3 border-b border-zinc-100`):
- Label: `<span class="text-[11px] uppercase tracking-wider font-semibold text-zinc-500">Filtr zaawansowany</span>`.
- Mode toggle: `<div class="h-7 rounded-xl bg-zinc-100 inline-flex items-center p-0.5">`:
  - Grid button (aktywny w VIEW-09): `bg-white text-zinc-900 soft-shadow`.
  - Query button (disabled w VIEW-09 z badge *„VIEW-09b"* `bg-amber-100 text-amber-700 px-1 py-px rounded text-[9.5px]`).
- ml-auto: count `<span class="text-[11.5px] text-zinc-400 num">{N} warunk{...}</span>` + *„Wyczyść"* button + ✕ close button.

**Body grid mode** (`p-5`):
- Lista conditions, każda jako `<div class="flex items-center gap-2">`:
  - i === 0: `<span class="text-[11px] uppercase tracking-wider font-semibold text-zinc-400 w-12">Gdzie</span>`.
  - i > 0: `<select>` z AND/OR (`h-9 w-12 text-[11px] uppercase tracking-wider font-semibold text-zinc-500 bg-zinc-50 rounded-lg px-1`).
  - Attribute select `<select>` z optgroup *„Ulubione"* (star: true) + *„Wszystkie atrybuty"* (~min-w-[160px]).
  - Type badge `<span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-500">{type}</span>`.
  - Operator select (min-w-[120px], font-mono) — w VIEW-09 hard-coded `["=", "≠", "IN", "NOT IN", "IS EMPTY", "IS NOT EMPTY"]`; pełne operatory per typ w VIEW-10.
  - Value input (flex-1, conditional na non-empty op).
  - Trash icon button (`h-9 w-9 hover:text-rose-600 hover:bg-rose-50 rounded-lg`).
- `<button onClick={addCond} class="mt-3 text-[12.5px] text-zinc-500 hover:text-zinc-900 inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg hover:bg-zinc-100">` + `<Plus>` + *„Dodaj warunek"*.

**Footer** (`px-5 h-12 flex items-center gap-3 border-t border-zinc-100 bg-zinc-50/50`):
- Dopasuj AND/OR toggle: `<div class="h-6 rounded-lg bg-white border border-zinc-200 inline-flex items-center p-0.5">` z 2 buttonami *„Wszystkie (AND)"* (default) / *„Dowolne (OR)"*.
- ml-auto: `<span class="text-[11.5px] text-zinc-400 inline-flex items-center gap-1.5">` z `<Link2>` lucide + *„URL zaktualizowany — udostępnij filtr"*.
- *„Zapisz jako Saved View"* button (link do existing SaveViewModal).
- *„Zapisz jako Smart Preset"* button (link do nowego SaveAsSmartPresetModal).
- *„Zastosuj filtr"* button primary `h-9 px-4 rounded-xl bg-zinc-900 text-white text-[12.5px] font-medium hover:bg-zinc-800`.

#### FilterChip (mockup `list-view-v2.jsx` l. 109-151)

Single-chip button z popover:

Wrapper: `<div class="relative inline-flex" ref={ref}>`.

Button: `<button onClick={() => setOpen(o => !o)} class="h-9 pl-3 pr-1.5 rounded-2xl bg-zinc-900 text-white text-[12.5px] font-medium inline-flex items-center gap-1.5 hover:bg-zinc-800">`:
- Label: `<span class="text-white/60">{label}</span>` (np. *„Marka"*).
- Operator: `<span class="text-white/50 font-mono text-[11px]">{op}</span>` (np. *„IN"*).
- Value: `<span class="font-medium">{value}</span>` (np. *„Festo, Bosch"*).
- ✕: `<span onClick={(e) => { e.stopPropagation(); onRemove(); }} class="ml-1 h-5 w-5 rounded-full hover:bg-white/15 grid place-items-center text-white/70">` z SVG X.

Popover (conditional `open`): `<div class="absolute top-11 left-0 z-30 min-w-[200px] bg-white rounded-2xl soft-shadow-lg border border-zinc-100 p-2 cmdk-pop">`:
- Operator section: `<div class="text-[10.5px] uppercase tracking-wider font-semibold text-zinc-400 px-2 pb-1">Operator</div>` + flex-wrap buttons per op.
- Value section: `<div class="text-[10.5px] uppercase tracking-wider font-semibold text-zinc-400 px-2 py-1 mt-1">Wartość</div>` + options list (`px-2 py-1.5 rounded-lg text-[12.5px] text-left hover:bg-zinc-50`).

Click-outside hook: `useEffect` z `mousedown` listener.

#### FilterChipsBar

Wrapper: `<div class="flex items-center gap-2 flex-wrap">` (conditional na `chips.length > 0`).
- Label: `<span class="text-[10.5px] uppercase tracking-wider font-semibold text-zinc-400">Aktywne filtry</span>`.
- Chips: array `<FilterChip>`.
- *„Wyczyść wszystkie"* underlined link button.
- Vertical separator `<span class="text-zinc-300">·</span>`.
- *„Skopiuj URL z filtrami"* button z `<Link2>` lucide → `navigator.clipboard.writeText(window.location.href)` + toast.

#### Toolbar refactor (mockup l. 153-223)

Layout: `<div class="flex items-center gap-2.5 flex-wrap">`:
- **Search input** (flex-1 min-w-[280px] relative, `h-11`):
  - `<Search>` icon absolute left.
  - `<input type="search" placeholder="SKU, nazwa, EAN, brand, tagi…" class="w-full h-11 pl-10 pr-32 rounded-2xl bg-white soft-shadow text-[14px]">`.
  - Hint right: `<span class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex items-center gap-1.5 text-[10.5px] text-zinc-400">` z *„debounce 800ms"* + `<kbd>/</kbd>` (slash to focus shortcut).
- **„Filtruj po atrybucie [N]"** button (zastępuje 4 hardcoded FilterPill): `<button onClick={toggleAdvanced} class="h-11 px-3.5 rounded-2xl ${advancedOpen ? 'bg-zinc-900 text-white border-zinc-900' : 'bg-white text-zinc-700 border-zinc-100 soft-shadow'}">` z `<SlidersHorizontal>` + label + count chip + `<ChevronDown>` rotate-180 gdy open.
- **Segmented Płasko/Drzewo** (bez zmian z VIEW-05).
- **Cmd+K button** (gradient violet — w VIEW-09 placeholder, render z `<Sparkles>` + label + `<kbd>⌘K</kbd>`, onClick `toast.info('Cmd+K — VIEW-19')`).
- Vertical separator.
- **Import** button (bez zmian, disabled placeholder z VIEW-05).
- **Nowy produkt** button (bez zmian).

### 3.5 i18n keys (~40 nowych)

Dodaj do `apps/admin/src/locales/pl.json` i `en.json`:

```
products.smart_filters.label
products.smart_filters.subtitle_rules
products.smart_filters.subtitle_llm_phase_1
products.smart_filters.custom_preset_button
products.smart_filters.save_as_preset_title
products.smart_filters.save_as_preset_name_placeholder
products.smart_filters.save_as_preset_icon_label
products.smart_filters.save_as_preset_save
products.smart_filters.save_as_preset_cancel
products.smart_filters.save_success
products.smart_filters.delete_confirm

products.smart_filters.builtin.inconsistent_translations
products.smart_filters.builtin.missing_images
products.smart_filters.builtin.weak_seo
products.smart_filters.builtin.red_low_completeness
products.smart_filters.builtin.no_category

products.advanced_filter.title
products.advanced_filter.mode_grid
products.advanced_filter.mode_query
products.advanced_filter.mode_query_phase_label
products.advanced_filter.where_label
products.advanced_filter.condition_count_one
products.advanced_filter.condition_count_few
products.advanced_filter.condition_count_other
products.advanced_filter.clear
products.advanced_filter.add_condition
products.advanced_filter.match_label
products.advanced_filter.match_all
products.advanced_filter.match_any
products.advanced_filter.url_updated
products.advanced_filter.save_as_view
products.advanced_filter.save_as_preset
products.advanced_filter.apply

products.filter_chips.active_label
products.filter_chips.clear_all
products.filter_chips.copy_url
products.filter_chips.copy_url_success
products.filter_chips.operator_label
products.filter_chips.value_label

products.toolbar.filter_by_attribute_button
products.toolbar.search_placeholder
products.toolbar.search_debounce_hint
products.toolbar.cmdk_button
products.toolbar.cmdk_phase_19_toast

products.attribute_groups.favorites
products.attribute_groups.all
```

Ban literałów w JSX — wszystko przez `t()`. Sprawdzone przez Biome rule + manual review.

### 3.6 a11y

- axe-core **0 violations serious/critical** na `/products`.
- **SmartFilterPresetsRow**: `role="tablist"` + `role="tab" aria-selected={isActive}` per chip. ←/→ arrow keys move focus. Enter/Space apply.
- **FilterChip**: shadcn `<Popover>` daje focus trap + aria-expanded + ESC close out-of-the-box. Button `aria-label="{label}: {value} (kliknij żeby edytować, ✕ żeby usunąć)"`.
- **AdvancedFilterPanel**: `role="region" aria-label="Zaawansowany filtr"`. `<select>` z `aria-label` per atrybut/operator. Mode toggle `role="tablist"` + `role="tab"`. Query tab `aria-disabled="true"` + visible badge.
- **SaveAsSmartPresetModal**: shadcn `<Dialog>` out-of-the-box focus trap. Input `aria-required="true"`. Icon picker grid `role="radiogroup"`.
- **FilterChipsBar**: `role="region" aria-label="Aktywne filtry"`. *„Wyczyść wszystkie"* button `aria-label="Wyczyść wszystkie filtry"`. *„Skopiuj URL"* button `aria-label="Skopiuj URL ze stanem filtrów"`.
- Focus ring: `focus-visible:ring-2 focus-visible:ring-zinc-900` na wszystkich interactive elements.
- Color contrast: AA pass (zinc-900 na zinc-50, white na zinc-900, violet-700 na violet-50, amber-700 na amber-100 dla badge).

### 3.7 Locales (multilingual fields)

VIEW-09 nie pokazuje multilingual product fields (name/description w gridzie — z VIEW-05 baseline default locale). **Smart Preset name** jest multilingual w bazie (JSONB `{"pl": ..., "en": ...}` zgodnie z CLAUDE.md punkt 8). UI w MVP używa aktualnego user locale (FE `t()` z fallback do PL).

### 3.8 Empty / loading / error states

- **Smart presets empty (built-in tylko)**: 5 system-shipped widocznych zawsze (seedowane w migracji). Brak user-defined → brak chipów po built-in.
- **Smart presets loading**: skeleton 3 chipy (`<div class="h-9 w-32 rounded-2xl bg-zinc-100 animate-pulse">`).
- **Smart preset apply no results**: Grid pokazuje empty state *„Brak produktów pasujących do filtra"* + button *„Wyczyść filtr"*.
- **Save preset error**: toast `toast.error('Nie udało się zapisać presetu — sprawdź połączenie')` + modal pozostaje otwarty.
- **Advanced filter no conditions**: button *„Zastosuj filtr"* disabled + hint *„Dodaj warunek żeby filtrować"*.
- **URL copy failure** (clipboard permission denied): toast `toast.error('Skopiuj URL ręcznie z paska adresu')`.

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Request | Response | Permissions | Filtry/sort/paginacja |
|---|---|---|---|---|---|
| GET | `/api/smart-filter-presets` | `?counts=true` (opcjonalne) | `{data: SmartFilterPreset[], counts?: {[id]: number}}` | authenticated user, all roles | TenantFilter + user filter (`tenant_shared OR own`); sort by `is_built_in DESC, sort_order ASC, created_at DESC`; brak paginacji (limit 50 max) |
| POST | `/api/smart-filter-presets` | `{name: {pl, en}, icon: string, query: FilterDsl}` | `SmartFilterPreset` 201 | authenticated user | TenantAssignment + user_id auto-set |
| PATCH | `/api/smart-filter-presets/{id}` | partial `{name?, icon?, query?, sort_order?}` | `SmartFilterPreset` 200 | owner only (built-in immutable, returns 403 z Problem Details) | — |
| DELETE | `/api/smart-filter-presets/{id}` | — | 204 | owner only (built-in 403) | — |
| GET | `/api/search/products` | extended: `?smart_preset=<slug-or-id>` lub `?filter=<base64-encoded-FilterDsl>` | unchanged | unchanged | resolver smart preset → FilterDsl → Meilisearch filter |

**Errors w RFC 7807 Problem Details:**
- 400 `validation_failed` (name <3 chars, icon empty, query invalid).
- 403 `built_in_immutable` (PATCH/DELETE on built-in).
- 404 `preset_not_found`.
- 409 `name_duplicate` (per tenant + user).

### 4.2 Encje / schema / migracje

**Nowa encja** `App\Catalog\Domain\Entity\SmartFilterPreset` (wzorzec analogiczny do `SavedView`):

```php
namespace App\Catalog\Domain\Entity;

class SmartFilterPreset implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;          // NULL = system-shipped
    private ?Uuid $userId;                    // NULL = tenant-shared (Faza 1+); user-defined = owner
    private string $slug;                     // unique per tenant
    /** @var array{pl: string, en: string} */
    private array $name;
    private string $icon;                     // emoji string lub lucide icon name
    /** @var array<string, mixed> */
    private array $query;                     // FilterDsl JSONB
    private bool $isBuiltIn = false;
    private int $sortOrder = 0;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    // ... gettery + assignTenant() + setters z touch()
}
```

**Migracja** `apps/api/migrations/Version20260513120000.php`:

```sql
CREATE TABLE smart_filter_presets (
    id UUID PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE, -- NULL = system
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,     -- NULL = tenant-shared
    slug VARCHAR(64) NOT NULL,
    name JSONB NOT NULL,                                      -- {pl, en}
    icon VARCHAR(64) NOT NULL,
    query JSONB NOT NULL,                                     -- FilterDsl
    is_built_in BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX uniq_smart_presets_slug ON smart_filter_presets (COALESCE(tenant_id, '00000000-0000-0000-0000-000000000000'::uuid), COALESCE(user_id, '00000000-0000-0000-0000-000000000000'::uuid), slug);
CREATE INDEX idx_smart_presets_tenant ON smart_filter_presets (tenant_id) WHERE tenant_id IS NOT NULL;
CREATE INDEX idx_smart_presets_user ON smart_filter_presets (user_id) WHERE user_id IS NOT NULL;
CREATE INDEX idx_smart_presets_builtin ON smart_filter_presets (is_built_in) WHERE is_built_in = TRUE;
```

**Migracja + seed** w jednej `up()` — 5 built-in z `tenant_id=NULL, user_id=NULL, is_built_in=TRUE` (system-shipped global).

**Down()** drop table + indeksy (reversible).

### 4.3 Listenery / event subscribers

- `TenantAssignmentListener` (istnieje) — auto-set `tenant_id` na prePersist gdy user-defined.
- Brak nowych listenerów.

### 4.4 Permissions / RBAC

`SmartFilterPresetVoter extends Voter`:
- `READ` — every authenticated user (system + own + tenant-shared).
- `WRITE` (PATCH) — owner only (`getUserId() === currentUser`). Built-in returns false.
- `DELETE` — owner only. Built-in returns false.

**MVP brak per-role gating** (zgodnie z CLAUDE.md punkt 6 + PRD §11.5).

Audit log: SmartFilterPreset create/update/delete loguje przez Doctrine event listener (z epik 0.11.4 AuditBundle, jeśli istnieje; w VIEW-09 jako TODO follow-up jeśli AuditBundle nie istnieje jeszcze).

### 4.5 Provenance

VIEW-09 nie pisze do `object_values` — N/A.

### 4.6 Worker / async

VIEW-09 nie odpala asynchronicznych jobów. Smart preset CRUD jest sync (low frequency operations).

### 4.7 Real-time (Mercure)

VIEW-09 nie używa Mercure. Smart preset add/remove jest re-fetched po POST/DELETE response.

## 5. Sub-tasks (checklist)

### Backend
- [ ] Migracja `Version20260513120000.php` z CREATE TABLE + 5 built-in seed.
- [ ] Encja `SmartFilterPreset.php` w `apps/api/src/Catalog/Domain/Entity/`.
- [ ] Doctrine ORM XML mapping w `apps/api/config/doctrine/`.
- [ ] Repository `SmartFilterPresetRepository.php`.
- [ ] ApiResource declaration (REST + JSON-LD przez API Platform).
- [ ] Custom controller `SmartFilterPresetCountsController.php` dla `?counts=true` (sub-query per preset).
- [ ] Voter `SmartFilterPresetVoter.php` z READ/WRITE/DELETE.
- [ ] FilterDsl resolver w `apps/api/src/Catalog/Application/Filter/FilterDslResolver.php` (basic version z 6 operatorami — pełne operatory → VIEW-10).
- [ ] Rozszerzenie `SearchController` o `smart_preset` + `filter` query params.
- [ ] PHPUnit unit testy: `SmartFilterPresetTest` (constructor + assignTenant + setters).
- [ ] ApiTestCase integration testy: `SmartFilterPresetControllerTest` (401 + 403 built-in + 404 + 409 duplicate + happy path GET/POST/PATCH/DELETE + multi-tenancy isolation).
- [ ] OpenAPI snapshot regen.

### Frontend
- [ ] `lib/filters/filter-dsl.ts` — TS types `FilterCondition`, `FilterDsl`, `FilterGroup`.
- [ ] `lib/filters/url-serializer.ts` — basic flat conditions ↔ URLSearchParams (single level; nested → VIEW-09b).
- [ ] `lib/filters/use-smart-presets.ts` — Refine `useList` hook z counts.
- [ ] `components/catalog/smart-filter-presets-row.tsx` — nowy.
- [ ] `components/catalog/save-as-smart-preset-modal.tsx` — nowy.
- [ ] `components/catalog/advanced-filter-panel.tsx` — nowy (grid mode only).
- [ ] `components/catalog/filter-chip.tsx` — nowy (zastępuje `filter-pill.tsx`).
- [ ] `components/catalog/filter-chips-bar.tsx` — nowy.
- [ ] `features/catalog/products/list.tsx` — refactor topbara.
- [ ] `features/catalog/search/use-catalog-search.ts` — rozszerzenie o `smartPresetId` + `filterDsl`.
- [ ] DELETE `components/catalog/advanced-filter-builder.tsx`.
- [ ] DELETE `components/catalog/filter-pill.tsx`.
- [ ] DELETE `components/catalog/product-filter-chips.tsx`.
- [ ] `locales/pl.json` + `en.json` — ~40 kluczy.
- [ ] Update `app.tsx` — confirm ToastProvider mounted (z VIEW-05).

### E2E + Integration
- [ ] `apps/admin/e2e/products-view-09.spec.ts`:
  - Scenariusz 1 happy path: login → smart preset *„Czerwone <50%"* click → grid filtruje → URL update.
  - Scenariusz 2 advanced filter: open panel → add condition `brand IN [Festo, Bosch]` → apply → chip pojawia się + URL update.
  - Scenariusz 3 user-defined preset: open advanced panel → add 2 conditions → *„Zapisz jako Smart Preset"* → modal → save → chip pojawia się w row.
  - Scenariusz 4 a11y axe-core.
  - Scenariusz 5 URL share: copy URL with filters → open in new context → filter restored.

### Testy non-functional
- [ ] PHPStan max: 0 errors.
- [ ] Biome strict: 0 errors.
- [ ] TypeScript strict: 0 errors.
- [ ] PHPUnit + ApiTestCase: nowe testy zielone + regression.
- [ ] Playwright E2E: 5 scenariuszy zielone.
- [ ] axe-core: 0 violations serious/critical.
- [ ] Lighthouse a11y =100, performance ≥85.
- [ ] Bundle size FE Δ <50KB gzip.
- [ ] composer audit + pnpm audit: 0 high/critical.
- [ ] OpenAPI snapshot zaktualizowany.

### Dokumentacja
- [ ] PR description z side-by-side mockup vs build screenshots.
- [ ] PR description z lista świadomych odejść (Query mode disabled w VIEW-09, pełne operatory hold w VIEW-10).
- [ ] PR description z linkami do follow-up VIEW-09b, VIEW-10..VIEW-19.
- [ ] `agent/current_status.md` — sekcja VIEW-09.
- [ ] `Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md` — update statusu (lista v2 fundament UI shipped).

### Manual smoke (operator po merge)
- [ ] Login `admin@demo.localhost / changeme` na `https://pim.localhost`.
- [ ] `/products` → assert SmartFilterPresetsRow widoczny z 5 built-in chipami + counts.
- [ ] Click *„🌐 Niespójne tłumaczenia"* → chip aktywny czarny + grid filtruje.
- [ ] Click *„Filtruj po atrybucie"* → AdvancedFilterPanel push-down pojawia się.
- [ ] Add condition: brand = Festo → Apply → chip *„Marka = Festo"* w FilterChipsBar.
- [ ] Click chip body → popover z operator + value → zmień na *„IN Festo, Bosch"* → chip update.
- [ ] Click ✕ na chipie → chip znika.
- [ ] *„Skopiuj URL z filtrami"* → toast success → URL w schowku.
- [ ] *„Zapisz jako Smart Preset"* w panel footer → modal → name + icon → save → toast + chip w row.
- [ ] Delete user-defined preset (kebab w row) → confirm → chip znika.
- [ ] Multi-tenant: tenant B widzi tylko własne + system-shipped, NIE user-defined tenanta A.
- [ ] DevTools Network: GET `/api/smart-filter-presets?counts=true` < 200ms.
- [ ] DevTools Console: brak czerwonych errorów.

## 6. Acceptance criteria — funkcjonalne

- [x] SmartFilterPresetsRow renderuje 5 built-in chipów (pixel-perfect z mockupem `list-view-v2.jsx` l. 71-107) + dynamicznie user-defined.
- [x] Aktywny preset chip ma `bg-zinc-900 text-white`, nieaktywny `bg-zinc-50 text-zinc-700`.
- [x] Click preset → grid filtruje + URL update + activeSmartPresetId state set.
- [x] Click drugi raz aktywnego preset → toggle off → grid pokazuje wszystko.
- [x] *„Własny preset"* button otwiera SaveAsSmartPresetModal (gdy `conditions.length > 0`) lub tooltip *„Najpierw dodaj warunek w Advanced filter"* (gdy puste).
- [x] AdvancedFilterPanel push-down sticky pojawia się po click *„Filtruj po atrybucie [N]"*. Grid mode default. Query mode tab disabled z badge *„VIEW-09b"*.
- [x] Add/remove conditions w grid mode (Akeneo style). Per condition: atrybut select + operator + value input + trash button.
- [x] Apply filtr → chipy w FilterChipsBar + URL update + grid refetch.
- [x] FilterChip body click otwiera popover (operator + value picker). ✕ kasuje chip.
- [x] *„Wyczyść wszystkie"* link czyści wszystkie chipy + conditions.
- [x] *„Skopiuj URL z filtrami"* button kopiuje URL do schowka + toast success.
- [x] Save as Smart Preset modal: name (multilingual jeśli i18n active) + icon picker + read-only preview conditions → POST `/api/smart-filter-presets` → toast + chip pojawia się w row.
- [x] Built-in preset PATCH/DELETE → 403 Problem Details + toast error.
- [x] Multi-tenancy: user A nie widzi user-defined presets user B.
- [x] i18n PL/EN togglue wszystkie nowe stringi.

## 7. Acceptance criteria — non-functional (TWARDE GATES)

- **Performance**:
  - p95 `GET /api/smart-filter-presets?counts=true` < 200ms na seed 50k SKU (k6 raport w PR).
  - p95 `POST /api/smart-filter-presets` < 100ms.
  - p95 `GET /api/search/products?smart_preset=<id>` < 300ms na seed 50k SKU.
- **N+1 query check**: `?counts=true` używa pojedynczego query z LATERAL JOIN lub batch count, NIE N+1. EXPLAIN ANALYZE w PR description.
- **Indeksy**: wszystkie nowe WHERE kolumny pokryte indeksami (`tenant_id`, `user_id`, `is_built_in`, `slug` unique).
- **Pagination**: smart_filter_presets list max 50 (hard limit per tenant).
- **Memory (worker)**: N/A (sync handler).
- **Bundle size FE**: Δ <50KB gzip (Vite build report w PR).
- **Lighthouse**: performance ≥85, a11y =100, best-practices ≥90.
- **PHPStan max**: 0 errors.
- **Biome strict**: 0 errors.
- **TypeScript strict**: 0 errors.
- **PHPUnit coverage**: ≥80% nowej logiki domenowej (`SmartFilterPreset` + `FilterDslResolver`).
- **ApiTestCase**: każdy nowy endpoint ma test 401 + 403 + 404 + 409 + walidacja + happy path + multi-tenancy isolation.
- **Playwright E2E**: 5 scenariuszy zielone.
- **axe-core**: 0 violations serious/critical na `/products`.
- **composer audit + pnpm audit**: 0 high/critical.
- **Multi-tenancy**: cross-tenant read test = 0 user-defined wyników (built-in widoczne).
- **RBAC**: voter test dla owner + non-owner + built-in immutability.
- **Audit log**: PATCH/DELETE pisze entry (jeśli AuditBundle istnieje; inaczej TODO follow-up).
- **Provenance**: N/A.
- **i18n coverage**: ~40 nowych kluczy obecnych w `pl.json` i `en.json`.
- **OpenAPI snapshot**: `docs/api-spec/v0.json` zaktualizowany (`docker compose exec -T -e APP_ENV=dev -e APP_DEBUG=0 api bin/console api:openapi:export | python3 -m json.tool > docs/api-spec/v0.json`).

## 8. Smoke-test scenariusze (manualne, dla operatora)

1. Login `admin@demo.localhost / changeme` na `https://pim.localhost`.
2. Nawigacja: sidebar → Produkty → `/products`.
3. Assert SmartFilterPresetsRow widoczny pod SavedViewsRail z 5 built-in chipami: 🌐 Niespójne tłumaczenia, 📷 Brakujące zdjęcia, 🔍 Niepełne SEO, 🔴 Czerwone (<50%), 📂 Bez kategorii. Każdy z counter (server-side count).
4. Click *„🔴 Czerwone (<50%)"* → chip czarny aktywny → grid filtruje do produktów z `completenessPct < 50` → URL update `?smart_preset=red-low-completeness`.
5. Click *„Filtruj po atrybucie [0]"* button w toolbarze → AdvancedFilterPanel push-down rozszerza się pod toolbar.
6. W panelu: dodaj condition: atrybut *„Marka"* + operator *„IN"* + value *„Festo, Bosch"*. Click *„Zastosuj filtr"*.
7. Assert: panel zamyka się, w FilterChipsBar pojawia się chip *„Marka IN Festo, Bosch"*, URL update.
8. Click chip body → popover z operator picker (`=`, `≠`, `IN`, `NOT IN`) + value picker (lista brandów). Zmień na *„NOT IN Bosch"*. Chip update.
9. Click ✕ na chipie → chip znika + URL czyści.
10. Click *„Filtruj po atrybucie"* → panel otwarty → click *„Wyczyść"* w header panel → wszystkie conditions usunięte.
11. Dodaj 2 conditions → click *„Zapisz jako Smart Preset"* w footer panel → SaveAsSmartPresetModal otwiera się.
12. Wpisz name *„Festo niski stock"* + wybierz icon ⚙️ → click Zapisz → toast success + chip *„⚙️ Festo niski stock"* pojawia się w SmartFilterPresetsRow.
13. Click *„🔴 Czerwone (<50%)"* (built-in) → click kebab/right-click → assert opcja Delete jest ukryta lub disabled (built-in immutable).
14. Click kebab przy user-defined *„⚙️ Festo niski stock"* → Delete → confirm modal → confirm → chip znika.
15. Multi-tenancy: zaloguj jako inny tenant (jeśli dostępny w dev) → `/products` → assert built-in 5 widocznych, user-defined *„⚙️ Festo niski stock"* nie.
16. *„Skopiuj URL z filtrami"* → toast success → paste URL w nowym tabie → assert filtry załadowane.
17. DevTools Network: GET `/api/smart-filter-presets?counts=true` < 200ms, GET `/api/search/products?smart_preset=red-low-completeness` < 300ms.
18. DevTools Console: brak czerwonych errorów.
19. Lighthouse `/products` → a11y =100, performance ≥85.

## 9. Edge cases / poza zakresem

### Edge cases pokryte
- Empty smart preset row (po deletecie wszystkich user-defined) → 5 built-in widocznych zawsze.
- Smart preset apply gdy grid loading → spinner, nie zacina.
- Save as preset gdy `conditions.length === 0` → button disabled z tooltipem.
- Save as preset z duplicate name (per tenant + user) → 409 Problem Details + toast error.
- Built-in preset PATCH/DELETE → 403 + toast error.
- URL share z preset usunętym (stale URL) → fallback graceful: redirect do `/products` bez preset + toast info *„Preset nie istnieje"*.
- AdvancedFilterPanel z 0 conditions → Apply button disabled z hint.
- FilterChip popover click-outside → close.

### Świadomie poza zakresem (deferred do follow-up VIEW-NN)
1. **Query mode AND/OR brackets** → VIEW-09b. W VIEW-09 mode toggle widoczny ale Query tab disabled z badge *„VIEW-09b"*.
2. **Pełne operatory per typ atrybutu (25 ops)** → VIEW-10. W VIEW-09 hardcoded 6 ops (`=`, `≠`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY`).
3. **URL hashed blob `?q=<base64>` dla query mode** → VIEW-09b.
4. **Cross-page selection toolbar** → VIEW-11. W VIEW-09 selection state z VIEW-05 (single page only).
5. **BulkBar 14 akcji + wizard** → VIEW-12+.
6. **Cmd+K palette** → VIEW-19. W VIEW-09 button gradient violet jest placeholder z toast *„Cmd+K — VIEW-19"*.
7. **Rollback toast + 24h cancel** → VIEW-17.
8. **Per-attribute lock w gridzie kolumna `lock`** → VIEW-18.
9. **Smart preset migration on schema change** (np. `completeness_pct` rename) → defer; ręczna naprawa przez admin.
10. **AuditLog dla SmartFilterPreset CRUD** — TODO jeśli AuditBundle (epik 0.11.4) nie istnieje. W innym przypadku auto-loggowane.
11. **react-querybuilder dla Advanced filter UI**: w VIEW-09 from scratch (prostsze, ~280 LOC). Decyzja library vs from scratch → VIEW-09b.

### Edge cases pomijane (low-priority)
- Mobile/tablet viewport <768px — admin desktop-first.
- Concurrent edits Smart Preset (user A edytuje, user B widzi stary) → MVP last-write-wins, brak optymistic locking.
- Smart preset z >100 conditions → not enforced (UI nie pozwoli przez UX, ale walidacja BE max 20 conditions per preset).
- Tenant-shared user-defined presets (`user_id IS NULL, tenant_id IS NOT NULL`) → schema supports, MVP brak UI button *„Udostępnij tenancie"* (Faza 1).

## 10. Powiązane ADR / dokumenty

**Nowy ADR (po merge VIEW-09 lub razem):**
- **ADR-015 — Filter DSL (JSONB) + URL serializer**: format flat conditions (VIEW-09) + nested AND/OR/NOT (VIEW-09b). Hashowany blob fallback. Resolver Meilisearch + Postgres `attributes_indexed @> ...`.

**Aktualizacje istniejących dokumentów (commit razem z PR):**
- `Project Plan/01-architektura-pim.md` — dodać sekcję dla Smart Filter Presets w §5 model danych (przeniesione z PRD §5.1).
- `Project Plan/02-plan-projektu-pim.md` — checkbox VIEW-09 + estymacja 40h + ryzyko R-30 (PRD §14.1).
- `agent/current_status.md` — sekcja *„2026-05-13: VIEW-09 marathon start"*.
- `agent/lessons.md` — sekcja *„Lessons z VIEW-09"* po merge.
- `Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md` — update statusu.
- `docs/api-spec/v0.json` — regenerowane.

**Memory updates:**
- Brak nowych memories — feedback patterns z `feedback_view_scope_literal.md` + `feedback_pim_destructive_volume_ops.md` przestrzegane.

**Brak nowego ADR-009 zmiany** — ObjectType pozostaje bez zmian (smart presets to feature meta, nie domain entity per kind).
