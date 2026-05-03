# [VIEW-03c] Modelowanie · Attribute Groups — visible_when rule editor popup

> Follow-up do VIEW-03b (#404, PR #405). Branch: `feat/view-03c-visible-when-editor`.
> Stan na 2026-05-03. Single deferral z VIEW-03b — edytor reguły `visible_when` jako osobny popup.
> Źródło prawdy designu: `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/modeling/groups-categories.jsx` sekcja `AttributeGroupDetail` rules editing flow.

---

## 1. Kontekst i cel widoku

W VIEW-03b (#405) Visibility rules Card renderuje się warunkowo (gdy `members.some(m => m.visible_when !== null)`) i pokazuje agregowane reguły dla grupy plus 2 test cards (VISIBLE / HIDDEN). Każda reguła ma „Edit rule" link, **aktualnie disabled** z tooltipem „Edytor reguły — VIEW-03c".

Ten ticket dostarcza ten edytor: popup który pozwala operatorowi:

1. Wybrać atrybut źródłowy (`field`) z listy member-attributów grupy + wbudowanych pól (`created_at`, `updated_at`, `created_by`, `updated_by`).
2. Wybrać operator (`equals` MVP; `in`, `gt`, `lt`, `between`, `is_set`, `is_not_set` jako extended w MVP wystarcza tylko `equals`).
3. Wprowadzić wartość (`value`) — typ inputa zależny od typu wybranego pola (`text` → input, `select` → dropdown z opcjami atrybutu, `boolean` → switch, `number` → numeric input, `date`/`datetime` → date-picker).
4. Zapisać regułę → PATCH na junction (`/api/attribute_groups/{id}/attributes/{attributeId}` z `visibleWhen: {field, operator, value}`).
5. Usunąć regułę (przycisk „Usuń regułę" w popupie → PATCH z `visibleWhen: null`).

Aktualnie aktywować popup mogą:
- **Klik „Edit rule"** (Visibility rules Card) — edycja istniejącej reguły.
- **Klik puste pole „brak reguły widoczności"** w wierszu atrybutu (Card „Attributes in this group") — dodanie reguły. *Aktualnie ten chip jest read-only.*

Powiązane: ADR-009 (`visible_when` na junction `AttributeGroupAttribute.visibleWhen` jako JSONB), VIEW-03b (#404, #405).

Epik nadrzędny: **UI-08** Modelowanie pixel-perfect.

## 2. Mockup / źródło designu

Source: `groups-categories.jsx` — niestety sam edytor nie jest jawnie zaimplementowany w prototypie operatora (mockup pokazuje TYLKO już-zapisane reguły). Operator zatwierdza implementację zgodną z istniejącym wzorcem `<EditRuleDialog>` z VIEW-02 (np. visible_when w attribute Show flagów) — to fallback design, nie pixel-perfect prototype binding.

### Layout popupu (proponowany — operator zatwierdza w trakcie review):

- **shadcn `<Dialog>`** z `<DialogContent className="max-w-[560px] gap-0 p-0">`.
- Header (`px-7 pt-6 pb-4 border-b zinc-100 flex items-start gap-3`):
  - violet eye icon `<div className="h-10 w-10 rounded-2xl bg-violet-100 grid place-items-center text-violet-700 shrink-0"><Eye/></div>`.
  - title `Edytor reguły widoczności` + desc `Atrybut "{X}" będzie widoczny tylko gdy spełniony jest warunek poniżej.`.
  - close button.
- Body (`px-7 py-5 space-y-4`):
  - **Field** select — opcje: lista member-attributes grupy (filtrowana — bez aktualnie edytowanego) + 4 wbudowane pola.
  - **Operator** select — MVP `equals` only (later `in`, `gt`, `lt`, `between`, `is_set`, `is_not_set`).
  - **Value** input — type-driven:
    - `text`/`richtext` → `<Input>`.
    - `number`/`metric`/`price` → `<Input type="number">`.
    - `boolean` → 2-button toggle (`true` / `false`).
    - `select`/`multiselect` → `<select>` z `GET /api/attributes/{code}/options`.
    - `date`/`datetime` → `<input type="date">`.
- Footer (`px-7 py-4 border-t zinc-100 bg-zinc-50/60 flex justify-between`):
  - Left: Audit log info `<span className="font-mono">attribute_group.attribute.update</span>`.
  - Right: 3 buttons: `Usuń regułę` (text-rose-600, widoczny tylko gdy editing existing), `Anuluj`, `Zapisz` (zinc-900, disabled gdy field=='' || value=='').

## 3. Zakres frontend (FE)

### 3.1 Routing

Brak nowych tras — popup wywoływany z `<AttributeGroupShowPage>` (sekcja Visibility rules Card + sekcja Attributes in this group).

### 3.2 Komponenty (lista płaska)

#### Nowe:

- `<EditVisibleWhenDialog open onOpenChange groupId attributeId currentRule availableFields onSaved>` — popup edytor + remover. Mapping w `apps/admin/src/components/modeling/edit-visible-when-dialog.tsx`.

#### Reuse:

- `<Dialog>`, `<DialogContent>` (shadcn).
- `<Input>`, `<Label>`, `<Button>`.
- `useQuery` dla pobrania `/api/attributes/{code}/options` gdy field type jest `select`/`multiselect`.

### 3.3 Wpięcia do istniejących plików

- `apps/admin/src/features/catalog/attribute-groups/show.tsx`:
  - Sekcja **Visibility rules Card** — `Edit rule` link już istnieje jako disabled. Zamienić na aktywny przycisk → `setEditingRule({attributeId, currentRule})`.
  - Sekcja **Attributes in this group → SortableMemberRow** — chip „brak reguły widoczności" obecnie read-only span. Zamienić na klikalny `<button>` → `setEditingRule({attributeId: row.attribute.id, currentRule: null})`.
  - Dodać state `editingRule: {attributeId, currentRule} | null`.
  - Render `<EditVisibleWhenDialog>` przy końcu komponentu, wiązany z `editingRule`.
  - onSaved: zamknąć dialog + reload members.

### 3.4 State management

- Lokalny `editingRule` state w `<Editor>`.
- Mutacje:
  - `useUpdateGroupAttributeRule(groupId, attributeId)` — PATCH `/api/attribute_groups/{id}/attributes/{attributeId}` z `{visibleWhen}` (object lub null).
- Cache invalidations: `['attribute_groups', id, 'attributes']`.

### 3.5 i18n keys

```json
{
  "modeling.attributeGroups.rule_editor": {
    "title": "Edytor reguły widoczności",
    "desc_template": "Atrybut „{{attribute}}\" będzie widoczny tylko gdy spełniony jest warunek poniżej.",
    "field_label": "Pole źródłowe",
    "field_placeholder": "Wybierz atrybut z grupy lub pole wbudowane",
    "operator_label": "Operator",
    "value_label": "Wartość",
    "value_placeholder": "Wartość warunku",
    "save_action": "Zapisz regułę",
    "save_action_create": "Dodaj regułę",
    "remove_action": "Usuń regułę",
    "remove_confirm": "Usunąć regułę widoczności?",
    "audit_hint": "Audit log: attribute_group.attribute.update",
    "validation_required": "Wybierz pole i wprowadź wartość"
  }
}
```

## 4. Zakres backend (BE)

N/A — endpoint `PATCH /api/attribute_groups/{id}/attributes/{attributeId}` z `visibleWhen` payloadem już istnieje (UI-08, używany w VIEW-03 dla `isRequiredInGroup`). Sprawdzić w handlerze że akceptuje pole `visibleWhen` (tak — patrz `AttributeGroupMembershipController::updateMembership`).

## 5. Sub-tasks

- [ ] FE: nowy komponent `<EditVisibleWhenDialog>` z type-driven value input (text/number/boolean/select/date).
- [ ] FE: wpięcie w show.tsx — chip „brak reguły" klikalny + Edit rule aktywny.
- [ ] FE: hook do pobrania options dla select-typowanych pól (`GET /api/attributes/{code}/options`).
- [ ] FE: i18n keys (pl + en).
- [ ] FE: smoke — dodać/zedytować/usunąć regułę.
- [ ] PR + CI + merge.

## 6. Acceptance criteria — funkcjonalne

- Klik „Edit rule" na istniejącej regule otwiera popup z prefilled wartościami.
- Klik chip „brak reguły widoczności" w wierszu atrybutu otwiera popup w trybie create.
- Zapis: reguła pojawia się w Visibility rules Card po close popupu (bez F5).
- Usunięcie: reguła znika z Card; jeśli była ostatnia, Card znika.
- Type-driven value input: dla `boolean` 2-button toggle, dla `select` dropdown z opcjami, dla `date` natywny date-picker.

## 7. Acceptance criteria — non-functional

- TypeScript noEmit: 0 errors.
- Biome strict: 0 errors.
- Vite build: 0 errors.
- Bundle size delta <10KB gzip.
- Smoke: 3 cykle add/edit/remove na różnych typach atrybutów (text, select, boolean) działają end-to-end.

## 8. Edge cases / poza zakresem

- **Out of scope (deferred do follow-up jeśli kiedyś będzie potrzeba):**
  - Operator inny niż `equals` (`in`, `between`, `gt`, `lt`, `is_set`, `is_not_set`) — backend wspiera tylko `equals` w MVP, więc nie rzucamy się na multi-operator UX.
  - Multi-condition rules (`AND`/`OR`) — `visibleWhen` JSONB przyjmuje pojedynczy warunek w MVP.
  - Cross-group rules (atrybut w grupie X widoczny gdy atrybut w grupie Y ma wartość Z) — niedopuszczalne w MVP, walidacja BE.
- **In scope edge cases**:
  - Cykl: atrybut A `visible_when B`, atrybut B `visible_when A` → BE odrzuca z 422, FE pokazuje toast.
  - Pole źródłowe usunięte z grupy po zapisaniu reguły → UI pokazuje ostrzeżenie „Pole źródłowe nie istnieje w grupie" w Visibility rules Card.

## 9. Powiązane

- Parent: VIEW-03b (#404, PR #405).
- Predecessor: VIEW-03 (#375, PR #403).
- Epik: UI-08 Modelowanie pixel-perfect.
- ADR-009 (ObjectType first-class).
