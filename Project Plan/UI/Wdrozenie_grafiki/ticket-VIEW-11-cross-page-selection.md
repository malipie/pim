# VIEW-11 — Cross-page selection toolbar (BaseLinker style)

## 1. Kontekst i cel

PRD §8.6 — selection state w MVP w `Set<string>` po stronie FE (limit ~200 visible per page). Kasia/Magda chcą szybkiej eskalacji: po zaznaczeniu wszystkich produktów na bieżącej stronie → button *„Zaznacz wszystkie 1247 pasujących"* → server-side count + lazy expansion do max 10k SKU (Pro tier) lub 50k (Enterprise).

VIEW-11 dostarcza:
- BE: `GET /api/products/count?filters=...` (total matching count) + reuse istniejącego endpointu `?smart_preset` / `?q=` z VIEW-10.
- FE: `SelectionToolbar` (mode `none | page | all-matching`) z fade-in animation pod toolbar.
- Selection state lifted do `useSelectionState` hook żeby BulkBar + Cmd+K (VIEW-19) dzieliły jednolity model.

## 2. Mockup

`list-view-v2.jsx` l. 226-254 — `SelectionToolbar` z bg-zinc-900 + count badge + prompt `Zaznacz wszystkie {matching} pasujących →`.

## 3. Zakres FE

**Nowe komponenty:**
- `components/catalog/selection-toolbar.tsx` (~120 LOC)
- `lib/selection/use-selection-state.ts` (~80 LOC)

**Modyfikacje:**
- `features/catalog/products/list.tsx` — replace local `selected` state z `useSelectionState({matchingCount})`. Toolbar renderowany pod `Toolbar` gdy `mode !== 'none'`.

## 4. Zakres BE

**Endpoint:**
- `GET /api/search/products?count_only=true` — variant istniejącego search, returns `{totalHits}` only (skip Meilisearch hits). Cheaper niż pełen page.
- `POST /api/products/select-all-matching` — body `{filter?, smart_preset?, limit?: 10000}`, returns `{ids: list<UUID>, capped: bool, totalMatched: int}`. Wymaga `customFilterExpression` + tenant scope.

## 5. Sub-tasks

- [ ] BE: dodaj `count_only` param w `SearchController::products()` skip hits + facets.
- [ ] BE: `POST /api/products/select-all-matching` w nowym `BulkSelectionController` z hard cap 10k IDs.
- [ ] BE: PHPUnit + ApiTestCase.
- [ ] FE: `useSelectionState` hook (modes + matchingCount).
- [ ] FE: `SelectionToolbar` pixel-perfect.
- [ ] FE: integracja w `list.tsx`.
- [ ] Playwright spec.

## 10. ADR
Bez nowych.
