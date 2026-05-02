# [VIEW-03] Modelowanie · Attribute Groups — pixel-perfect lista + create + detail edit-in-place + 2 popupy (Z biblioteki / Stwórz nowy)

> GitHub Issue: [#375](https://github.com/malipie/PIM/issues/375). Branch: `feat/view-03-modelowanie-attribute-groups`.
> Ticket view-first wg szablonu `feedback_view_first_ticket_template.md`. Stan na 2026-05-02.
> Źródło prawdy designu (cały scope tego ticketu pochodzi z jednego pliku):
> - `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/groups-categories.jsx` — sekcje:
>   - `AttributeGroupsView` (linie 3–52) — lista grup
>   - `GroupRow` (linie 54–80) — wiersz w liście
>   - `AttributeGroupDetail` (linie 82–253) — detail edit-in-place z 2 popupami
>   - `NewAttributeGroupView` (linie 482–603) — pełnoekranowy widok tworzenia grupy
>   - `AddAttributeFromLibraryModal` (linie 705–807) — popup „Z biblioteki"
>   - `CreateAttributeInGroupModal` (linie 813–953) — popup „Stwórz nowy"
> Prototyp uruchamiany przez `python3 -m http.server 3000` w `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/` → `http://localhost:3000/Modelowanie.html`, zakładka **Attribute Groups**.
> Poza scope tego ticketu (z tego samego pliku): `CategoriesView`, `TreeNode`, `FormPreviewRow`, `AttachAttributeGroupModal` (popup attach grupy do ObjectType — to inny popup, nie ten z mockupu operatora).

---

## 1. Kontekst i cel widoku

Widok **Modelowanie · Attribute Groups** to zarządzanie grupami atrybutów jako **wymienialnymi jednostkami** — w naszym modelu (proponowany ADR-012) grupa ma **własny URL, audit, wersjonowanie i jest first-class entity**. Pimcore tej abstrakcji nie ma, Akeneo traktuje grupę tylko jako sortowanie. Operator (architekt informacji w organizacji-tenancie) używa tego widoku żeby:

1. **Przeglądać 12+ grup atrybutów** (system + business) z search po `code` / `label`. Sekcje: `System (auto-attached)` (system grupy domyślnie dołączone do każdego ObjectType, np. `identification`, `audit`) + `Business groups` (custom, dołączane do wybranych ObjectType lub Categories).
2. **Wejść w detail dowolnej grupy** — zobaczyć i edytować definicję (Code, Color, Description, Locale labels), listę atrybutów w grupie z drag-reorder, where-used (ObjectTypes globalnie / Categories deklarują / instancji dotkniętych).
3. **Stworzyć nową business group** przez pełnoekranowy formularz — Identyfikacja (Code immutable + Locale label + Description) + Wygląd (8 swatch kolorów + 14 ikon emoji) + Zachowanie (3 toggle: Wymagana / Współdzielona / Conditional visibility) + sidebar Preview + Następnie.
4. **Dodać atrybuty do grupy z biblioteki** — popup multi-select z search + filtr typu, wybierz N atrybutów z globalnej biblioteki, batch attach do grupy. Atrybuty już w grupie są wyłączone (checkbox disabled, badge „w grupie").
5. **Stworzyć nowy atrybut bezpośrednio w grupie** — popup ze skróconym formularzem Attribute create (Code + Type + Nazwa PL/EN + Walidacja flagi: Required/Unique/Localizable). Po submit atrybut tworzony w globalnej bibliotece + automatyczny attach do grupy (audit log: `attribute.create` + `group.attach`).
6. **Zarządzać `visible_when` rules dla grupy** — gdy grupa ma reguły widoczności w junction (`AttributeGroupAttribute.visibleWhen`), pokaż Card „Visibility rules" z testami pass/fail dla par atrybutów.

Powiązane: ADR-009 (ObjectType jako koncept pierwszej klasy — grupa attach'owana do ObjectType lub Category), proponowany ADR-012 (AttributeGroup as first-class — własny URL, audit, wersjonowanie), CLAUDE.md sekcja „Reguły implementacyjne" punkt 4 (hybrid attributes model: `attributes` + junction `object_type_attributes` + `object_values` + `attributes_indexed`).

Epik: **UI-08** — pixel-perfect Modelowanie. Backlog źródłowy: `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md`. Ticket nadrzędny: zamyka trzeci widok view-first flow Modelowania (po VIEW-01 ObjectTypes i VIEW-02 Attributes Library). VIEW-04 (Categories Tree) zależy od konwencji ustalonych w VIEW-02 + VIEW-03 (np. `<ColorSwatchPicker>`, `<IconPicker>`, edit-in-place pattern, drag-reorder z dnd-kit).

## 2. Mockup / źródło designu

> **WAŻNE — pixel-perfect binding**: implementacja FE MUSI 1:1 odwzorować kod prototypu z `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/groups-categories.jsx`. To **single source of truth dla layoutu, klas Tailwind, struktury DOM, copy, paddingów, fontów, kolorów i animacji**. Każdy element w `AttributeGroupsView`, `GroupRow`, `AttributeGroupDetail`, `NewAttributeGroupView`, `AddAttributeFromLibraryModal`, `CreateAttributeInGroupModal` ma odpowiednik w produkcyjnym kodzie React+Tailwind w `apps/admin/src`. Adaptacje stack-specific (shadcn primitive zamiast hand-rolled, dnd-kit zamiast hand-rolled drag) są dozwolone, ale wizualny rezultat ma się zgadzać <2% pixel mismatch.

### Szczegółowe odwołania do prototypu (`groups-categories.jsx`):

- **Lista (`AttributeGroupsView`)**: linie 3–52. Sekcje system + business, search, CTA „Nowa grupa".
- **Wiersz listy (`GroupRow`)**: linie 54–80. Grid 6-kolumn (icon 44px / name+desc 1.6fr / code 1fr / N atrybutów 120px / N typy·N kat. 120px / chevron 28px).
- **Detail edit-in-place (`AttributeGroupDetail`)**: linie 82–253. Sticky header z X close + ikona + name + URL + Preview/Edytuj. 4 Cards: Identyfikacja, Attributes in this group (z 2 button do popupów), Visibility rules (gdy `g.rules.length > 0`), Where used.
- **Create form (`NewAttributeGroupView`)**: linie 482–603. **Ticket implementuje DOKŁADNIE TEN widok** — back button + header (ikona 56px + caption + title + description + 2 buttons) + grid 1fr+320px (left: Card z 3 sekcjami Identyfikacja/Wygląd/Zachowanie, right: 2 sidebar Cards Podgląd + Następnie). Pełny mapping w sekcji 3.4c.
- **Popup „Z biblioteki" (`AddAttributeFromLibraryModal`)**: linie 705–807. **Ticket implementuje DOKŁADNIE TEN popup** — width 780px, sticky header z layers icon + title + description + close, search + filter (Wszystkie typy dropdown), scrollable list z chip type + checkbox + lock badge + "w grupie" badge dla istniejących, footer z licznikiem + Anuluj/Dołącz. Pełny mapping w sekcji 3.4d.
- **Popup „Stwórz nowy" (`CreateAttributeInGroupModal`)**: linie 813–953. **Ticket implementuje DOKŁADNIE TEN popup** — width 820px, sticky header z violet zap icon + title + description + close, scrollable body z 4 sekcjami: Identyfikacja (Code + Typ danych + Nazwa wyświetlana LocaleTabs), Konfiguracja typu (warunkowo gdy number/money lub select/multi-select), Walidacja i flagi (3 cards: Required/Unique/Localizable), Podgląd w grupie (preview row); footer z audit log info + Anuluj/Utwórz i dołącz. Pełny mapping w sekcji 3.4e.
- **Komponenty pomocnicze**: `<window.Card>`, `<window.LockBadge>`, `<window.LocaleTabs>`, `<window.TypeBadge>`, `<window.SettingRow>`, `<window.Modal>` z `Zrodla/.../src/modeling/shared.jsx` + reuse z VIEW-01 (`<LocaleTabsField>`, `<LocaleAddDialog>`, `<BuiltInLockBadge>`).
- **Mock data**: `Zrodla/.../src/modeling/data.jsx`:
  - `ATTRIBUTE_GROUPS` (12 grup) — pola: `code, name, nameEn?, description, color, icon, system, attrs[code], rules[{attr, when}], typesUsed, categoriesUsed, objectsAffected`. Kontrakt response BE musi pokrywać wszystkie pola.
  - `ATTRIBUTES` (27 atrybutów) — wykorzystywane w detail (lista atrybutów w grupie) + popup „Z biblioteki" (selektor).
- **Powiązane widoki w tym samym shell-u**: zakładki `Object Types` (#VIEW-01), `Attributes` (#VIEW-02), `Categories` (#VIEW-04) w tym samym `/modeling/*`. Nie dotykamy ich w VIEW-03 — tylko spójność topbar/breadcrumb (TabBadge counter dla Attribute Groups pokazuje `12`).
- **Rodzic**: `/modeling` (shell z tab nav); **dzieci/popupy w VIEW-03**: `<AddAttributeFromLibraryDialog>` (popup multi-select), `<CreateAttributeInGroupDialog>` (popup skróconego attribute create), `<LocaleAddDialog>` (z VIEW-01, wywoływany z `<LocaleTabsField>` przy klik „+ Dodaj język"), `<ConfirmDialog>` przy próbie nawigacji z dirty stanem.

### Sposób weryfikacji „pixel-perfect":

1. **Side-by-side comparison** — operator otwiera prototyp `http://localhost:3000/Modelowanie.html` (zakładka Attribute Groups) w lewej połowie ekranu i implementację `https://pim.localhost/modeling/attribute-groups[/...]` w prawej. Każda sekcja, padding, font-size, border-radius musi się zgadzać.
2. **Visual regression Playwright** — `toHaveScreenshot()` na każdej z 3 tras (`/modeling/attribute-groups`, `/modeling/attribute-groups/new`, `/modeling/attribute-groups/{code}`) + 2 modali (`<AddAttributeFromLibraryDialog>` open + `<CreateAttributeInGroupDialog>` open) z baseline'em wygenerowanym z prototypu. Tolerancja <2% pixel mismatch.
3. **Manual review** — operator przejdzie przez listę elementów z sekcji 3.4a–3.4e (niżej) i odznaczy każdy zgodny z mockupem.

## 3. Zakres frontend (FE)

### 3.1 Routing

> **WAŻNE**: Lista, create, detail (edit-in-place) są **osobnymi pełnoekranowymi widokami trasowanymi**, renderowanymi w shellu `/modeling/*` jako `<Outlet>` zakładki Attribute Groups. **Brak pełnoekranowych Sheetów / pełnoekranowych Dialogów dla tych trzech widoków**. Popupy w VIEW-03 to wyłącznie:
> - `<AddAttributeFromLibraryDialog>` — z detail edit-in-place, klik „Z biblioteki" w Card „Attributes in this group" lub w pustym stanie listy atrybutów grupy.
> - `<CreateAttributeInGroupDialog>` — z detail edit-in-place, klik „Stwórz nowy" w Card „Attributes in this group".
> - `<LocaleAddDialog>` (z VIEW-01) — z `<LocaleTabsField>` przy klik „+ Dodaj język".
> - `<ConfirmDialog>` — przy próbie nawigacji z dirty stanem.

| Trasa | Status | Komponent | Auth |
|---|---|---|---|
| `/modeling/attribute-groups` | ✅ istnieje, pełna pixel-perfect przebudowa | `<AttributeGroupsListPage>` | `IS_AUTHENTICATED_FULLY` + `attribute_group:read` |
| `/modeling/attribute-groups/new` | ✅ istnieje, pełna przebudowa pixel-perfect (z text inputów na swatch + icon picker + sidebar) | `<AttributeGroupCreatePage>` | `IS_AUTHENTICATED_FULLY` + `attribute_group:write` |
| `/modeling/attribute-groups/:code` | ✅ istnieje, rebuild edit-in-place z 2 popupami | `<AttributeGroupShowPage>` | `IS_AUTHENTICATED_FULLY` + `attribute_group:read` (edit submit gated by `attribute_group:write`) |

**UWAGA**: aktualnie route show jest `/modeling/attribute-groups/:id` (UUID-based). Zmieniamy na `/modeling/attribute-groups/:code` żeby URL-e były czytelne (`/modeling/attribute-groups/identification` zamiast `/modeling/attribute-groups/0193abcd-...`). Refine resource `meta.identifierKey = 'code'`. Migracja routów: redirect ze starych UUID-paths na nowe code-paths (1 generation backward compat) — alternatywnie just hard-cut, zważywszy że nikt jeszcze nie używa.

**Tab navigation w shellu Modelowanie** (`apps/admin/src/features/catalog/modeling/layout.tsx`): TabBadge dla `Attribute Groups` aktualizuje się z `useList('attribute_groups', { pagination: { pageSize: 1 } })` — pokazuje aktualny `total` (po seed = 12).

#### Dlaczego osobne widoki, nie popupy

1. **Pixel-perfect zgodność z mockupami** — `NewAttributeGroupView` ma 320px sidebar (Preview + Następnie), breadcrumb back-button, 2 buttons w prawym górnym rogu. `AttributeGroupDetail` ma sticky header + 4 Cards (Identyfikacja, Attributes in this group, Visibility rules conditional, Where used). To wszystko nie mieści się w 420px Sheet.
2. **URL shareable** — operator może wkleić link do edycji konkretnej grupy.
3. **Spójność z VIEW-01 ObjectTypes i VIEW-02 Attributes** — detail / wizard tam też są osobnymi widokami, więc Attribute Groups idą tym samym wzorem.
4. **A11y** — popup/Sheet trapuje focus, blokuje scroll, dodaje warstwy ARIA. Pełnoekranowy widok nie ma tych komplikacji (tylko bottom-sticky bar — pozostaje w obrębie main, nie modal).
5. **Edit-in-place pattern** (decyzja operatora 2026-05-02) — detail to JEST edit, brak osobnej trasy `/edit`. Klik w pole = focus + enable edit; sticky bottom bar agreguje wszystkie zmiany do jednego PATCH. Analogicznie do VIEW-01 ObjectTypes show.tsx i VIEW-02 Attributes show.tsx.
6. **Popupy DOZWOLONE tylko dla mikro-akcji** — wybór z biblioteki + skrócony create — to są punkty wykonujące akcję na obiekcie macierzystym (grupie), nie samodzielne widoki ze swoim URL-em. Alignuje się z `feedback_view_scope_literal.md`.

### 3.2 Komponenty (lista płaska)

#### Komponenty istniejące do reużycia (sprawdzone w kodzie):

- `ModelingPageHeader` — `caption`, `title`, `description`, `ctaLabel`, `onCtaClick`. **Reuse jako nagłówek listy + create**.
- `TabBadge` (z `modeling/layout.tsx`) — counter w tab nav, **reuse**.
- `TypeBadge` — istnieje w `apps/admin/src/components/modeling/type-badge.tsx`. **Reuse w popup „Z biblioteki" + popup „Stwórz nowy" preview row**.
- `BuiltInLockBadge` — istnieje. **Reuse dla badge przy code grupy system + przy code atrybutu system w liście atrybutów grupy + popup „Z biblioteki"**.
- `LocaleTabsField` (z VIEW-01) — `values={{pl, en}} onChange? readOnly? primary='pl'`. **Reuse** dla pól nazwa grupy + nazwa atrybutu w popup „Stwórz nowy".
- `LocaleAddDialog` (z VIEW-01) — modal dodawania języka. **Reuse**.
- `WhereUsedList` — istnieje (lista grup/typów/kategorii). **Reuse w detail Card „Where used" — chips dla object types + categories**.
- `StatBox` (z VIEW-01) — duża cyfra + label. **Reuse w 3-kolumnowej siatce „ObjectTypes globalnie / Categories deklarują / instancji dotkniętych"**.
- `Card`, `CardContent` — shadcn, reuse.
- `Sheet`, `Dialog` — shadcn, reuse pod 2 popupami + LocaleAddDialog + ConfirmDialog.
- `Button`, `Input`, `Textarea`, `Switch`, `Checkbox`, `Tooltip` — shadcn, reuse.
- `IconPicker` — **istnieje** w `apps/admin/src/components/modeling/icon-picker.tsx` z `DEFAULT_WIZARD_ICONS`. **Reuse w create form sekcja „Wygląd"** — extend/override `options` żeby zawierał ikony z mockupu (`📦 📐 🔧 ⚙️ 🛡️ 💧 🌡️ 🏗️ 📋 🎨 🔌 📡 🪛 🧰`).
- `useCurrentWorkspace()` — hook z VIEW-01, dostarcza `enabledLocales`, `primaryLocale`. **Reuse w create form + popup „Stwórz nowy"**.
- `cn()` z `@/lib/utils`.
- `<StickyFormFooter dirty count onCancel onSave saveLabel?>` — z VIEW-02 (jeśli już wyciągnięty), w przeciwnym razie wyciągnij w nowy reusable w `components/modeling/sticky-form-footer.tsx`. **Reuse w detail edit-in-place**.
- `useAttributeGroupUsage(code)` — hook z `apps/admin/src/features/catalog/attribute-groups/list.tsx` (już istnieje, fetchuje `GET /api/attribute_groups/{id}/usage`). **Reuse + rozszerzenie o `instancesAffected` (BE musi zwrócić)**.
- `jsonFetch()` z `@/lib/http` — typed fetch wrapper z RFC 7807 error parsing.
- `useList`, `useShow`, `useCreate`, `useUpdate`, `useDelete`, `useInvalidate` z Refine.

#### Komponenty NOWE do napisania (apps/admin/src/components/modeling/):

- `<ColorSwatchPicker selected onChange swatches? size? showClear? />` — picker 8 swatches z `groups-categories.jsx:488` (`#71717a #3b82f6 #8b5cf6 #10b981 #f59e0b #ef4444 #06b6d4 #ec4899`). Każdy swatch 9×9 px (`h-9 w-9`), `rounded-xl`, ring-2 ring-zinc-900 ring-offset-2 gdy selected. **Współdzielony z VIEW-02 Attributes Values (`attribute-values.jsx` swatches) — należy uspójnić z 10 swatch'em VIEW-02 (zarówno VIEW-02 jak i VIEW-03 może użyć pełnego zbioru 10, lub propsem `swatches` przeciąć do 8).** **TODO**: Ustalić pojedynczy zestaw swatches w `lib/swatches.ts` (8 dla group, 10 dla attribute values — różne policy), i `<ColorSwatchPicker>` ma `swatches?` prop.
- `<GroupIconPicker selected onChange icons? />` — dedykowany IconPicker dla grup z 14 ikonami mockup'u. Najprościej: **reuse `<IconPicker>` z VIEW-01** + override `options` propsem `icons={GROUP_ICONS}`. Jeśli `<IconPicker>` ma sztywny zestaw, dorzucić prop `options?` (extend istniejący komponent — minimal change).
- `<SettingToggleRow label desc checked onChange disabled? />` — kafelka `label + desc + Switch`. **Sprawdź czy `setting-toggle-row.tsx` w `components/modeling/` ma już taki signature** — jeśli tak, reuse. **Used in create form sekcja „Zachowanie"**.
- `<GroupRowItem group onClick />` — wiersz w liście grup, mapping z `GroupRow` (`groups-categories.jsx:54–80`). Grid 6-kolumn z color icon, name + lock + visible_when badge + description, code mono, `N atrybutów`, `N typy · N kat.`, chevron.
- `<AttributeGroupAttributesTable groupCode attrs onReorder onAddFromLibrary onCreateNew onToggleRequired onUpdateRule onRemove />` — Card body w detail edit-in-place. Drag-reorder z dnd-kit (Sortable). Każdy wiersz: drag handle, code+name+lock badge, type badge, visible_when chip (lub „brak reguły widoczności"), required checkbox, trash icon. **Composed z `<AttributeGroupAttributeRow>`**.
- `<AttributeGroupAttributeRow attribute rule isRequired onToggleRequired onUpdateRule onRemove dragHandle />` — pojedynczy wiersz atrybutu w grupie. Mapping z `groups-categories.jsx:151–179`.
- `<VisibilityRulesCard rules onEditRule />` — Card „Visibility rules" widoczna gdy `rules.length > 0`. Mapping z `groups-categories.jsx:188–215`. Lista rules + 2 testy pass/fail.
- `<AttributeGroupCreateSidebar code name color icon />` — prawy sidebar w create form: Card „Podgląd" (live preview color icon + name + code) + Card „Następnie" (3 next steps).
- `<AttributeGroupColorBadge color icon size? />` — small box `h-9 w-9 rounded-xl grid place-items-center` z `style={{ background: color + "18", color }}>{icon}`. **Reuse w liście wierszy + detail header + popup „Stwórz nowy" preview**.
- `<AddAttributeFromLibraryDialog open onClose groupCode existingCodes onAttach />` — popup multi-select z search + type filter + scrollable list. Mapping z `groups-categories.jsx:705–807`.
- `<CreateAttributeInGroupDialog open onClose groupCode onCreated />` — popup skróconego attribute create. Mapping z `groups-categories.jsx:813–953`. Body z 4 sekcjami (Identyfikacja, Konfiguracja typu warunkowo, Walidacja i flagi, Podgląd w grupie).
- `<AttributeTypeSelect value onChange types? />` — prosty `<select>` do wyboru typu w popup „Stwórz nowy" (skrócona alternatywa do `<AttributeTypeGrid>` z VIEW-02 — bo modal ma mniej miejsca). 10 typów z VIEW-02.

#### Komponenty do przebudowy:

- `AttributeGroupsListPage` (`features/catalog/attribute-groups/list.tsx`):
  - **Pełna pixel-perfect przebudowa wg `AttributeGroupsView`**.
  - Header: `<ModelingPageHeader>` z caption `12 grup atrybutów` + violet badge `⭐ FIRST-CLASS ENTITY`, title `Attribute Groups`, description (Pimcore/Akeneo positioning), CTA `+ Nowa grupa`.
  - Single Card z 2 sekcjami zamiast Tab/Filter: `System (auto-attached)` (lock badge prefix) + `Business groups`. Każda sekcja ma divider z uppercase tracking-wider label.
  - Search input sticky top w karcie.
  - Wiersze: `<GroupRowItem>` z grid 6-kolumn, hover bg-zinc-50/70, klik → `/modeling/attribute-groups/{code}`.
  - Zachować obecny `useAttributeGroupsUsage()` hook (parallel fetch usage per grupa).
  - Zachować obecny voter-based gating dla delete (BE), w UI **brak akcji delete w wierszu** — delete dostępny tylko w detail.
- `AttributeGroupShowPage` (`features/catalog/attribute-groups/show.tsx`):
  - **Pełna przebudowa pixel-perfect z edit-in-place pattern + 2 popupami**.
  - Sticky header: X close (back), color icon 12×12, name + lock badge, URL `<font-mono>/modeling/attribute-groups/{code}</>`, prawy stack: `Preview formularza` + `Edytuj` (NO — z VIEW-02 lessons usuwamy „Edytuj", zamiast tego sticky bottom bar; ale w VIEW-03 mockup pokazuje „Edytuj" jako wciąż istniejący, więc **zachowujemy** zgodnie z mockupem — operator zatwierdza pixel-perfect).
    - **Pytanie do operatora podczas implementacji**: czy VIEW-03 ma trzymać przycisk „Edytuj" z mockupu (toggle: read-only ↔ edit) czy iść jednolicie z VIEW-02 (auto edit-in-place + sticky bottom bar)? **Default**: idziemy zgodnie z mockupem (Edytuj toggle), żeby nie odchodzić od pixel-perfect; jeśli operator powie inaczej w trakcie review, zmieniamy na edit-in-place.
  - 4 Cards w body (`p-7 space-y-6`):
    1. **Card „Identyfikacja"**: title (uppercase tracking-wider) → Nazwa (LocaleTabsField, primary PL) → grid 2 kolumn (Code mono lock + Color colored swatch) → Description textarea/display.
    2. **Card „Attributes in this group"**: title + drag-to-reorder hint, prawa strona buttony `+ Z biblioteki` (zinc) + `+ Stwórz nowy` (violet bg, akcent). Lista wierszy z drag-handle + code+name + type badge + visible_when chip + required checkbox + trash. Empty hint: dashed border button „+ Add attribute from library" gdy `!system && attrs.length === 0`.
    3. **Card „Visibility rules"** (warunkowo `rules.length > 0`): title + visible_when violet badge + rules list w violet-50/40 frame + 2 test cards (test pass / test fail).
    4. **Card „Where used"**: title → grid 3 cols z `<StatBox>` (typesUsed / categoriesUsed / objectsAffected) → optional declared-by-categories frame (gdy grupa ma jakąś kategorię ze swoim deklarowaniem — tylko dla `wymagania-medyczne` w mockupie, ale logika ogólna).
  - Sticky bottom bar (gdy dirty): `Anuluj` + `Zapisz zmiany` z `<ChangesSummary count>`. Reuse `<StickyFormFooter>`.
  - Audit log indicator (top-right z VIEW-01) → reuse, kropka + tekst „Audit log: aktywny · ostatnia zmiana N min temu".
- `AttributeGroupCreatePage` (`features/catalog/attribute-groups/create.tsx`):
  - **Pełna przebudowa pixel-perfect wg `NewAttributeGroupView`**.
  - Back button góra, header (h-14 color icon + caption „Nowa Attribute Group" + title live `displayName` + description Pimcore/Akeneo + 2 buttons Anuluj/Utwórz grupę).
  - Grid `1fr+320px`:
    - Left Card `p-6 space-y-6`:
      - section „Identyfikacja": Code input (h-10 rounded-xl mono) + helper „Niezmienialny po utworzeniu. Używany w API i mapowaniach." + LocaleTabsField label + Description textarea.
      - section „Wygląd" (grid 2 cols): Kolor → `<ColorSwatchPicker swatches={GROUP_SWATCHES}>` + Ikona → `<GroupIconPicker icons={GROUP_ICONS}>`.
      - section „Zachowanie": 3 `<SettingToggleRow>` (Wymagana sekcja default off, Współdzielona default on, Conditional visibility default off).
    - Right aside: 2 sidebar Cards (Podgląd live + Następnie 3 lista).
- `AttributeGroupAttachPanel` / istniejący kod junction:
  - **W tym tickecie**: BE flow „Z biblioteki" → POST nowy endpoint `/api/attribute_groups/{code}/attributes/bulk-attach` (lub iteruj per atrybut). „Stwórz nowy" → `POST /api/attributes` + auto attach via processor.

### 3.3 State management

#### Refine resources (apps/admin/src/App.tsx)

- `attribute_groups` — istnieje. Operations: `list`, `show`, `create`, `edit` (NEW: PATCH bez osobnej trasy, używa show), `delete` (jest, gated voterem).
  - `list`: `/modeling/attribute-groups`
  - `show`: `/modeling/attribute-groups/:code` (zmiana z `:id` na `:code`, sekcja 3.1)
  - `create`: `/modeling/attribute-groups/new`
  - `edit`: action wiązany z `show` (brak osobnej trasy) — Refine `useUpdate` na `attribute_groups`
  - `delete`: invoked z DangerZone w show (gdy `!system`), confirm dialog → DELETE
- `attribute_group_attributes` — **NOWY resource** (junction z `position`, `isRequiredInGroup`, `visibleWhen`).
  - `list`: brak osobnej trasy (renderowane wewnątrz `<AttributeGroupShowPage>`).
  - mutacje: PATCH przez `useUpdate('attribute_group_attributes', { id: junctionId, ... })`, DELETE przez `useDelete`, POST batch przez custom mutation.

#### React Query keys

```ts
// existing
['attribute_groups'] // list
['attribute_groups', code] // show
['attribute_groups', code, 'usage'] // statboxes
// new / extended
['attribute_groups', code, 'attributes'] // members collection (junction-enriched)
['attribute_groups', code, 'attributes', attributeCode, 'rule'] // visible_when (rzadko, tylko jeśli osobny query)
```

#### Mutations + invalidations

| Mutacja | Endpoint | Invalidate keys |
|---|---|---|
| `useCreateAttributeGroup()` | `POST /api/attribute_groups` | `['attribute_groups']` + redirect do `['attribute_groups', newCode]` |
| `useUpdateAttributeGroup(code)` | `PATCH /api/attribute_groups/{id}` | `['attribute_groups', code]`, `['attribute_groups']`, `['attribute_groups', code, 'usage']` |
| `useDeleteAttributeGroup(code)` | `DELETE /api/attribute_groups/{id}` | `['attribute_groups']` + redirect to list |
| `useBulkAttachAttributesToGroup(code)` | `POST /api/attribute_groups/{id}/attributes/bulk-attach` z `{attributeCodes: string[]}` | `['attribute_groups', code, 'attributes']`, `['attribute_groups', code, 'usage']`, `['attribute_groups', code]` |
| `useDetachAttributeFromGroup(groupCode, attributeCode)` | `DELETE /api/attribute_groups/{id}/attributes/{attributeId}` | `['attribute_groups', code, 'attributes']`, `['attribute_groups', code, 'usage']` |
| `useReorderGroupAttributes(groupCode)` | `POST /api/attribute_groups/{id}/attributes/reorder` z `{order: [attributeCode1, attributeCode2, ...]}` | `['attribute_groups', code, 'attributes']` |
| `useUpdateGroupAttributeRule(groupCode, attrCode)` | `PATCH /api/attribute_groups/{id}/attributes/{attributeId}` z `{visibleWhen?: {...}, isRequiredInGroup?: bool}` | `['attribute_groups', code, 'attributes']` |
| `useCreateAttributeAndAttachToGroup(groupCode)` | `POST /api/attributes` z `{...attributeFields, attachToGroup: groupCode}` (lub 2 calls: POST attribute + POST bulk-attach) | `['attribute_groups', code, 'attributes']`, `['attributes']` |

**Optimistic updates**: dla reorder (UX <50ms perceived). Rollback on error przez `onError` callback z toast errror.

**Cache stale time**: `['attribute_groups']` 60s, `['attribute_groups', code]` 30s, `['attribute_groups', code, 'attributes']` 30s, `['attribute_groups', code, 'usage']` 60s.

#### Local state per route

- **list**: search query — `useState`, filtrowanie client-side po fetched 12.
- **show (edit-in-place)**: React Hook Form z `defaultValues` z `useShow()`. `mode: 'onBlur'`. `formState.isDirty` używane przez sticky footer + nav guard. Lokalny state dla:
  - `pickerOpen: boolean` (popup „Z biblioteki")
  - `createOpen: boolean` (popup „Stwórz nowy")
  - drag state (zarządzany przez dnd-kit).
- **create**: React Hook Form z domyślnymi `{ code: '', label: { pl: '' }, description: { pl: '' }, color: '#71717a', icon: '📦', isRequiredSection: false, isShared: true, hasConditionalVisibility: false }`. Submit → `useCreateAttributeGroup()` → redirect `/modeling/attribute-groups/{code}`.
- **popup „Z biblioteki"**: lokalny `q: string`, `picked: Set<string>`, `typeFilter: string`. Reset w `useEffect` na `open === true`.
- **popup „Stwórz nowy"**: lokalny RHF z `code, names: {pl, en}, type, unit, required, unique, localizable`. Reset w `useEffect`.

### 3.4 Struktura sekcji widoku (kolejność renderu)

#### 3.4a Lista — `/modeling/attribute-groups` (`AttributeGroupsListPage`)

Mapping z `AttributeGroupsView` (`groups-categories.jsx:3–52`):

1. **Workspace shell** (sidebar lewy + topbar) — obecny shell, `useAuth` guard.
2. **Tab nav Modelowanie** (Object Types | Attributes | Attribute Groups | Categories) — `<TabBadge>` per tab, aktywna `Attribute Groups`.
3. **`<ModelingPageHeader>`** (mapping `groups-categories.jsx:13–29`):
   - caption (flex items-center gap-2): `{count} grup atrybutów` (z `useList('attribute_groups').total`) + violet badge `<span className="text-[10.5px] uppercase tracking-wider font-semibold px-1.5 py-0.5 rounded bg-violet-100 text-violet-700">⭐ first-class entity</span>`.
   - title: `Attribute Groups` (font-display 28px font-semibold tracking-tight).
   - description: `Grupa atrybutów jako wymienialna jednostka — przypinasz ją do ObjectType (globalnie) lub Category (z dziedziczeniem). Pimcore nie ma tej abstrakcji, Akeneo traktuje ją tylko jako sortowanie. U nas — własny URL, audit, wersjonowanie.` (text-13px text-zinc-500).
   - cta: `+ Nowa grupa` (px-4 h-9 rounded-xl bg-zinc-900 text-white text-13px font-medium hover:bg-zinc-800 flex items-center gap-2) → router push `/modeling/attribute-groups/new`.
4. **`<Card>` z całą tabelą** (mapping `groups-categories.jsx:32–49`):
   - Sticky top: search input z `<I.search>` icon + placeholder „Szukaj grup…" (px-4 py-3 flex items-center gap-3, border-b zinc-100, input bg-transparent outline-none text-13.5px).
   - Section divider 1: `<window.LockBadge />` + `<span className="text-[11px] uppercase tracking-wider text-zinc-500 font-medium">System (auto-attached)</span>` (px-4 py-2 flex items-center gap-2 border-b zinc-100).
   - Body system rows (`divide-y divide-zinc-50`): mapped `<GroupRowItem>` per system grupa.
   - Section divider 2: `<span className="text-[11px] uppercase tracking-wider text-zinc-500 font-medium">Business groups</span>` (px-4 py-2 mt-1 flex items-center gap-2 border-y zinc-100).
   - Body business rows: mapped `<GroupRowItem>` per business grupa.
5. **`<GroupRowItem>` body** (mapping `GroupRow` `groups-categories.jsx:54–80`):
   - Wrapper: `<button>` (group w-full grid grid-cols-[44px_1.6fr_1fr_120px_120px_28px] gap-3 items-center px-4 py-3.5 hover:bg-zinc-50/70 text-left).
   - Col 1 (icon): `<div className="h-9 w-9 rounded-xl grid place-items-center text-[16px]" style={{background: g.color + "18", color: g.color}}>{g.icon}</div>`.
   - Col 2 (name + desc): flex flex-col min-w-0:
     - row1 flex items-center gap-2: `<span className="text-[13.5px] font-semibold tracking-tight truncate">{g.name}</span>` + `<BuiltInLockBadge>` (gdy system) + visible_when violet chip (gdy `g.rules.length > 0`).
     - row2: `<div className="text-[11.5px] text-zinc-500 truncate">{g.description}</div>`.
   - Col 3 (code): text-11.5px text-zinc-500 → `<span className="font-mono">{g.code}</span>`.
   - Col 4 (N atrybutów): text-12px num → `<span className="font-medium">{g.attrs.length}</span> <span className="text-zinc-500">atrybutów</span>`.
   - Col 5 (N typy · N kat.): text-12px num → `<span className="text-zinc-700"><span className="font-medium">{g.typesUsed}</span> typy </span><span className="text-zinc-300">·</span><span className="text-zinc-700"> <span className="font-medium">{g.categoriesUsed}</span> kat.</span>`.
   - Col 6 (chevron): `<I.chevRight>` text-zinc-300 group-hover:text-zinc-700.
   - onClick → router push `/modeling/attribute-groups/{g.code}`.
6. **Empty state**: gdy filtered.length === 0 → text „Brak grup spełniających kryteria" w środku karty (paddings i font matching VIEW-02 list empty).

#### 3.4b Detail edit-in-place — `/modeling/attribute-groups/:code` (`AttributeGroupShowPage`)

Mapping z `AttributeGroupDetail` (`groups-categories.jsx:82–253`):

1. **Workspace shell** + tab nav.
2. **Sticky header** (mapping `:92–113`):
   - wrapper: `sticky top-0 z-10 bg-zinc-50/95 backdrop-blur border-b border-zinc-200 px-7 py-5 flex items-start gap-4`.
   - Close button: `<button onClick={onClose} className="h-9 w-9 rounded-xl hover:bg-zinc-200/60 grid place-items-center text-zinc-600 shrink-0"><I.close></button>` → router back.
   - Color icon: `<AttributeGroupColorBadge size={12} color icon />` 12×12 rounded-2xl text-20px shrink-0.
   - Center stack (flex-1 min-w-0):
     - flex items-center gap-2: `<div className="font-display text-[22px] font-semibold tracking-tight">{name}</div>` + `<BuiltInLockBadge>` (gdy system).
     - text-12px text-zinc-500 mt-0.5: `<span className="font-mono">/modeling/attribute-groups/{code}</span>`.
   - Right buttons (flex items-center gap-2 shrink-0):
     - `Preview formularza` (text-12.5px font-medium px-3 h-9 rounded-xl hover:bg-zinc-200/60 flex items-center gap-1.5 text-zinc-700, `<I.eye>`) — opens `<FormPreviewDialog>` (lub link do read-only mockup view; **MVP**: disabled tooltip „Funkcja w VIEW-03b" jeśli BE nie zwróci preview).
     - `Edytuj` (px-3 h-9 rounded-xl bg-zinc-900 text-white text-13px font-medium hover:bg-zinc-800 flex items-center gap-1.5, `<I.pencil>`) — toggle edit mode (lub no-op gdy edit-in-place auto-enabled, sekcja 3.1 dyskusja).
3. **Body**: `p-7 space-y-6`.
4. **Card „Identyfikacja"** (mapping `:116–132`, `Card p-6`):
   - title: `IDENTYFIKACJA` (uppercase tracking-wider text-zinc-500 11px font-medium mb-4).
   - mb-5: `Nazwa` label (text-11.5px text-zinc-500 font-medium mb-2) + `<LocaleTabsField values={{pl: name, en: nameEn || ''}} placeholder="Nazwa grupy" primary="pl">`.
   - grid `grid-cols-2 gap-x-8 gap-y-4`:
     - `<FieldDisplay label="Code" value={code} mono lock={system} />`.
     - `<FieldDisplay label="Color" value={<inline color swatch + hex mono>} editable={!system} />` — wyrenderuj `<span className="inline-flex items-center gap-2"><span className="h-4 w-4 rounded" style={{background:color}}/><span className="font-mono text-[12px]">{color}</span></span>` z opcjonalnym `<ColorSwatchPicker>` w trybie edit.
   - mt-4: `Description` label + textarea/display `<div className="px-3 py-2.5 rounded-xl bg-zinc-50 border border-zinc-100 text-[13px] text-zinc-700">{description}</div>` (read-only display; w edit mode → textarea).
5. **Card „Attributes in this group"** (mapping `:134–186`, `Card p-6`):
   - flex items-center justify-between mb-4:
     - left flex items-center gap-2: `<span className="text-[11px] uppercase tracking-wider text-zinc-500 font-medium">Attributes in this group</span>` + `<span className="text-[11px] text-zinc-400">— drag to reorder</span>`.
     - right buttons (flex items-center gap-2):
       - `+ Z biblioteki` (text-12px font-medium text-zinc-700 px-2.5 h-8 rounded-lg hover:bg-zinc-100 flex items-center gap-1.5, `<I.plus>`) → setPickerOpen(true).
       - `+ Stwórz nowy` (text-12px font-medium text-violet-700 bg-violet-50 px-2.5 h-8 rounded-lg hover:bg-violet-100 flex items-center gap-1.5, `<I.plus>`) → setCreateOpen(true).
   - Lista `<AttributeGroupAttributesTable>` `space-y-1.5`:
     - Per row `<AttributeGroupAttributeRow>`: grid `grid-cols-[24px_1.5fr_120px_180px_100px_60px] gap-3 items-center px-3 py-2.5 rounded-xl border border-zinc-100 hover:border-zinc-200 hover:bg-zinc-50/60 bg-white`.
     - Drag handle: `<I.drag>` text-zinc-300 cursor-grab.
     - Code+name min-w-0: row1 flex gap-2 (`<span className="text-[13px] font-mono font-medium truncate">{code}</span>` + `<BuiltInLockBadge>` gdy `attribute.system`); row2 `<div className="text-[11.5px] text-zinc-500 truncate">{name}{unit ? ` (${unit})` : ''}</div>`.
     - Type badge: `<TypeBadge type={attribute.type}>`.
     - Visible_when chip / placeholder: gdy `rule` istnieje → `<span className="inline-flex items-center gap-1.5 text-[11px] font-mono px-2 py-1 rounded-lg bg-violet-50 text-violet-700"><span className="text-violet-500"><I.eye></span>when {rule.when}</span>`; w przeciwnym razie `<span className="text-[11px] text-zinc-300">brak reguły widoczności</span>`.
     - Required checkbox: `<label className="flex items-center gap-1.5 text-[11.5px] text-zinc-600"><input type="checkbox" checked={isRequiredInGroup} onChange={...} className="rounded"/>required</label>`.
     - Trash button: `<button className="text-zinc-300 hover:text-rose-600 justify-self-end"><I.trash></button>` → onRemove.
   - Empty state CTA (gdy `!system && attrs.length === 0`): `<button className="w-full py-2.5 rounded-xl border border-dashed border-zinc-200 text-zinc-500 hover:text-violet-700 hover:border-violet-300 hover:bg-violet-50/40 text-[12.5px] font-medium flex items-center justify-center gap-2 transition"><I.plus> Add attribute from library</button>` → setPickerOpen(true).
6. **Card „Visibility rules"** (warunkowo `rules.length > 0`, mapping `:188–215`, `Card p-6`):
   - title: `VISIBILITY RULES` + violet badge `visible_when` (uppercase 10.5px).
   - rounded-2xl border-violet-200 bg-violet-50/40 p-4: lista rules per row (flex items-center gap-3 py-1):
     - `<span className="font-mono text-[12.5px] font-medium">{rule.attr}</span>`.
     - `<span className="text-[11.5px] text-zinc-500">visible_when</span>`.
     - `<span className="font-mono text-[12.5px] text-violet-700 bg-white px-2 py-0.5 rounded border border-violet-200">{rule.when}</span>`.
     - `Edit rule` button (ml-auto text-11.5px text-zinc-500 hover:text-zinc-900) → opens `<EditVisibilityRuleDialog>` (out-of-scope dla VIEW-03; **MVP**: disabled link tooltip „Funkcja w VIEW-03c").
   - mt-3 grid grid-cols-2 gap-3: 2 test cards (test pass = bg-emerald-50/40 border emerald-200 / test fail = bg-zinc-50 border zinc-200) — copy z mockupu.
7. **Card „Where used"** (mapping `:217–238`, `Card p-6`):
   - title: `WHERE USED`.
   - grid 3 cols mb-5: `<StatBox value={typesUsed} label="ObjectTypes (globalnie)" />` + `<StatBox value={categoriesUsed} label="Categories (deklarują)" />` + `<StatBox value={objectsAffected.toLocaleString('pl-PL')} label="instancji dotkniętych" />`.
   - Optional declared-by-categories frame (gdy grupa ma `categoriesDeclaring.length > 0` z BE): rounded-2xl bg-zinc-50 border-zinc-100 p-4 z chipsami categories declared + inherited list (mapping `:224–237`).
8. **Sticky bottom bar** (gdy `formState.isDirty`):
   - position: `sticky bottom-0`.
   - bg-white border-t border-zinc-200 px-6 py-4 flex items-center justify-between.
   - left: `<ChangesSummary count={dirtyFieldsCount} />` „N pól zmienionych".
   - right: stack of buttons (gap-2): `Anuluj` + `Zapisz zmiany`.
9. **Audit log indicator** (top-right z VIEW-01) → reuse, kropka + tekst „Audit log: aktywny · ostatnia zmiana N min temu".

#### 3.4c Create form — `/modeling/attribute-groups/new` (`AttributeGroupCreatePage`)

Mapping z `NewAttributeGroupView` (`groups-categories.jsx:482–603`):

1. **Workspace shell** + tab nav.
2. **Back button** (mapping `:495–498`): `<button className="mb-4 inline-flex items-center gap-1.5 text-[12.5px] text-zinc-500 hover:text-zinc-900 font-medium"><I.arrowLeft><span>Wstecz do Attribute Groups</span></button>` → router push `/modeling/attribute-groups`.
3. **Header row** (mapping `:500–518`, flex items-start justify-between gap-6 mb-6):
   - Left (flex items-start gap-4 flex-1):
     - color icon 14×14 rounded-2xl text-24px live `style={{background: color + "18", color}}>{icon}` (z react-hook-form watch).
     - stack flex-1:
       - caption: `<div className="text-[13px] text-zinc-500 font-medium">Nowa Attribute Group</div>`.
       - title: `<div className="font-display text-[28px] font-semibold tracking-tight">{displayName || 'Nazwa grupy'}</div>` (live z `name.pl || name.en || 'Nazwa grupy'`).
       - description: `<div className="text-[13px] text-zinc-500 mt-1 max-w-2xl">Grupa to wielokrotnego użytku zbiór atrybutów (np. „Wymiary", „Bezpieczeństwo"), który można dołączać do dowolnego ObjectType. Po utworzeniu zacznij dodawać atrybuty z biblioteki.</div>`.
   - Right buttons (flex items-center gap-2 shrink-0):
     - `Anuluj` (px-3 h-9 rounded-xl hover:bg-zinc-100 text-13px font-medium text-zinc-600) → router push `/modeling/attribute-groups`.
     - `<I.check> Utwórz grupę` (px-4 h-9 rounded-xl bg-zinc-900 text-white text-13px font-medium hover:bg-zinc-800 flex items-center gap-1.5) → submit RHF.
4. **Main grid** `grid-cols-[1fr_320px] gap-6`:
   - **Left Card** `p-6 space-y-6` (mapping `:521–577`):
     - section „Identyfikacja" (uppercase 11px tracking-wider mb-4):
       - input `Code` (h-10 px-3 rounded-xl bg-white border zinc-200 text-13px font-mono focus-ring outline-none, regex `^[a-z][a-z0-9-]{1,63}$`) + helper `<div className="text-[11px] text-zinc-400 mt-1">Niezmienialny po utworzeniu. Używany w API i mapowaniach.</div>`.
       - LocaleTabsField label `Nazwa` (primary `pl`, placeholder „np. Wymiary").
       - textarea `Opis (opcjonalny)` rows=2 px-3 py-2 rounded-xl bg-white border zinc-200 text-13px focus-ring outline-none resize-none.
     - section „Wygląd" (grid grid-cols-2 gap-6):
       - left col: `Kolor` label + `<ColorSwatchPicker swatches={GROUP_SWATCHES} selected={color} onChange={setColor} size="9">` — 8 swatch h-9 w-9 rounded-xl, ring-2 ring-offset-2 ring-zinc-900 gdy selected.
       - right col: `Ikona` label + `<GroupIconPicker icons={GROUP_ICONS} selected={icon} onChange={setIcon}>` — 14 ikon h-9 w-9 rounded-xl text-18px grid place-items-center, bg-zinc-900 white gdy selected, w przeciwnym razie bg-white border zinc-200 hover:bg-zinc-50.
     - section „Zachowanie": 3 `<SettingToggleRow>`:
       - `Wymagana sekcja` desc „Grupa zawsze widoczna w formularzu" default false.
       - `Współdzielona` desc „Może być dołączona do wielu ObjectType" default true.
       - `Conditional visibility` desc „Pokaż grupę warunkowo (visible_when)" default false.
   - **Right aside** `space-y-3` (mapping `:579–599`):
     - Card `p-5` „Podgląd":
       - title `PODGLĄD` (uppercase 11px tracking-wider mb-3).
       - flex items-center gap-2.5: `<AttributeGroupColorBadge size={10} color icon>` 10×10 + stack min-w-0 (`<div className="text-[13.5px] font-semibold tracking-tight">{displayName || 'Nazwa grupy'}</div>` + `<div className="text-[11px] text-zinc-500 font-mono">{code || 'code…'}</div>`).
     - Card `p-5` „Następnie":
       - title `NASTĘPNIE` (uppercase 11px tracking-wider mb-3).
       - ul space-y-1.5 text-12px text-zinc-600:
         - `1. Utwórz grupę`
         - `2. Dodaj atrybuty z biblioteki`
         - `3. Dołącz grupę do ObjectType`

#### 3.4d Popup „Z biblioteki" — `<AddAttributeFromLibraryDialog>`

Mapping z `AddAttributeFromLibraryModal` (`groups-categories.jsx:705–807`):

1. **Modal wrapper**: shadcn `<Dialog>` z `<DialogContent className="max-w-[780px] p-0">` (override default padding, custom header/body/footer).
2. **Header** (mapping `:733–743`, px-7 pt-6 pb-4 flex items-start gap-3 border-b zinc-100):
   - layers icon: `<div className="h-10 w-10 rounded-2xl bg-zinc-900 grid place-items-center text-white shrink-0"><I.layers></div>`.
   - center min-w-0 flex-1:
     - title: `<div className="font-display text-[18px] font-semibold tracking-tight">Dodaj atrybuty z biblioteki</div>`.
     - desc: `<div className="text-[12.5px] text-zinc-500 mt-0.5">Wybierz istniejące atrybuty do grupy <span className="font-medium text-zinc-700">„{groupName}"</span>. {existingCodes.length > 0 && <span> Atrybuty już w grupie są wyłączone.</span>}</div>`.
   - close button shrink-0: `<I.close>` h-9 w-9 rounded-xl hover:bg-zinc-100 text-zinc-500.
3. **Search + filter** (mapping `:745–755`, px-7 pt-4 pb-3 flex items-center gap-2):
   - search box flex-1: `<div className="flex-1 flex items-center gap-2 px-3 h-10 rounded-xl bg-zinc-50 border border-zinc-200"><I.search><input placeholder="Szukaj atrybutów po code lub nazwie…" /></div>`.
   - type select: `<select className="h-10 px-3 rounded-xl bg-white border border-zinc-200 text-13px font-medium">` z opcjami `Wszystkie typy` + per-type (`text`, `richtext`, `number`, `boolean`, `select`, `multi-select`, `money`, `datetime`, `reference:user`, `uuid`).
4. **Scrollable list** (mapping `:757–789`, px-7 pb-2 max-h-420px overflow-y-auto scrollbar-thin):
   - space-y-1: per atrybut `<label>` clickable:
     - grid-cols-[24px_1fr_120px_80px] gap-3 items-center px-3 py-2.5 rounded-xl border cursor-pointer transition.
     - state classes:
       - `isExisting` → `bg-zinc-50 border-zinc-100 opacity-50 cursor-not-allowed`.
       - `isPicked` → `bg-zinc-900 border-zinc-900 text-white`.
       - default → `bg-white border-zinc-100 hover:border-zinc-300 hover:bg-zinc-50`.
     - col 1: checkbox (rounded, controlled, disabled gdy isExisting).
     - col 2 min-w-0: row1 flex gap-2 (`<span className="text-[13px] font-mono font-medium truncate">{a.code}</span>` + `<BuiltInLockBadge>` gdy system + zinc badge `w grupie` gdy isExisting); row2 `<div className="text-[11.5px] truncate">{a.name}{a.unit ? ` (${a.unit})` : ''}</div>` (white/70 text gdy picked).
     - col 3: `<TypeBadge type={a.type}>`.
     - col 4: text-11px num text-right `{a.typesUsed} types` (white/70 gdy picked).
   - empty state: `<div className="px-4 py-12 text-center text-[13px] text-zinc-400">Brak atrybutów dla podanych kryteriów</div>`.
5. **Footer** (mapping `:791–803`, px-7 py-4 border-t zinc-100 flex items-center justify-between bg-zinc-50/60):
   - left: `<div className="text-[12.5px] text-zinc-600">Wybrano <span className="font-semibold text-zinc-900 num">{picked.size}</span> {picked.size === 1 ? 'atrybut' : 'atrybutów'}</div>`.
   - right buttons:
     - `Anuluj` (px-3 h-9 rounded-xl text-13px font-medium text-zinc-700 hover:bg-zinc-100) → onClose.
     - `<I.check> Dołącz {N}` (px-4 h-9 rounded-xl bg-zinc-900 text-white text-13px font-medium flex items-center gap-1.5, disabled gdy picked.size === 0) → onAttach + close.
6. **Submit flow**: onAttach → `useBulkAttachAttributesToGroup(groupCode).mutate({attributeCodes: [...picked]})` → invalidate `['attribute_groups', groupCode, 'attributes']` + `['attribute_groups', groupCode, 'usage']` → toast „Dołączono N atrybutów" → close.
7. **Optimistic UI** (opcjonalnie): natychmiast dorzucamy attached attributes do lokalnego renderu w `<AttributeGroupAttributesTable>` przed odpowiedzią BE; rollback on 422.

#### 3.4e Popup „Stwórz nowy" — `<CreateAttributeInGroupDialog>`

Mapping z `CreateAttributeInGroupModal` (`groups-categories.jsx:813–953`):

1. **Modal wrapper**: shadcn `<Dialog>` z `<DialogContent className="max-w-[820px] p-0 max-h-[88vh] flex flex-col">`.
2. **Header** (mapping `:835–844`, px-7 pt-6 pb-4 flex items-start gap-3 border-b zinc-100):
   - violet zap icon: `<div className="h-10 w-10 rounded-2xl bg-violet-100 grid place-items-center text-violet-700 shrink-0"><I.zap></div>`.
   - center: title `Nowy atrybut w grupie „{groupName}"` (font-display 18px font-semibold tracking-tight) + desc `Atrybut zostanie utworzony w globalnej bibliotece i automatycznie dołączony do tej grupy.` (text-12.5px text-zinc-500 mt-0.5).
   - close button shrink-0.
3. **Body** (mapping `:846–933`, px-7 py-5 overflow-y-auto scrollbar-thin space-y-6):
   - **Sekcja Identyfikacja**:
     - title `IDENTYFIKACJA` (uppercase 11px tracking-wider mb-3).
     - grid grid-cols-2 gap-x-6 gap-y-4:
       - col1: `Code` label (text-11.5px text-zinc-500 font-medium mb-1.5) + input (h-10 px-3 rounded-xl bg-white border zinc-200 text-13px font-mono focus-ring outline-none, placeholder „np. warranty_months") + helper `<div className="text-[11px] text-zinc-400 mt-1">snake_case · niezmienialny po utworzeniu</div>`.
       - col2: `Typ danych` label + `<select>` z 10 typami (text richtext number boolean select multi-select money datetime reference:user uuid).
     - mt-4: `Nazwa wyświetlana` label + `<LocaleTabsField placeholder="np. Gwarancja (msc)" primary="pl">`.
   - **Sekcja Konfiguracja typu** (warunkowo `showUnit || showOptions`, pt-5 border-t zinc-100):
     - title `KONFIGURACJA TYPU`.
     - gdy showUnit (`type === 'number' || type === 'money'`): grid grid-cols-2 gap-x-6 gap-y-4 → `Jednostka` input (placeholder „PLN" gdy money, „kg / mm / V" w przeciwnym razie).
     - gdy showOptions (`type === 'select' || type === 'multi-select'`): violet info banner `<div className="rounded-xl bg-violet-50/60 border border-violet-200 px-4 py-3 flex items-start gap-2.5"><I.info><div className="text-[12px] text-violet-900">Po utworzeniu atrybutu typu <span className="font-mono">{type}</span> będziesz mógł zdefiniować wartości (z tłumaczeniami) w widoku „Zarządzaj wartościami".</div></div>`.
   - **Sekcja Walidacja i flagi** (pt-5 border-t zinc-100):
     - title `WALIDACJA I FLAGI`.
     - grid grid-cols-3 gap-3: 3 `<label>` cards:
       - `Required` desc „Pole musi być wypełnione".
       - `Unique` desc „Wartość unikalna w typie".
       - `Localizable` desc „Per locale (PL/EN/DE)".
     - Każda card: px-3 py-2.5 rounded-xl border cursor-pointer transition; gdy checked → `bg-emerald-50/50 border-emerald-200`, w przeciwnym razie `border-zinc-200 hover:bg-zinc-50`.
   - **Sekcja Podgląd w grupie** (pt-5 border-t zinc-100):
     - title `PODGLĄD W GRUPIE`.
     - rounded-2xl border zinc-200 bg-white px-4 py-3 grid grid-cols-[24px_1fr_120px_100px] gap-3 items-center:
       - drag icon zinc-300.
       - code+name min-w-0: row1 `<div className="text-[13px] font-mono font-medium truncate">{code || 'attribute_code'}</div>`; row2 `<div className="text-[11.5px] text-zinc-500 truncate">{names.pl || 'Nazwa atrybutu…'}{unit ? ` (${unit})` : ''}</div>`.
       - `<TypeBadge type={type}>`.
       - flex items-center gap-1.5 flex-wrap justify-end: chipsy `required` (rose-50 text-rose-700) / `unique` (blue-50 text-blue-700) / `i18n` (violet-50 text-violet-700) — uppercase 10px tracking-wider font-semibold px-1.5 py-0.5 rounded.
4. **Footer** (mapping `:935–949`, px-7 py-4 border-t zinc-100 flex items-center justify-between bg-zinc-50/60):
   - left text-11.5px:
     - gdy `code && names.pl` → `Audit log: <font-mono>attribute.create</> + <font-mono>group.attach</>` (text-zinc-500).
     - w przeciwnym razie → `Wymagane: code i nazwa PL` (text-amber-700).
   - right buttons:
     - `Anuluj` (px-3 h-9 rounded-xl text-13px font-medium text-zinc-700 hover:bg-zinc-100).
     - `<I.check> Utwórz i dołącz` (disabled gdy `!code || !names.pl`).
5. **Submit flow**: onSubmit → `useCreateAttributeAndAttachToGroup(groupCode).mutate(formValues)`:
   - **Wariant A (preferowany)**: 1 endpoint `POST /api/attributes` z opcjonalnym body `attachToGroups: [groupCode]` — BE robi atomowy create + attach (transactional).
   - **Wariant B (fallback)**: 2 calls — `POST /api/attributes` + `POST /api/attribute_groups/{groupCode}/attributes/bulk-attach { attributeCodes: [newCode] }`. Drugi w `onSuccess` pierwszego.
   - Invalidate `['attributes']`, `['attribute_groups', groupCode, 'attributes']`, `['attribute_groups', groupCode, 'usage']` → toast „Atrybut utworzony i dołączony" → close.
6. **Validation** (RHF + zod):
   - `code`: `^[a-z][a-z0-9_]{1,63}$` (snake_case), unique (BE 422 → toast).
   - `names.pl`: required, max 200.
   - `type`: enum 10 typów.
   - `unit`: opcjonalnie, max 20.

### 3.5 i18n (klucze pl + en, ban literałów)

Dorzucamy do `apps/admin/src/locales/pl.json` + `en.json` pod gałęzią `modeling.attributeGroups.*`. Lista nowych kluczy (~75):

```json
{
  "modeling.attributeGroups": {
    "list_title": "Attribute Groups",
    "list_caption": "{count} grup atrybutów",
    "list_first_class_badge": "⭐ first-class entity",
    "list_description": "Grupa atrybutów jako wymienialna jednostka — przypinasz ją do ObjectType (globalnie) lub Category (z dziedziczeniem). Pimcore nie ma tej abstrakcji, Akeneo traktuje ją tylko jako sortowanie. U nas — własny URL, audit, wersjonowanie.",
    "create_action": "Nowa grupa",
    "search_placeholder": "Szukaj grup…",
    "section_system_label": "System (auto-attached)",
    "section_business_label": "Business groups",
    "row_attrs_count": "{count} atrybutów",
    "row_types_count": "{count} typy",
    "row_categories_count": "{count} kat.",
    "back_to_library": "Wstecz do Attribute Groups",
    "back_to_group": "Wstecz do grupy „{code}\"",
    "preview_form_action": "Preview formularza",
    "edit_action": "Edytuj",
    "save_action": "Zapisz zmiany",
    "cancel_action": "Anuluj",
    "fields": {
      "code": "Code",
      "color": "Color",
      "icon": "Ikona",
      "name": "Nazwa",
      "description": "Description",
      "description_optional": "Opis (opcjonalny)",
      "description_placeholder": "Krótki opis grupy — kiedy używać, jakie atrybuty zawiera."
    },
    "definition_title": "Identyfikacja",
    "appearance_title": "Wygląd",
    "behavior_title": "Zachowanie",
    "behavior_required_section_label": "Wymagana sekcja",
    "behavior_required_section_desc": "Grupa zawsze widoczna w formularzu",
    "behavior_shared_label": "Współdzielona",
    "behavior_shared_desc": "Może być dołączona do wielu ObjectType",
    "behavior_conditional_label": "Conditional visibility",
    "behavior_conditional_desc": "Pokaż grupę warunkowo (visible_when)",
    "members_title": "Attributes in this group",
    "members_drag_hint": "— drag to reorder",
    "members_from_library_action": "Z biblioteki",
    "members_create_new_action": "Stwórz nowy",
    "members_required_label": "required",
    "members_no_visibility_rule": "brak reguły widoczności",
    "members_visibility_rule_template": "when {when}",
    "members_empty_action": "Add attribute from library",
    "rules_title": "Visibility rules",
    "rules_visible_when_badge": "visible_when",
    "rules_test_pass_label": "Test: {expr}",
    "rules_test_pass_status": "VISIBLE",
    "rules_test_fail_status": "HIDDEN",
    "rules_edit_action": "Edit rule",
    "where_used_title": "Where used",
    "where_used_object_types_label": "ObjectTypes (globalnie)",
    "where_used_categories_label": "Categories (deklarują)",
    "where_used_instances_label": "instancji dotkniętych",
    "where_used_declared_by_label": "Declared by categories",
    "where_used_inherited_by_label": "inherited by:",
    "create": {
      "caption": "Nowa Attribute Group",
      "title_default": "Nazwa grupy",
      "description": "Grupa to wielokrotnego użytku zbiór atrybutów (np. „Wymiary\", „Bezpieczeństwo\"), który można dołączać do dowolnego ObjectType. Po utworzeniu zacznij dodawać atrybuty z biblioteki.",
      "submit_action": "Utwórz grupę",
      "code_helper": "Niezmienialny po utworzeniu. Używany w API i mapowaniach.",
      "preview_card_title": "Podgląd",
      "preview_code_placeholder": "code…",
      "next_card_title": "Następnie",
      "next_step_1": "Utwórz grupę",
      "next_step_2": "Dodaj atrybuty z biblioteki",
      "next_step_3": "Dołącz grupę do ObjectType"
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
      "system_immutable_note": "Grupa systemowa — code i wybrane pola są niezmienne. Pozostałe pola edytowalne."
    },
    "popups": {
      "from_library_title": "Dodaj atrybuty z biblioteki",
      "from_library_desc": "Wybierz istniejące atrybuty do grupy „{groupName}\".",
      "from_library_existing_hint": "Atrybuty już w grupie są wyłączone.",
      "from_library_search_placeholder": "Szukaj atrybutów po code lub nazwie…",
      "from_library_type_filter_all": "Wszystkie typy",
      "from_library_in_group_badge": "w grupie",
      "from_library_empty": "Brak atrybutów dla podanych kryteriów",
      "from_library_picked_count": "Wybrano {count} {count, plural, one {atrybut} other {atrybutów}}",
      "from_library_attach_action": "Dołącz",
      "from_library_attach_action_with_count": "Dołącz ({count})",
      "create_new_title": "Nowy atrybut w grupie „{groupName}\"",
      "create_new_desc": "Atrybut zostanie utworzony w globalnej bibliotece i automatycznie dołączony do tej grupy.",
      "create_new_section_identification": "Identyfikacja",
      "create_new_section_type_config": "Konfiguracja typu",
      "create_new_section_validation": "Walidacja i flagi",
      "create_new_section_preview": "Podgląd w grupie",
      "create_new_code_placeholder": "np. warranty_months",
      "create_new_code_helper": "snake_case · niezmienialny po utworzeniu",
      "create_new_type_label": "Typ danych",
      "create_new_name_placeholder": "np. Gwarancja (msc)",
      "create_new_unit_label": "Jednostka",
      "create_new_unit_placeholder_money": "PLN",
      "create_new_unit_placeholder_general": "kg / mm / V",
      "create_new_select_info": "Po utworzeniu atrybutu typu {type} będziesz mógł zdefiniować wartości (z tłumaczeniami) w widoku „Zarządzaj wartościami\".",
      "create_new_required_label": "Required",
      "create_new_required_desc": "Pole musi być wypełnione",
      "create_new_unique_label": "Unique",
      "create_new_unique_desc": "Wartość unikalna w typie",
      "create_new_localizable_label": "Localizable",
      "create_new_localizable_desc": "Per locale (PL/EN/DE)",
      "create_new_audit_log_ready": "Audit log: {createEvent} + {attachEvent}",
      "create_new_audit_log_missing": "Wymagane: code i nazwa PL",
      "create_new_submit_action": "Utwórz i dołącz"
    }
  }
}
```

**English fallback**: identyczne klucze, treści po angielsku. Operator może rozszerzyć tłumaczenia w follow-upie. **MVP**: PL + EN minimum.

### 3.6 a11y

- Wszystkie buttons mają `aria-label` jeśli icon-only (close X w header detail, drag handle w members table — drag handle ma natywny `aria-label` z dnd-kit).
- TabBadge ma `aria-label` dynamiczny `Attribute Groups (12)` żeby Playwright nie wpadał w timeout exact-match (lessons VIEW-01 #7).
- LocaleTabs: `role="tablist"`, każdy tab ma `role="tab"` + `aria-selected`.
- Sticky bottom bar: `role="region"` + `aria-label="Niezapisane zmiany"`.
- Color swatch picker: każdy swatch button ma `aria-label="Kolor {hex}"`, klawiatura strzałka L/P do nawigacji, Enter do wyboru.
- Icon picker: każdy button ma `aria-label="Ikona {emoji}"`, keyboard navigation L/P/up/down (grid), Enter/Space wybiera.
- DnD: `dnd-kit/sortable` ma natywne wsparcie dla keyboard reorder (Space + arrows). Test w Playwright keyboard mode.
- Focus ring: `focus:ring-2 ring-zinc-900 ring-offset-2` na wszystkich interactive elementach.
- Dialog (popupy): focus trap + ESC close + initial focus na pierwszym input (search w „Z biblioteki", code w „Stwórz nowy"). shadcn `<Dialog>` daje to natywnie.
- Form fields: `<Label htmlFor>` parowane z `<Input id>` przez React Hook Form `register` — auto-generowane id.
- axe-core: 0 violations serious/critical na wszystkich 3 trasach + 2 popupach (test w Playwright z `<Dialog>` open).

### 3.7 Locales (multi-language fields)

Used in: `label` (grupa), `description` (grupa, opcjonalnie), `name` (atrybut tworzony w popup „Stwórz nowy"). Wszystkie jako JSONB `{pl: ..., en: ..., de?: ...}` w BE, w FE jako `<LocaleTabsField>` (z VIEW-01).

- Tab list: PL (primary, badge `PRIMARY`) + EN + ewentualnie DE/inne dodane przez `<LocaleAddDialog>`.
- Workspace `enabled_locales` z `useCurrentWorkspace()` decyduje które tab pojawiają się domyślnie.
- Dodanie języka: `<LocaleAddDialog>` modal → POST `/api/workspaces/current/locales` (z VIEW-01) + lokalna aktualizacja `enabledLocales` state + dorzucenie pustego entry do edytowanej JSONB.
- Removal locale: out of scope (zarządzanie w Settings · Workspace, nie w Modelowaniu).

### 3.8 Empty / loading / error states

- **List loading**: skeleton 12 wierszy (animate-pulse, 64px each), per sekcja.
- **List empty po search**: text „Brak grup spełniających kryteria" w centrum karty.
- **Show loading**: skeleton header + 4 cards.
- **Show error 404**: redirect do `/modeling/attribute-groups` + toast „Grupa nie istnieje".
- **Show error 403**: redirect do `/dashboard` + toast „Brak uprawnień".
- **Create submit error 422**: pokaz błędów per-field z RFC 7807 `violations[]`.
- **Delete error 422 (in-use)**: toast „Grupa używana przez {N} ObjectType — odepnij ją zanim usuniesz" + link do where-used.
- **Popup „Z biblioteki" loading**: skeleton 8 wierszy w body listy.
- **Popup „Z biblioteki" empty po filter**: text „Brak atrybutów dla podanych kryteriów".
- **Popup „Stwórz nowy" submit error 409 (code conflict)**: toast „Atrybut o code „{code}" już istnieje w bibliotece" + focus na code input.
- **Detach attribute fail**: toast „Nie udało się odpiąć atrybutu" + retain UI state.

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Request body | Response | Permissions | Filtry/sort/pagination | Status |
|---|---|---|---|---|---|---|
| GET | `/api/attribute_groups` | — | `AttributeGroup[]` collection | `attribute_group:read` | filter `system`, `auto_attached`; search `q`; sort `position\|code`; cursor pagination 200 max | ✅ istnieje |
| GET | `/api/attribute_groups/{id}` | — | `AttributeGroup` item (z `code` jako `id`-fallback) | `attribute_group:read` | — | ✅ istnieje |
| POST | `/api/attribute_groups` | `{code, label JSONB, description? JSONB, color?, icon?, position?, isRequiredSection?, isShared?, hasConditionalVisibility?}` | `AttributeGroup` 201 | `attribute_group:write` | — | ✅ istnieje |
| PATCH | `/api/attribute_groups/{id}` | partial fields (NIE `code` — system_immutable; NIE `isSystemGroup`) | `AttributeGroup` 200 | `attribute_group:write` | — | ✅ istnieje |
| DELETE | `/api/attribute_groups/{id}` | — | 204 / 422 (in-use lub system) | `attribute_group:delete` | — | ✅ istnieje |
| GET | `/api/attribute_groups/{id}/usage` | — | `{attributeCount, typesUsed, categoriesUsed, instancesAffected, directlyAttachedTo: {objectTypes[], categories[]}}` | `attribute_group:read` | 60s cache | ✅ istnieje, **rozszerzenie o `instancesAffected` i `typesUsed/categoriesUsed`** (jeśli BE zwraca tylko `attributeCount + directlyAttachedTo`) |
| GET | `/api/attribute_groups/{id}/attributes` | — | `{attributeCode, label, type, position, isRequiredInGroup, visibleWhen, system, unit?}[]` | `attribute_group:read` | — | ✅ istnieje (`AttributeGroupAttributesController`) |
| POST | `/api/attribute_groups/{id}/attributes/bulk-attach` | `{attributeCodes: string[]}` | `{attached: AttributeGroupAttribute[]}` 200 | `attribute_group:write` | — | ✨ NEW |
| DELETE | `/api/attribute_groups/{id}/attributes/{attributeId}` | — | 204 / 422 (system attribute w system group) | `attribute_group:write` | — | ✨ NEW |
| POST | `/api/attribute_groups/{id}/attributes/reorder` | `{order: [attributeCode1, attributeCode2, ...]}` | 204 | `attribute_group:write` | — | ✨ NEW (lub reuse `PATCH /attribute_groups/{id}/attributes/{attrId}` z `{position: int}` per atrybut — wybierzmy bulk endpoint dla optymalizacji jednej transakcji) |
| PATCH | `/api/attribute_groups/{id}/attributes/{attributeId}` | `{position?, isRequiredInGroup?, visibleWhen? {field, operator, value}}` | `AttributeGroupAttribute` 200 | `attribute_group:write` | — | ✅ istnieje (`AttributeGroupAttributeController`) |
| POST | `/api/attributes` (rozszerzenie) | `{...attributeFields, attachToGroups?: string[]}` (nowe pole opcjonalne) | `Attribute` 201 + auto-attach | `attribute:create` + `attribute_group:write` (gdy `attachToGroups` provided) | — | ✅ istnieje (z VIEW-02), **rozszerzenie**: w `AttributeProcessor` po `flush` zrobić `bulkAttachToGroups` jeśli `attachToGroups` provided. Atomicity: jeden DB transaction. |

**Errors**: RFC 7807 Problem Details. Standardowe response:

```json
{
  "type": "https://pim.example.com/errors/system-group-immutable",
  "title": "Grupa systemowa jest niezmienna",
  "status": 422,
  "detail": "Atrybuty system grupy 'identification' nie mogą być odpinane.",
  "instance": "/api/attribute_groups/identification/attributes/sku",
  "violations": [
    {"propertyPath": "attributeId", "code": "system_group_member"}
  ]
}
```

**Cursor pagination**: `/api/attribute_groups?cursor=eyJ...&limit=20`. Default limit 20, max 200.

### 4.2 Encje / schema / migracje

#### Migracja Doctrine

Plik: `apps/api/migrations/Version20260502NNNNNN.php` (NNNNNN = HHmm w UTC, do ustalenia w trakcie implementacji; ostatnia wersja przed VIEW-03 to `Version20260502120000.php`).

**Zmiany w schemie**:
- `attribute_groups` (istnieje):
  - `is_required_section BOOLEAN NOT NULL DEFAULT false` — dla flagi „Wymagana sekcja" (kontrolka z create form sekcja Zachowanie). **NEW column**.
  - `is_shared BOOLEAN NOT NULL DEFAULT true` — dla flagi „Współdzielona". **NEW column**.
  - `has_conditional_visibility BOOLEAN NOT NULL DEFAULT false` — dla flagi „Conditional visibility". **NEW column**.
  
- `attribute_group_attributes` (istnieje, NO schema changes — `position`, `is_required_in_group`, `visible_when` już są).

```sql
ALTER TABLE attribute_groups
  ADD COLUMN is_required_section BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN is_shared BOOLEAN NOT NULL DEFAULT true,
  ADD COLUMN has_conditional_visibility BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN attribute_groups.is_required_section IS 'Group always rendered in form (cannot be skipped/collapsed)';
COMMENT ON COLUMN attribute_groups.is_shared IS 'Group can be attached to multiple ObjectTypes (vs. exclusive to one)';
COMMENT ON COLUMN attribute_groups.has_conditional_visibility IS 'Group rendering controlled by visible_when rules per attribute';
```

**Rollback**: drop columns. Backfill defaults wystarczają.

#### Encje

`apps/api/src/Catalog/Domain/Entity/AttributeGroup.php` — rozszerzenie:

```php
class AttributeGroup implements TenantScoped {
  // existing: id, tenant, code, label, description, icon, color,
  //           isSystemGroup, autoAttached, position, createdAt
  private bool $isRequiredSection = false;
  private bool $isShared = true;
  private bool $hasConditionalVisibility = false;

  public function isRequiredSection(): bool { return $this->isRequiredSection; }
  public function setRequiredSection(bool $value): void {
    $this->guardSystemImmutable('isRequiredSection');
    $this->isRequiredSection = $value;
  }
  public function isShared(): bool { return $this->isShared; }
  public function setShared(bool $value): void {
    $this->guardSystemImmutable('isShared');
    $this->isShared = $value;
  }
  public function hasConditionalVisibility(): bool { return $this->hasConditionalVisibility; }
  public function setConditionalVisibility(bool $value): void {
    $this->guardSystemImmutable('hasConditionalVisibility');
    $this->hasConditionalVisibility = $value;
  }

  private function guardSystemImmutable(string $field): void {
    if ($this->isSystemGroup) {
      throw new SystemGroupImmutableException($field, $this->code);
    }
  }
}
```

`apps/api/src/Catalog/Domain/Exception/SystemGroupImmutableException.php` (nowy, mapped to RFC 7807 422 z violations[].code = `system_immutable`).
`apps/api/src/Catalog/Domain/Exception/AttributeGroupInUseException.php` (nowy, dla DELETE z `attachedToObjectTypes > 0` lub `instancesAffected > 0`).
`apps/api/src/Catalog/Domain/Exception/SystemGroupMemberDetachException.php` (nowy, dla DETACH atrybutu z system grupy).

#### ApiPlatform mapping

`apps/api/config/api_platform/AttributeGroup.xml` — rozszerzenie input/patch o nowe pola: `isRequiredSection`, `isShared`, `hasConditionalVisibility`. Zaktualizuj DTO `AttributeGroupInput` + `AttributeGroupPatchInput`.

`apps/api/config/api_platform/AttributeGroupAttribute.xml` (rozszerzenie lub utworzenie):
- Nowe operations:
  - `POST /api/attribute_groups/{id}/attributes/bulk-attach` (custom controller `BulkAttachAttributesToGroupController`).
  - `DELETE /api/attribute_groups/{id}/attributes/{attributeId}` (custom controller `DetachAttributeFromGroupController`).
  - `POST /api/attribute_groups/{id}/attributes/reorder` (custom controller `ReorderGroupAttributesController`).

### 4.3 Listenery / event subscribers

- `AttributeGroupSchemaVersionBumper` (nowy lub rozszerzenie `Catalog/Infrastructure/Doctrine/EventListener/AttributeGroupSchemaVersionBumper.php`): bump `tenant.schema_version` na lifecycle events `prePersist`, `preUpdate` (`code | label | isSystemGroup | isShared | hasConditionalVisibility`), `preRemove`. Tenant filter active.
- `AttributeGroupAttributePositionAssigner` — `prePersist` automatycznie ustawia `position = max(position) + 1` dla nowego junction record jeśli nie podano w request.
- `AttributeGroupAttributeAuditSubscriber` — emituje events do `dh_auditor` na attach/detach atrybutu (audit log: `attribute_group.attach`, `attribute_group.detach`).

Wszystkie listenery emitują events do `dh_auditor` (natywne ORM hooks).

### 4.4 Permissions / RBAC

#### Macierz ról × operacji

| Rola | attribute_group:read | attribute_group:write | attribute_group:delete |
|---|---|---|---|
| `ROLE_INFORMATION_ARCHITECT` | ✅ | ✅ | ✅ |
| `ROLE_EDITOR` | ✅ | ✅ (member edit, no create/delete group) | ❌ |
| `ROLE_INTEGRATION_OPERATOR` | ✅ | ❌ | ❌ |
| `ROLE_OBSERVER` | ✅ | ❌ | ❌ |

**Voter (`AttributeGroupVoter` istnieje)**: rozszerzenie `attributeMap()` jeśli mamy granularne perms `attribute_group:create` (na razie mapuje wszystko na `write`); dla VIEW-03 zostajemy z `read|write|delete`.

**Endpoint security expressions** (api_platform.xml):
- list / show: `is_granted('ROLE_USER') and is_granted('attribute_group:read')` (item: voter na object).
- create: `is_granted('ROLE_INFORMATION_ARCHITECT') and is_granted('attribute_group:write', object)`.
- patch: `is_granted('attribute_group:write', object)`.
- delete: `is_granted('attribute_group:delete', object)`.
- bulk-attach: `is_granted('attribute_group:write', object)` + dodatkowo per attached attribute voter `attribute:read` (żeby user nie attach'ował atrybutu którego nie widzi).
- detach: `is_granted('attribute_group:write', object)` + special check w controllerze gdy `system_group_member` (throws `SystemGroupMemberDetachException`).
- reorder: `is_granted('attribute_group:write', object)`.

#### Audit log entries

- `attribute_group.create` (z encji + po `flush`).
- `attribute_group.update` (z `dh_auditor` natywnie).
- `attribute_group.delete`.
- `attribute_group.attach_attribute` — `{groupCode, attributeCode}` w meta.
- `attribute_group.detach_attribute` — `{groupCode, attributeCode}`.
- `attribute_group.reorder_attributes` — `{groupCode, oldOrder, newOrder}`.
- `attribute_group_attribute.update_rule` — `{groupCode, attributeCode, oldRule, newRule}`.

### 4.5 Provenance

VIEW-03 nie pisze do `object_values` — `provenance` field N/A dla AttributeGroup / AttributeGroupAttribute. **N/A**.

### 4.6 Worker / async

**N/A dla VIEW-03** — wszystkie operacje synchroniczne. Bulk-attach do max 100 atrybutów (limit w DTO walidacji) — żeby nie blokować requesta. Reorder atomowo per single transaction.

**Future**: dla migration impact (DELETE grupy z `instancesAffected > 1000`), worker + dry-run preview — out-of-scope VIEW-03, follow-up VIEW-03b.

### 4.7 Real-time (Mercure)

**N/A dla MVP**. Future: `attribute_group.update` event publikowany do `https://pim.example.com/groups/{id}` topic. Out-of-scope VIEW-03.

## 5. Sub-tasks (checklist)

### Backend
- [ ] Migracja Doctrine: dodanie 3 kolumn do `attribute_groups` (`is_required_section`, `is_shared`, `has_conditional_visibility`) + comments.
- [ ] Encja `AttributeGroup`: 3 nowe properties + getters/setters + guard `SystemGroupImmutableException`.
- [ ] Domain exceptions: `SystemGroupImmutableException`, `AttributeGroupInUseException`, `SystemGroupMemberDetachException`.
- [ ] DTO `AttributeGroupInput` + `AttributeGroupPatchInput`: rozszerzenie o 3 nowe pola.
- [ ] Endpoint POST `/api/attribute_groups/{id}/attributes/bulk-attach` + controller `BulkAttachAttributesToGroupController` + state processor.
- [ ] Endpoint DELETE `/api/attribute_groups/{id}/attributes/{attributeId}` + controller `DetachAttributeFromGroupController`.
- [ ] Endpoint POST `/api/attribute_groups/{id}/attributes/reorder` + controller `ReorderGroupAttributesController`.
- [ ] Rozszerzenie `POST /api/attributes` o opcjonalne `attachToGroups: string[]` w `AttributeInput` + `AttributeProcessor` zrobi atomic create + attach.
- [ ] Rozszerzenie `GET /api/attribute_groups/{id}/usage` o `instancesAffected` (count z `object_values` joinowane przez attribute → group attributes).
- [ ] Listenery: `AttributeGroupAttributePositionAssigner`, `AttributeGroupAttributeAuditSubscriber`.
- [ ] Voter checks dla bulk-attach (per attribute `attribute:read`).
- [ ] Fixtures: rozbudowanie `BuiltInSystemAttributesSeeder` lub `DemoCatalogSeeder` o 12 grup z mockupu (audit, identification, marketing, tech-spec, pricing, wymagania-medyczne, refundacja-nfz, chirurgia-szczegoly, ortopedia, scheduling, cennik-medyczny, specyfika-fryzjerska) + ich attribute attaches + 2 visible_when rules dla wymagania-medyczne.
- [ ] dh_auditor.yaml: confirm AttributeGroup audit + dodanie AttributeGroupAttribute (jeśli nie ma).
- [ ] PHPStan max → 0 errors.
- [ ] PHPUnit jednostkowe (encje, exceptions): ≥80% coverage nowej logiki.
- [ ] ApiTestCase: per nowy endpoint test 401 + 403 + 404 + walidacja + happy path + multi-tenancy cross-read = 0.

### Frontend
- [ ] Komponent `<ColorSwatchPicker>` w `apps/admin/src/components/modeling/color-swatch-picker.tsx`.
- [ ] Komponent `<GroupIconPicker>` (lub extend `<IconPicker>` z VIEW-01 propsem `options?`).
- [ ] Komponent `<SettingToggleRow>` (lub reuse istniejący — confirm w trakcie).
- [ ] Komponent `<AttributeGroupColorBadge>`.
- [ ] Komponent `<GroupRowItem>`.
- [ ] Komponent `<AttributeGroupAttributesTable>` + `<AttributeGroupAttributeRow>` (dnd-kit Sortable).
- [ ] Komponent `<VisibilityRulesCard>`.
- [ ] Komponent `<AttributeGroupCreateSidebar>`.
- [ ] Komponent `<AttributeTypeSelect>` (uproszczony do popup „Stwórz nowy").
- [ ] Komponent `<AddAttributeFromLibraryDialog>`.
- [ ] Komponent `<CreateAttributeInGroupDialog>`.
- [ ] Pełna przebudowa `AttributeGroupsListPage` (`features/catalog/attribute-groups/list.tsx`) wg sekcji 3.4a.
- [ ] Pełna przebudowa `AttributeGroupShowPage` (`features/catalog/attribute-groups/show.tsx`) wg sekcji 3.4b.
- [ ] Pełna przebudowa `AttributeGroupCreatePage` (`features/catalog/attribute-groups/create.tsx`) wg sekcji 3.4c.
- [ ] Mutations + invalidations w `lib/mutations/attribute-groups.ts` (lub inline w stronach).
- [ ] Refine resource update: `meta.identifierKey = 'code'` + zmiana routów `:id` → `:code`.
- [ ] i18n keys (~75) dorzucone do `pl.json` + `en.json`.
- [ ] axe-core 0 violations serious/critical.
- [ ] Vite build passes (bundle size Δ <50KB gzip vs. baseline).
- [ ] TypeScript strict: 0 errors.
- [ ] Biome strict: 0 errors.

### E2E + integration
- [ ] Playwright spec `apps/admin/e2e/modeling-attribute-groups-list.spec.ts`: login → nawigacja → filter system/business → klik wiersza → redirect do detail.
- [ ] Playwright spec `apps/admin/e2e/modeling-attribute-groups-create.spec.ts`: klik „Nowa grupa" → wypełnij form → swatch + icon picker → submit → toast → redirect do detail.
- [ ] Playwright spec `apps/admin/e2e/modeling-attribute-groups-show.spec.ts`: detail open → edit name → save → toast → reload → persistent state.
- [ ] Playwright spec `apps/admin/e2e/modeling-attribute-groups-add-from-library.spec.ts`: detail → klik „Z biblioteki" → search „price" → check 2 atrybuty → klik Dołącz → toast → list refresh.
- [ ] Playwright spec `apps/admin/e2e/modeling-attribute-groups-create-new-attribute.spec.ts`: detail → klik „Stwórz nowy" → wypełnij code+name+type → submit → toast → list refresh + attribute w bibliotece.
- [ ] Playwright spec `apps/admin/e2e/modeling-attribute-groups-reorder.spec.ts`: detail → drag attribute → reorder → save → reload → persist.
- [ ] Playwright axe-core injection per spec.

### Testy non-functional
- [ ] k6 raport: GET `/api/attribute_groups` p95 <300ms na 100 grup.
- [ ] k6 raport: GET `/api/attribute_groups/{id}/usage` p95 <300ms na 50k SKU.
- [ ] EXPLAIN ANALYZE per nowy query w PR description (bulk-attach insert, detach, reorder).
- [ ] Indeksy: confirm `idx_attribute_group_attributes_group_position` (jeśli nie istnieje, dodać w migracji).
- [ ] Memory profile worker: N/A (nie ma worker'a w VIEW-03).
- [ ] Bundle size delta z Vite report.
- [ ] Lighthouse: performance ≥85, a11y =100, best-practices ≥90 na każdej z 3 tras.
- [ ] composer audit + pnpm audit: 0 high/critical.

### Dokumentacja
- [ ] `docs/api-spec/v0.json` regenerowany (`api:openapi:export`).
- [ ] `agent/current_status.md` update z VIEW-03 progress.
- [ ] `agent/lessons.md` update gdy odkryjemy non-obvious patterns (np. dnd-kit z drag handle ARIA, popup z scrollable list).
- [ ] PR description z screenshot before/after.

### Manual smoke (operator)
- [ ] Login `admin@demo.localhost / changeme` → nawigacja do `/modeling/attribute-groups`.
- [ ] Sprawdź: 12 grup w 2 sekcjach (system + business), pixel-perfect z mockupem 1.
- [ ] Klik „Nowa grupa" → wypełnij Code „dimensions" + Nazwa PL „Wymiary" + Color → Icon → Submit → redirect do detail z grupą.
- [ ] Detail: pixel-perfect z mockupem 2. Klik „Z biblioteki" → popup pixel-perfect z mockupem 3. Zaznacz 2 atrybuty → Dołącz → toast + atrybuty pojawiają się w liście.
- [ ] Klik „Stwórz nowy" → popup pixel-perfect z mockupem 4. Wypełnij code „warranty_months" + name PL „Gwarancja (msc)" + type „number" + unit „msc" → Required → Utwórz i dołącz → toast + atrybut w liście.
- [ ] Drag-reorder atrybuty w grupie → save → reload → kolejność persist.
- [ ] DevTools Console: brak czerwonych errorów.
- [ ] DevTools Network: wszystkie requesty 200/201 (lub 422 z RFC 7807 dla deliberate validation testów).
- [ ] Z drugiego tenanta: cross-read = brak grup demo (multi-tenancy isolation).

## 6. Acceptance criteria — funkcjonalne

- [ ] Wygląda pixel-perfect jak 4 mockupy (Figma/screenshot diff <2%) na wszystkich 3 trasach + 2 popupach.
- [ ] Wszystkie interakcje działają end-to-end (klik → BE → visible result):
  - List → search filtruje client-side po code + label.
  - List → klik wiersza → detail.
  - List → CTA „Nowa grupa" → create.
  - Create → submit → POST → toast → redirect do detail z aktualnymi danymi.
  - Detail → edit pól (label, color, icon, description, behavior toggles) → sticky bottom bar → save → PATCH → toast → reload mostly.
  - Detail → klik „Z biblioteki" → popup → search + filter → check N → klik Dołącz → POST bulk-attach → atrybuty pojawiają się w liście grupy.
  - Detail → klik „Stwórz nowy" → popup → wypełnij → submit → POST attributes z attachToGroups → atrybut w bibliotece + w grupie.
  - Detail → drag-reorder atrybuty → save → POST reorder → kolejność persist.
  - Detail → klik trash przy atrybucie → confirm dialog → DELETE detach → atrybut znika z grupy (nie z biblioteki).
  - Detail → klik checkbox required przy atrybucie → PATCH `isRequiredInGroup` → save.
- [ ] Empty/loading/error states zaobserwowalne (skeleton, search empty, 404 redirect, 422 toast z violations).
- [ ] i18n PL/EN przełącza się — wszystkie copy z mockupu w obu językach.
- [ ] Lock badge + system immutability respektowany — system grupa (`identification`, `audit`) ma fields disabled w detail, brak buttonu delete, brak detach atrybutów system z system grupy.

## 7. Acceptance criteria — non-functional (TWARDE GATES, NIENEGOCJOWALNE)

- **Performance**: p95 endpointów <300ms na seed 50k SKU + 100 grup, k6 raport w PR.
- **N+1 query check**: EXPLAIN ANALYZE każdego nowego query w PR description, zero N+1. Główne query do sprawdzenia:
  - `GET /api/attribute_groups` (z usage subquery).
  - `GET /api/attribute_groups/{id}/attributes` (join attribute + junction).
  - `GET /api/attribute_groups/{id}/usage` (count z `object_type_attribute_groups` + `category_attribute_groups` + `object_values`).
- **Indeksy**:
  - `idx_attribute_group_attributes_group_position` na `(attribute_group_id, position)` — confirm istnieje, dodać jeśli nie.
  - `idx_attribute_group_attributes_attribute` na `attribute_id` — confirm istnieje (FK auto-index).
  - `idx_attribute_groups_tenant_code` UNIQUE na `(tenant_id, code)` — confirm istnieje.
- **Pagination**: limit max 200, default 20, cursor-based jeśli >1000 grup (raczej never, 12 w MVP).
- **Memory** (worker): N/A.
- **Bundle size FE**: Δ <50KB gzip (Vite build report). Color swatch picker + icon picker = ~5KB; popupy = ~15KB; show.tsx rebuild = ~10KB. Limit z buforem.
- **Lighthouse**: performance ≥85, a11y =100, best-practices ≥90 na wszystkich 3 trasach.
- **PHPStan max**: 0 errors.
- **Biome strict**: 0 errors.
- **PHPUnit coverage**: ≥80% nowej logiki domenowej (encja `AttributeGroup` flagi, exceptions, processor bulk-attach).
- **ApiTestCase**: każdy nowy endpoint ma test 401 + 403 + 404 + walidacja + happy path:
  - bulk-attach: + test cross-tenant attribute denial + system group member error.
  - detach: + test system group member detach denial + attribute not in group 404.
  - reorder: + test order length mismatch 422 + duplicate codes 422.
  - POST attributes z `attachToGroups`: + test atomicity (rollback on second-stage failure).
- **Playwright E2E**: happy path + ≥1 edge case zielony per spec (sekcja 5).
- **axe-core**: 0 violations serious/critical na wszystkich 3 trasach + 2 popupach.
- **composer audit + pnpm audit**: 0 high/critical.
- **Multi-tenancy**: cross-tenant read test = 0 wyników (`Tenant.demo` nie widzi `Tenant.acme` grup).
- **RBAC**: voter test dla każdej roli mającej dostęp + jednej bez dostępu.
- **Audit log**: write/update/delete grupy pisze entry; attach/detach/reorder atrybutu też.
- **Provenance**: N/A.
- **i18n coverage**: wszystkie nowe ~75 kluczy obecne w `pl.json` i `en.json`.
- **OpenAPI snapshot**: `docs/api-spec/v0.json` zaktualizowany (`docker compose exec -T -e APP_ENV=dev -e APP_DEBUG=0 api bin/console api:openapi:export | python3 -m json.tool > docs/api-spec/v0.json`).

## 8. Smoke-test scenariusze (manualne, dla operatora)

1. **Login**: `admin@demo.localhost / changeme` w `https://pim.localhost`.
2. **Nawigacja**: Modelowanie → Attribute Groups → sprawdź pixel-perfect z mockupem 1 (lista). 12 grup w 2 sekcjach.
3. **Search**: wpisz „audit" → tylko 1 grupa „Audyt" widoczna w sekcji System.
4. **Klik wiersza** „Identification" → redirect do detail. Pixel-perfect z mockupem 2. Zauważ lock badge + brak buttonu delete.
5. **Klik „Z biblioteki"** w detail business grupy „Marketing" → popup pixel-perfect z mockupem 3. Zaznacz 2 atrybuty → klik Dołącz → toast „Dołączono 2 atrybuty" → atrybuty pojawiają się w liście. Sprawdź response 200 w DevTools Network.
6. **Klik „Stwórz nowy"** w tym samym detail → popup pixel-perfect z mockupem 4. Wypełnij code „warranty_months" + name PL „Gwarancja (msc)" + type „number" + unit „msc" + Required → Utwórz i dołącz → toast → atrybut w liście. Sprawdź też w `/modeling/attributes` → atrybut „warranty_months" widoczny w bibliotece.
7. **Edit grupy**: zmień description Marketing → save → reload → persist.
8. **Drag-reorder** atrybuty w grupie Marketing → save → reload → persist.
9. **Klik „Nowa grupa"** → create form pixel-perfect. Wypełnij Code „dimensions" + Nazwa PL „Wymiary" + Color z swatch + Icon → submit → redirect do detail z pustą grupą + Add attribute from library hint widoczny.
10. **Multi-tenancy**: zaloguj się jako drugi tenant (acme), sprawdź że grupy demo niedostępne.
11. **Permissions**: zaloguj się jako `ROLE_OBSERVER`, sprawdź że buttony „Z biblioteki" + „Stwórz nowy" + „Edytuj" disabled lub niewidoczne.
12. **DevTools Console**: brak czerwonych errorów na wszystkich 3 trasach + 2 popupach.

## 9. Edge cases / poza zakresem

### Świadomie poza zakresem (deferred do follow-up):

- **„Preview formularza" button** w detail header — wymaga osobnego widoku pełnoekranowego pokazującego rendered form jak by go widział operator wypełniający produkt z tą grupą. **Defer do VIEW-03b** (~6h). MVP: button disabled z tooltip „Funkcja w VIEW-03b".
- **Visibility rule editor** (klik „Edit rule" w Card „Visibility rules") — kompletny edytor reguł `visible_when` z dropdownem field + operator + value. Aktualnie tylko wyświetlanie, edycja przez API call manual. **Defer do VIEW-03c** (~8h). MVP: button disabled.
- **Migration impact preview** dla DELETE grupy z `instancesAffected > 0` — proponowany worker async + dry-run modal pokazujący które obiekty stracą jakie wartości. **Defer do VIEW-03d** (~16h). MVP: DELETE 422 jeśli `instancesAffected > 0` z toastem „Grupa używana — odepnij ją zanim usuniesz".
- **Bulk import grup z CSV** — out-of-scope. Defer do osobnego ticketu UI-08.X.
- **Real-time updates (Mercure)** — out-of-scope. Defer do Phase 2.
- **Versioning grup** (proponowany ADR-012) — out-of-scope MVP. Każdy update overwrite, audit log trzyma diff.
- **Wybór emoji custom z picker'a Apple/Google** — używamy fixed 14 z mockupu. Custom emoji input out-of-scope.
- **Per-locale icon picker** — icon jest globalny dla grupy, nie per-locale.
- **Conditional visibility rendering w form preview** — preview pokazuje WSZYSTKIE atrybuty bez aplikowania `visible_when`. Defer do VIEW-03b/c.

### Edge cases pokryte:

- System grupa (`identification`, `audit`) — pełna immutability code + immutable list of attributes (system attrs nie da się odpiąć), edytowalne tylko `description`, `color`, `icon`, `label`. Test w show.tsx pixel-perfect: lock badge + disabled inputs + brak trash przy atrybutach.
- DELETE grupa attach'owana do ObjectType — 422 z RFC 7807 + toast + link do where-used.
- Attach atrybutu już w grupie (idempotency) — BE bulk-attach pomija duplicates (no-op), zwraca tylko nowo dodane junction records.
- Detach atrybutu mającego `instancesWith > 0` w object_values — 422 z toastem „Atrybut używany w {N} obiektach grupy — usuń wartości zanim odepniesz" (lub: detach ale zachowaj object_values jako orphan — decyzja architektoniczna, default: blokuj).
- Reorder z duplicate codes lub missing codes — 422 walidacja w controllerze.
- Race condition przy concurrent attach → BE optimistic lock przez `updatedAt` na grupie (rollback retries 422 z toastem „Spróbuj ponownie").
- Empty mockup label PL → walidacja required toast „Nazwa PL jest wymagana".

### Edge cases zostawione na później (z linkiem do follow-up):

- **Drag-reorder z dnd-kit screen reader announcements** — natywne wsparcie dnd-kit, ale custom announcements dla Polish lokalizacji (np. „Atrybut przeniesiony na pozycję 3"). Follow-up: ticket a11y polish UI-08.A11Y.
- **Skeletons z gradient shimmer** zamiast `animate-pulse` (może lepiej wyglądać). Follow-up: ticket UI polish UI-08.UX.
- **Toast notifications w shadcn Sonner** zamiast obecny toast helper. Follow-up: globalny refactor toast systemu.

## 10. Powiązane ADR / dokumenty

### ADR

- **ADR-009** (ObjectType jako koncept pierwszej klasy) — referencyjnie, AttributeGroup attach'owany do ObjectType lub Category z tej decyzji.
- **Proponowany ADR-012** (AttributeGroup as first-class entity) — własny URL `/modeling/attribute-groups/{code}`, audit, versioning. **Aktualizacja `Project Plan/01-architektura-pim.md` sekcja 13** w PR z VIEW-03 implementacją: zatwierdzenie ADR-012 jako accepted (był proposed), dodanie sekcji w architekturze opisującej first-class status.
- **Brak nowego ADR potrzebnego w VIEW-03** — wszystkie decyzje (route URL z code zamiast UUID, popupy vs full views, edit-in-place pattern) są zgodne z ADR-009 i wzorcami z VIEW-01/VIEW-02.

### Aktualizacje plików:

- `Project Plan/01-architektura-pim.md` — confirm ADR-012 accepted (po ticket implementation merge).
- `Project Plan/02-plan-projektu-pim.md` — checkbox VIEW-03 mark done.
- `agent/current_status.md` — update z VIEW-03 progress per faza.
- `agent/lessons.md` — dorzucenie lessons gdy odkryjemy non-obvious patterns w trakcie implementacji (np. dnd-kit + popup, route param `:code` w Refine, swatch picker reuse z VIEW-02).
- `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md` — checkbox VIEW-03 done.
- `docs/api-spec/v0.json` — regen po BE changes.

### Lessons z VIEW-01 / VIEW-02 do uwzględnienia w VIEW-03:

1. **Mockup używa `getterFunction()` jako PHP getter** — dla bool propsów używaj `is*()` lub explicit `getXxx()`, sprawdź PropertyAccessor compat (lessons VIEW-01 #1-2).
2. **OpenAPI drift w CI** — re-eksportuj snapshot przed pushem (lessons VIEW-01 #3).
3. **DBAL `fetchAllAssociative` z `mixed`** — assert types przed użyciem (lessons VIEW-01 #4).
4. **Test environment `pim:db:reset --with-fixtures`** + `doctrine:fixtures:load --no-interaction` przed Playwright (lessons VIEW-01 #5).
5. **TabBadge regex z `(^|\s)Attribute Groups(\s|$)`** żeby Playwright nie wpadał w timeout (lessons VIEW-01 #6).
6. **Rate limiter cache reset** `rm -rf /app/var/share/dev/pools` przed Playwright run (lessons VIEW-01 #7).
7. **Dialog focus trap** — shadcn `<Dialog>` daje natywnie, sprawdź initial focus na pierwszym input.
8. **dnd-kit + DialogContent** — czasem `pointer-events: none` na Dialog blokuje drag handle. Workaround: `Dialog.Overlay` z `pointer-events: auto`.

---

## Estymacja

| Faza | Estymacja |
|---|---|
| Backend (3 nowe endpointy + 3 nowe pola encji + listenery + tests) | ~10h |
| Frontend (3 strony rebuild + 2 popupy + 6 nowych komponentów + i18n) | ~16h |
| E2E (6 specs) + axe-core | ~4h |
| Quality gates + dokumentacja + smoke iteracje | ~2h |
| **Total** | **~32h** |

---

> **Uwaga implementacyjna**: Ticket VIEW-03 zakłada że VIEW-02 (Attributes Library, issue #374) jest już zmergowany do main lub przynajmniej `<ColorSwatchPicker>` + `<TypeBadge>` + `<AttributeTypeGrid>` + reuse przez VIEW-02 są dostępne. **Jeśli VIEW-03 startuje przed merge VIEW-02**, należy zsynchronizować swatch palette (8 vs 10 swatch) i `<AttributeTypeSelect>` (uproszczony do popup) w trakcie implementacji.
> Operator może wybrać kolejność:
> 1. **VIEW-02 first → VIEW-03** — preferowany flow (zgodny z VIEW-first incremental order). Reuse łatwiejszy.
> 2. **VIEW-03 first → VIEW-02** — działa, ale `<ColorSwatchPicker>` napisać tu, a w VIEW-02 zrobić extend.
> 3. **Równoległe** — możliwe, ale wymaga mergowania konfliktów w shared components folder.
