# [VIEW-02] Modelowanie · Attributes — pixel-perfect lista + detail edit-in-place + create + values editor

> Ticket view-first wg szablonu `feedback_view_first_ticket_template.md`. Stan na 2026-05-02.
> Źródła prawdy designu (oba w scope tego ticketu):
> - `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/attributes.jsx` (lista + detail + create form)
> - `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/attribute-values.jsx` (edytor wartości dla select/multi-select)
> Prototyp uruchamiany przez `python3 -m http.server 3000` w `Zrodla/.../src` → `http://localhost:3000/Modelowanie.html`, zakładka **Attributes** + przycisk „Zarządzaj wartościami" przy atrybutach typu select.

---

## 1. Kontekst i cel widoku

Widok **Modelowanie · Attributes** to globalna biblioteka pól PIM-u — single source of truth dla każdego typowanego atrybutu używanego w obrębie wszystkich `ObjectType` (Produkty, Usługi, Zasoby, Kategorie). Operator (architekt informacji w organizacji-tenancie) używa go żeby:

1. **Przeglądać 27+ atrybutów w bibliotece** (system + custom) — z filtrami po typie i pełnotekstowym search po `code`/`label`.
2. **Wejść w detail dowolnego atrybutu** żeby zobaczyć i edytować definicję, flagi (Localizable / Scopable / Unique), UI configuration, where-used (Object Types × Attribute Groups × instancje).
3. **Edytować custom atrybuty** w trybie inline (mode operatora: „mockup minimalnie naruszony, zamiast Edit powinno być Zapisz na samym dole — uspójnij z innymi widokami") — pola edytowalne w miejscu, sticky bottom bar z `Anuluj` / `Zapisz zmiany`. System atrybuty (sku, name, slug, created_at, updated_at, created_by, ...) mają pola krytyczne zalockowane.
4. **Stworzyć nowy custom atrybut** przez pełnoekranowy formularz z 3 sekcjami (Identyfikacja → Typ danych → Walidacja) + sidebar (Live preview + Tips „Następnie").
5. **Zarządzać wartościami atrybutów typu `select` / `multi-select`** w pełnoekranowym edytorze — lista po lewej (DnD, search, color dot, default badge, lang counter, instances), edytor po prawej (Code, Color swatch picker 10 swatches, LocaleTabs na label, Default/Wycofana toggle, Preview per locale, Wpływ i audyt).
6. **Zobaczyć wpływ migracji typu** (przez istniejący `/migrate-type` flow z Sprintu 0 — out of scope tego ticketu, refresh pixel-perfect w VIEW-02c).

Powiązane: ADR-009 (ObjectType jako koncept pierwszej klasy), proponowany ADR-012 (AttributeGroup as first-class), CLAUDE.md sekcja „Reguły implementacyjne" punkt 4 (hybrid attributes model: `attributes` + junction `object_type_attributes` + `object_values` + `attributes_indexed`).

Epik: **UI-08** — pixel-perfect Attributes Library. Backlog źródłowy: `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md`. Ticket nadrzędny: zamyka drugi widok view-first flow Modelowania (po VIEW-01 ObjectTypes). VIEW-03 (Attribute Groups) i VIEW-04 (Categories) zależą od konwencji ustalonych tutaj (np. `<FlagPill>`, `<ColorSwatchPicker>`, edit-in-place pattern w detail).

## 2. Mockup / źródło designu

> **WAŻNE — pixel-perfect binding**: implementacja FE MUSI 1:1 odwzorować kod prototypów z `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/attributes.jsx` + `attribute-values.jsx`. To **single source of truth dla layoutu, klas Tailwind, struktury DOM, copy, paddingów, fontów, kolorów i animacji**. Każdy element w `AttributesView`, `AttributeDetail`, `NewAttributeView`, `AttributeValuesView`, `ValueRow`, `FlagPill`, `UsageRow` ma odpowiednik w produkcyjnym kodzie React+Tailwind w `apps/admin/src`. Adaptacje stack-specific (shadcn primitive zamiast hand-rolled, dnd-kit zamiast hand-rolled drag) są dozwolone, ale wizualny rezultat ma się zgadzać <2% pixel mismatch.

### Szczegółowe odwołania do prototypów:

- **Lista (`AttributesView`)**: `attributes.jsx:1–112`.
- **Detail edit-in-place (`AttributeDetail`)**: `attributes.jsx:114–245` (uwaga: w mockupie jest „Edytuj" w header, my zastępujemy sticky bottom bar z „Zapisz" — zgodnie z decyzją operatora 2026-05-02; reszta layoutu 1:1).
- **Create form (`NewAttributeView`)**: `attributes.jsx:352–448`. **Ticket implementuje DOKŁADNIE TEN widok** — nie wymyślamy własnego flow. Pełny mapping w sekcji 3.4c.
- **Values editor (`AttributeValuesView`)**: `attribute-values.jsx:43–327`. **Ticket implementuje DOKŁADNIE TEN widok** — pełny mapping w sekcji 3.4d.
- **Komponenty pomocnicze**: `FlagPill` (`attributes.jsx:247–257`), `UsageRow` (`attributes.jsx:259–270`), `ValueRow` (`attribute-values.jsx:9–41`).
- **Migration impact modal (`MigrationImpactModal`)**: `attributes.jsx:273–350` — **POZA SCOPE VIEW-02**. Current `apps/admin/src/features/catalog/attributes/migrate-type.tsx` (pełnoekranowa, Sprint 0) zostaje as-is. Pixel-perfect refresh w follow-up VIEW-02c.
- **Shared (`Card`, `LocaleTabs`, `LockBadge`, `TypeBadge`, `I` ikony)**: `Zrodla/.../src/modeling/shared.jsx` + reuse z VIEW-01 (`ObjectTypeIcon`, `LocaleTabsField`, `LocaleAddDialog`, `BuiltInLockBadge`).
- **Mock data**: `Zrodla/.../src/modeling/data.jsx` — `ATTRIBUTES` (27 atrybutów), `ATTRIBUTE_VALUES` (per-code map: ip_rating × 7, currency × 5, vat_rate × 5, tags × 4). Kontrakt response BE musi pokrywać wszystkie pola z mock-a (`code`, `name`, `type`, `unit?`, `system`, `unique`, `localizable`, `scopable`, `helper`, `min?`, `max?`, `typesUsed`, `groupsUsed`, `instancesWith`, `where: { groups, types, categories }`). AttributeOption: `code`, `label JSONB`, `color?`, `default?`, `deprecated?`, `order`, `instances`.
- **Powiązane widoki w tym samym shell-u**: zakładki `Object Types` (#VIEW-01), `Attribute Groups` (#VIEW-03), `Categories` (#VIEW-04) w tym samym `/modeling/*`. Nie dotykamy ich w VIEW-02 — tylko spójność topbar/breadcrumb (TabBadge counter dla Attributes pokazuje `27`).
- **Rodzic**: `/modeling` (shell z tab nav); **dzieci/modale**: `<LocaleAddDialog>` (z VIEW-01, jedyny popup w VIEW-02 — sekcja 3.7), `<ConfirmDialog>` przy próbie nawigacji z dirty stanem, opcjonalny modal mapping przy delete option z `instances > 0` (sekcja 4.1).

### Sposób weryfikacji „pixel-perfect":

1. **Side-by-side comparison** — operator otwiera prototyp `http://localhost:3000/Modelowanie.html` w lewej połowie ekranu i implementację `https://pim.localhost/modeling/attributes/{code}` (oraz `/values`) w prawej. Każda sekcja, padding, font-size, border-radius musi się zgadzać.
2. **Visual regression Playwright** — `toHaveScreenshot()` na każdej z 4 tras (`/modeling/attributes`, `/modeling/attributes/{code}`, `/modeling/attributes/new`, `/modeling/attributes/{code}/values`) z baseline'em wygenerowanym z prototypu. Tolerancja <2% pixel mismatch.
3. **Manual review** — operator przejdzie przez listę elementów z sekcji 3.4a–3.4d (niżej) i odznaczy każdy zgodny z mockupem.

## 3. Zakres frontend (FE)

### 3.1 Routing

> **WAŻNE**: Detail (edit-in-place), create wizard i values editor są **osobnymi pełnoekranowymi widokami trasowanymi**, renderowanymi w shellu `/modeling/*` jako `<Outlet>` zakładki Attributes. **Brak popupów / Sheetów / Dialogów dla tych trzech widoków** — jedyny popup w VIEW-02 to `<LocaleAddDialog>` (z VIEW-01) wywoływany z `<LocaleTabsField>` przy klik „+ Dodaj język" oraz `<ConfirmDialog>` przy próbie nawigacji z dirty stanem.

| Trasa | Status | Komponent | Auth |
|---|---|---|---|
| `/modeling/attributes` | ✅ istnieje, dopracowanie | `<AttributesListPage>` | `IS_AUTHENTICATED_FULLY` + `attribute:read` |
| `/modeling/attributes/new` | ✨ nowa trasa | `<AttributeCreatePage>` | `IS_AUTHENTICATED_FULLY` + `attribute:create` |
| `/modeling/attributes/:code` | ✅ rebuild edit-in-place | `<AttributeShowPage>` | `IS_AUTHENTICATED_FULLY` + `attribute:read` (edit submit gated by `attribute:update`) |
| `/modeling/attributes/:code/values` | ✅ rebuild (z MOCK na live) | `<AttributeValuesPage>` | `IS_AUTHENTICATED_FULLY` + `attribute_option:read` (mutacje gated by `attribute_option:update`) |
| `/modeling/attributes/:code/migrate-type` | ✅ as-is, poza scope | `<AttributeMigrateTypePage>` (Sprint 0) | `attribute:update` |

**Tab navigation w shellu Modelowanie** (`apps/admin/src/features/catalog/modeling/layout.tsx`): TabBadge dla `Attributes` aktualizuje się z `useList('attributes', { pagination: { pageSize: 1 } })` — pokazuje aktualny `total` (po seed = 27).

#### Dlaczego osobne widoki, nie popupy

1. **Pixel-perfect zgodność z mockupami** — `NewAttributeView` ma 320px sidebar (Live preview + Następnie), breadcrumb, buttons w prawym górnym rogu. `AttributeValuesView` ma 360px lewą kolumnę (lista wartości DnD) + 1fr prawą (edytor). To wszystko nie mieści się w 420px Sheet.
2. **URL shareable** — operator może wkleić link do edycji konkretnego atrybutu / values editor.
3. **Spójność z VIEW-01 ObjectTypes** — detail / wizard tam też są osobnymi widokami, więc Attributes idą tym samym wzorem.
4. **A11y** — popup/Sheet trapuje focus, blokuje skroll, dodaje warstwy ARIA. Pełnoekranowy widok nie ma tych komplikacji (tylko bottom-sticky bar — pozostaje w obrębie main, nie modal).
5. **Edit-in-place pattern** (decyzja operatora 2026-05-02) — detail to JEST edit, brak osobnej trasy `/edit`. Klik w pole = focus + enable edit; sticky bottom bar agreguje wszystkie zmiany do jednego PATCH. Analogicznie do VIEW-01 ObjectTypes show.tsx.

### 3.2 Komponenty (lista płaska)

#### Komponenty istniejące do reużycia (sprawdzone w kodzie):

- `ModelingPageHeader` — `caption`, `title`, `description`, `ctaLabel`, `onCtaClick`. **Reuse jako nagłówek listy.**
- `TabBadge` (z `modeling/layout.tsx`) — counter w tab nav, **reuse**.
- `TypeBadge` — istnieje w `apps/admin/src/components/modeling/type-badge.tsx` (z VIEW-01 inventory). **Reuse** — kolor-mapped per type (`text/zinc, number/blue, select/amber, boolean/emerald, datetime/sky, money/violet, reference/rose, richtext/indigo, multi-select/orange, uuid/slate`).
- `BuiltInLockBadge` — istnieje, mała `<Lock>` z tekstem „system". **Reuse dla badge przy code atrybutu system.**
- `LocaleTabsField` (z VIEW-01) — `values={{pl, en}} onChange? readOnly? primary='pl'`. **Reuse** dla pól nazwa/helper/label.
- `LocaleAddDialog` (z VIEW-01) — modal dodawania języka. **Reuse**.
- `WhereUsedList` — istnieje (lista grup/typów/kategorii). **Reuse w detail Card „Where used".**
- `StatBox` (z VIEW-01) — duża cyfra + label. **Reuse w 3-kolumnowej siatce „instancji ma tę wartość / pozycja / event".**
- `Card`, `CardContent` — shadcn, reuse.
- `Sheet`, `Dialog` — shadcn, reuse pod LocaleAddDialog + ConfirmDialog.
- `Button`, `Input`, `Textarea`, `Switch`, `Tooltip` — shadcn, reuse.
- `MockBadge` — istnieje, **usuwamy z values.tsx** po przejściu na live.
- `useAttributeUsageCounts()` — hook z `apps/admin/src/features/catalog/attributes/list.tsx`, **reuse**, ale rozszerzyć żeby zwracał też `where: { groups, types, categories }` z BE.
- `jsonFetch()` z `@/lib/http` — typed fetch wrapper z RFC 7807 error parsing.
- `useCurrentWorkspace()` — hook z VIEW-01, dostarcza `enabledLocales`, `primaryLocale`. **Reuse w values editor + create form**.
- `cn()` z `@/lib/utils`.
- `<StickyFormFooter dirty count onCancel onSave saveLabel?>` — z VIEW-01 ObjectTypes show.tsx jeśli istnieje, lub **wyciągnij w nowy reusable** w `components/modeling/sticky-form-footer.tsx`. Stuck do `bottom-0`, `bg-white border-t border-zinc-200`, padding 16px, prawa strona buttons.

#### Komponenty NOWE do napisania (apps/admin/src/components/modeling/):

- `<FlagPill on label desc onChange? disabled? />` — kafelka wzorowana na `attributes.jsx:247–257`. Tryb display (read-only checkbox z FlagPill) i tryb toggle (clickable z onChange). Border emerald gdy `on=true`, zinc gdy `on=false`. **Used in detail Card „Flagi"**.
- `<ColorSwatchPicker selected onChange swatches? size? showClear? />` — picker 10 swatches z `attribute-values.jsx:4–7` (`#71717a #3b82f6 #10b981 #f59e0b #ef4444 #a855f7 #ec4899 #14b8a6 #f97316 #06b6d4`). Każdy swatch 9×9 px (`h-9 w-9`), `rounded-lg`, border-2 zinc-900 gdy selected. Plus opcjonalny clear button (X icon, 9×9).
- `<AttributeTypeGrid value onChange types? />` — siatka 4×3 buttonów dla 10 typów (`text richtext number boolean select multi-select money datetime reference:user uuid`). Active state: bg-zinc-900 white. Inactive: bg-white border zinc-200. Każdy button 10px height (`h-10`), `font-mono text-[12px]`, `rounded-xl`. **Used in create form sekcja „Typ danych"**.
- `<ValueRowItem value isActive onSelect locales onDragStart? />` — wiersz w lewej kolumnie values editora (`attribute-values.jsx:9–41`). Drag handle (`I.drag` icon), color dot (jeśli `value.color`), label (PL fallback EN fallback code), code mono mały, default badge (uppercase tracking-wider), lang counter (`{filledLocales}/{locales.length} lang`), instances number. Active state: bg-zinc-900 white. **Wraps z dnd-kit `useSortable`**.
- `<AttributeValueList values activeId onSelectActive onAdd locales searchPlaceholder />` — lewa kolumna values editora. Search input góra, scrollable list (max-h-560px), CTA „+ Dodaj wartość" dół (border-dashed). DnD context z dnd-kit `DndContext` + `SortableContext`. **Composed z `<ValueRowItem>`**.
- `<AttributeValueDefinitionCard value onChange onDelete onMoveUp onMoveDown localesMissing locales />` — prawa kolumna values editora, główna karta z fields (Code, Color, LocaleTabsField na label) + toolbar (↑/↓/trash) + 2 toggle cards (Default, Deprecated). Composed z istniejących + `<ColorSwatchPicker>`.
- `<AttributeValuePreviewCard value attribute locales />` — Card „Podgląd" w values editor — grid 3 kolumn per locale, mock select field z color dot + label + chevron icon.
- `<AttributeValueAuditCard value />` — Card „Wpływ i audyt" — 3 StatBox-y (instances, position, event name) + warning banner (amber, gdy `instances > 0`).
- `<AttributesListFilters filter onFilterChange query onQueryChange types />` — search input + 9 chip buttons (`wszystkie | system | text | number | boolean | select | richtext | money | datetime`). Pixel-perfect z `attributes.jsx:36–49`.
- `<AttributePreview type value unit? helper? />` — extracted z current `apps/admin/src/features/catalog/attributes/show.tsx` `AttributePreview`. **Reuse w UI Configuration card**.
- `<AttributeWherUsedSection groups types categories instanceCount typesUsed groupsUsed />` — 3 StatBox-y w grid + 3 UsageRow-y (Groups/Object Types/Categories). Pixel-perfect z `attributes.jsx:226–241`.
- `<UsageRow label items icon />` — wiersz „Groups: 🗂 group1 group2 ..." z `attributes.jsx:259–270`. Items renderowane jako chipsy `bg-zinc-50 border-zinc-100`.
- `<AttributeCreateSidebar code name type />` — prawy sidebar w create form: Card „Podgląd" (live preview code + TypeBadge + name) + Card „Następnie" (3 next steps).

#### Komponenty do przebudowy:

- `AttributesListPage` (`features/catalog/attributes/list.tsx`):
  - **Usunąć baner „Read-only — write deferred to schema-add Phase 2"** — operacja create/update jest in-scope VIEW-02.
  - **Usunąć dropdown filtr „Origin" (Wszystkie/Biznesowe/Systemowe)** + filtr „Flags" — zostają tylko chipsy `wszystkie | system | text | number | boolean | select | richtext | money | datetime` zgodnie z mockupem (jeden zestaw filtrów).
  - **Dopasować grid kolumn do mockupu**: `grid-cols-[40px_1.4fr_100px_140px_100px_90px_100px_120px]` (icon | code+name | type | flags | typesUsed | groupsUsed | instances | values).
  - **CTA „Nowy atrybut"** jest aktywny i prowadzi do `/modeling/attributes/new` (już ma `to`, ale obecnie przerwany handler).
  - **Klik wiersza** prowadzi do `/modeling/attributes/{code}` (show edit-in-place).
  - **Klik chip „N wartości"** przy select/multi-select prowadzi do `/modeling/attributes/{code}/values` (już jest, dopracować styling: `bg-violet-50 text-violet-700 hover:bg-violet-100`).
  - **System lock badge** w wierszu: `<BuiltInLockBadge />` przy code dla `system === true`.
  - **Unique badge**: `bg-amber-50 text-amber-700` chip „unique" gdy `unique === true`.
  - **i18n**: `bg-blue-50 text-blue-700` chip „i18n" gdy `localizable === true`. Scope: `bg-purple-50 text-purple-700` „scope" gdy `scopable === true`.
- `AttributeShowPage` (`features/catalog/attributes/show.tsx`):
  - **Pełna przebudowa pixel-perfect z edit-in-place pattern** — pola w polach jako inputy/togglees, sticky bottom bar z Anuluj/Zapisz, dirty state guard. Dotychczasowy read-only DetailRow zastąpione `<FieldDisplay editable />`.
  - **Usunąć przycisk „Edytuj" z header** — zgodnie z decyzją operatora.
  - **Dodać przyciski w header**: `Zarządzaj wartościami` (jeśli `type ∈ {select, multi-select}`), `Migruj typ` (jeśli `!system`).
  - **Zachować obecny `AttributePreview`** — tylko przenieść do Card „UI Configuration".
  - **System immutable note** w stopce karty „Definicja" pozostaje (renderowany gdy `system === true`).
- `AttributeValuesPage` (`features/catalog/attributes/values.tsx`):
  - **Usunąć MOCK banner** + placeholder cards.
  - **Pełna przebudowa pixel-perfect** wg `attribute-values.jsx`. Lewa kolumna (lista DnD) + prawa (edytor).
  - **Dodać optimistic mutations** dla create/update/delete/reorder option.
  - **Dirty state**: zmiany lokalne (Code edit, color, label, default, deprecated, kolejność) trzymane w lokalnym `useState`. „Zapisz zmiany" w header → batch PATCH `/api/attributes/{code}/options/reorder` + per-option PATCH-e.

### 3.3 State management

#### Refine resources (apps/admin/src/App.tsx)

- `attributes` — istnieje. Operations: `list`, `show`, `create` (NEW: → POST), `edit` (NEW: → PATCH bez osobnej trasy, używa show), `delete` (NEW: → DELETE).
  - `list`: `/modeling/attributes`
  - `show`: `/modeling/attributes/:code` (edit-in-place)
  - `create`: `/modeling/attributes/new`
  - `edit`: action wiązany z `show` (brak osobnej trasy) — Refine `useUpdate` na `attributes` resource
  - `delete`: invoked z DangerZone w show (jeśli decydujemy się dodać; w mockupie nie ma — **TODO(handoff)**: defer delete UI do follow-upa, BE endpoint zostaje gotowy)
- `attribute_options` — **NOWY resource**.
  - `list`: brak osobnej trasy listy (renderowane wewnątrz `/modeling/attributes/:code/values`)
  - `show`: brak (edytor renderuje się jako prawa kolumna `<AttributeValuesPage>`)
  - `create`/`edit`/`delete`: invoked z `<AttributeValueDefinitionCard>` mutacjami przez React Query

#### React Query keys

```ts
// existing
['attributes'] // list
['attributes', code] // show
['attributes', code, 'usage']
// new
['attributes', code, 'options'] // collection
['attributes', code, 'options', optionCode, 'usage'] // per-option instances count
['attribute-options', id] // single option (rarely used)
```

#### Mutations + invalidations

| Mutacja | Endpoint | Invalidate keys |
|---|---|---|
| `useCreateAttribute()` | `POST /api/attributes` | `['attributes']` + redirect do `['attributes', newCode]` |
| `useUpdateAttribute(code)` | `PATCH /api/attributes/{code}` | `['attributes', code]`, `['attributes']`, `['attributes', code, 'usage']` |
| `useDeleteAttribute(code)` | `DELETE /api/attributes/{code}` | `['attributes']` + redirect to list |
| `useCreateAttributeOption(code)` | `POST /api/attributes/{code}/options` | `['attributes', code, 'options']`, `['attributes', code]` (option count) |
| `useUpdateAttributeOption(id)` | `PATCH /api/attribute_options/{id}` | `['attributes', code, 'options']` |
| `useDeleteAttributeOption(id)` | `DELETE /api/attribute_options/{id}` | `['attributes', code, 'options']`, `['attributes', code, 'options', optionCode, 'usage']` |
| `useReorderAttributeOptions(code)` | `POST /api/attributes/{code}/options/reorder` | `['attributes', code, 'options']` |

**Optimistic updates**: dla reorder + label/color edit (UX <50ms perceived). Rollback on error przez `onError` callback z toast errror.

**Cache stale time**: `['attributes']` 60s, `['attributes', code]` 30s (stays fresh through edit), `['attributes', code, 'options']` 30s.

#### Local state per route

- **list**: filter chip state (`filter: 'all' | 'system' | typeName`), search query — `useState`, NIE w Refine filtrze (filtruje client-side po fetched 27).
- **show (edit-in-place)**: React Hook Form z `defaultValues` z `useShow()`. `mode: 'onBlur'` żeby na każdy blur walidacja. `formState.isDirty` używane przez sticky footer + nav guard.
- **create**: React Hook Form z domyślnymi `{ code: '', name: { pl: '' }, type: 'text', isLocalizable: false, isScopable: false, isRequired: false, helper: { pl: '' } }`. Submit → `useCreateAttribute()` → redirect `/modeling/attributes/{code}`.
- **values**: lokalna kopia listy options + activeOptionCode (`useState`). Drag/reorder operuje na lokalnej kopii, „Zapisz zmiany" wysyła diff (nowe options POST, zmienione PATCH, usunięte DELETE, kolejność reorder). **Alternatywa**: osobne mutacje per akcja (każdy klik save'uje od razu) — wybierzemy w trakcie implementacji w zależności od UX feel.

### 3.4 Struktura sekcji widoku (kolejność renderu)

#### 3.4a Lista — `/modeling/attributes` (`AttributesListPage`)

1. **Workspace shell** (sidebar lewy + topbar) — obecny shell, `useAuth` guard.
2. **Tab nav Modelowanie** (Object Types | Attributes | Attribute Groups | Categories) — `<TabBadge>` per tab.
3. **`<ModelingPageHeader>`**:
   - caption: `27 atrybutów w bibliotece` (z `useList('attributes').total`)
   - title: `Attributes` (font-display 28px font-semibold)
   - description: `Globalna biblioteka pól — każdy atrybut ma własny code, typ i walidację. Atrybuty dołączane są do ObjectType lub Attribute Group; tu zarządzasz nimi w jednym miejscu.`
   - cta: `+ Nowy atrybut` (h-9 px-4 rounded-xl bg-zinc-900 text-white)
4. **`<Card>` z całą tabelą**:
   - Sticky top: search input + 9 filter chips (px-4 py-3, border-b zinc-100)
   - Header row (10.5px uppercase tracking-wider, px-5 py-2.5, border-b zinc-100):
     - empty 40px
     - `Code · nazwa` (1.4fr)
     - `Type` (100px)
     - `Flagi` (140px)
     - `Used in` (100px right)
     - `Groups` (90px right)
     - `Instances` (100px right)
     - `Wartości` (120px right)
   - Body rows (divide-y zinc-50, px-5 py-3, hover:bg-zinc-50/70):
     - icon button (40px) — `<I.shield>` jeśli system, `<I.zap>` w przeciwnym razie
     - code + name button (clickable → show)
     - type badge button (`<TypeBadge type={a.type}>`)
     - flags button (chip pills: i18n / scope / unique / —)
     - typesUsed (right-aligned, num font, „N typów")
     - groupsUsed (right-aligned, num font, „N")
     - instancesWith (right-aligned, num font, `toLocaleString('pl-PL')`)
     - values chip button (gdy `select | multi-select` → violet bg, link do `/values`; w przeciwnym razie `—`)
5. **Empty state**: gdy `filtered.length === 0` → text „Brak wyników dla filtra X" w środku karty.

#### 3.4b Detail edit-in-place — `/modeling/attributes/:code` (`AttributeShowPage`)

1. **Workspace shell** + tab nav.
2. **Back button**: `← Wstecz do biblioteki Attributes` (mb-4, text-zinc-500 hover:zinc-900).
3. **Header row** (flex items-start gap-4 mb-6):
   - Left: ikona 14×14 rounded-2xl bg-white border zinc-200 grid place-items-center text-zinc-700.
   - Center: stack
     - row: `<font-mono text-[26px] font-semibold>{code}</>` + `<BuiltInLockBadge />` (gdy system) + `<TypeBadge type>`
     - subtitle: `{name} · jednostka {unit}` (gdy unit, text-13px text-zinc-500)
   - Right: stack of buttons (gap-2)
     - `Zarządzaj wartościami` (gdy select|multi-select; bg-violet-50 hover:bg-violet-100 text-violet-700, h-9 px-3 rounded-xl, prowadzi do `/values`)
     - `Migruj typ` (gdy !system; bg-amber-50 hover:bg-amber-100 text-amber-700; prowadzi do `/migrate-type`)
     - **BRAK przycisku „Edytuj"** — usunięty (decyzja operatora). Save jest w sticky bottom bar.
4. **Card „Definicja"** (p-6, space-y-6):
   - title: `DEFINICJA` (uppercase tracking-wider text-zinc-500 11px font-medium mb-4)
   - grid `grid-cols-2 gap-x-8 gap-y-4`:
     - `<FieldDisplay label="Code" value={code} mono lock />` (zawsze locked po utworzeniu)
     - `<LocaleTabsField label="Nazwa" values={name} onChange?={!system} primary='pl' />`
     - `<FieldDisplay label="Type" value={type} mono lock />` (zawsze locked, zmiana przez `/migrate-type`)
     - `<FieldDisplay label="Jednostka" value={unit} editable={!system && type === 'number'} />` (opcjonalnie)
     - `<FieldDisplay label="Min" value={min} mono editable={!system && type === 'number'} />` (opcjonalnie)
     - `<FieldDisplay label="Max" value={max} mono editable={!system && type === 'number'} />` (opcjonalnie)
   - separator (mt-6 pt-6 border-t zinc-100)
   - `Flagi` label (11.5px text-zinc-500 mb-3)
   - grid `grid-cols-3 gap-3`:
     - `<FlagPill on={localizable} label="Localizable" desc="per locale (PL/EN/DE)" onChange={!system} />`
     - `<FlagPill on={scopable} label="Scopable" desc="per channel (Shopify/Allegro)" onChange={!system} />`
     - `<FlagPill on={unique} label="Unique" desc="unikalna wartość w obrębie typu" onChange={!system} />`
5. **Card „Allowed values"** (p-6, gdy `select | multi-select`):
   - flex items-start justify-between mb-4:
     - left: `Allowed values` (uppercase 11px) + `{N} wartości · z tłumaczeniami w {M} językach`
     - right: button `<I.pencil> Zarządzaj wartościami` (h-9 px-3 rounded-xl bg-zinc-900 text-white)
   - flex flex-wrap gap-1.5: pierwsze 12 chipsów `[code · label]` (bg-zinc-50 border-zinc-100 px-2.5 py-1 rounded-lg text-12px) + `+N więcej` (gdy total > 12) lub italic „Brak zdefiniowanych wartości — kliknij „Zarządzaj wartościami"" gdy 0.
6. **Card „UI Configuration"** (p-6):
   - title: `UI CONFIGURATION`
   - grid 2 kolumny:
     - `<FieldDisplay label="Widget" value={derivedWidget} mono lock />` (derived from type)
     - `<FieldDisplay label="Placeholder" value={placeholder} editable={!system} />`
     - `<FieldDisplay label="Helper text" value={helper.pl || helper.en} editable={!system} />` (jednowierszowy display, klik otwiera LocaleTabsField inline)
   - rounded-2xl border zinc-200 bg-white p-5 (preview):
     - title: `Preview` (11.5px text-zinc-500 mb-2.5)
     - flex items-center gap-3:
       - label (text-13px text-zinc-700 font-medium w-24): `{name}` 
       - input mock (flex-1, bg-zinc-50 border zinc-200 h-10 rounded-xl px-3): `<AttributePreview type={type} value={a.unit ? '230' : null} unit={unit} />`
     - helper (gdy istnieje): text-11.5px text-zinc-500 mt-2 ml-[6.25rem]
7. **Card „Where used"** (p-6):
   - title: `WHERE USED`
   - grid 3 cols mb-5: `<StatBox value={typesUsed} label="Object Types" />` + `<StatBox value={groupsUsed} label="Attribute Groups" />` + `<StatBox value={instancesWith} label="instancji z wartością" />`
   - space-y-3 (gdy `where` zwrócony z BE):
     - `<UsageRow label="Groups" items={where.groups} icon="🗂" />`
     - `<UsageRow label="Object Types" items={where.types} icon="📦" />`
     - `<UsageRow label="Categories" items={where.categories} icon="📂" />`
8. **Sticky bottom bar** (gdy `formState.isDirty`):
   - position: `sticky bottom-0` (alternatywnie `fixed` przy bottom z padding-b zachowanym w main)
   - bg-white border-t border-zinc-200 px-6 py-4 flex items-center justify-between
   - left: `<ChangesSummary count={dirtyFieldsCount} />` „N pól zmienionych"
   - right: stack of buttons (gap-2):
     - `Anuluj` (h-9 px-3 rounded-xl text-zinc-600 hover:bg-zinc-100) — revert form do server state
     - `Zapisz zmiany` (h-9 px-4 rounded-xl bg-zinc-900 text-white) — submit
9. **Audit log indicator** (top-right z VIEW-01) → reuse, kropka + tekst „Audit log: aktywny · ostatnia zmiana N min temu".

#### 3.4c Create form — `/modeling/attributes/new` (`AttributeCreatePage`)

1. **Workspace shell** + tab nav.
2. **Back button**: `← Wstecz do biblioteki Attributes`.
3. **Header row** (flex items-start justify-between gap-6 mb-6):
   - Left:
     - caption: `Nowy Attribute` (text-13px text-zinc-500 font-medium)
     - title: `<font-mono font-display text-[28px] font-semibold>{code || 'attribute_code'}</>` (live update z input)
     - description: `Atrybut to typowane pole, które można dołączać do ObjectType lub Attribute Group. Po utworzeniu pojawi się w globalnej bibliotece.` (max-w-2xl)
   - Right: button group
     - `Anuluj` (h-9 px-3 rounded-xl hover:bg-zinc-100, prowadzi do `/modeling/attributes`)
     - `<I.check> Utwórz atrybut` (h-9 px-4 rounded-xl bg-zinc-900 text-white)
4. **Main grid** `grid-cols-[1fr_320px] gap-6`:
   - Left: Card p-6 space-y-6:
     - section „Identyfikacja" (uppercase 11px tracking-wider mb-4):
       - input `Code` (h-10 rounded-xl bg-white border zinc-200 font-mono text-13px, regex `^[a-z][a-z0-9_]{1,63}$`)
       - `<LocaleTabsField label="Nazwa" values={name} onChange primary='pl' placeholder='np. Gwarancja (msc)' />`
       - textarea `Opis (opcjonalny)` (rows=2, h-auto px-3 py-2 rounded-xl border zinc-200)
     - section „Typ danych":
       - `<AttributeTypeGrid value={type} onChange types={[text,richtext,number,boolean,select,multi-select,money,datetime,reference:user,uuid]} />`
     - section „Walidacja":
       - `<SettingToggleRow label="Required" desc="Pole musi być wypełnione" checked={required} onChange />`
       - `<SettingToggleRow label="Unique" desc="Wartość unikalna w obrębie ObjectType" checked={unique} onChange />`
       - `<SettingToggleRow label="Indexed" desc="Indeks dla wyszukiwania" checked={indexed} onChange />`
   - Right: aside `space-y-3`:
     - Card p-5 „Podgląd":
       - title `PODGLĄD` (uppercase 11px mb-3)
       - flex items-center gap-2: `<font-mono font-semibold>{code || 'code…'}</>` + `<TypeBadge type>`
       - text-12px text-zinc-500: `{name.pl || 'Nazwa atrybutu…'}`
     - Card p-5 „Następnie":
       - title `NASTĘPNIE` (uppercase 11px mb-3)
       - ul space-y-1.5:
         - `1. Utwórz atrybut`
         - `2. Dołącz do Attribute Group lub ObjectType`
         - `3. Ustaw mapowania na kanały (Shopify, Allegro)`

#### 3.4d Values editor — `/modeling/attributes/:code/values` (`AttributeValuesPage`)

1. **Workspace shell** + tab nav.
2. **Back button**: `← Wstecz do atrybutu „{code}"`.
3. **Header row** (flex items-start gap-4 mb-6):
   - Left: ikona 14×14 rounded-2xl bg-violet-50 border violet-200 text-violet-700 grid place-items-center: `<I.layers />`
   - Center:
     - caption: `Allowed values · {type}` (text-12.5px text-zinc-500 font-medium)
     - title: `<font-mono>{code}</> · {name}` (font-display 26px font-semibold)
     - meta: `{values.length} wartości · {totalInstances} instancji używa wartości · {enabledLocales.length} języków`
   - Right: button group:
     - `<I.upload> Importuj CSV` (disabled tooltip „Funkcja w VIEW-02d", h-9 px-3 rounded-xl hover:bg-zinc-100)
     - `<I.download> Eksport` (disabled tooltip)
     - `<I.check> Zapisz zmiany` (h-9 px-4 rounded-xl bg-zinc-900 text-white) — submit batch jeśli używamy lokalnego state pattern; alternatywnie usunięte gdy mutacje per-akcja
4. **Main grid** `grid-cols-[360px_1fr] gap-6`:
   - **Lewa: Card p-3 self-start**:
     - search input (px-2 py-1.5 flex items-center gap-2): `<I.search>` + input bg-transparent
     - scrollable list (mt-1 px-1 max-h-560px overflow-y-auto scrollbar-thin space-y-1): `<DndContext>` + `<SortableContext>` z `<ValueRowItem>`-ami
     - `+ Dodaj wartość` (mt-2 w-full h-10 rounded-xl border-dashed border-zinc-300 text-zinc-600 hover:bg-zinc-50)
   - **Prawa: space-y-6** (gdy `active` istnieje):
     - **Card „Definicja wartości"** (p-6):
       - flex items-center justify-between mb-5: title + toolbar (↑ ↓ | trash)
       - grid grid-cols-2 gap-x-8 gap-y-5:
         - `Code` input (h-10 rounded-xl border zinc-200 font-mono) + helper „Stabilny identyfikator..."
         - `Kolor (opcjonalny)`: `<ColorSwatchPicker selected={active.color} onChange showClear />`
       - separator (mt-6 pt-6 border-t zinc-100):
         - flex justify-between mb-3: title „Etykiety wyświetlane" + `Brakuje: PL, EN` badge (gdy `localesMissing.length > 0`)
         - `<LocaleTabsField key={active.code} values={active.label} onChange primary='pl' placeholder='Etykieta wartości' />`
       - separator (mt-6 pt-6 border-t zinc-100, grid grid-cols-2 gap-4):
         - `<label>` „Wartość domyślna" (border emerald gdy `active.default`):
           - checkbox (controlled, na change wywołuje `setDefault(active.code)` lub `unset`)
           - title + desc
         - `<label>` „Wycofana" (border zinc-300 bg-zinc-100 gdy `active.deprecated`):
           - checkbox + title + desc
     - **Card „Podgląd"** (p-6):
       - title `PODGLĄD`
       - grid grid-cols-3 gap-3 (pierwsze 3 enabled locales):
         - rounded-xl border zinc-200 p-4 bg-white per locale:
           - flag + locale code uppercase
           - attribute name (text-11.5px text-zinc-500 mb-2)
           - mock select field (flex items-center gap-2 px-3 h-10 rounded-xl border bg-zinc-50): `color dot` (gdy color) + `label[locale]` (lub italic „(brak tłumaczenia)") + `chevron-down icon`
     - **Card „Wpływ i audyt"** (p-6):
       - title `WPŁYW I AUDYT`
       - grid grid-cols-3 gap-4:
         - `<StatBox value={instances} label="instancji ma tę wartość" />`
         - `<StatBox value={order} label="pozycja w sortowaniu" />`
         - `<StatBox value={<font-mono text-14px>attribute.value.update</>} label="zdarzenie audit log" />`
       - warning banner (gdy `instances > 0`): mt-4 bg-amber-50 border amber-200 rounded-xl px-4 py-2.5 flex items-start gap-2.5 — alert icon + tekst „Uwaga: ta wartość jest używana przez {N} obiektów. Usunięcie wymagać będzie migracji — system zaproponuje mapowanie na inną wartość."
   - **Empty state** (gdy `!active`):
     - Card p-12 text-center: layers icon (zinc-400) + „Brak wybranej wartości" + „Wybierz wartość z listy lub dodaj nową, aby edytować szczegóły."

### 3.5 i18n (klucze pl + en, ban literałów)

Dorzucamy do `apps/admin/src/locales/pl.json` + `en.json` pod gałęzią `modeling.attributes.*` + `attribute_values.*`. Lista nowych kluczy (~45):

```json
{
  "modeling.attributes": {
    "list_title": "Attributes",
    "list_caption": "{count} atrybutów w bibliotece",
    "list_description": "Globalna biblioteka pól — każdy atrybut ma własny code, typ i walidację. Atrybuty dołączane są do ObjectType lub Attribute Group; tu zarządzasz nimi w jednym miejscu.",
    "create_action": "Nowy atrybut",
    "back_to_library": "Wstecz do biblioteki Attributes",
    "back_to_attribute": "Wstecz do atrybutu „{code}\"",
    "manage_values_action": "Zarządzaj wartościami",
    "migrate_type_action": "Migruj typ",
    "filter_chip_all": "wszystkie",
    "filter_chip_system": "system",
    "fields": {
      "code": "Code",
      "name_pl": "Nazwa (PL)",
      "type": "Type",
      "unit": "Jednostka",
      "min": "Min",
      "max": "Max",
      "widget": "Widget",
      "placeholder": "Placeholder",
      "helper_text": "Helper text",
      "preview": "Preview"
    },
    "flags": {
      "title": "Flagi",
      "localizable_label": "Localizable",
      "localizable_desc": "per locale (PL/EN/DE)",
      "scopable_label": "Scopable",
      "scopable_desc": "per channel (Shopify/Allegro)",
      "unique_label": "Unique",
      "unique_desc": "unikalna wartość w obrębie typu"
    },
    "allowed_values": {
      "title": "Allowed values",
      "subtitle": "{count} wartości · z tłumaczeniami w {locales} językach",
      "empty_hint": "Brak zdefiniowanych wartości — kliknij „Zarządzaj wartościami\".",
      "more": "+{count} więcej"
    },
    "ui_configuration_title": "UI Configuration",
    "where_used": {
      "title": "Where used",
      "object_types": "Object Types",
      "attribute_groups": "Attribute Groups",
      "instances": "instancji z wartością",
      "groups_label": "Groups",
      "categories_label": "Categories"
    },
    "definition_title": "Definicja",
    "create": {
      "title_caption": "Nowy Attribute",
      "title_default_code": "attribute_code",
      "description": "Atrybut to typowane pole, które można dołączać do ObjectType lub Attribute Group. Po utworzeniu pojawi się w globalnej bibliotece.",
      "submit_action": "Utwórz atrybut",
      "cancel_action": "Anuluj",
      "section_identification": "Identyfikacja",
      "section_data_type": "Typ danych",
      "section_validation": "Walidacja",
      "field_description_label": "Opis (opcjonalny)",
      "field_description_placeholder": "Krótki opis atrybutu — pomocne dla zespołu.",
      "validation_required_label": "Required",
      "validation_required_desc": "Pole musi być wypełnione",
      "validation_unique_label": "Unique",
      "validation_unique_desc": "Wartość unikalna w obrębie ObjectType",
      "validation_indexed_label": "Indexed",
      "validation_indexed_desc": "Indeks dla wyszukiwania",
      "preview_card_title": "Podgląd",
      "preview_code_placeholder": "code…",
      "preview_name_placeholder": "Nazwa atrybutu…",
      "next_card_title": "Następnie",
      "next_step_1": "Utwórz atrybut",
      "next_step_2": "Dołącz do Attribute Group lub ObjectType",
      "next_step_3": "Ustaw mapowania na kanały (Shopify, Allegro)"
    },
    "edit": {
      "save_action": "Zapisz zmiany",
      "cancel_action": "Anuluj",
      "dirty_count_singular": "{count} pole zmienione",
      "dirty_count_few": "{count} pola zmienione",
      "dirty_count_many": "{count} pól zmienionych",
      "discard_confirm_title": "Niezapisane zmiany",
      "discard_confirm_desc": "Masz niezapisane zmiany. Czy chcesz je porzucić?",
      "discard_confirm_action": "Porzuć zmiany",
      "system_immutable_note": "Atrybut systemowy — code, typ i walidacja są niezmienne. Pozostałe pola edytowalne."
    }
  },
  "attribute_values": {
    "page_caption": "Allowed values · {type}",
    "meta": "{count} wartości · {instances} instancji używa wartości · {locales} języków",
    "import_csv_action": "Importuj CSV",
    "export_action": "Eksport",
    "save_action": "Zapisz zmiany",
    "search_placeholder": "Szukaj wartości…",
    "search_empty": "Brak wyników dla „{query}\"",
    "add_action": "Dodaj wartość",
    "definition_title": "Definicja wartości",
    "code_label": "Code",
    "code_helper": "Stabilny identyfikator — używany w API i mapowaniach.",
    "color_label": "Kolor (opcjonalny)",
    "labels_title": "Etykiety wyświetlane",
    "labels_desc": "Tłumaczenia widoczne w UI, kanałach sprzedaży i exportach",
    "labels_missing": "Brakuje: {locales}",
    "labels_placeholder": "Etykieta wartości",
    "default_label": "Wartość domyślna",
    "default_desc": "Wybierana automatycznie dla nowych obiektów",
    "deprecated_label": "Wycofana",
    "deprecated_desc": "Ukryj w nowych formularzach, zachowaj w istniejących",
    "preview_title": "Podgląd",
    "preview_no_translation": "(brak tłumaczenia)",
    "audit_title": "Wpływ i audyt",
    "audit_instances_label": "instancji ma tę wartość",
    "audit_position_label": "pozycja w sortowaniu",
    "audit_event_label": "zdarzenie audit log",
    "audit_event_value": "attribute.value.update",
    "audit_warning_title": "Uwaga:",
    "audit_warning_body": "ta wartość jest używana przez {count} obiektów. Usunięcie wymagać będzie migracji — system zaproponuje mapowanie na inną wartość.",
    "lang_counter": "{filled}/{total} lang",
    "default_badge": "default",
    "no_value_selected_title": "Brak wybranej wartości",
    "no_value_selected_desc": "Wybierz wartość z listy lub dodaj nową, aby edytować szczegóły.",
    "move_up_tooltip": "Wyżej",
    "move_down_tooltip": "Niżej",
    "delete_tooltip": "Usuń",
    "delete_confirm_in_use": "Wartość używana przez {count} obiektów. Wybierz wartość docelową dla migracji."
  }
}
```

**English fallback**: identyczne klucze, treści po angielsku. Operator może rozszerzyć tłumaczenia w follow-upie. **MVP**: PL + EN minimum.

### 3.6 a11y

- Wszystkie buttons mają `aria-label` jeśli icon-only (move-up/move-down/delete w values editor).
- TabBadge ma `aria-label` dynamiczny `Atrybuty (27)` żeby Playwright nie wpadał w timeout exact-match (lessons VIEW-01 #7).
- LocaleTabs: `role="tablist"`, każdy tab ma `role="tab"` + `aria-selected`.
- Sticky bottom bar: `role="region"` + `aria-label="Niezapisane zmiany"`.
- Color swatch picker: każdy swatch button ma `aria-label="Kolor {hex}"`, klawiatura strzałka L/P do nawigacji, Enter do wyboru.
- DnD: `dnd-kit/sortable` ma natywne wsparcie dla keyboard reorder (Space + arrows). Test w Playwright keyboard mode.
- Focus ring: `focus:ring-2 ring-zinc-900 ring-offset-2` na wszystkich interactive elementach.
- Form fields: `<Label htmlFor>` parowane z `<Input id>` przez React Hook Form `register` — auto-generowane id.
- axe-core: 0 violations serious/critical na wszystkich 4 trasach. Skanowane przez `apps/admin/e2e/modeling-attributes-*.spec.ts` przy `await injectAxe(page); await checkA11y(page, undefined, { detailedReport: true });`.

### 3.7 Locales (multi-language fields)

Used in: `name`, `helper`, `label` (per AttributeOption). Wszystkie jako JSONB `{pl: ..., en: ..., de?: ...}` w BE, w FE jako `<LocaleTabsField>` (z VIEW-01).

- Tab list: PL (primary, badge `PRIMARY`) + EN + ewentualnie DE/inne dodane przez `<LocaleAddDialog>`.
- Workspace `enabled_locales` z `useCurrentWorkspace()` decyduje które tab pojawiają się domyślnie.
- Dodanie języka: `<LocaleAddDialog>` modal (jedyny popup w VIEW-02) → POST `/api/workspaces/current/locales` (z VIEW-01) + lokalna aktualizacja `enabledLocales` state + dorzucenie pustego entry do edytowanej JSONB.
- Removal locale: out of scope (zarządzanie w Settings · Workspace, nie w Modelowaniu).

### 3.8 Empty / loading / error states

- **List loading**: skeleton 27 wierszy (animate-pulse, 64px each).
- **List empty po filtrze**: text „Brak atrybutów spełniających kryteria" w centrum karty.
- **Show loading**: skeleton header + 4 cards.
- **Show error 404**: redirect do `/modeling/attributes` + toast „Atrybut nie istnieje".
- **Show error 403**: redirect do `/dashboard` + toast „Brak uprawnień".
- **Create submit error 422**: pokaz błędów per-field z RFC 7807 `violations[]`.
- **Values editor loading**: skeleton lewa kolumna (10 wierszy) + prawa „Brak wybranej wartości".
- **Values editor 0 wartości**: lewa kolumna pokazuje tylko `+ Dodaj wartość`, prawa „Brak wybranej wartości — dodaj pierwszą".
- **Values save error 422 (default uniqueness violation, in-use deletion)**: toast z translated message.

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Request body | Response | Permissions | Filtry/sort/pagination | Status |
|---|---|---|---|---|---|---|
| GET | `/api/attributes` | — | `Attribute[]` collection | `attribute:read` | filter `type[]`, `system`, `localizable`, `scopable`; search `q`; sort `code|position`; cursor pagination 200 max | ✅ istnieje |
| GET | `/api/attributes/{code}` | — | `Attribute` item | `attribute:read` | — | ✅ istnieje |
| POST | `/api/attributes` | `{code, name JSONB, type, isLocalizable?, isScopable?, isRequired?, isUnique?, isIndexed?, helper? JSONB, unit?, min?, max?}` | `Attribute` 201 | `attribute:create` | — | ✨ NEW |
| PATCH | `/api/attributes/{code}` | partial fields | `Attribute` 200 | `attribute:update` | — | ✨ NEW |
| DELETE | `/api/attributes/{code}` | — | 204 / 422 (in-use) | `attribute:delete` | — | ✨ NEW |
| GET | `/api/attributes/{code}/usage` | — | `{groups, objectTypes, categories, instanceCount, where: {groups, types, categories}}` | `attribute:read` | — | ✅ istnieje, **rozszerzenie o `where`** |
| GET | `/api/attributes/{code}/options` | — | `AttributeOption[]` (sorted by position) | `attribute_option:read` | — | ✨ NEW |
| POST | `/api/attributes/{code}/options` | `{code, label JSONB, color?, isDefault?, isDeprecated?}` | `AttributeOption` 201 | `attribute_option:create` | — | ✨ NEW |
| PATCH | `/api/attribute_options/{id}` | partial | `AttributeOption` 200 | `attribute_option:update` | — | ✨ NEW |
| DELETE | `/api/attribute_options/{id}` | — | 204 / 422 (instances > 0) | `attribute_option:delete` | — | ✨ NEW |
| POST | `/api/attributes/{code}/options/reorder` | `{order: [optionCode1, optionCode2, ...]}` | 204 | `attribute_option:update` | — | ✨ NEW |
| GET | `/api/attributes/{code}/options/{optionCode}/usage` | — | `{instances: int}` | `attribute_option:read` | 60s cache | ✨ NEW |
| POST | `/api/attributes/{code}/migrate-type` | dryRun + execute | 200 | `attribute:update` | — | ✅ istnieje (Sprint 0) |

**Errors**: RFC 7807 Problem Details. Standardowe response:

```json
{
  "type": "https://pim.example.com/errors/system-attribute-immutable",
  "title": "Atrybut systemowy jest niezmienny w polach krytycznych",
  "status": 422,
  "detail": "Pole 'type' atrybutu 'sku' nie może być modyfikowane (system attribute).",
  "instance": "/api/attributes/sku",
  "violations": [
    {"propertyPath": "type", "code": "system_immutable"}
  ]
}
```

**Cursor pagination**: `/api/attributes?cursor=eyJ...&limit=20`. Default limit 20, max 200.

### 4.2 Encje / schema / migracje

#### Migracja Doctrine

Plik: `apps/api/migrations/Version20260502NNNNNN.php` (NNNNNN = HHmm w UTC, do ustalenia w trakcie implementacji).

```sql
ALTER TABLE attribute_options
  ADD COLUMN color VARCHAR(7) NULL,
  ADD COLUMN is_default BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN is_deprecated BOOLEAN NOT NULL DEFAULT false,
  ADD CONSTRAINT chk_attribute_options_color_hex CHECK (color IS NULL OR color ~ '^#[0-9A-Fa-f]{6}$');

CREATE UNIQUE INDEX idx_attribute_options_one_default
  ON attribute_options(attribute_id) WHERE is_default = true;

-- Performance indexes (already may exist, sanity check):
-- CREATE INDEX IF NOT EXISTS idx_attribute_options_attribute_position
--   ON attribute_options(attribute_id, position);

COMMENT ON COLUMN attribute_options.color IS 'Hex color #RRGGBB for UI swatch display';
COMMENT ON COLUMN attribute_options.is_default IS 'One option per attribute can be default — enforced by partial unique index';
COMMENT ON COLUMN attribute_options.is_deprecated IS 'Deprecated values hidden in new forms but kept in existing object_values';
```

**Rollback**: `down()` drop columns + drop unique index. Bez backfill — wszystkie istniejące rekordy dostają `is_default=false, is_deprecated=false, color=NULL` (defaults z `ADD COLUMN`).

#### Encje

`apps/api/src/Catalog/Domain/Entity/AttributeOption.php` — rozszerzenie:

```php
class AttributeOption {
  // existing: id, attribute, code, label, position, tenant
  private ?string $color = null;
  private bool $isDefault = false;
  private bool $isDeprecated = false;

  public function getColor(): ?string { return $this->color; }
  public function setColor(?string $color): void {
    if ($color !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
      throw new InvalidColorFormatException($color);
    }
    $this->color = $color;
  }
  public function isDefault(): bool { return $this->isDefault; }
  public function isDeprecated(): bool { return $this->isDeprecated; }
  public function setDeprecated(bool $value): void { $this->isDeprecated = $value; }

  // domain method z guard'em: jeden default per attribute
  public function markAsDefault(AttributeOptionRepository $repo): void {
    $existing = $repo->findDefaultForAttribute($this->attribute);
    if ($existing !== null && $existing->getId() !== $this->getId()) {
      $existing->isDefault = false; // unset previous
      $repo->save($existing);
    }
    $this->isDefault = true;
  }

  public function unsetDefault(): void {
    $this->isDefault = false;
  }
}
```

`apps/api/src/Catalog/Domain/Exception/InvalidColorFormatException.php` (nowy).
`apps/api/src/Catalog/Domain/Exception/SystemAttributeImmutableException.php` (nowy, mapped to RFC 7807 422).
`apps/api/src/Catalog/Domain/Exception/AttributeOptionInUseException.php` (nowy, mapped to RFC 7807 422 z metadata `instances`).
`apps/api/src/Catalog/Domain/Exception/AttributeInUseException.php` (nowy, dla DELETE attribute z `instancesWith > 0`).

#### ApiPlatform mapping

`apps/api/src/Catalog/Infrastructure/ApiPlatform/Resource/Attribute.xml` — rozszerzenie o operations Post, Patch, Delete (z security `is_granted('ROLE_INFORMATION_ARCHITECT')` + voter `attribute:create/update/delete`).

`apps/api/src/Catalog/Infrastructure/ApiPlatform/Resource/AttributeOption.xml` (NOWY):
- GET collection nested `/api/attributes/{attributeCode}/options` (custom controller routing z `Symfony\Routing`)
- POST collection nested
- GET item `/api/attribute_options/{id}`
- PATCH item
- DELETE item
- Custom action POST `/api/attributes/{attributeCode}/options/reorder` (controller `ReorderAttributeOptionsController`).

### 4.3 Listenery / event subscribers

- `AttributeSchemaVersionBumper` (`apps/api/src/Catalog/Infrastructure/Doctrine/EventListener/AttributeSchemaVersionBumper.php`): bump `tenant.schema_version` na lifecycle events `prePersist` (Attribute, AttributeOption — tworzenie), `preUpdate` (Attribute — zmiana `code | type | isUnique | isLocalizable | isScopable`; ignoruje `name | helper`), `preRemove`. Tenant filter active.
- `AttributeOptionDefaultUniquenessListener` — defence in depth, drugiemu `prePersist`/`preUpdate` ustawia `isDefault=false` dla pozostałych opcji w obrębie attribute jeśli właśnie zaznaczamy `isDefault=true`. (Partial unique index w DB jest pierwszym safeguardem.)
- `AttributeOptionPositionAssigner` — `prePersist` automatycznie ustawia `position = max(position) + 1` jeśli nie podano w request.

Wszystkie listenery emitują events do `dh_auditor` — TylkoConfig sanity check, `dh_auditor` natywnie łapie zmiany ORM.

### 4.4 Permissions / RBAC

#### Macierz ról × operacji

| Rola | attribute:read | attribute:create | attribute:update | attribute:delete | attribute_option:read | attribute_option:create | attribute_option:update | attribute_option:delete |
|---|---|---|---|---|---|---|---|---|
| `ROLE_INFORMATION_ARCHITECT` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `ROLE_EDITOR` | ✅ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ |
| `ROLE_VIEWER` | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |

**System attributes**: dla wszystkich ról `attribute:update` jest ograniczone na poziomie domain do pól nie-krytycznych (label, helper). Pola `code`, `type`, `isSystem`, `isUnique`, `isLocalizable`, `isScopable` blokowane przez `SystemAttributeImmutableException` w `UpdateAttributeService`.

#### Voter

`apps/api/src/Identity/Infrastructure/Security/AttributeOptionVoter.php` — voter analogiczny do `AttributeVoter`, sprawdza role + (opcjonalnie) tenant context.

#### Endpoint security

W ApiPlatform XML:
```xml
<operation class="ApiPlatform\Metadata\Patch" 
           security="is_granted('attribute:update', object)" 
           securityPostDenormalize="is_granted('attribute:update', object)">
```

#### Audit log entries

dh_auditor automatically loguje create/update/delete dla `Attribute` + `AttributeOption`. `actor_id` z `Symfony\Security\Core\Authentication\Token\Storage\TokenStorageInterface`. `actor_name` denormalizowany z User entity.

### 4.5 Provenance

**N/A** — `attribute_options` definiują wartości DOPUSZCZALNE, nie zapisują się do `object_values`. Provenance dotyczy `object_values` i nie zmienia się w VIEW-02.

### 4.6 Worker / async

- **Bulk reorder** `POST /api/attributes/{code}/options/reorder` — synchroniczny w jednej transakcji DB, do max 200 options (UI limit). Bez Messenger handler (overkill).
- **Usage count per option** `GET /api/attributes/{code}/options/{optionCode}/usage` — query z `EXPLAIN ANALYZE` weryfikowanym pre-PR; cache 60s w `cache.app` z tagiem `attribute_option_usage_{id}` invalidowanym przy `object_values` change. Analogicznie do `UsageQueryService` dla Attribute.
- **Bulk import CSV** wartości — out of scope, follow-up VIEW-02d. Wtedy `BulkImportAttributeOptionsHandler` (Symfony Messenger) z `AbstractBatchHandler` + `EntityManager::clear()` co N=200.

### 4.7 Real-time (Mercure)

**Skip dla VIEW-02** — model attributes zmieniają się rzadko (1 admin per workspace w MVP). Follow-up dla multi-admin scenario w Fazie 2 (kiedy wprowadzamy SaaS multi-tenant).

### 4.8 Fixtures

`apps/api/src/Catalog/Application/DemoCatalogSeeder.php` — expand:

```php
// Add 8 missing attributes from mockup:
$this->createAttribute('appointment_duration', ['pl' => 'Czas trwania wizyty (min)'], AttributeType::Number, ['unit' => 'min']);
$this->createAttribute('requires_appointment', ['pl' => 'Wymaga umówienia'], AttributeType::Boolean);
$this->createAttribute('requires_referral', ['pl' => 'Wymaga skierowania'], AttributeType::Boolean);
$this->createAttribute('min_age', ['pl' => 'Minimalny wiek (lat)'], AttributeType::Number, ['unit' => 'lat']);
$this->createAttribute('contraindications', ['pl' => 'Przeciwwskazania'], AttributeType::Richtext, ['localizable' => true]);
$this->createAttribute('specialist_required', ['pl' => 'Wymagany specjalista'], AttributeType::Boolean);
$this->createAttribute('is_nfz_eligible', ['pl' => 'Refundacja NFZ'], AttributeType::Boolean);
$this->createAttribute('nfz_code', ['pl' => 'Kod NFZ'], AttributeType::Text);

// IP rating values (for existing ip_rating attribute):
$ipRating = $this->getAttribute('ip_rating');
foreach ([
  ['IP20', '#71717a', 84],
  ['IP44', '#71717a', 121],
  ['IP54', '#3b82f6', 96, true],  // default
  ['IP65', '#10b981', 71],
  ['IP66', '#10b981', 24],
  ['IP67', '#f59e0b', 12],
  ['IP68', '#ef4444', 4],
] as $i => [$code, $color, $instances, $isDefault = false]) {
  $this->createAttributeOption($ipRating, $code, ['pl' => $code], $color, $isDefault, $i + 1);
  // simulate $instances by creating object_values seed (lub: pomijamy w fixtures, zostawiamy 0; UI pokazuje computed)
}

// Currency values (for existing currency attribute):
foreach (['PLN', 'EUR', 'USD', 'GBP', 'CHF'] as $i => $cur) {
  $this->createAttributeOption(...);
}

// VAT rate values:
foreach (['0%', '5%', '8%', '23%', 'zw'] as $i => $rate) {
  $this->createAttributeOption(...);
}

// Tags multi-select values:
foreach (['promocja', 'nowość', 'bestseller', 'wyprzedaż'] as $i => $tag) {
  $this->createAttributeOption(...);
}
```

**Total**: 27 attributes (19 existing + 8 new + 4 system: created_at, updated_at, created_by, updated_by). 7 IP rating + 5 currency + 5 vat_rate + 4 tags = **21 attribute options seeded**.

`object_values` seed: dosypujemy ~412 instances ip_rating values per breakdown (84+121+96+71+24+12+4) — generowane przez `DemoCatalogSeeder` przy seedingu produktów.

### 4.9 dh_auditor

`apps/api/config/packages/dh_auditor.yaml` — sanity check: `Attribute` ✅, `AttributeOption` ✅, `AttributeGroup` ✅. Nowe pola (`color`, `isDefault`, `isDeprecated`) auto-łapane przez dh_auditor (śledzi wszystkie kolumny encji poza `created_at | updated_at`).

## 5. Sub-tasks (checklist)

### Backend
- [ ] Migracja Doctrine: `Version20260502NNNNNN.php` (3 nowe kolumny + partial unique index).
- [ ] AttributeOption.php: gettery/settery `color | isDefault | isDeprecated` + domain methods `markAsDefault | unsetDefault`.
- [ ] AttributeOption.orm.xml: dodać 3 mapowania pól.
- [ ] InvalidColorFormatException.php (nowa).
- [ ] SystemAttributeImmutableException.php (nowa).
- [ ] AttributeOptionInUseException.php (nowa).
- [ ] AttributeInUseException.php (nowa).
- [ ] CreateAttributeController.php (POST) + AttributeCreatePayload DTO + AttributeCreateValidator.
- [ ] UpdateAttributeController.php (PATCH) + system-immutable guard.
- [ ] DeleteAttributeController.php (DELETE) + in-use check via UsageQueryService.
- [ ] AttributeOptionsController.php (GET + POST nested + reorder).
- [ ] UpdateAttributeOptionController.php (PATCH).
- [ ] DeleteAttributeOptionController.php (DELETE) + in-use check.
- [ ] AttributeOptionService.php (Application service: create, update, delete, reorder, markAsDefault).
- [ ] AttributeOptionVoter.php (RBAC).
- [ ] AttributeSchemaVersionBumper.php (listener).
- [ ] AttributeOptionDefaultUniquenessListener.php (listener).
- [ ] AttributeOptionPositionAssigner.php (listener).
- [ ] UsageQueryService rozszerzenie: dodać `where: { groups, types, categories }` do response `/api/attributes/{code}/usage` + nowy method `countObjectsUsingOption(AttributeOption)`.
- [ ] DemoCatalogSeeder.php: dosypać 8 attributes + 21 options + ~412 instances.
- [ ] AttributeOption.xml ApiPlatform resource (collection nested + item).
- [ ] Attribute.xml ApiPlatform: rozszerzenie o POST/PATCH/DELETE.
- [ ] dh_auditor.yaml sanity check.

### Frontend
- [ ] `apps/admin/src/components/modeling/flag-pill.tsx`.
- [ ] `apps/admin/src/components/modeling/color-swatch-picker.tsx`.
- [ ] `apps/admin/src/components/modeling/attribute-type-grid.tsx`.
- [ ] `apps/admin/src/components/modeling/value-row-item.tsx` (DnD wrapper).
- [ ] `apps/admin/src/components/modeling/attribute-value-list.tsx`.
- [ ] `apps/admin/src/components/modeling/attribute-value-definition-card.tsx`.
- [ ] `apps/admin/src/components/modeling/attribute-value-preview-card.tsx`.
- [ ] `apps/admin/src/components/modeling/attribute-value-audit-card.tsx`.
- [ ] `apps/admin/src/components/modeling/attribute-where-used-section.tsx`.
- [ ] `apps/admin/src/components/modeling/usage-row.tsx`.
- [ ] `apps/admin/src/components/modeling/attribute-create-sidebar.tsx`.
- [ ] `apps/admin/src/components/modeling/sticky-form-footer.tsx` (extracted z VIEW-01 jeśli nie istnieje, lub nowy).
- [ ] `apps/admin/src/lib/use-attribute-mutations.ts` (create/update/delete + invalidations).
- [ ] `apps/admin/src/lib/use-attribute-options.ts` (CRUD + reorder + usage).
- [ ] `apps/admin/src/features/catalog/attributes/list.tsx` rebuild (usuń banner, popraw filtry, dopasuj grid).
- [ ] `apps/admin/src/features/catalog/attributes/show.tsx` rebuild edit-in-place + sticky footer + dirty guard.
- [ ] `apps/admin/src/features/catalog/attributes/new.tsx` (NOWY).
- [ ] `apps/admin/src/features/catalog/attributes/values.tsx` rebuild (z MOCK na live).
- [ ] `apps/admin/src/App.tsx`: dodać Refine resource `attribute_options`, rebind route `/values` na nowy komponent.
- [ ] `apps/admin/src/locales/pl.json`: ~45 nowych kluczy.
- [ ] `apps/admin/src/locales/en.json`: ~45 odpowiedników.
- [ ] Add dnd-kit/sortable do dependencies (`pnpm add @dnd-kit/sortable @dnd-kit/core` w `apps/admin/package.json` jeśli nie ma).

### E2E + integration
- [ ] `apps/admin/e2e/modeling-attributes-list.spec.ts` (filter chips, search, klik wiersza, klik values chip, CTA Nowy atrybut).
- [ ] `apps/admin/e2e/modeling-attributes-create.spec.ts` (happy path + walidacja code regex + walidacja name required).
- [ ] `apps/admin/e2e/modeling-attributes-edit-in-place.spec.ts` (edit field, dirty footer, save, revert, nav guard, system locked).
- [ ] `apps/admin/e2e/modeling-attributes-values-editor.spec.ts` (add value, edit code, edit color, edit label PL/EN, set default, deprecate, drag-reorder, delete with in-use blocker).
- [ ] axe-core scan w każdym z 4 specs (0 violations serious/critical).
- [ ] `tests/ApiTest/AttributeCreateTest.php` (POST 201 + 400 walidacja code regex + 401 + 403 voter + 422 duplicate code + multi-tenant).
- [ ] `tests/ApiTest/AttributeUpdateTest.php` (PATCH 200 + 422 system immutable + 403 voter).
- [ ] `tests/ApiTest/AttributeDeleteTest.php` (DELETE 204 + 422 in-use + 403 voter).
- [ ] `tests/ApiTest/AttributeOptionCrudTest.php` (full CRUD).
- [ ] `tests/ApiTest/AttributeOptionReorderTest.php` (POST reorder + idempotent).
- [ ] `tests/ApiTest/AttributeOptionUsageTest.php` (instances count + cache invalidation).
- [ ] `tests/ApiTest/AttributeOptionDefaultUniquenessTest.php` (1 default per attribute, partial index).
- [ ] `tests/Unit/Catalog/Domain/AttributeOptionTest.php` (markAsDefault domain logic).
- [ ] `tests/Unit/Catalog/Domain/InvalidColorFormatExceptionTest.php`.

### Testy non-functional
- [ ] PHPStan max: 0 errors.
- [ ] Biome strict: 0 errors.
- [ ] PHPUnit + ApiTestCase: 100% pass.
- [ ] Playwright E2E: 4 specs zielone.
- [ ] axe-core: 0 violations serious/critical.
- [ ] composer audit + pnpm audit: 0 high/critical.
- [ ] EXPLAIN ANALYZE w PR description dla każdego nowego query.
- [ ] k6 raport p95 < 300ms na seed 50k SKU dla `/api/attributes` + `/api/attributes/{code}/options`.
- [ ] Lighthouse CI raport (performance ≥ 85, a11y = 100).
- [ ] Bundle size FE: Δ < 50KB gzip (Vite raport).

### Dokumentacja
- [ ] `docs/api-spec/v0.json` regeneracja po dodaniu route'ów.
- [ ] `agent/current_status.md`: dodać sekcję `## 2026-05-02: VIEW-02 view-first marathon — Modelowanie · Attributes`.
- [ ] `agent/lessons.md`: post-mortem (jeśli odkryjemy non-obvious — dnd-kit + Playwright flaky drag, partial unique index w Doctrine, default uniqueness w Application service).
- [ ] PR description z `## Summary | ## Backend | ## Frontend | ## Quality gates | ## Test plan | ## Świadome odejścia`.

### Manual smoke (operator po merge)
- [ ] Login `admin@demo.localhost / changeme`.
- [ ] Lista 27 atrybutów, filter chips działają.
- [ ] Klik `ip_rating` → detail edit-in-place z 7 wartościami w Allowed values preview.
- [ ] Klik „Zarządzaj wartościami" → values editor, IP54 jako default.
- [ ] Edycja koloru IP65 z `#10b981` na `#06b6d4`, zapisz → DevTools Network 200.
- [ ] Drag-reorder IP67 → IP65 → 200, kolejność trzyma się po refresh.
- [ ] Klik `Anuluj` przy pending changes → confirm dialog.
- [ ] `/modeling/attributes/new` → utwórz `warranty_months` typu number z `unit=msc` → redirect do detail.
- [ ] Edit nazwy `warranty_months` na PL „Gwarancja w miesiącach" → sticky footer pokazuje „1 pole zmienione" → Zapisz → 200.
- [ ] Próba edytowania `code` w detail `sku` (system) → field disabled z LockBadge.
- [ ] Próba usunięcia IP54 (z 96 instances) → 422 + toast „Wartość używana przez 96 obiektów. Wybierz wartość docelową dla migracji."
- [ ] DevTools Console — brak czerwonych errorów.

## 6. Acceptance criteria — funkcjonalne

- Wygląda **pixel-perfect** jak mockup (Figma/screenshot diff < 2%) — verified side-by-side + Playwright `toHaveScreenshot()`.
- **Wszystkie 4 widoki interaktywne** end-to-end (klik → BE → visible result):
  - Lista: filter chips + search + klik wiersza/values chip/CTA.
  - Detail: edit field → dirty footer → save → 200 → toast.
  - Create: form → submit → 201 → redirect.
  - Values editor: add/edit/delete/reorder/default/deprecate → optimistic update → save → 200/204.
- **Empty / loading / error states** zaobserwowalne dla każdego z 4 widoków.
- **i18n PL/EN** przełącza się przez topbar dropdown — wszystkie nowe klucze widoczne w obu lokalach.
- **System locked**: klik na pole `code | type` w `sku.show` → field disabled z LockBadge tooltip „Pole systemowe — niezmienne".
- **Multi-tenancy**: drugi tenant (`/api/auth/login` jako inny user) widzi inną listę atrybutów (cross-read = 0).

## 7. Acceptance criteria — non-functional (TWARDE GATES, NIENEGOCJOWALNE)

- **Performance**: p95 `/api/attributes` < 300ms na seed 50k SKU. p95 `/api/attributes/{code}/options` < 100ms. p95 `/api/attributes/{code}/options/{optionCode}/usage` < 200ms (z cache hit) / < 500ms (cache miss). k6 raport w PR description.
- **N+1 query check**: EXPLAIN ANALYZE dla każdego nowego query (CreateAttribute, UpdateAttribute, DeleteAttribute, AttributeOptions list, ReorderOptions, OptionUsage) w PR description, **zero N+1**.
- **Indeksy**: `attribute_options(attribute_id, position)` (już jest), `attribute_options(attribute_id) WHERE is_default=true` partial unique (NOWY).
- **Pagination**: limit max 200, default 20, cursor-based dla `/api/attributes` przy >1000.
- **Memory worker**: peak < 128MB przy bulk reorder 200 options. Symfony Messenger handler nie używany w VIEW-02 (sync only).
- **Bundle size FE**: Δ < 50KB gzip. dnd-kit/sortable ~12KB, dnd-kit/core ~14KB — łącznie ~26KB. Pozostałe ~24KB na nowe komponenty.
- **Lighthouse CI**: performance ≥ 85, a11y = 100, best-practices ≥ 90 dla każdego z 4 trasy.
- **PHPStan max**: 0 errors.
- **Biome strict**: 0 errors.
- **PHPUnit coverage**: ≥ 80% nowej domain logic (AttributeOption, services, exceptions).
- **ApiTestCase**: każdy nowy endpoint ma test 401 + 403 + 404 + walidacja + happy path + multi-tenancy isolation.
- **Playwright E2E**: 4 specs zielone (list, create, edit-in-place, values-editor) + ≥ 1 edge case per spec.
- **axe-core**: 0 violations serious/critical na wszystkich 4 trasach.
- **composer audit + pnpm audit**: 0 high/critical.
- **Multi-tenancy**: cross-tenant read test = 0 wyników (Doctrine TenantFilter).
- **RBAC**: voter test dla każdej roli (Architect, Editor, Viewer) + jednej bez dostępu (anonymous).
- **Audit log**: write/update/delete attribute + option pisze entry przez dh_auditor (verified `audit_log` table).
- **Provenance**: N/A (attribute_options nie pisze do object_values).
- **i18n coverage**: ~45 nowych kluczy w `pl.json` + `en.json`. Brak hardcoded literałów w nowych komponentach (verified Biome rule + manual grep).
- **OpenAPI snapshot**: `docs/api-spec/v0.json` zaktualizowany przez `bin/console api:openapi:export`.
- **TypeScript**: `tsc -b --noEmit` zielony.
- **Vite build**: zielony, raport bundle size + timing.

## 8. Smoke-test scenariusze (manualne, dla operatora po merge)

1. **Login**: `admin@demo.localhost / changeme` → 200 OK, JWT token, redirect do `/dashboard`.
2. **Nawigacja**: Dashboard → Modelowanie sidebar → tab `Attributes`. Lista pokazuje 27 atrybutów. Tab Badge pokazuje `27`.
3. **Filter test**: klik chip `select` → filtrowane do 3 atrybutów (`ip_rating`, `currency`, `vat_rate`). Klik `wszystkie` → wraca 27.
4. **Search test**: wpisz `nfz` → filtrowane do 2 atrybutów (`is_nfz_eligible`, `nfz_code`).
5. **Detail show**: klik wiersz `ip_rating` → `/modeling/attributes/ip_rating`. Header: code mono + TypeBadge `select` + nazwa „Klasa szczelności IP". Card „Allowed values": 7 chipsów IP20–IP68. Card „Where used": 1 typ + 2 grupy + 412 instancji.
6. **Edit-in-place test**: klik pole „Helper text" → input editable. Wpisz „Standard ochrony przed pyłem i wodą". Sticky footer pojawia się: „1 pole zmienione" + „Anuluj" + „Zapisz zmiany". Klik „Zapisz zmiany" → DevTools Network PATCH `/api/attributes/ip_rating` 200. Toast „Zapisano". Sticky footer znika.
7. **Dirty guard test**: edytuj pole „Nazwa" w `voltage`. Próba kliknięcia „Wstecz do biblioteki" → ConfirmDialog „Niezapisane zmiany". Klik „Anuluj" → zostaję. Klik „Zapisz zmiany" → save → wracam.
8. **System locked test**: detail `sku` (system). Klik pole „Code" → input disabled, LockBadge widoczny obok. Klik „Type" → też disabled.
9. **Manage values test**: detail `ip_rating` → klik „Zarządzaj wartościami" → `/modeling/attributes/ip_rating/values`. Lista 7 wartości po lewej. IP54 z badge `default`.
10. **Add value test**: klik „+ Dodaj wartość" → nowy wiersz `value_8` aktywny. Edytuj code na `IP69`. Color swatch picker → wybierz `#06b6d4`. LocaleTabs PL → „Klasa IP69". Klik „Zapisz zmiany" → POST 201 + reorder PATCH 204. Wartość persystuje po refresh.
11. **DnD reorder test**: drag IP67 → przed IP65. „Zapisz zmiany" → POST `/options/reorder` 204. Po refresh kolejność: IP20, IP44, IP54, IP67, IP65, IP66, IP68, IP69.
12. **Default toggle test**: klik checkbox „Wartość domyślna" przy IP65 → poprzedni default IP54 unset, IP65 ma badge `default`. Save → po refresh IP65 jest default.
13. **Deprecated test**: klik „Wycofana" przy IP69 → wiersz w liście ma szare tło. Save → po refresh trzyma stan.
14. **Delete blocker test**: klik trash przy IP54 (96 instances) → toast 422 „Wartość używana przez 96 obiektów. Wybierz wartość docelową dla migracji." (mapping modal poza scope, tylko blocker).
15. **Create attribute test**: klik „Nowy atrybut" → `/modeling/attributes/new`. Wpisz code `warranty_months`, nazwa PL „Gwarancja (msc)", typ `number`. Sidebar live preview pokazuje `warranty_months · number` + „Gwarancja (msc)". Klik „Utwórz atrybut" → POST 201 + redirect do `/modeling/attributes/warranty_months`.
16. **Walidacja test**: w create form wpisz code `123-bad-code` → walidacja 400/422 „Code must match `^[a-z][a-z0-9_]+$`".
17. **Multi-tenancy test**: logout → login jako inny user (drugi tenant) → lista atrybutów inna (filtrowana TenantFilter).
18. **DevTools Console**: w żadnym z scenariuszy 1–17 brak czerwonych errorów (warningi OK).

## 9. Edge cases / poza zakresem

### Pokryte edge cases:
- Code regex walidacja (FE + BE).
- Duplicate code (412 / 422).
- System attribute immutable (422 + RFC 7807).
- Default uniqueness (partial unique index + listener).
- Option in-use deletion blocker (422 + instances count).
- Empty collection (filter no results, 0 options).
- Multi-tenancy isolation.
- Concurrent edit (optimistic update + rollback on 409).

### Świadomie poza zakresem (deferred → follow-up tickety):

1. **MigrationImpactModal pixel-perfect refresh** — current `migrate-type.tsx` zostaje as-is. Pixel-perfect refresh w **VIEW-02c** jeśli operator zdecyduje że potrzebne. Estymacja ~6h.
2. **Import CSV / Export wartości** — disabled buttons z tooltipem w values editor, follow-up **VIEW-02d** (~8h). Wymaga decyzji CSV schema (ADR).
3. **Real-time updates Mercure** — skip dla MVP (1 admin per workspace). Follow-up dla SaaS multi-tenant.
4. **Bulk attribute import** — placeholder w UI, brak w VIEW-02 (wymaga ADR CSV schema).
5. **Schema_rev counter footer** — bumpa robimy, ale wyświetlanie globalnego rev w stopce jest cross-cutting → **VIEW-04** (Categories) ma to na liście.
6. **Mapping modal przy delete option z `instances > 0`** — mockup nie definiuje, zostaje tylko blocker 422 z toastem. Follow-up **VIEW-02e** (~10h) dorabiamy proper mapping flow.
7. **Attribute Group attachment z poziomu attribute detail** — w mockupie sekcja „Groups" pokazuje gdzie jest dołączony, ale add/remove jest w VIEW-03 (Attribute Groups). VIEW-02 tylko czyta `groupsUsed`.
8. **Delete attribute UI** — mockup nie ma DangerZone w detail (tylko create/edit). DELETE endpoint dorabiamy w BE (ready), UI w follow-up jeśli potrzebne. **TODO(handoff)** w show.tsx.

### Edge cases zostawione na później (z linkiem do follow-up):

- Duplicate label w obrębie locale (np. dwie wartości `IP54` z label „Norma 54" PL) — BE accept, brak walidacji unique label per locale. Follow-up jeśli okaże się problemem.
- Soft delete attribute (zachowaj dane historyczne) — current behavior: hard delete + 422 jeśli in-use. Follow-up: tombstone field.
- Attribute versioning (ADR-012) — proposed, deferred do osobnego ticketu.

## 10. Powiązane ADR / dokumenty

### Bez nowego ADR
Zmiany VIEW-02 są w jednym bounded contexcie (Catalog), bez wpływu na ObjectType / ObjectValue / Provenance / multi-tenancy strategy. Schemy expand-only (ADD COLUMN + partial unique index). AttributeOption jako sub-resource API Platform — drobna decyzja API design, dokumentowana w PR description, nie wymaga ADR.

### Aktualizacje dokumentacji

- **`agent/current_status.md`** — dodać sekcję `## 2026-05-02: VIEW-02 view-first marathon — Modelowanie · Attributes`. Aktualna sub-faza: MVP-Alpha. Aktualny epik: UI-08. Aktualny ticket: VIEW-02. Następny krok: VIEW-03 (Attribute Groups).
- **`agent/lessons.md`** — post-mortem na koniec marathonu, sekcja `## Lessons z VIEW-02`. Spodziewane lessons: dnd-kit + Playwright flaky drag (alternatywa: `data-testid` + manual `keyboard.down(' ')` + arrows), partial unique index w Doctrine ORM (XML mapping `<unique-constraint>` + raw SQL w migration), default uniqueness defence in depth (DB index + listener).
- **`Project Plan/02-plan-projektu-pim.md`** — checkbox MVP-Alpha „Modelowanie · Attributes pixel-perfect" → ✅ po merge.
- **`Project Plan/UI/Wdrozenie_grafiki/plan-handoff-wdrozenie.md`** — odznaczyć VIEW-02 jako done w mapie zależności.
- **`docs/api-spec/v0.json`** — regenerate przez `bin/console api:openapi:export | python3 -m json.tool > docs/api-spec/v0.json`.

### Powiązane tickety
- **#372 / PR #373 (VIEW-01 Object Types)** — merged, dostarczył `LocaleTabsField`, `LocaleAddDialog`, `BuiltInLockBadge`, `StatBox`, `useCurrentWorkspace`. Reuse w VIEW-02.
- **VIEW-03 (Attribute Groups)** — depend on VIEW-02 conventions (FlagPill toggle pattern, edit-in-place, sticky footer).
- **VIEW-04 (Categories)** — depend on VIEW-02 (ltree + attached attributes preview).

### Operator follow-up plan po merge

1. Smoke test 18 scenariuszy z sekcji 8.
2. Decyzja czy VIEW-02c (MigrationImpactModal pixel-perfect) jest potrzebny.
3. Decyzja czy VIEW-02d (CSV import/export) jest priorytetem.
4. Akceptacja → przejście do VIEW-03 (Attribute Groups) wg planu handoff.

---

**Estymacja**: ~48h (16 BE + 24 FE + 6 testy non-func + 2 PR/CI).
**Status**: 📋 PLANNED — czeka na sygnał operatora „lecimy z implementacją".
**Ostatnia aktualizacja**: 2026-05-02 przez SKILL-VIEW-FIRST-TICKET.
