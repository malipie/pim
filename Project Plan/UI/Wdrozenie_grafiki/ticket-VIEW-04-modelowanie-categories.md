# VIEW-04 — modeling/categories: pixel-perfect tree + detail + create + edit

> **Konwencje:** PL po polsku (kod / commit messages / API po angielsku). View-first ticket — operator dostarczył screenshot widoku listy `/modeling/categories`; create i edit projektowane na bazie wzoru z `attributes/new.tsx` + `attributes/show.tsx`.
>
> **Decyzje (AskUserQuestion):**
> - Jeden ticket VIEW-04 zamiast trzech split (operator wycofał wstępną sugestię o split na 04/04b/04c).
> - „+ Create test object" CTA w Effective preview = **MOCK** (`<MockBadge>` + tooltip „Wymaga wizard tworzenia obiektu (Faza 1)").

---

## 1. Kontekst i cel widoku

`/modeling/categories` jest sercem warstwy modelingu w PIM — drzewo ltree (PostgreSQL `LTREE`) + per-kategoria deklaracja jakie **AttributeGroup'y** dziedziczą obiekty w tej gałęzi. Killer feature: **Effective preview** który pokazuje co użytkownik zobaczy w formularzu `Stwórz obiekt → <kategoria>` — Pimcore i Akeneo nie mają tej abstrakcji (Akeneo traktuje grupę atrybutów jako sortowanie, Pimcore w ogóle).

Aktualny widok produkcyjny (`apps/admin/src/features/catalog/categories/list.tsx`) jest stub'em — drzewo render-only, brak detail panelu, brak declare-group CRUD, brak effective preview, brak Create/Edit pages. VIEW-04 dostarcza pełną pixel-perfect implementację z mockupu `Modelowanie.html` plus dwie nowe strony trasowane (Create + Edit) wzorowane na atrybutach (operator: „wzoruj się na dodawaniu atrybutów").

