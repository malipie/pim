# Modelowanie — backlog do oprogramowania

> Baza pod kolejne GitHub tickety. Każda pozycja zaznaczona w kodzie komentarzem `MOCK:` (przy bloku UI z hardkodowanymi danymi) lub `TODO(handoff)` (przy luce w wiringu).
>
> **Konwencja**: pojedyncza pozycja → osobny issue na GitHubie z labelami `frontend` (+ ewentualnie `backend`), `must-have` / `optional`, `UI`, `epik-UI-03` (lub przeniesione do nowego epika gdy tematyka backendu się rozjedzie).
>
> Stan na 2026-05-01 po merge'u #357. Aktualna implementacja modelowania jest w ~60% gotowa — ten plik wylicza luki.

## Object Types

### Frontend + nowy endpoint backendowy
- [ ] **NewObjectType wizard (4 kroki)** — Identyfikacja → Atrybuty → Ustawienia → Podsumowanie. Backend: `POST /api/object_types` (dziś brak; istnieje tylko `CreateCustomObjectTypeDialog` minimal). Estymacja: L (FE 8-10h, BE 6-8h).
- [ ] **Edit ObjectType** (icon, color, hierarchical / hasVariants / isAbstract toggle) — `PATCH /api/object_types/{id}` (dziś brak).
- [ ] **Drag-reorder grup atrybutów** w detailu — `PATCH /api/object_types/{id}/groups/order` z dnd-kit po stronie FE.
- [ ] **Audit log section** w detailu (last 5 changes — who/when/diff) — `GET /api/object_types/{id}/audit-log`. Pliki FE: `apps/admin/src/features/catalog/object-types/show.tsx` (sekcja oznaczona MOCK).
- [ ] **Icon picker** (lucide subset) + **color picker** (akcent palette) w wizardzie + edit form.

## Attributes

### Frontend + nowy endpoint backendowy
- [ ] **AttributeValuesView** (full-page editor select / multi-select values) — locale tabs, color swatches, default/deprecated toggles, drag-reorder. Endpointy: `GET/POST/PATCH/DELETE /api/attributes/{id}/values`. Wymaga **nowej encji `attribute_values`** (czy istnieje? Sprint 0 tabele do potwierdzenia). Pliki FE: `apps/admin/src/features/catalog/attributes/values.tsx` (placeholder mock zamknięty w UI-03.2). Estymacja: L (FE 12-15h, BE 14-18h).
- [ ] **Kolumna "Wartości"** violet badge z licznikiem dla select / multi-select — wymaga endpointu `/values` (count). Plik FE: `apps/admin/src/features/catalog/attributes/list.tsx` (komponent `ValuesBadge` ma TODO comment "—" zamiast prawdziwego count).
- [ ] **NewAttribute form** — `POST /api/attributes` (dziś brak; lista oznacza `attributes.write_deferred_note`).
- [ ] **Edit Attribute metadata** — `PATCH /api/attributes/{id}`.
- [ ] **MigrationImpactModal** — `POST /api/attributes/{code}/migrate?dryRun=true` zwraca `{ affectedInstances, suggestedMapping, sampleDiff }`. Następnie `POST /api/attributes/{code}/migrate` commituje. Strona migrate-type istnieje (UI-08.12 #267) — ale brakuje pełnego dryRun preview UI per handoff.
- [ ] **DELETE Attribute** z guardrails (rejects if used in groups) — `DELETE /api/attributes/{id}`.

## Attribute Groups (ADR-012 first-class entity ⭐)

### Frontend + nowy endpoint backendowy
- [ ] **Sticky detail header** z close + icon + name + edit button per handoff.
- [ ] **Sekcja "Atrybuty in this group"** w detailu z:
  - Drag-reorder — `PATCH /api/attribute_groups/{id}/attributes/order`.
  - **AddAttributeFromLibraryModal** — picker existing attributes z multi-select + filter po type. Endpoint: `POST /api/attribute_groups/{id}/attributes`.
  - **CreateAttributeInGroupModal** — skrócona forma New Attribute + auto-attach do current grupy (jeden POST + atomic).
- [ ] **NewAttributeGroup form** — `POST /api/attribute_groups`. Strona `attribute-groups/create.tsx` istnieje minimum, brak icon/color picker handoff style.
- [ ] **Edit Group metadata** — `PATCH /api/attribute_groups/{id}`.
- [ ] **Visibility rules section** (`visible_when` JSON rules) z preview "test" działający na sample produktach.

### Wymaga decyzji architektonicznej
- [ ] **`visible_when` rule format** — JsonLogic vs custom JSON-DSL vs hand-rolled rule builder (jak w handoff prototype). Wpływa na schema `attribute_groups.visible_when` JSONB column oraz UI rule builder. Zob. handoff CLAUDE.md "Open architecture decisions" pkt 5. Wymaga ADR.

## Categories

### Frontend + nowy endpoint backendowy
- [ ] **Two-column layout 320px tree + flex detail** per handoff. Aktualnie `categories/list.tsx` to one-column tree, `categories/show.tsx` to standalone page. Trzeba przebudować na sticky detail panel obok drzewa.
- [ ] **ObjectType filter chips** (Service / Product / Asset) w headerze listy — wymaga listing categories po `objectType.kind`.
- [ ] **Drag-and-drop tree** — move subtree to new parent. Endpoint: `PATCH /api/categories/{id}/move` (z cascade ltree update). Estymacja: L (FE 8-10h dnd-kit + BE 8-10h ltree cascade).
- [ ] **Attach/detach groups at node** — `POST /api/categories/{id}/groups/{groupCode}` + DELETE. Aktualnie effective-groups read-only.
- [ ] **Declared directly section** w detailu z edit/delete buttons per group (różnicować od inherited).
- [ ] **Inherited from parents section** read-only z arrow → source category (link do parent category).

### Już zrealizowane (UI-08.14 #269 — nie blokuje)
- [x] **Effective preview card** z provenance — endpoint `/api/categories/{id}/effective-groups` istnieje, FE renderuje listę z sortowaniem auto/system/manual.

## Cross-cutting (Modelowanie)

- [ ] **schema_rev counter** w stopce / topbar (`model schema rev 47`) — wymaga global schema rev tracking po stronie BE (kolumna `schema_rev` na każdej tabeli modelowej + bump na każdą migrację) i FE indicator.
- [ ] **Audit log per encja** (last 5 changes w detailu) — wspólny endpoint `GET /api/{resource}/{id}/audit-log` dla object_types / attributes / attribute_groups / categories.
- [ ] **LocaleTabs** dla pól multilingual (label, description) — handoff specyfikuje `LocaleTabs` z primary `pl` non-removable + chips do dodawania innych z `LOCALE_LIBRARY` (14 langs). Aktualnie mamy plain JSONB inputs.

## Zależności od innych epików

- Epik 0.6 (Admin UI core CRUD) — bazowe formularze już zrealizowane, ale POST/PATCH dla resource modeli jeszcze nie wszystkie.
- Epik 0.11 (Hardening + analityka) — schema_rev + audit_log endpoints to kandydaty do tego epiku.
- Faza 2 (Agent layer) — visible_when rule builder mógłby być pisany przez agenta (chat-driven) dla nietechnicznych użytkowników, ale to dalsze rozważanie.
