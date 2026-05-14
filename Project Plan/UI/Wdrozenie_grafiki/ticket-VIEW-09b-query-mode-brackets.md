# VIEW-09b — Query mode AND/OR brackets editor w AdvancedFilterPanel

## 1. Kontekst i cel widoku

Po VIEW-10 (#539) lista produktów ma pełne 25 operatorów per typ + BE resolver + URL DSL serializer (base64 fallback `?q=<blob>`). VIEW-09b odblokowuje **drugi tryb** push-down panelu — Query mode z nested AND/OR/NOT brackets editor dla power users (Magda/Marcin, PRD §3.2 + §5.3 + §14.2 open).

W VIEW-09 mode toggle widoczny ale Query tab **disabled z badge `VIEW-09b`**. VIEW-09b: badge znika, tab klikalny, edytor renderuje rekursywne grupy do depth 3 (PRD §13.2 walidacja BE), apply przez istniejący `?q=<base64>` flow z VIEW-10.

Decyzja library vs from scratch: **from scratch** — react-querybuilder v8 to ~50KB gzip z dependency chain (dnd-kit, immer); 3-level limit + 9 ops + 8 typów to ~250 LOC własnego rekurencyjnego komponentu (`<QueryGroup>` self-referencing przez `<QueryCondition>` leaves + child groups). Mniej bundle, pełna kontrola nad Tailwind tokens pixel-perfect z mockupem.

## 2. Mockup / źródło designu

- **Read-only display reference** (mockup `list-v2-overlays.jsx` l. 116-126): kolorowe tokens `text-violet-700` (attr), `text-zinc-400` (operator + AND/OR/NOT), `text-emerald-700` (literal), `text-amber-700` (numeric).
- **Pixel-perfect Tailwind binding**: panel header + footer reused z VIEW-09 `AdvancedFilterPanel`. Query mode body to nowy `<QueryGroupEditor>` zastępujący grid conditions list w `<div className="p-5">`.
- **PRD §5.3** DSL format (recursive structure):

```json
{
  "operator": "AND",
  "conditions": [
    {"attr": "brand", "op": "IN", "value": ["Festo", "Bosch"]},
    {"operator": "OR", "conditions": [
      {"attr": "completeness_pct", "op": "<", "value": 50},
      {"attr": "description_en", "op": "IS EMPTY"}
    ]}
  ]
}
```

## 3. Zakres frontend (FE)

### 3.1 Routing
Bez zmian.

### 3.2 Komponenty

**Nowe:**
| Komponent | Plik | LOC | Cel |
|---|---|---|---|
| `QueryGroupEditor` | `components/catalog/query-group-editor.tsx` | ~180 | Rekursywny editor grupy AND/OR/NOT z `+ Dodaj warunek` / `+ Dodaj grupę` / `↻ Zmień AND/OR` / `× Usuń grupę` buttony |
| `QueryConditionRow` | `components/catalog/query-condition-row.tsx` | ~100 | Pojedynczy warunek leaf: attr select + operator picker + value input + delete |

**Modyfikacje:**
| Plik | Zmiana |
|---|---|
| `components/catalog/advanced-filter-panel.tsx` | Mode toggle Query tab → enabled, nested click switches `mode='query'` state. Conditional rendering `mode === 'grid' ? <grid editor> : <QueryGroupEditor>` |
| `features/catalog/products/list.tsx` | Stan `advancedMode: 'grid' \| 'query'` + `queryDsl: FilterDsl \| null`. Apply w query mode → `dslToBase64(queryDsl)` → `filterBlob` prop dla `useCatalogSearch` |

### 3.3 State management

`AdvancedFilterPanel` props rozszerzone:
- `mode: 'grid' \| 'query'`
- `setMode: (m) => void`
- `queryDsl: FilterDsl | null` (gdy mode='query')
- `setQueryDsl: (dsl) => void`

W grid mode panel używa flat `conditions: FilterCondition[]`. W query mode używa rekurencyjnego `queryDsl`. Toggle mode konwertuje flat → top-level AND group via `conditionsToDsl()`.

### 3.4 Mapping element-po-elemencie

**`QueryGroupEditor` rekursywny** (mockup-aligned):

```tsx
<div className="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-3">
  <div className="flex items-center gap-2 mb-2">
    <span className="text-[10.5px] uppercase tracking-wider font-semibold text-zinc-500">
      Grupa
    </span>
    <button onClick={toggleOp} className="h-7 px-2 rounded-md bg-white border text-[11px] font-mono">
      {operator}  {/* AND / OR */}
    </button>
    {!isRoot && (
      <button onClick={onRemove} className="ml-auto text-zinc-400 hover:text-rose-600">
        <X className="size-3.5" />
      </button>
    )}
  </div>
  <div className="space-y-2">
    {conditions.map((cond, i) => isFilterGroup(cond)
      ? <QueryGroupEditor key={i} ... onRemove={() => removeAt(i)} depth={depth+1} />
      : <QueryConditionRow key={i} ... onRemove={() => removeAt(i)} />
    )}
  </div>
  <div className="mt-3 flex gap-2">
    <button onClick={addCondition}>+ Dodaj warunek</button>
    {depth < 3 && <button onClick={addGroup}>+ Dodaj grupę</button>}
  </div>
</div>
```

Max depth 3 (PRD §13.2). Nested groups bg-color slightly darker (`bg-zinc-100/70` na depth 1, `bg-zinc-200/70` na depth 2).

### 3.5 i18n keys (~10)

```
products.advanced_filter.query_mode.group_label
products.advanced_filter.query_mode.add_condition
products.advanced_filter.query_mode.add_group
products.advanced_filter.query_mode.remove_group
products.advanced_filter.query_mode.depth_limit_reached
```

### 3.6 a11y

- Group `role="group" aria-label="Grupa AND/OR poziom N"`.
- Operator toggle `aria-pressed={operator === 'AND'}`.
- Add buttons keyboard accessible + focus ring.
- Depth limit communicated via `aria-disabled` na `+ Dodaj grupę` button.
- axe-core 0 violations.

### 3.7 Locales
N/A (UI labels only).

### 3.8 Empty / loading / error states
- Empty root group → Apply disabled.
- Depth >3 → button hidden + tooltip *„Limit zagnieżdżenia 3 poziomy"*.

## 4. Zakres backend (BE)

**Zero nowych encji / migracji.** `FilterDslResolver` już obsługuje nested groups w `compile()` + `compileMeili()` (recursive z depth check max 3). VIEW-09b tylko **dodaje walidację graniczną**:

- `FilterDslResolver::validateGroup()` już rzuca `BadRequestHttpException` przy depth > 3.
- Test PHPUnit potwierdzający że nested DSL (depth 2 z OR group inside AND root) compile do poprawnego Meilisearch expression z parentheses.

### 4.1 Endpointy
Bez zmian. `?q=<base64>` (VIEW-10) akceptuje nested DSL.

### 4.2-4.7
N/A.

## 5. Sub-tasks (checklist)

### Backend
- [ ] PHPUnit test: nested DSL (depth 2) compile do Meilisearch z parentheses + AND/OR mix.

### Frontend
- [ ] `components/catalog/query-group-editor.tsx` — rekursywny.
- [ ] `components/catalog/query-condition-row.tsx` — leaf z attr/op/value.
- [ ] `advanced-filter-panel.tsx` — Query tab enabled + conditional render.
- [ ] `features/catalog/products/list.tsx` — state `advancedMode` + `queryDsl` + Apply propagacja `filterBlob`.
- [ ] i18n keys.

### E2E
- [ ] `apps/admin/e2e/products-view-09b.spec.ts` — 3 scenariusze (toggle to query mode, build nested group, apply → URL `?q=<blob>`).

### Quality gates
- [ ] PHPStan max 0, Biome strict 0, TS strict 0.
- [ ] PHPUnit + ApiTestCase regression zielone.
- [ ] Playwright 3 scenariusze zielone.
- [ ] composer + pnpm audit clean.

## 6. Acceptance criteria — funkcjonalne

- Query mode tab klikalny, badge `VIEW-09b` znika.
- Toggle Grid ↔ Query konwertuje state (grid → top-level AND group; query → flatten or warning).
- Rekursywny editor pozwala dodać grupę OR wewnątrz AND root.
- Max depth 3 enforced (button hidden).
- Apply → URL `?q=<base64>` → BE resolver → Meilisearch nested filter expression.

## 7. Acceptance criteria — non-functional

- PHPStan max + Biome strict + TS strict: 0 errors.
- Playwright 3 zielone scenariusze.
- composer + pnpm audit: 0 high/critical.
- Bundle size FE Δ <30KB gzip (from scratch nested editor).

## 8. Smoke-test (manualnie)

1. Login + `/products`.
2. `Filtruj zaawansowane` → mode toggle Query → assert tab aktywny (badge `VIEW-09b` zniknął).
3. Edytor pokazuje root group AND.
4. `+ Dodaj warunek` × 2 → 2 conditions.
5. `+ Dodaj grupę` → nested OR.
6. W nested: `+ Dodaj warunek` × 2.
7. Apply → URL `?q=<base64>` → grid filtruje.

## 9. Edge cases / poza zakresem

- **Edge cases pokryte**: depth 3 hard cap, empty group → Apply disabled, grid → query toggle preserves conditions.
- **Świadome odejścia**: drag-and-drop reorder conditions → Faza 1 (manual remove + re-add wystarczy w MVP); NOT operator → też Faza 1 (UI complexity vs. value MVP — AND/OR pokrywa 95% queries).

## 10. Powiązane ADR

- ADR-015 (Filter DSL + URL serializer) — VIEW-10 wprowadził; VIEW-09b dopisuje sekcję *„Query mode editor"*.
