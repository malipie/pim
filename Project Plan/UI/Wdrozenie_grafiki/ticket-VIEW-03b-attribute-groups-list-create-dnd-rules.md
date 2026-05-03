# [VIEW-03b] Modelowanie · Attribute Groups — list + create pixel-perfect + dnd-kit reorder + visibility rules card

> Follow-up do VIEW-03 (#375, PR #403). Branch: `feat/view-03b-attribute-groups-list-create-dnd-rules`.
> Stan na 2026-05-03. Cztery świadome odejścia z VIEW-03 zebrane w jeden ticket żeby domknąć VIEW-03 epikalnie.
> Źródło prawdy designu: `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/groups-categories.jsx`.

---

## 1. Kontekst i cel widoku

PR #403 (VIEW-03) dostarczył detail page + 4 popupy + reverse buttons na attribute create. Cztery sekcje pixel-perfect zostały świadomie odsunięte do follow-up żeby nie blokować shipowania funkcjonalnego flow:

1. **List page pixel-perfect rebuild** — aktualnie funkcjonalny shadcn Table; mockup `AttributeGroupsView` wymaga single Card z 2 sekcjami (System auto-attached + Business groups), search sticky-top, grid 6-col rows.
2. **Create page pixel-perfect rebuild** — aktualnie 3 sekcje + sidebar Preview/Następnie nie istnieje; mockup `NewAttributeGroupView` wymaga 8-swatch picker + 14-emoji icon picker + 3 SettingToggleRow + sidebar 320px Preview live + Następnie 3-step.
3. **dnd-kit drag-reorder w members** — aktualnie wiersze atrybutów renderują w kolejności `position` z BE; brak chwytania myszy. BE endpoint `POST /api/attribute_groups/{id}/attributes/reorder` istnieje od UI-08.
4. **Visibility rules Card** — warunkowo gdy `members[].visible_when` nie jest pusty; aktualnie `visible_when` jest tylko chip w wierszu, brak osobnej karty z agregowanym widokiem reguł + 2 testy pass/fail.

Epik nadrzędny: **UI-08** Modelowanie pixel-perfect.

## 2. Mockup / źródło designu

> **Pixel-perfect binding**: implementacja MUSI 1:1 odwzorować kod prototypu z `groups-categories.jsx` (sekcje wskazane niżej). Adaptacje stack-specific dozwolone (dnd-kit zamiast hand-rolled drag, shadcn primitive zamiast hand-rolled), ale wizualny rezultat <2% pixel mismatch.

### Szczegółowe odwołania:

- **List rebuild**: `AttributeGroupsView` (`groups-categories.jsx:3–52`) + `GroupRow` (`:54–80`).
- **Create rebuild**: `NewAttributeGroupView` (`:482–603`) — back button + header (h-14 color icon + caption + title live + description + 2 buttons) + grid `1fr+320px`.
- **dnd-kit members**: `AttributeGroupDetail` (`:134–186`) — drag handle wykorzystywany aktualnie tylko wizualnie, ma działać.
- **Visibility rules Card**: `AttributeGroupDetail` (`:188–215`) — Card warunkowo widoczna gdy `rules.length > 0`.

## 3. Zakres frontend (FE)

### 3.1 List page pixel-perfect rebuild

Plik: `apps/admin/src/features/catalog/attribute-groups/list.tsx`.

- Header: `<ModelingPageHeader>` z caption `{count} grup atrybutów` + violet badge `⭐ FIRST-CLASS ENTITY`, title `Attribute Groups`, description (Pimcore/Akeneo positioning), CTA `+ Nowa grupa`.
- Single `<Card>` z 2 sekcjami zamiast Tab/Filter:
  - **System (auto-attached)** — divider z lock badge + uppercase tracking-wider label.
  - **Business groups** — divider z uppercase tracking-wider label.
- Search input sticky-top w karcie (px-4 py-3 flex items-center gap-3, border-b zinc-100).
- Wiersze (`<GroupRowItem>`): grid 6-kolumn `[44px_1.6fr_1fr_120px_120px_28px]`, hover `bg-zinc-50/70`:
  - Col 1: ikona 9×9 rounded-xl `bg-{color}18 text-{color}` + emoji.
  - Col 2: nazwa + lock badge (gdy system) + violet visible_when chip (gdy `rules.length > 0`); description text-11.5px.
  - Col 3: code mono.
  - Col 4: `{N} atrybutów`.
  - Col 5: `{typesUsed} typy · {categoriesUsed} kat.`.
  - Col 6: chevron right.
- Klik wiersza → `/modeling/attribute-groups/{code}` (zachować obecną konwencję `:id` → `:code` zostawić jak jest).
- Empty state „Brak grup spełniających kryteria".

### 3.2 Create page pixel-perfect rebuild

Plik: `apps/admin/src/features/catalog/attribute-groups/create.tsx`.

- Back button góra (`Wstecz do Attribute Groups`).
- Header (`flex items-start justify-between gap-6 mb-6`):
  - Left: color icon 14×14 rounded-2xl text-24px live + stack (caption „Nowa Attribute Group" + title live `displayName` + description Pimcore/Akeneo).
  - Right: `Anuluj` + `Utwórz grupę` (zinc-900).
- Grid `1fr+320px`:
  - **Left Card** `p-6 space-y-6`:
    - Sekcja **Identyfikacja**: Code input mono + helper „Niezmienialny po utworzeniu" + LocaleTabsField label + Description textarea.
    - Sekcja **Wygląd** (grid 2-col):
      - `<ColorSwatchPicker>` z 8 swatchami `[#71717a #3b82f6 #8b5cf6 #10b981 #f59e0b #ef4444 #06b6d4 #ec4899]` h-9 w-9 rounded-xl, ring-2 ring-zinc-900 ring-offset-2 gdy selected.
      - `<GroupIconPicker>` z 14 ikonami `[📦 📐 🔧 ⚙️ 🛡️ 💧 🌡️ 🏗️ 📋 🎨 🔌 📡 🪛 🧰]` h-9 w-9 rounded-xl text-18px grid place-items-center, bg-zinc-900 white gdy selected, border zinc-200 hover bg-zinc-50 default.
    - Sekcja **Zachowanie**: 3 `<SettingToggleRow>`:
      - `Wymagana sekcja` desc „Grupa zawsze widoczna w formularzu" default false.
      - `Współdzielona` desc „Może być dołączona do wielu ObjectType" default true.
      - `Conditional visibility` desc „Pokaż grupę warunkowo (visible_when)" default false.
  - **Right aside** `space-y-3`:
    - Card `p-5` **Podgląd** (uppercase 11px tracking-wider mb-3): live color icon 10×10 + nazwa + code mono.
    - Card `p-5` **Następnie**: ul `1. Utwórz grupę` / `2. Dodaj atrybuty z biblioteki` / `3. Dołącz grupę do ObjectType`.

### 3.3 dnd-kit drag-reorder w members

Plik: `apps/admin/src/features/catalog/attribute-groups/show.tsx` (sekcja Card „Attributes in this group").

- Pakiety `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` są już w `package.json` (used in VIEW-02 values editor).
- Wrap listę w `<DndContext sensors collisionDetection onDragEnd>` + `<SortableContext items={memberIds} strategy={verticalListSortingStrategy}>`.
- Per-row `useSortable({id})` — wiązanie `attributes`/`listeners` na drag handle (`<GripVertical>`), `style={transform/transition}` na całym wierszu.
- onDragEnd — recompute pozycji z `arrayMove`, optimistic update lokalnego `members` state, POST `/api/attribute_groups/{id}/attributes/reorder` z `{order: [attributeCode1, attributeCode2, ...]}`.
- Rollback on 422: refetch `['attribute_groups', id, 'attributes']` z toastem error.

### 3.4 Visibility rules Card

Plik: `apps/admin/src/features/catalog/attribute-groups/show.tsx` — nowa Card pomiędzy „Attributes in this group" a „Where used".

- Warunkowo: render gdy `members.some(m => m.visible_when !== null)`.
- Card `p-6`:
  - title `VISIBILITY RULES` (uppercase 11px tracking-wider) + violet badge `visible_when` (uppercase 10.5px font-semibold px-1.5 py-0.5 rounded bg-violet-100 text-violet-700).
  - rounded-2xl border-violet-200 bg-violet-50/40 p-4 space-y-1: per row `flex items-center gap-3 py-1`:
    - `<span className="font-mono text-[12.5px] font-medium">{row.attribute.code}</span>`.
    - `<span className="text-[11.5px] text-zinc-500">visible_when</span>`.
    - `<span className="font-mono text-[12.5px] text-violet-700 bg-white px-2 py-0.5 rounded border border-violet-200">{rule.field}={String(rule.value)}</span>`.
    - `Edit rule` button ml-auto text-11.5px text-zinc-500 hover:text-zinc-900 → MVP: disabled tooltip „Funkcja w VIEW-03c".
  - mt-3 grid 2-col gap-3: 2 test cards
    - **test pass**: bg-emerald-50/40 border emerald-200, copy „Test: {expr} → VISIBLE".
    - **test fail**: bg-zinc-50 border zinc-200, copy „Test: {expr} → HIDDEN".

### 3.5 i18n

Dorzucamy do `pl.json` + `en.json`:

```json
{
  "modeling.attributeGroups.list_first_class_badge": "⭐ first-class entity",
  "modeling.attributeGroups.section_system_label": "System (auto-attached)",
  "modeling.attributeGroups.section_business_label": "Business groups",
  "modeling.attributeGroups.row_attrs_count": "{count} atrybutów",
  "modeling.attributeGroups.row_types_count": "{count} typy",
  "modeling.attributeGroups.row_categories_count": "{count} kat.",
  "modeling.attributeGroups.create.caption": "Nowa Attribute Group",
  "modeling.attributeGroups.create.title_default": "Nazwa grupy",
  "modeling.attributeGroups.appearance_title": "Wygląd",
  "modeling.attributeGroups.behavior_title": "Zachowanie",
  "modeling.attributeGroups.behavior_required_section_label": "Wymagana sekcja",
  "modeling.attributeGroups.behavior_required_section_desc": "Grupa zawsze widoczna w formularzu",
  "modeling.attributeGroups.behavior_shared_label": "Współdzielona",
  "modeling.attributeGroups.behavior_shared_desc": "Może być dołączona do wielu ObjectType",
  "modeling.attributeGroups.behavior_conditional_label": "Conditional visibility",
  "modeling.attributeGroups.behavior_conditional_desc": "Pokaż grupę warunkowo (visible_when)",
  "modeling.attributeGroups.next_card_title": "Następnie",
  "modeling.attributeGroups.next_step_1": "Utwórz grupę",
  "modeling.attributeGroups.next_step_2": "Dodaj atrybuty z biblioteki",
  "modeling.attributeGroups.next_step_3": "Dołącz grupę do ObjectType",
  "modeling.attributeGroups.preview_card_title": "Podgląd",
  "modeling.attributeGroups.rules_title": "Visibility rules",
  "modeling.attributeGroups.rules_visible_when_badge": "visible_when",
  "modeling.attributeGroups.rules_test_pass_status": "VISIBLE",
  "modeling.attributeGroups.rules_test_fail_status": "HIDDEN",
  "modeling.attributeGroups.rules_edit_action": "Edit rule"
}
```

## 4. Zakres backend (BE)

N/A — wszystkie endpointy (`reorder`, `bulk-attach`, `usage`, etc.) już istnieją.

## 5. Sub-tasks

- [ ] FE: list page rebuild per `AttributeGroupsView` mockup
- [ ] FE: create page rebuild per `NewAttributeGroupView` mockup
- [ ] FE: dorzucenie `<ColorSwatchPicker>` (8 swatchy) do `components/modeling/`
- [ ] FE: rozszerzenie `<IconPicker>` o 14 emoji ikon (lub osobny `<GroupIconPicker>`)
- [ ] FE: dnd-kit Sortable wiring w members list w show.tsx
- [ ] FE: optimistic reorder + rollback on 422
- [ ] FE: Visibility rules Card warunkowo + 2 test cards
- [ ] FE: i18n keys (pl + en)
- [ ] Quality gates: typecheck/lint/build, smoke
- [ ] PR + CI + merge

## 6. Acceptance criteria — funkcjonalne

- List page wygląda jak mockup `AttributeGroupsView` (side-by-side <2% pixel mismatch).
- Create page wygląda jak mockup `NewAttributeGroupView` z 8-swatch + 14-icon + sidebar.
- Drag-reorder działa end-to-end (drag → optimistic update → POST reorder → BE persists → no flicker).
- Visibility rules Card pojawia się wyłącznie gdy `members.some(m => m.visible_when !== null)` i pokazuje agregowane reguły.

## 7. Acceptance criteria — non-functional

- TypeScript noEmit: 0 errors.
- Biome strict: 0 errors.
- Vite build: 0 errors.
- Bundle size delta <30KB gzip (dnd-kit już w bundle).
- Smoke: drag 3 atrybutów w grupie, F5 → kolejność persistuje.

## 8. Edge cases / poza zakresem

- Świadomie poza zakresem:
  - Edytor reguły `visible_when` — VIEW-03c osobno (popup z field/operator/value picker).
  - URL identifier change `:id` → `:code` (per ticket VIEW-03 sekcja 3.1) — zostawiamy `:id` w MVP.
  - System grup w create (read-only seed) — nie tykamy.

## 9. Powiązane

- Parent: VIEW-03 (#375, PR #403).
- Epik: UI-08 Modelowanie pixel-perfect.
- ADR-009 (ObjectType first-class), proponowany ADR-012 (AttributeGroup as first-class).