Powiązane ADR: **ADR-009** (ObjectType jako koncept pierwszej klasy + sugar paths `/api/categories`), **ADR-012** (proponowany — AttributeGroup jako first-class entity z dziedziczeniem przez kategorie + ObjectType). VIEW-04 implementuje frontowe spojrzenie na ADR-012 — backend domain layer (`CategoryAttributeGroup` + `EffectiveAttributeGroupResolver` + `GET /effective-groups`) już jest zaimplementowany w epiku UI-08 (#259, #269).

---

## 2. Mockup / źródło designu

- **Plik prototypu:** `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/groups-categories.jsx:255–455`
  - Lin. 255–423 — `CategoriesView` (split-layout 320px tree + 1fr detail).
  - Lin. 425–455 — `TreeNode` (recursive node z dot indicators, instances count, expand/collapse).
  - Lin. 457+ — `FormPreviewRow` (pojedyncza linijka effective preview).
- **Screenshot:** dostarczony przez operatora (pixel reference dla wszystkich klas Tailwind, paddingów, borderów, typografii).
- **Powiązany prototyp:** `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/Modelowanie.html` — wrapper z tab-navem (Object Types / Attributes / Attribute Groups / Categories).

**Pixel-perfect binding:** JSX prototyp jest single source of truth dla layoutu, klas Tailwind, paddingów, copy, animacji. Adaptacje stack-specific (shadcn/ui zamiast hand-rolled, Refine zamiast TanStack Query) dozwolone, ale wizualny rezultat <2% pixel mismatch w side-by-side comparison.

**Brak mockupów dla `/new` i `/:id`** — zaprojektowane jako pełnoekranowe widoki trasowane wzorowane 1:1 na `apps/admin/src/features/catalog/attributes/new.tsx` (Create) i `attributes/show.tsx` (Edit z dirty bar, audit indicator, danger zone, where-used).

---

## 3. Zakres frontend (FE)

### 3.1 Routing

Refine resources (rejestracja w `apps/admin/src/App.tsx`):

```ts
{
  name: 'categories',
  list: '/modeling/categories',          // CategoriesTreePage (split: tree + detail)
  create: '/modeling/categories/new',    // CategoryCreatePage
  edit: '/modeling/categories/:id',      // CategoryShowPage (edit-in-place jak attribute show)
  show: '/modeling/categories/:id',      // alias, ten sam komponent
  meta: { /* navbar config */ },
}
```

Auth: `IS_AUTHENTICATED_FULLY` per route (Refine guard).

**Dlaczego osobne strony, nie popupy:** zgodnie z `feedback_view_scope_literal.md` create i edit są pełnoekranowymi widokami trasowanymi. Popup dopuszczalny tylko dla mikro-akcji — w VIEW-04 popup używamy dla `<DeclareAttributeGroupDialog>` i `<MoveCategoryDialog>` (mikro-akcje na junction / parent change).

### 3.2 Komponenty (lista płaska)

**Reuse (istniejące, bez zmian):**
- `<ModelingPageHeader>` — caption / title / description / CTA / trailing
- `<ModelingSection>` — wrapper `<Card className="p-6 space-y-6">` z section title
- `<LocaleTabsField>` — wielojęzyczne pole JSONB pl/en (PL/EN tabs + add locale)
- `<IconPicker>` — emoji tile picker
- `<BuiltInLockBadge>` — lock badge dla kategorii built-in (jeśli istnieją; aktualnie brak `is_built_in` na CatalogObject)
- `<DangerZoneCard>` — delete confirmation pattern z attribute show
- `<AuditLogIndicator>` — wskaźnik audit log (MOCK lub wired w zależności od BE state)
- `<WhereUsedList resource="categories" id={id} />` — pokazuje powiązania (instanceCount, descendantCount, declaredFor[])
- `<MockBadge>` — badge dla CTA "Create test object" (Faza 1)
- `<Button>`, `<Card>`, `<Input>`, `<Label>`, `<Textarea>`, `<Dialog>` — shadcn primitives

**Nowe (do napisania):**
- `<CategoryTree>` w `apps/admin/src/components/modeling/category-tree.tsx` — recursive tree z node selection, expand/collapse, group dot indicators, instances count. Props: `categories[]`, `selectedId?`, `onSelect(id)`, `disabledIds?` (dla MoveCategoryDialog), `currentTargetType` (dla group dot color mapping).
- `<CategoryDetailPanel>` w `apps/admin/src/components/modeling/category-detail-panel.tsx` — prawy panel z header + Declared directly + Inherited from parents + Effective preview. Props: `categoryId`, `targetObjectTypeId`, `targetObjectTypeKind`, `onDeclareGroupClick`, `onDetachGroup(groupId)`.
- `<DeclareAttributeGroupDialog>` w `apps/admin/src/components/modeling/declare-attribute-group-dialog.tsx` — popup modal. Search input + checkbox list AttributeGroup'ów. Props: `open`, `onOpenChange`, `categoryId`, `targetObjectTypeId`, `targetObjectTypeKind`, `excludeGroupIds[]` (declared + inherited — disabled), `inheritedFromMap` (groupId → ancestor name dla tooltip), `onDeclared()`.
- `<ObjectTypeFilterDropdown>` w `apps/admin/src/components/modeling/object-type-filter-dropdown.tsx` — Select z built-in ObjectTypes (Service/Product/Asset/Brand/Category). Props: `value`, `onChange`. Persist w URL `?targetType=service`.
- `<MoveCategoryDialog>` w `apps/admin/src/components/modeling/move-category-dialog.tsx` — popup z tree picker + computed new path preview. Props: `open`, `onOpenChange`, `category`, `onMoved()`.
- `<EffectivePreviewCard>` w `apps/admin/src/components/modeling/effective-preview-card.tsx` — Card border-violet z lista `<FormPreviewRow>` + MOCK CTA "Create test object". Props: `categoryId`, `targetObjectTypeKind`.
- `<FormPreviewRow>` w `apps/admin/src/components/modeling/form-preview-row.tsx` — pojedyncza linia preview (icon + group name + first 3 attrs comma-joined + source badge). Props: `icon`, `name`, `attrs[]`, `source: 'object_type' | 'declared_here' | { type: 'inherited', from: string }`.
- `<CategoryPathPreview>` w `apps/admin/src/components/modeling/category-path-preview.tsx` — live computed ltree path z code + parent. Props: `parentPath?`, `code`. Render: `service.lekarz.chirurg.<code>` w font-mono.

**Nowe strony:**
- `apps/admin/src/features/catalog/categories/list.tsx` — **rebuild** istniejącego (split-layout).
- `apps/admin/src/features/catalog/categories/new.tsx` — **nowa**, wzorzec `attributes/new.tsx`.
- `apps/admin/src/features/catalog/categories/show.tsx` — **nowa**, wzorzec `attributes/show.tsx`.

**Nowe lib:**
- `apps/admin/src/lib/category-icons.ts` — `CATEGORY_ICONS = ['📁', '📂', '🩺', '💉', '🪒', '💆', '🛒', '🍔', '🎓', '🏗️', '🚗', '🎨', '📡', ...]` (12-16 emoji semantycznie pasujących do kategorii usług/produktów/zasobów).

### 3.3 State management

**Refine resources:**
- `categories` resource — `useList`, `useOne`, `useCreate`, `useUpdate`, `useDelete` (BE: `/api/categories` sugar path → CatalogObject z `kind=category`).
- `object_types` resource — `useList` dla `<ObjectTypeFilterDropdown>` (cached aggressively — built-in lista nie zmienia się).
- `attribute_groups` resource — `useList` dla `<DeclareAttributeGroupDialog>` (cached, ale invalidated po declare).

**Custom mutacje** (poza Refine resource convention):
- Declare: `POST /api/categories/{id}/attribute_groups` body `{ groupId, targetObjectTypeKind }` — `jsonFetch` direct.
- Detach: `DELETE /api/categories/{id}/attribute_groups/{groupId}/{targetTypeId}` — `jsonFetch` direct.
- Reorder: `PATCH /api/categories/{id}/attribute_groups/{groupId}/{targetTypeId}` body `{ position }` — `jsonFetch` direct (UI: Move via dialog z input number, DnD deferred).
- Move category: `PATCH /api/categories/{id}/move` body `{ newParentId | null }` — `jsonFetch` direct.

**Cache invalidation po mutacjach:**
- Declare/Detach/Reorder → invalidate `['categories', id, 'attribute-groups', targetTypeKind]` + `['categories', id, 'effective-groups', targetTypeKind]` + descendants effective-groups (best-effort prefix).
- Move → invalidate `['categories']` (cała lista — paths się zmieniły dla subtree) + `['categories', id]`.
- Create → invalidate `['categories']` + redirect z `?selected=<newId>`.
- Edit (PATCH) → invalidate `['categories', id]` + lista (jeśli code lub path się zmieniły).
- Delete → invalidate `['categories']` + redirect `/modeling/categories`.

**Local state:**
- `<CategoryTree>` — `expanded: Record<string, boolean>` (persist w URL `?expanded=lekarz,fryzjer`).
- `<CategoryDetailPanel>` — `selectedGroupForReorder?: GroupId` dla edit-position dialog.
- `<DeclareAttributeGroupDialog>` — `pickedGroupIds: Set<string>`, `searchQuery: string`.
- `CategoryShowPage` — per-pole state (labelPl, labelEn, descriptionPl, descriptionEn, icon, parent) + `dirty: boolean` derived.

### 3.4 Struktura sekcji widoku — **`/modeling/categories` (list+detail)**

Sekcje w kolejności renderu:
1. **Header strony** (`<ModelingPageHeader>`):
   - caption: `"drzewo ltree · target {currentTargetTypeKind}"` (np. "drzewo ltree · target Service")
   - title: `"Categories · modeling"` (font-display 28px)
   - description: `"Drzewo kategorii deklaruje jakie grupy atrybutów mają obiekty w tej gałęzi. Dziedziczenie idzie w dół — Ortopeda dziedziczy wszystko od Lekarz + Chirurg, plus własne. Inheritance preview pokazuje co użytkownik zobaczy w formularzu."`
   - CTA: `+ Nowa kategoria` (link `/modeling/categories/new`).
   - Trailing: `<ObjectTypeFilterDropdown>` (Select Service/Product/...).

2. **Split layout** (`grid grid-cols-[320px_1fr] gap-6`):
   - **Lewy: `<Card className="p-3">` z header `"DRZEWO KATEGORII"` + `target: {kind}`** (mockup lin. 308–311) + `<CategoryTree>`.
   - **Prawy: `<CategoryDetailPanel>`** (jeśli `selectedId`) lub empty state `"← Wybierz kategorię z drzewa"`.

3. **Detail panel — Card 1 (kategoria info + declared + inherited)** — mockup lin. 319–386:
   - Header: ikona + nazwa (font-display 22px) + ltree path (font-mono 12px text-zinc-500) + counter instancji (po prawej, font-display 22px num).
   - Sekcja **Declared directly** (lin. 336–361): label `"Declared directly"` + lista chip'ów (icon + name + attr count + edit + trash buttons) + CTA `"+ Declare group"` (dashed border).
   - Sekcja **Inherited from parents** (lin. 363–384): label `"Inherited from parents"` + `<ReadOnlyBadge>` ("read-only") + lista chip'ów z `↪ source` badge (bg-white border-zinc-200 font-mono).

4. **Detail panel — Card 2 (Effective preview, killer feature)** — mockup lin. 388–416:
   - Card border-violet bg-violet-50/30.
   - Header: label `"Effective preview"` (text-violet-700 font-semibold) + `<KillerFeatureBadge>` ("killer feature") + `<MockBadge>` z tooltipem "Create test object (Faza 1)" jako CTA `"+ Create test object"` po prawej.
   - Intro: `"Obiekt typu Service w kategorii „<NazwaKategorii>" zobaczy w formularzu:"` (text-12.5px text-zinc-700).
   - Lista `<FormPreviewRow>` (Card border-violet-200 bg-white):
     - **Identification** (icon 🔑, attrs `sku, name, slug`, source `z ObjectType`, lock).
     - **Audit** (icon 🛡, attrs `created_at, updated_at, created_by`, source `z ObjectType`, lock).
     - Każda effective grupa (z `/api/categories/{id}/effective-groups`): icon + name + first 3 attrs + badge (`tutaj` jeśli source=declared_here, `↪ <source>` jeśli inherited).
   - Footer: `<InfoBadge>` z text "Tego nie ma w Pimcore ani Akeneo — Adam zobaczy dokładnie to co Kasia w formularzu „Stwórz <typ obiektu> → <Kategoria>"."

### 3.4a Struktura sekcji — **`/modeling/categories/new`**

Layout: `<Card className="p-6 space-y-6">` z trzema sekcjami (wzorzec `attributes/new.tsx`):
1. **Sekcja Identyfikacja**:
   - Code (Input, snake_case validation, required, h-10 rounded-xl).
   - Parent (Select Refine z `useList` na categories — wszystkie categories tenanta hierarchicznie + opcja "— root —").
   - Name PL/EN (`<LocaleTabsField>`, primary locale = pl).
   - Description PL/EN (`<LocaleTabsField>` z Textarea, primary locale = pl, opcjonalne).
2. **Sekcja Wizualizacja**:
   - Icon picker (`<IconPicker>` z `CATEGORY_ICONS`).
3. **Live preview ltree path** (`<CategoryPathPreview>`):
   - Box bg-zinc-50 z text "Ścieżka po zapisie: `service.lekarz.chirurg.{code}`".
4. **Submit + Cancel**:
   - Bottom right: Cancel (link back `/modeling/categories`) + Submit (primary zinc-900 "Utwórz kategorię").
   - Po success: redirect `/modeling/categories?selected=<newId>`.

### 3.4b Struktura sekcji — **`/modeling/categories/:id`**

Layout: header + sekcje + sticky dirty bar (wzorzec `attributes/show.tsx`):

1. **Header strony** (flex justify-between):
   - Lewy stack: button "Wstecz do drzewa" (link `/modeling/categories?selected=<id>`) z ikoną ArrowLeft.
   - Prawy stack: `<AuditLogIndicator>` (MOCK na razie, BE endpoint /audit-log nie jest dostępny dla CatalogObject) + Save button (primary zinc-900) + Move button (secondary outline) + Delete button (otwiera `<DangerZoneCard>` confirmation).

2. **Header kategorii** (mockup-like lin. 322–334):
   - Ikona + ltree path (font-mono) + nazwa (font-display 22px).
   - Counter instancji po prawej.

3. **Sekcja Definicja** (`<ModelingSection>` "Definicja"):
   - Code (Input LOCKED, immutable po create — jak attribute code).
   - Parent (read-only display ścieżki: `service.lekarz.chirurg` + button "Move").
   - Name PL/EN (`<LocaleTabsField>` editable).
   - Description PL/EN (`<LocaleTabsField>` editable).

4. **Sekcja Wizualizacja**:
   - Icon picker (`<IconPicker>` editable).

5. **Sekcja Declared groups** (zwięzła wersja — link do detail panelu):
   - Tekst: "Grupy atrybutów dla tej kategorii zarządzasz w widoku [drzewa](/modeling/categories?selected={id})." z linkiem.

6. **Sekcja Where used** (`<WhereUsedList resource="categories" id={id} />`):
   - Pokazuje `instanceCount`, `descendantCount`, `attachedToObjectTypes[]` (jeśli BE wystawi).

7. **Sekcja Audit trail** (`<AuditTrailCompact resource="categories" id={id} />`):
   - **MOCK** na razie z `<MockBadge>` "Wymaga włączenia audytu na CatalogObject" — bo enable dh_auditor dla CatalogObject jest ryzykowne (komentarz w `dh_auditor.yaml:24-32`); deferred do dedicated audit ticket.

8. **Sekcja Danger Zone** (`<DangerZoneCard>`):
   - Delete button + confirmation dialog "Wpisz code aby potwierdzić".
   - Walidacja BE: 409 Conflict jeśli `instanceCount > 0` lub `descendantCount > 0` → wyświetl detail w UI.

9. **Sticky dirty bar** (jak attributes/show.tsx, fixed bottom z-30):
   - Lewy: "{N} pól zmienionych".
   - Prawy: Cancel (revert state) + Save (primary).
   - PATCH `/api/categories/{id}` body z dirty fields.

### 3.5 i18n

Wszystkie nowe klucze w `apps/admin/src/locales/pl/common.json` + `apps/admin/src/locales/en/common.json`. Konwencja `categories.*` (analog do `attributes.*`).

Lista pełna ~55 kluczy:
- **List/detail**: `categories.list_title`, `list_description`, `list_caption`, `create_action`, `target_type_label`, `tree_label`, `tree_target_suffix`, `empty_select_node`, `instance_count` (z plural).
- **Detail panel**: `categories.detail.declared_directly`, `inherited_from_parents`, `read_only_badge`, `declare_group`, `empty_declared`, `attr_count_short`.
- **Effective preview**: `categories.preview.title`, `killer_feature_badge`, `create_test_object`, `create_test_object_mock_tooltip`, `intro`, `competitor_note`, `system_group_object_type`, `inherited_source_prefix`, `here_badge`.
- **Declare dialog**: `categories.declare_dialog.title`, `description`, `search_placeholder`, `submit`, `cancel`, `already_inherited_tooltip`, `already_declared_tooltip`, `empty_results`.
- **Move dialog**: `categories.move_dialog.title`, `description`, `preview_path`, `submit`, `cancel`, `cycle_error`, `same_parent_warning`.
- **Create**: `categories.create_title`, `create_caption`, `create_description`, `create_back`, `create_submit`, `create_path_preview_label`, `create_path_root_prefix`.
- **Show**: `categories.show_title`, `show_back`, `code_locked_help`, `dirty_count`, `save_changes`.
- **Fields**: `categories.fields.code`, `code_help`, `parent`, `parent_help`, `parent_root_option`, `name`, `description`, `icon`.
- **Actions**: `categories.actions.save`, `move`, `delete`, `view`.
- **Delete dialog**: `categories.delete_dialog.title`, `confirm_text`, `with_descendants_error`, `with_objects_error`, `confirm_input_placeholder`.
- **Audit/where-used**: `categories.audit.section_title`, `audit.mock_tooltip`, `where_used.section_title`.

EN tłumaczenia 1:1 (wszystkie klucze z `defaultValue` fallback w komponentach jako safety net dla MVP).

### 3.6 a11y

- **`<CategoryTree>`** — `role="tree"`, każdy node `role="treeitem"` + `aria-expanded` + `aria-selected` + `aria-level={depth+1}`. Klawisze: ArrowDown/ArrowUp (focus next/prev), ArrowRight (expand), ArrowLeft (collapse), Enter (select).
- **`<DeclareAttributeGroupDialog>`** — `<Dialog>` shadcn (focus trap built-in), search input z `aria-label`, lista checkboxów z `<label>` link, disabled checkboxy z `aria-disabled` + `<title>` tooltip.
- **`<ObjectTypeFilterDropdown>`** — `<Select>` shadcn (Radix, full a11y).
- **`<MoveCategoryDialog>`** — focus trap + `<CategoryTree>` z disabled state dla cycle prevention.
- **Effective preview** — semantyczna sekcja z `<h2>` heading.
- **Audit indicator MOCK** — `<MockBadge>` ma `aria-label="MOCK"` + tooltip via `<Tooltip>` shadcn.
- **Color contrast** — wszystkie badges WCAG AA (text-zinc-500 na bg-white, text-violet-700 na bg-violet-50, text-emerald-700 na bg-emerald-50 — sprawdzone w istniejących komponentach modelingu).
- **axe-core 0 violations** serious/critical na wszystkich trzech stronach + 4 dialogach.

### 3.7 Locales (multilingual fields)

Name + description kategorii to JSONB `{ pl: "...", en: "..." }`. Reuse `<LocaleTabsField>` (z attribute show pattern):
- Tabs PL/EN (primary locale = pl badge).
- + Dodaj język button → `<LocaleAddDialog>` (już istnieje, dodaje do `enabledLocales`).
- Backend zachowuje shape `Record<string, string>`.

### 3.8 Empty / loading / error states

**Loading:**
- Lista kategorii (initial fetch) — `<p className="py-6 text-center text-sm text-muted-foreground">{t('app.loading')}</p>`.
- Detail panel (po selekcji) — Skeleton `<Card className="p-6 animate-pulse">` z 3 placeholder rows.
- Effective preview — Skeleton 4 placeholder FormPreviewRows.

**Empty:**
- Brak kategorii w tenancie → centered message `"Brak kategorii. Utwórz pierwszą kategorię."` + CTA primary.
- Brak selekcji w detail panelu → `"← Wybierz kategorię z drzewa"` (text-zinc-400 italic, centered, py-12).
- Brak declared groups → `"— brak własnych grup, dziedziczy wszystko"` (italic text-zinc-400).
- Brak inherited groups → cała sekcja Inherited ukryta (jak w mockupie lin. 363).
- Brak effective groups (root + brak declared) → tylko system Identification + Audit visible.

**Error:**
- API error → toast notification (Refine `useNotification`) z RFC 7807 detail.
- Form validation error → wyświetlone pod polem (text-rose-600 text-xs mt-1).
- 409 Delete (z descendants/objects) → `<Alert>` w danger zone z explicit message + lista przeszkód.
- 422 Move (cycle) → toast + form-level `<Alert>`.

---

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Request body | Response | Permissions | Notes |
|--------|------|--------------|----------|-------------|-------|
| `POST` | `/api/categories` | `{ code, name?: {pl,en}, description?: {pl,en}, parentId?: uuid, icon?: string, objectTypeKind?: 'category' }` | 201 z `CatalogObject` (kind=category) JSON-LD | `WRITE` voter | **Już działa** via ApiPlatform sugar path; rozszerzenie: listener `CategoryPathBuilder` ustawia `path` automatycznie z `parent.path . '.' . code`. |
| `PATCH` | `/api/categories/{id}` | `{ name?, description?, icon?, code? }` (merge-patch+json) | 200 z updated CatalogObject | `WRITE` | **Rozszerzenie**: jeśli `code` zmieniony → service `RecomputeCategorySubtreePathsService` rebuildje path tej kategorii + descendants w jednej transakcji. |
| `DELETE` | `/api/categories/{id}` | — | 204 | `DELETE` | **Rozszerzenie**: `CategoryDeleteGuard` listener — 409 Conflict jeśli `descendantCount > 0` lub `instanceCount > 0` (objects.parent_id = id). |
| `PATCH` | `/api/categories/{id}/move` | `{ newParentId: uuid \| null }` | 200 z updated CatalogObject + `affectedDescendants: int` | `WRITE` | **Nowy** custom controller. Walidacja cycle (`WHERE path <@ $movingPath`) + cross-tenant + recursive update path dla subtree w transakcji. |
| `POST` | `/api/categories/{id}/attribute_groups` | `{ groupId: uuid, targetObjectTypeKind: string }` | 201 z `CategoryAttributeGroup` JSON | `WRITE` | **Nowy**. Position = max(existing for category+target) + 1. Idempotent (re-attach = 200 no-op). |
| `DELETE` | `/api/categories/{id}/attribute_groups/{groupId}/{targetTypeId}` | — | 204 | `WRITE` | **Nowy**. Tolerant (already-detached = 204). |
| `PATCH` | `/api/categories/{id}/attribute_groups/{groupId}/{targetTypeId}` | `{ position: int }` | 200 | `WRITE` | **Nowy**. Reorder w sekcji Declared directly (deferred DnD UI; MVP = input number dialog). |
| `GET` | `/api/categories/{id}/attribute_groups?targetObjectTypeKind=service` | — | 200 z `{ declaredGroups: [{ groupId, position, group: {...} }] }` | `READ` | **Nowy**. Reuse `EffectiveAttributeGroupResolver::loadGroupAttributes()`. Zwraca tylko declared (nie inherited). |
| `GET` | `/api/categories/{id}/effective-groups?objectTypeKind=service` | — | 200 z `{ effectiveGroups: [{ ..., source: 'object_type' \| 'declared_here' \| 'inherited_from:<categoryId>:<categoryName>' }] }` | `READ` | **Już działa** (UI-08.14); **rozszerzenie**: dodanie pola `source` per group (zmiana `EffectiveAttributeGroupResolver` żeby trackował origin). |
| `GET` | `/api/categories/{id}/usage` | — | 200 z `{ instanceCount, descendantCount, declaredFor: [{ targetTypeKind, count }] }` | `READ` | **Nowy** custom controller. Zlicza objects.parent_id = id + objects.path matchujące descendants + grupowanie po target type. |

Wszystkie errors RFC 7807 (`application/problem+json`). Cursor pagination N/A — listy są małe (kategorie typowo <500 per tenant, declared groups per category <20).

### 4.2 Encje / schema / migracje

**Brak migracji** dla nowych tabel — wszystkie encje (`CatalogObject`, `CategoryAttributeGroup`, `ObjectType`, `AttributeGroup`) już istnieją.

**Migracja addytywna** (jedna nowa migracja Doctrine `VersionXXXXXXXXXXXXXX_view_04_audit_categories.php`):
- Dodaj index GiST na `objects.path` jeśli jeszcze nie istnieje (sprawdzić ORM XML; potrzebny dla performance MOVE z subtree).
- Dodaj FK constraint na `category_attribute_groups.category_object_id` → `objects.id` ON DELETE CASCADE (obecnie brak — junction zostawiałby orphans przy delete kategorii).
- Stworzenie `category_attribute_groups_audit` table (DH Auditor robi auto-discover po enable w yaml — sprawdź czy nie potrzebna manual migration).

**Backfill:** dla istniejących kategorii bez audit history — N/A (audit zaczyna od dziś).

### 4.3 Listenery / event subscribers

1. **`CategoryPathBuilder`** w `apps/api/src/Catalog/Infrastructure/Doctrine/EventListener/CategoryPathBuilder.php`:
   - Trigger: `prePersist` na `CatalogObject` z `kind=category`.
   - Logic: jeśli `path` NULL i `parent_id` set → `path = parent.path . '.' . code`. Jeśli `parent_id` NULL → `path = code`.
   - Side effects: brak.
   - Tenant filter: aktywny (parent musi być z tego samego tenanta — sprawdzane w voter).

2. **`CategoryDeleteGuard`** w `apps/api/src/Catalog/Infrastructure/Doctrine/EventListener/CategoryDeleteGuard.php`:
   - Trigger: `preRemove` na `CatalogObject` z `kind=category`.
   - Logic: count descendants (`WHERE path <@ $thisPath AND id != $thisId`) + count children objects (`WHERE parent_id = $thisId`). Jeśli >0 → throw `HttpException(409)` z RFC 7807 detail.
   - Side effects: blokuje cascade z Doctrine.

3. **`CategoryAttributeGroupAuditTagger`** — N/A (DH Auditor handles automatically po enable w yaml).

4. **Deferred (NIE w VIEW-04):**
   - Subtree path rebuild listener przy zmianie code → realized as **application service** `RecomputeCategorySubtreePathsService` (wywoływany ręcznie z PATCH controller, nie listener — bo wymaga dostępu do DBAL transaction).
   - Schema rev bumper — backlog.

### 4.4 Permissions / RBAC

Generic `CatalogObjectVoter` już covers wszystkie operacje (READ/WRITE/DELETE) dla kind=category. Dodatkowych voterów nie potrzeba — voter mapuje wszystkie kindy.

Macierz ról × operacji:

| Operation | admin | editor | viewer |
|-----------|-------|--------|--------|
| GET /api/categories (list) | ✓ | ✓ | ✓ |
| GET /api/categories/{id} | ✓ | ✓ | ✓ |
| POST /api/categories | ✓ | ✓ | ✗ |
| PATCH /api/categories/{id} | ✓ | ✓ | ✗ |
| DELETE /api/categories/{id} | ✓ | ✗ | ✗ |
| PATCH /api/categories/{id}/move | ✓ | ✓ | ✗ |
| Declare/detach/reorder group | ✓ | ✓ | ✗ |
| GET effective-groups, usage, attribute_groups | ✓ | ✓ | ✓ |

Audit log entries: każda operacja write/delete/move/declare/detach/reorder pisze audit row dla `CategoryAttributeGroup` (po dh_auditor enable).

### 4.5 Provenance

**N/A** — kategorie to model schema, nie ObjectValues. `provenance` field dotyczy `object_values` (manual/import/agent/integration). Na CategoryAttributeGroup junction → audit log via dh_auditor wystarcza (kto + kiedy + diff).

### 4.6 Worker / async

**N/A** — wszystkie operacje są synchroniczne, request-response.
- MOVE subtree rebuild — single DBAL UPDATE w transakcji (nie batch handler).
- Declare/Detach — pojedynczy junction insert/delete.
- Performance: MOVE 50 descendants ~50ms (test na seed 1000 kategorii potwierdzi).

### 4.7 Real-time (Mercure)

**N/A** w VIEW-04. Mercure publishing dla CategoryAttributeGroup mutacji jest poza scope (kandydat na epik 0.11 hardening — wszystkie modeling mutacje przez Mercure topics).

---

## 5. Sub-tasks (checklist)

### Backend
- [ ] Migracja Doctrine: `Version{ts}_view_04_audit_categories.php` — index GiST na objects.path (jeśli brak), FK CASCADE na category_attribute_groups.category_object_id, audit tables dla CategoryAttributeGroup.
- [ ] `CategoryPathBuilder` listener (prePersist).
- [ ] `CategoryDeleteGuard` listener (preRemove).
- [ ] `CategoryAttributeGroupController` (4 routes: POST/DELETE/PATCH/GET).
- [ ] `CategoryAttributeGroupRepository` interface + Doctrine impl.
- [ ] `MoveCategoryService` application service + `RecomputeCategorySubtreePathsService` helper.
- [ ] `CategoryMoveController` (PATCH /api/categories/{id}/move).
- [ ] `CategoryUsageController` (GET /api/categories/{id}/usage).
- [ ] Extend `EffectiveAttributeGroupResolver` z source tracking.
- [ ] Extend `CategoryEffectiveGroupsController` response shape o `source` field per group.
- [ ] dh_auditor.yaml: enable `App\Catalog\Domain\Entity\CategoryAttributeGroup` (CatalogObject deferred).
- [ ] Tests: `CategoryAttributeGroupApiTest`, `CategoryMoveApiTest`, extend `CategoriesApiTest`, `CategoryPathBuilderTest`, `MoveCategoryServiceTest`, `CategoryAttributeGroupReorderTest`.
- [ ] PHPStan max → 0 errors.
- [ ] PHPUnit + ApiTestCase → wszystkie zielone.
- [ ] OpenAPI snapshot regen (`docs/api-spec/v0.json`).

### Frontend
- [ ] `lib/category-icons.ts` z `CATEGORY_ICONS` array.
- [ ] `<CategoryTree>` component (recursive, a11y, dot indicators, instances count).
- [ ] `<CategoryDetailPanel>` component (Card 1 z header + declared + inherited).
- [ ] `<EffectivePreviewCard>` component (Card 2 violet z FormPreviewRows + MOCK CTA).
- [ ] `<FormPreviewRow>` component.
- [ ] `<CategoryPathPreview>` component (live ltree path).
- [ ] `<DeclareAttributeGroupDialog>` (search + checkbox list, parallel POST per picked).
- [ ] `<ObjectTypeFilterDropdown>` (Select Refine z built-in).
- [ ] `<MoveCategoryDialog>` (tree picker z disabled subtree + preview path).
- [ ] Rebuild `features/catalog/categories/list.tsx` jako split-layout.
- [ ] Nowy `features/catalog/categories/new.tsx` (wzorzec attributes/new.tsx).
- [ ] Nowy `features/catalog/categories/show.tsx` (wzorzec attributes/show.tsx, edit + danger zone + audit MOCK).
- [ ] Register routes w `App.tsx` Refine resources.
- [ ] i18n keys ~55 sztuk w `pl/common.json` + `en/common.json`.
- [ ] TypeScript noEmit → 0 errors.
- [ ] Biome strict → 0 errors.
- [ ] Vite build → success.
- [ ] axe-core scan → 0 serious/critical violations.

### E2E + integration
- [ ] `apps/admin/e2e/categories-modeling.spec.ts` (11 scenariuszy z sekcji testów).
- [ ] Reset rate-limiter cache przed local Playwright run.

### Testy non-functional
- [ ] EXPLAIN ANALYZE każdego nowego query (`/effective-groups` z source, `/usage`, MOVE update).
- [ ] k6 raport p95 endpointu `/effective-groups` na seed 50k SKU + 500 kategorii — <300ms.
- [ ] Memory worker: dla MOVE 50 descendants peak <50MB.
- [ ] Bundle size FE delta: <50KB gzip (Vite build report).
- [ ] Lighthouse: performance ≥85, a11y =100, best-practices ≥90.

### Dokumentacja
- [ ] Update `agent/current_status.md` z VIEW-04 progress.
- [ ] Update `agent/lessons.md` po merge (per-ticket sekcja).
- [ ] OpenAPI snapshot `docs/api-spec/v0.json` zaktualizowany.

### Manual smoke (operator)
- [ ] Login → /modeling/categories → wybierz Service → kliknij Ortopedę → assert detail panel + effective preview.
- [ ] Declare grupę → assert pojawiła się w Declared directly + zniknęła z Inherited (jeśli była).
- [ ] Detach → wróciła do Inherited (jeśli ancestor deklaruje).
- [ ] Create kategorię → preview ltree path → submit → assert w drzewie.
- [ ] Edit nazwy → save → assert w drzewie i detail panel.
- [ ] Move → assert nowa ścieżka + descendants follow.
- [ ] Delete leaf → 204 → znika z drzewa.
- [ ] Delete z descendants → 409 → komunikat.

---

## 6. Acceptance criteria — funkcjonalne

- Pixel-perfect zgodne z mockupem `groups-categories.jsx:255–423` (split layout, Tailwind klasy, paddingi, copy, badges) — Figma diff <2%.
- Klik na node w drzewie → detail panel renderuje declared + inherited + effective preview dla aktualnego target ObjectType.
- Zmiana target ObjectType (dropdown) → przeładowanie detail panelu z nowymi declared/inherited/effective dla nowego targetu.
- Declare grupy → 201 → grupa pojawia się w Declared directly + znika z Inherited (jeśli była dziedziczona — co znaczy że teraz overrride localnie).
- Detach grupy → 204 → znika z Declared, wraca do Inherited (jeśli ancestor deklaruje).
- Create kategorii → 201 → redirect do `/modeling/categories?selected=<id>` → kategoria w drzewie z auto-computed path.
- Edit nazwy/icony → PATCH → invalidate cache → drzewo i detail update.
- Move kategorii → PATCH /move → drzewo update z nową strukturą, descendants paths zaktualizowane.
- Delete pustej kategorii (no descendants, no objects) → 204 → redirect do listy → znika z drzewa.
- Delete z descendants → 409 z polskim komunikatem w UI.
- Empty/loading/error states zaobserwowalne (skeleton, empty messages, toast errors).
- i18n PL/EN przełącza się — wszystkie copy zlokalizowane.

---

## 7. Acceptance criteria — non-functional (TWARDE GATES, NIENEGOCJOWALNE)

- **Performance**: p95 `/api/categories/{id}/effective-groups` <300ms na seed 50k SKU + 500 kategorii (k6 raport w PR).
- **N+1 query check**: EXPLAIN ANALYZE każdego nowego query w PR description, zero N+1 (effective-groups + source = 1 query dla object_type_groups, 1 query dla category_attribute_groups subtree, 1 query dla group attributes — total 3, niezależnie od głębokości drzewa).
- **Indeksy**: GiST na `objects.path` (potwierdzony lub dodany w migracji), btree na `category_attribute_groups (category_object_id, target_object_type_id)` (już jest, lin. 11 ORM XML).
- **Pagination**: N/A (listy małe — declared <20, effective <30, kategorie <500).
- **Memory** (MOVE subtree): peak <50MB przy 50 descendants. Nie używa Doctrine `flush()` w pętli, tylko jeden `executeStatement` DBAL.
- **Bundle size FE**: Δ <50KB gzip (Vite build report w PR).
- **Lighthouse**: performance ≥85, a11y =100, best-practices ≥90.
- **PHPStan max**: 0 errors.
- **Biome strict**: 0 errors.
- **PHPUnit coverage**: ≥80% nowej logiki domenowej (CategoryPathBuilder, MoveCategoryService, CategoryDeleteGuard, EffectiveAttributeGroupResolver source tracking).
- **ApiTestCase**: każdy nowy endpoint ma test 401 + 403 + 404 + walidacja + happy path.
- **Playwright E2E**: 11 scenariuszy zielonych (lista w sekcji 8).
- **axe-core**: 0 violations serious/critical na list + new + show + 4 dialogach.
- **composer audit + pnpm audit**: 0 high/critical.
- **Multi-tenancy**: cross-tenant read test = 0 wyników (test w `CategoryAttributeGroupApiTest`).
- **RBAC**: voter test dla każdej roli (admin/editor/viewer) na każdym endpointzie.
- **Audit log**: write/update/delete na `CategoryAttributeGroup` pisze entry (sprawdzić w `category_attribute_groups_audit` po teście).
- **Provenance**: N/A (junction table, nie object_value).
- **i18n coverage**: wszystkie nowe klucze obecne w `pl/common.json` i `en/common.json`.
- **OpenAPI snapshot**: `docs/api-spec/v0.json` zaktualizowany.

---

## 8. Smoke-test scenariusze (manualne, dla operatora)

1. Login `admin@demo.localhost / changeme` → `/modeling/categories`.
2. Sprawdź header: caption "drzewo ltree · target Service" + dropdown Service/Product/Asset + CTA "+ Nowa kategoria".
3. Kliknij dowolną kategorię w drzewie (np. Lekarz → Chirurg → Ortopeda).
4. Sprawdź detail panel: header z ikoną + ltree path + counter, sekcja "Declared directly", sekcja "Inherited from parents" z `↪ source` badges, Card "Effective preview" z FormPreviewRows.
5. DevTools Network: GET `/api/categories/{id}/attribute_groups?targetObjectTypeKind=service` → 200, GET `/api/categories/{id}/effective-groups?objectTypeKind=service` → 200, GET `/api/categories/{id}/usage` → 200.
6. Kliknij "+ Declare group" → dialog → wpisz "cennik" w search → zaznacz grupę → Submit → DevTools Network: POST `/api/categories/{id}/attribute_groups` → 201 → grupa pojawia się w Declared directly.
7. Kliknij trash przy declared grupie → confirmation → DELETE → 204 → grupa znika.
8. Zmień target ObjectType na "Product" → detail panel reload z nowymi declared/inherited/effective.
9. Kliknij "+ Nowa kategoria" → wypełnij code "test", parent="Chirurg" → live preview pokazuje `service.lekarz.chirurg.test` → Submit → POST `/api/categories` → 201 → redirect do `/modeling/categories?selected=<newId>` → "test" w drzewie.
10. Kliknij ikonkę edycji przy kategorii w drzewie (lub URL `/modeling/categories/test`) → strona Show → zmień nazwę PL → dirty bar pokazuje "1 pole zmienione" → Save → PATCH `/api/categories/{id}` → 200 → toast success.
11. W Show strona → Move button → dialog tree picker → wybierz nowy parent → Submit → PATCH `/api/categories/{id}/move` → 200 → drzewo update.
12. W Show strona → Delete button → potwierdzenie → DELETE → jeśli leaf: 204 → redirect; jeśli z descendants: 409 → komunikat w danger zone.
13. Sprawdź multi-tenancy: zaloguj się jako drugi tenant → `/modeling/categories` → BRAK kategorii z tenant 1.
14. DevTools Console: brak czerwonych errorów (warningi OK).

---

## 9. Edge cases / poza zakresem

**Świadomie poza zakresem (deferred):**
- **„+ Create test object" CTA wired** → MOCK badge w VIEW-04. Wired wymaga wizard'a tworzenia obiektu pod kategorią z preselected ObjectType + parent — duży feature dla Faza 1.
- **Drag-and-drop reorder w drzewie kategorii** (Move via DnD) → MoveCategoryDialog z parent picker jako MVP. DnD kandydat na VIEW-04b follow-up.
- **DnD reorder declared groups** → edit-position dialog z input number jako MVP. PATCH endpoint istnieje od VIEW-04.
- **Schema rev bumper + display "v1.0.0-rc.4 · model schema rev 47" w stopce** → backlog kandydat na epik 0.11 hardening.
- **Audit trail wired w show page** → MOCK badge `<MockBadge>` z tooltipem "Wymaga włączenia audytu na CatalogObject (potencjalne interakcje z search indexer + Mercure publisher — wymaga test harness)". Aktywujemy w dedicated audit ticket.
- **Object Type Custom kindy** → wyłączone feature flagiem (zgodnie z ADR-009 — custom kindy w Fazie 2).
- **Mercure publishing** dla mutacji CategoryAttributeGroup → poza scope (kandydat na epik 0.11).

**Edge cases pokryte:**
- Cycle detection w MOVE (parent pod własnym potomkiem → 422).
- Cross-tenant move/declare (404 dla obcego tenanta).
- Duplicate junction (POST tej samej (category, group, target) → 200 idempotent no-op).
- Detach system group (analog do `AttachObjectTypeAttributeGroupController`) → 403 jeśli `is_system_group=true`.
- Delete kategorii z descendants → 409 z polskim komunikatem.
- Delete kategorii z objects (parent_id matches) → 409 z `instanceCount` w detail.
- Empty state w drzewie (no categories) → CTA "Utwórz pierwszą".
- Empty state w detail panelu (no selection) → "← Wybierz kategorię".
- Empty state declared (root + brak deklaracji) → italic "— brak własnych grup, dziedziczy wszystko".

**Edge cases na później (z linkiem do follow-up):**
- Bulk move (multiple categories naraz) — backlog.
- Bulk declare group na N kategoriach — backlog.
- Inheritance preview dla custom ObjectType kindów — gdy custom kindy odblokowane (Faza 2).

---

## 10. Powiązane ADR / dokumenty

- **ADR-009** (`Project Plan/01-architektura-pim.md`): ObjectType jako koncept pierwszej klasy + sugar paths `/api/categories`. VIEW-04 nie zmienia ADR-009.
- **ADR-012** (proponowany): AttributeGroup jako first-class entity + dziedziczenie przez Category × ObjectType. VIEW-04 implementuje frontowe spojrzenie. Po merge VIEW-04 przejdzie status z „proponowany" na „accepted" (do potwierdzenia po smoke teście).
- **`agent/current_status.md`**: dopisz sekcję `## 2026-05-03: VIEW-04 view-first marathon — modeling/categories`.
- **`agent/lessons.md`**: per-ticket sekcja `## Lessons z VIEW-04` po merge (lessons o ltree subtree update, dh_auditor caveats, Refine custom mutations w detail panelu).

---

**Estymacja:** 18-22h (BE 8h + FE 8h + tests/quality gates 4h + smoke/cleanup 2h).

**Branch:** `feat/view-04-modeling-categories`
**Issue:** #TBD (open via `gh issue create`)
**PR:** #TBD (squash + delete-branch po CI green)
