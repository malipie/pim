# VIEW-07 — Produkty: edycja + tworzenie (relayout, inline edit, duplikat)

> Epik: UI-02 · Status: open · Estymacja: 8–12h · Priorytet: must-have
> Mockup: `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/produkty/detail-view.jsx` + screenshot operatora (priorytetowy)

## 1. Kontekst i cel widoku

Operator dostarczył screenshot widoku edycji produktu (Czujnik X-200) wraz z plikiem prototypu `detail-view.jsx`. **Screenshot ma priorytet** nad mockupem — makieta była aktualizowana po stronie prototypu.

Stan obecny w PIM:
- `/products/new` (`apps/admin/src/features/catalog/products/create.tsx`) renderuje 3-step `CreateProductWizard`.
- `/products/:id/edit` (`apps/admin/src/features/catalog/products/edit.tsx`) — osobna prosta formulka (SKU/name/brand/description przez `ProductForm`).
- `/products/:id` (`apps/admin/src/features/catalog/products/show.tsx`) — sticky header + 5 zakładek (attributes/variants/media/relationships/history). Atrybuty przez `DetailDynamicForm` z autosave 3s. Layout odbiega od mockupu (brak completeness ringu w dużym formacie, brak provenance per pole, brak locale/channel switcherów, brak grup collapsible w stylu AttrGroupCard).

Cel:
1. Jeden widok edycji pixel-perfect z mockupu (zakładka Atrybuty + relayout zakładki Warianty w stylu AttrGroupCard). Pozostałe zakładki (Multimedia/Powiązania/Historia) zostają jako mocki bez zmian wizualnych.
2. `/products/new` używa tego samego komponentu w trybie `create` (zamiast wizarda).
3. Inline edit globalny: jeden przycisk „Edytuj"/"Zapisz zmiany" w prawym górnym rogu — toggle dla całego body.
4. Duplikuj jako klik z defaultami → POST `/api/products/{id}/duplicate` → redirect.
5. Podgląd jako mock placeholder.
6. Locale (PL/EN/DE/CS) i Channel (Shopify/BaseLinker/Allegro) jako dropdowny zamiast pasków tabów.

## 2. Mockup / źródło designu

- **Plik JSX referencyjny**: `Zrodla/Front_Claude_Design/design_handoff_modelowanie/src/produkty/detail-view.jsx` (linie 1–370). `CompletenessRing` (l. 7–23), `AttrRow` (l. 25–61), `AttrGroupCard` (l. 63–94), `ProductDetail` (l. 96–370).
- **Screenshot operatora (nadrzędny)**: czujnik X-200 — sticky header z back/breadcrumb/Podgląd/Duplikuj/⋯/Zapisz, awatar 72×72 + nazwa + completeness ring 60%, pasek tabów z PL/EN/DE/CS + Shopify/BaseLinker/Allegro po prawej, sekcje Identyfikacja/Marketing/Specyfikacje techniczne/Logistyka/Cennik/Audyt jako collapsible cards, sidebar z Status publikacji + Warianty + Efektywny model + Agent sugestie, footer „Dodaj grupę atrybutów ad-hoc".
- **Powiązane widoki niewchodzące w scope**: lista produktów (`list-view.jsx` → już zrobione w VIEW-05), strona Multimedia (epik UI-05, mock placeholder zostaje), strona Powiązania (Faza 1), strona Historia (audit-log endpoint to follow-up).
- **Pixel-perfect binding**: Tailwind klasy + paddingi + copy 1:1. Adaptacje shadcn/Radix (Select dla dropdownów, Sheet dla modali) dozwolone — wizualny rezultat ≤2% pixel mismatch.

## 3. Zakres frontend (FE)

### 3.1 Routing (`apps/admin/src/App.tsx`)

- `/products/new` → `<ProductCreatePage>` (przepisana, używa `ProductDetailPage mode="create"`).
- `/products/:id/edit` → `<Navigate to="/products/:id" replace />` (back-compat redirect).
- `/products/:id` → `<ProductShowPage>` (przepisana, używa `ProductDetailPage mode="edit">`).
- Refine resource `products`: `edit` field zostaje na `/products/:id` (resource lookup tylko, no longer routed).

### 3.2 Komponenty (lista płaska)

| Plik | Akcja | Opis |
|---|---|---|
| `features/catalog/products/components/product-detail-page.tsx` | NOWY | Główny komponent widoku. Props: `mode: 'edit' \| 'create'`, `productId?: string`. Sticky header + tabs + body 2-col (lewa = active tab, prawa = sidebar). Stan globalny `isEditing` + `dirtyFields: Map<string, unknown>`. |
| `features/catalog/products/components/attr-group-card.tsx` | NOWY | Collapsible card per AttributeGroup. Header: ikona + nazwa + filledCount/total + pasek progresu + chevron. Body: lista `AttrRow`. |
| `features/catalog/products/components/attr-row.tsx` | NOWY | Wiersz atrybutu (label + value/input + provenance badge). W `isEditing && !isLocked` → input/textarea. W readonly → text z locale-badge gdy lokalizowany. Dirty tracking via `onChange`. |
| `features/catalog/products/components/locale-channel-toolbar.tsx` | NOWY | Dwa shadcn `Select`: locale (PL/EN/DE/CS) i channel (Shopify/BaseLinker/Allegro). Stan w parent. |
| `features/catalog/products/components/save-toggle-button.tsx` | NOWY | Globalny przycisk Edytuj↔Zapisz. W trybie create przycisk = „Utwórz produkt". W edit gdy `dirtyFields.size === 0` przycisk Zapisz disabled. |
| `features/catalog/products/components/duplicate-button.tsx` | NOWY | Klik → POST `/api/products/{id}/duplicate` z defaults `{ with_categories: true, with_assets: false, with_relations: false }` → toast „Tworzę kopię…" → redirect na `/products/{newId}`. |
| `features/catalog/products/components/preview-button.tsx` | NOWY | Mock. Klik → toast „Podgląd produktu — funkcja w przygotowaniu". Disabled w trybie create. |
| `features/catalog/products/components/sync-status-card.tsx` | NOWY | Sidebar card „Status publikacji" — 3 kanały (status dot + note typu „opublikowany 14:08" / „sync co 15 min" / „brak mapowania kategorii") + przycisk „Wymuś synchronizację" (mock disabled). |
| `features/catalog/products/components/variants-list-card.tsx` | NOWY | Sidebar card „Warianty" — lista wariantów master produktu (`GET /api/products?master_object_id={id}` lub embedded w GET /products/:id) z SKU + axis + status dot + przycisk „Nowy wariant". |
| `features/catalog/products/components/effective-model-card.tsx` | NOWY | Sidebar card „Efektywny model" — lista grup z source typu „ObjectType: Product" / „Kat: Czujniki ind." / „system" + lock badge dla system. |
| `features/catalog/products/components/agent-suggestions-card.tsx` | REUSE | Już istnieje. Tweak treść + gradient violet zgodnie z mockupem. |
| `features/catalog/products/components/completeness-ring.tsx` | REUSE | Już istnieje. Zmień default size=72 (mockup) lub przekaż przez prop. |
| `components/catalog/variants-tab.tsx` | PRZEBUDOWA | Body taba: na górze lista wariantów jako `AttrGroupCard` (każdy collapsible z atrybutami osi + status), pod listą zachowany kreator (axes editor + matrix generator). |
| `features/catalog/products/show.tsx` | PRZEPISZ | Renderuje `<ProductDetailPage mode="edit">`. Usuń stary multi-tab markup. |
| `features/catalog/products/create.tsx` | PRZEPISZ | Renderuje `<ProductDetailPage mode="create">`. Usuń `CreateProductWizard` import + Stepper UI. |
| `features/catalog/products/edit.tsx` | USUŃ | Funkcjonalność wchłonięta przez `show.tsx` w trybie inline edit. |
| `features/catalog/products/form.tsx` | USUŃ | Używana wyłącznie przez `edit.tsx` (do skasowania). |
| `components/catalog/create-product-wizard.tsx` | USUŃ | Cały plik + ewentualne testy. |
| `components/catalog/detail-dynamic-form.tsx` | DEPRECATE | Logika przeniesiona do `<AttrGroupCard>` + `<AttrRow>` z dirty-tracking globalnym. Plik usuwamy po migracji. |
| `components/catalog/detail-group-nav.tsx` | DEPRECATE | Sidebar nawigacja per group nie istnieje w mockupie — sekcje są collapsible w głównym body. Plik usuwamy. |
| `components/catalog/detail-sidebar.tsx` | DEPRECATE | Zastąpiony 4 nowymi kartami sidebar (`sync-status-card`, `variants-list-card`, `effective-model-card`, `agent-suggestions-card`). Plik usuwamy. |

### 3.3 State management

- `useShow` (Refine) dla mode="edit" → `product` + `effectiveAttributeGroups` (drugi fetch z `/api/products/{id}/effective-attribute-groups`).
- Local state w `ProductDetailPage`:
  - `isEditing: boolean` — globalny toggle.
  - `dirtyFields: Record<string, unknown>` — collected via `<AttrRow onChange>`.
  - `locale: 'pl' \| 'en' \| 'de' \| 'cs'` — default `'pl'`.
  - `channel: 'shopify' \| 'baselinker' \| 'allegro' \| null` — default `null` (no channel scope).
  - `expandedGroups: Set<string>` — default `new Set(allGroupIds)` (wszystkie expanded zgodnie ze screenshotem).
  - `activeTab: 'attributes' \| 'variants' \| 'media' \| 'relationships' \| 'history'`.
- Klik „Zapisz zmiany": `useUpdate` z body `{ attributes: dirtyFields, locale, channel }` → invalidate `['products', id]` + `['products', id, 'effective-attribute-groups']` → reset `dirtyFields` + flip `isEditing=false` + toast.
- Tryb create: brak `useShow`, lokalny state jak edit ale `productId=null`. Klik „Utwórz produkt": POST `/api/products` z `{ code: dirtyFields.sku, objectTypeId, attributes: dirtyFields }` → redirect na `/products/{id}`.

### 3.4 Struktura sekcji widoku

1. **Sticky header** (sekcja 1, top z=20):
   1.1. Top bar: back arrow, breadcrumb (Produkty / kategoria / SKU), akcje (Podgląd, Duplikuj, ⋯ menu, separator, Edytuj/Zapisz).
   1.2. Product info row: avatar 72×72 (placeholder ▣ lub thumbnail), SKU mono + brand + status dot, h1 nazwa, kategoria chips + „Wariantowy · N wariantów" / „Bez wariantów", CompletenessRing 72px po prawej.
   1.3. Tabs bar: 5 tabów (Atrybuty/Multimedia/Powiązania/Historia/Warianty) z liczkami + po prawej `LocaleChannelToolbar` (2 dropdowny).
2. **Body — 2 kolumny** (`grid grid-cols-[1fr_320px] gap-5`):
   2.1. Lewa: aktywny tab.
       - Atrybuty: lista `AttrGroupCard` per group + footer „Dodaj grupę atrybutów ad-hoc" (mock).
       - Multimedia: stub (bez zmian).
       - Powiązania: stub (bez zmian).
       - Historia: stub (bez zmian).
       - Warianty: lista wariantów jako `AttrGroupCard` + kreator pod listą.
   2.2. Prawa: 4 sidebar cards (Status publikacji, Warianty, Efektywny model, Agent sugestie).

### 3.4a Mapping element-po-elemencie z prototypu

| Mockup element | Komponent produkcyjny | Klasy Tailwind / copy |
|---|---|---|
| Header glass | `<header>` w `ProductDetailPage` | `sticky top-0 z-20 -mx-6 -mt-6 border-b border-line glass-strong px-6 pt-5 pb-3` |
| Back button | shadcn `Button` variant=ghost size=icon | `h-9 w-9 rounded-xl bg-white soft-shadow` + `<ArrowLeft className="size-4">` |
| Breadcrumb | `<nav>` zwykły | `text-[12px] text-zinc-500` z separator `mx-1.5 text-zinc-300` |
| Akcje (Podgląd/Duplikuj/⋯) | `<Button variant="ghost" size="sm">` | `h-9 px-3 rounded-xl bg-white soft-shadow text-[12.5px]` |
| Separator między akcjami a Save | `<span>` | `h-6 w-px bg-zinc-200 mx-1` |
| Save toggle | `<Button>` (primary) | `h-9 px-4 rounded-xl bg-zinc-900 text-white text-[12.5px] font-medium hover:bg-zinc-800` |
| Avatar produktu | `<div>` placeholder | `h-[72px] w-[72px] rounded-2xl bg-white soft-shadow grid place-items-center text-[34px]` |
| SKU mono | `<span>` | `font-mono text-[12px] text-zinc-500` |
| Status dot | `<span>` | `h-1.5 w-1.5 rounded-full bg-emerald-500` (lub `bg-zinc-300`) |
| H1 name | `<h1>` | `font-display text-[26px] font-semibold tracking-tight leading-tight mt-1` |
| Category chips | `<span>` | `text-[11px] px-2 py-1 rounded-full bg-white soft-shadow text-zinc-700 font-medium` |
| Completeness ring | `<CompletenessRing pct={x} size={72}>` | reused |
| Tab button (active) | `<button>` | `h-[44px] px-3.5 text-[13px] font-medium tracking-tight text-zinc-900` + bottom indicator `absolute left-0 right-0 -bottom-px h-[2px] bg-zinc-900 rounded-t` |
| Tab badge (count) | `<span>` | `text-[10.5px] num px-1.5 py-0.5 rounded bg-zinc-900 text-white` (active) lub `bg-zinc-100 text-zinc-500` (inactive) |
| Locale dropdown | shadcn `<Select>` | `h-9 rounded-xl bg-white soft-shadow text-[12px] uppercase font-mono` |
| Channel dropdown | shadcn `<Select>` | `h-9 rounded-xl bg-white soft-shadow text-[12px] font-medium` |
| Section card | `<Card>` shadcn | `overflow-hidden rounded-2xl border border-line bg-surface soft-shadow` |
| Section header | `<button>` w card | `w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-zinc-50/60` |
| Section icon | `<span>` | `h-9 w-9 rounded-xl bg-zinc-100 grid place-items-center text-[16px]` |
| Section title | `<div>` | `text-[14px] font-semibold tracking-tight` |
| System badge | `<span>` | `text-[10px] font-medium px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-500` |
| Filled progress text | `<div>` | `text-[11.5px] text-zinc-500 num mt-0.5` typu „5 / 5 wypełnione · 100%" |
| Progress bar | `<div>` | `h-1 w-20 bg-zinc-100 rounded-full` z fill `bg-zinc-900` |
| AttrRow | `<div>` | `grid grid-cols-[180px_1fr_auto] items-start gap-3 py-2.5 px-3 rounded-xl hover:bg-white group` |
| AttrRow label | `<div>` | `text-[13px] text-zinc-600 font-medium pt-1.5` |
| AttrRow locale chip | `<span>` | `text-[9px] font-mono px-1 py-0.5 rounded bg-zinc-100 text-zinc-500 uppercase` |
| AttrRow value (readonly) | `<div>` | `text-[13.5px] px-3 py-2 rounded-xl border border-transparent` |
| AttrRow input (editing) | shadcn `<Input>` | `w-full px-3 py-2 rounded-xl bg-white border border-zinc-200 text-[13.5px]` |
| Provenance badge | `<ProvenanceBadge>` | reused (manual/import/integration/system + custom: `sap`, `ręczne`) |
| Footer „Dodaj grupę ad-hoc" | `<button>` | `w-full mt-2 h-12 rounded-2xl border border-dashed border-zinc-300 text-zinc-500 hover:bg-white inline-flex items-center justify-center gap-2 text-[13px] font-medium` (mock toast) |
| Sidebar card | `<Card>` | `rounded-2xl border border-line bg-surface p-5 soft-shadow` |
| Sidebar status row | `<li>` | `flex items-center gap-2.5` z dot `h-2 w-2 rounded-full` |
| Sidebar variant row | `<li>` | `flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-zinc-50` |
| Sidebar wiersz model | `<li>` | `flex items-center gap-2 px-2 py-1 rounded-lg` z lock icon `text-zinc-300` |
| Agent sugestie card | `<Card>` | `bg-gradient-to-br from-violet-50 to-white` z headerem `h-7 w-7 rounded-xl bg-violet-100 text-violet-700 grid place-items-center` |

### 3.5 i18n (`apps/admin/public/locales/{pl,en}/translation.json`)

Klucze nowe (z polskimi defaultami; angielski w en.json):
- `products.detail.actions.preview` — „Podgląd"
- `products.detail.actions.duplicate` — „Duplikuj"
- `products.detail.actions.more` — „Więcej"
- `products.detail.actions.edit` — „Edytuj"
- `products.detail.actions.save` — „Zapisz zmiany"
- `products.detail.actions.create` — „Utwórz produkt"
- `products.detail.actions.cancel` — „Anuluj"
- `products.detail.preview.unavailable` — „Podgląd produktu — funkcja w przygotowaniu"
- `products.detail.duplicate.pending` — „Tworzę kopię…"
- `products.detail.duplicate.success` — „Utworzono kopię produktu"
- `products.detail.duplicate.failed` — „Nie udało się utworzyć kopii"
- `products.detail.save.success` — „Zapisano zmiany"
- `products.detail.save.failed` — „Nie udało się zapisać"
- `products.detail.tabs.attributes` — „Atrybuty"
- `products.detail.tabs.multimedia` — „Multimedia"
- `products.detail.tabs.relations` — „Powiązania"
- `products.detail.tabs.history` — „Historia"
- `products.detail.tabs.variants` — „Warianty"
- `products.detail.locale.label` — „Język"
- `products.detail.channel.label` — „Kanał"
- `products.detail.channel.none` — „Wszystkie kanały"
- `products.detail.completeness.aria` — „Kompletność {{pct}}%"
- `products.detail.has_variants` — „Wariantowy · {{count}} wariantów"
- `products.detail.no_variants` — „Bez wariantów"
- `products.detail.add_attribute_group` — „Dodaj grupę atrybutów ad-hoc"
- `products.detail.add_attribute_group.unavailable` — „Custom grupy ad-hoc — follow-up"
- `products.detail.group.system` — „system"
- `products.detail.group.filled` — „{{filled}} / {{total}} wypełnione · {{pct}}%"
- `products.detail.field.locked` — „Zablokowane"
- `products.detail.sidebar.publication_status` — „Status publikacji"
- `products.detail.sidebar.force_sync` — „Wymuś synchronizację"
- `products.detail.sidebar.force_sync.unavailable` — „Wymuś synchronizację — follow-up Faza 1"
- `products.detail.sidebar.variants` — „Warianty"
- `products.detail.sidebar.variants.new` — „Nowy wariant"
- `products.detail.sidebar.effective_model` — „Efektywny model"
- `products.detail.sidebar.effective_model.intro` — „Atrybuty obiektu pochodzą z:"
- `products.detail.sidebar.effective_model.see_in_modeling` — „Zobacz w Modelowanie →"
- `products.detail.sidebar.agent` — „Agent · sugestie"
- `products.detail.sidebar.agent.translate` — „Wygeneruj opis EN z atrybutów technicznych"
- `products.detail.sidebar.agent.hs_code` — „Uzupełnij kod HS dla pozycji celnej"
- `products.detail.sidebar.agent.accessories` — „Dodaj 3 sugerowane akcesoria"
- `products.detail.create.subtitle` — „Wypełnij obowiązkowe pola: SKU, nazwa, typ obiektu"
- `products.detail.create.placeholder.sku` — „TST-001"
- `products.detail.create.placeholder.name` — „Nazwa produktu"
- `products.detail.empty_state` — „Brak danych do wyświetlenia"

### 3.6 a11y

- ARIA: `<header role="banner">`, `<nav aria-label="Zakładki produktu">`, `<aside aria-label="Panel boczny produktu">`, sticky toolbar nie blokuje skip-link.
- Save toggle: `aria-pressed={isEditing}`, label switches Edytuj↔Zapisz (`aria-label`).
- Tabs: `role="tablist"`, każdy tab `role="tab" aria-selected={...}`, panel `role="tabpanel"`.
- Locale/channel dropdowns: shadcn Select daje `combobox` z `aria-expanded`.
- Inputs w AttrRow: `<label htmlFor>` + `<Input id={...}>`. Kbd `Tab` przechodzi sekwencyjnie po polach gdy isEditing.
- Provenance badge: `aria-label="Provenance: ręczne"`.
- Focus ring widoczny na każdym interaktywnym elemencie.
- axe-core: 0 violations serious/critical na nowym widoku.

### 3.7 Locale toolbar (per-product)

Operator chce 4 lokale (PL/EN/DE/CS) dostępne dla zawartości produktu. UI admina pozostaje PL/EN (i18next keys). DE/CS są wartościami `ObjectValue.locale` które przyjmuje backend już dziś.

Dropdown z 4 opcjami. Stan w parent. Zmiana locale → re-fetch nie potrzebny (wartości już są w `attributesIndexed` per locale jeśli zindexowane). Dla atrybutów lokalizowanych: wyświetla `value[locale]` (jeśli JSONB) lub kontaktuje API `?locale={l}`.

### 3.8 Empty / loading / error states

- **Loading**: `<p className="text-sm text-muted-foreground">{t('app.loading')}</p>` — całe body.
- **Error fetch produktu**: `<p role="alert" className="text-sm text-rose-600">` z retry button.
- **Brak attribute groups**: pokaż empty state z linkiem do Modelowania (CTA „Skonfiguruj grupy atrybutów").
- **Empty wariants tab**: pokaż „Ten produkt nie ma jeszcze wariantów" + link do kreatora.
- **Save failed**: toast + `dirtyFields` zachowane (nie reset), `isEditing` zostaje true.
- **Duplicate failed (409)**: toast „SKU już istnieje, spróbuj ponownie".

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Body | Response | Permissions | Notes |
|---|---|---|---|---|---|
| POST | `/api/products` | `{ code, objectTypeId, attributes? }` | 201 `{ id, code, ... }` | `IS_AUTHENTICATED_FULLY` | Już istnieje (CatalogObjectInput) |
| GET | `/api/products/{id}` | — | 200 `CatalogObject` | `IS_AUTHENTICATED_FULLY` | Już istnieje |
| PATCH | `/api/products/{id}` | `{ attributes: {<code>: <value>} }` (merge-patch) | 200 | `IS_AUTHENTICATED_FULLY` | Już istnieje (DetailDynamicForm). Zweryfikuj że >1 atrybut na request działa bez N+1 |
| POST | `/api/products/{id}/duplicate` | `{ sku?, with_categories?, with_assets?, with_relations? }` | 201 `{ id, code, source_id, kind }` | `IS_AUTHENTICATED_FULLY` | Już istnieje (`DuplicateProductController`). Test ApiTestCase do dodania |
| GET | `/api/products/{id}/effective-attribute-groups` | — | 200 `{ groups: [...] }` | `IS_AUTHENTICATED_FULLY` | Już istnieje |
| GET | `/api/products/{id}/audit-log?limit=N` | — | 200 `{ entries: [...] }` | `IS_AUTHENTICATED_FULLY` | Stub (zostaje mock w tym tickecie) |
| GET | `/api/products/{id}/channels-status` | — | 200 `{ aggregate, channels: [] }` | `IS_AUTHENTICATED_FULLY` | Już istnieje |

Errors w RFC 7807 (`Content-Type: application/problem+json`). Cursor pagination dla list >1000 (n/a tutaj).

### 4.2 Encje / schema / migracje

Brak nowych encji. Wszystko schema-ready:
- `CatalogObject` (`apps/api/src/Catalog/Domain/Entity/CatalogObject.php`).
- `ObjectValue` z `provenance` enum + `locale` field.
- `ObjectKind::Product`.
- Brak migracji Doctrine.

### 4.3 Listenery / event subscribers

Bez zmian. Istniejące:
- `TenantAssignmentListener` — auto-set `tenant_id` na save.
- Listener który aktualizuje `attributesIndexed` po save `ObjectValue` (już działa per UI-02.5).
- `dh_auditor` (jeśli skonfigurowany) — audit log dla zmian.

### 4.4 Permissions / RBAC

- `DuplicateProductController` używa `#[IsGranted('IS_AUTHENTICATED_FULLY')]`. Zostaje, brak voter w MVP.
- Test ApiTestCase: 401 (unauthenticated) + 200 (admin).
- Multi-tenancy: cross-tenant duplicate test = 404 (źródło niewidoczne dla obcego tenant).

### 4.5 Provenance

`DuplicateProductController` resetuje provenance na `Manual` dla wszystkich klonowanych `ObjectValue` (już zrobione w istniejącym kodzie).

PATCH `attributes` z UI również ustawia `Manual` (server-side default w state processorze).

### 4.6 Worker / async

Brak workerów w tym tickecie.

### 4.7 Real-time (Mercure)

Brak w tym tickecie. Save → invalidate React Query cache (no SSE).

## 5. Sub-tasks (checklist)

### Backend
- [ ] Test ApiTestCase `DuplicateProductControllerTest`: 401, 200 happy path (z auto-gen SKU), 200 z explicit SKU, 404 (nieproductowy), 409 (collision), 200 multi-tenancy (cross-tenant 404).
- [ ] Test ApiTestCase `PatchProductMultiAttributeTest`: PATCH z 5 atrybutami w jednym request → 200, wszystkie zapisane.
- [ ] EXPLAIN ANALYZE `GET /api/products/{id}` na seedzie 50k SKU — w PR description, brak N+1.
- [ ] OpenAPI snapshot regen po zmianie endpointów (jeśli jakaś zmiana w spec).

### Frontend
- [ ] `<ProductDetailPage>` z modes edit/create.
- [ ] `<AttrGroupCard>` collapsible.
- [ ] `<AttrRow>` z dirty tracking + provenance.
- [ ] `<LocaleChannelToolbar>` (2 dropdowny).
- [ ] `<SaveToggleButton>` z disabled state.
- [ ] `<DuplicateButton>` jednego klika.
- [ ] `<PreviewButton>` mock toast.
- [ ] `<SyncStatusCard>` sidebar.
- [ ] `<VariantsListCard>` sidebar.
- [ ] `<EffectiveModelCard>` sidebar.
- [ ] `<AgentSuggestionsCard>` sidebar (relayout).
- [ ] Reuse `<CompletenessRing>` (size=72).
- [ ] Reuse `<ProvenanceBadge>`.
- [ ] Przebudowa `<VariantsTab>` body (lista + kreator).
- [ ] Przepisz `show.tsx` → `<ProductDetailPage mode="edit">`.
- [ ] Przepisz `create.tsx` → `<ProductDetailPage mode="create">`.
- [ ] Usuń `edit.tsx`, `form.tsx`, `create-product-wizard.tsx`, `detail-dynamic-form.tsx`, `detail-group-nav.tsx`, `detail-sidebar.tsx`.
- [ ] App.tsx: `/products/:id/edit` → redirect.
- [ ] i18n keys (pl + en).

### E2E + integration
- [ ] Playwright `view-07.spec.ts`: happy path (login → /products/new → utwórz → redirect → edytuj → zapisz → duplikuj → redirect na -COPY-1 → preview toast).
- [ ] Edge: klik Edytuj bez zmian → klik Zapisz → no PATCH wywołane (dirtyFields empty).
- [ ] axe-core scan na `/products/new` i `/products/:id`.

### Testy non-functional
- [ ] Lighthouse perf ≥85, a11y =100 dla `/products/:id`.
- [ ] Bundle size delta <50KB gzip.
- [ ] Memory peak <128MB przy edit + 100 atrybutów.

### Dokumentacja
- [ ] PR body z sekcjami Summary / Backend / Frontend / Quality gates / Test plan / Świadome odejścia.
- [ ] `agent/current_status.md` — sekcja `## 2026-05-04: VIEW-07 view-first marathon`.
- [ ] `agent/lessons.md` — lessons z VIEW-07 (dirtyFields collection, dropdown vs pasek tabs, mock-toast pattern).

### Manual smoke (operator)
- [ ] Login → /products/new → formatka się ładuje (nie wizard).
- [ ] Wpisanie SKU + Nazwa + ObjectType → klik „Utwórz produkt" → redirect na /products/{id}.
- [ ] Klik Edytuj → pola odblokowane, zmień Marka → klik Zapisz → toast OK.
- [ ] Locale dropdown PL→EN → wartości lokalizowane się przełączają.
- [ ] Channel dropdown Shopify→Allegro → wartości channel-scopowane się przełączają.
- [ ] Klik Duplikuj → toast → redirect na nowy produkt z `-COPY-1`.
- [ ] Klik Podgląd → toast „Funkcja w przygotowaniu".
- [ ] Tab Warianty → każdy wariant collapsible AttrGroupCard.
- [ ] DevTools Console: 0 czerwonych errorów.

## 6. Acceptance criteria — funkcjonalne

- Wygląda pixel-perfect jak screenshot (Figma/screenshot diff <2%).
- `/products/new` ładuje formatkę edycji (nie wizard 3-step).
- `/products/:id/edit` redirect na `/products/:id`.
- Inline edit globalny: klik Edytuj → wszystkie pola edytowalne (poza locked/system) → zmiana wartości + klik Zapisz → PATCH 200 → tryb readonly.
- Duplikuj jeden klik → toast pending → redirect.
- Podgląd jeden klik → toast „w przygotowaniu".
- Locale dropdown przełącza widoczne wartości lokalizowane.
- Channel dropdown przełącza wartości channel-scopowane (lub none).
- Tab Warianty pokazuje listę wariantów jako AttrGroupCard.
- Pozostałe taby (Multimedia/Powiązania/Historia) pozostają jako mocki.
- i18n PL/EN przełącza copy admina.

## 7. Acceptance criteria — non-functional (TWARDE GATES)

- Performance: p95 GET `/api/products/{id}` <300ms na seed 50k SKU. EXPLAIN ANALYZE w PR.
- Indeksy: brak nowych zapytań → brak nowych indeksów.
- Pagination: nie dotyczy widoku detail.
- Memory peak <128MB w edit z 100 polami.
- Bundle size delta <50KB gzip (Vite build report).
- Lighthouse: perf ≥85, a11y =100, best-practices ≥90.
- PHPStan max: 0 errors.
- Biome strict: 0 errors.
- PHPUnit coverage: ≥80% nowej logiki domenowej (DuplicateProductController już ma testy lub dorzucamy).
- ApiTestCase: 401 + 403 + 404 + walidacja + happy path dla każdego nowego/zmienionego endpointu.
- Playwright E2E: view-07.spec.ts zielony.
- axe-core: 0 violations serious/critical.
- composer audit + pnpm audit: 0 high/critical.
- Multi-tenancy: cross-tenant duplicate test = 404.
- RBAC: voter test (n/a — `IS_AUTHENTICATED_FULLY` only).
- Audit log: write/update/delete pisze entry (`dh_auditor` aktywny).
- Provenance: każdy write ma provenance set (`Manual` default).
- i18n coverage: wszystkie nowe klucze obecne w `pl.json` + `en.json` (lub `defaultValue` fallback).
- OpenAPI snapshot: zaktualizowany jeśli zmiana w spec.

## 8. Smoke-test scenariusze (manualne, dla operatora)

1. Login `admin@demo.localhost / changeme` przez `https://pim.localhost`.
2. Przejście na `/products/new` → renderuje się formatka edycji z pustym SKU/Nazwą + sticky header z przyciskiem „Utwórz produkt".
3. Wypełnij SKU=`TEST-001`, Nazwa PL=`Produkt testowy`, klik „Utwórz produkt" → DevTools Network: POST `/api/products` 201 → redirect na `/products/{id}` → completeness ring pokazuje %.
4. Klik „Edytuj" → przycisk staje się „Zapisz zmiany" + wszystkie pola odblokowane (poza Audyt locked) → zmień Marka na „Festo" → klik Zapisz → DevTools Network: PATCH 200, body `{ attributes: { brand: "Festo" } }` → toast „Zapisano zmiany" → tryb readonly.
5. Locale dropdown: PL→EN → wartości lokalizowane (Nazwa EN) widoczne, niełokalizowane (SKU, Marka) bez zmian.
6. Channel dropdown: Shopify→Allegro → wartości channel-scopowane się przełączają (jeśli są).
7. Klik „Duplikuj" → toast „Tworzę kopię…" → DevTools Network: POST `/api/products/{id}/duplicate` 201 → redirect na `/products/{newId}` z SKU `TEST-001-COPY-1`.
8. Klik „Podgląd" → toast „Podgląd produktu — funkcja w przygotowaniu".
9. Tab „Warianty" (na produkcie wariantowym z fixtures, np. SKU=`TST-001` po reset DB) → każdy wariant w stylu AttrGroupCard (collapsible, expanded by default).
10. Refresh strony → stan persistuje, tryb readonly (Edytuj button widoczny).
11. Multi-tenancy: drugi tenant nie widzi produktów pierwszego (smoke przez switch + fail GET).
12. DevTools Console: 0 czerwonych errorów na każdym kroku.

## 9. Edge cases / poza zakresem

### Świadomie poza zakresem (deferred)
- Multimedia, Powiązania, Historia — zostają jako mocki bez zmian wizualnych. Pixel-perfect alignment = follow-up VIEW-08+ (Multimedia związane z UI-05 DAM).
- Per-product `enabledLocales` config — w MVP wszystkie produkty mają taki sam zestaw `[pl,en,de,cs]` z fixtures. Per-tenant lub per-product override = follow-up.
- Pełny i18n admina dla DE/CS — content-locale only w tym tickecie. UI admina zostaje PL/EN.
- Channel mapping wartości (publish do Shopify/BaseLinker/Allegro) — read-only sidebar już jest, write = Faza 1 (Shopify epik 0.9).
- Audit log endpoint — zakładka Historia mock; real audit log = follow-up (`/api/products/{id}/audit-log` produkcyjny).
- Real preview produktu — mock toast w tym tickecie. Real preview wymaga renderera per kanał (Faza 1).
- Force sync button — disabled mock, działa po Faza 1 epik 09.
- „Nowy wariant" w sidebarze — link do kreatora w tabie Warianty (już istnieje), bez nowego endpointu.
- „Dodaj grupę atrybutów ad-hoc" — mock toast „Custom grupy ad-hoc — follow-up".

### Pokryte edge cases
- Klik Edytuj bez zmian → klik Zapisz → no PATCH wywołane (dirtyFields empty, button disabled).
- 409 SKU collision → toast z czytelnym komunikatem.
- 401 (token expired) → redirect na /login.
- 404 (produkt usunięty w innej zakładce) → redirect na /products + toast „Produkt nie istnieje".
- Dirty state warning przed nawigacją (Refine `warnWhenUnsavedChanges: true`).

## 10. Powiązane ADR / dokumenty

- ADR-009 (ObjectType jako koncept pierwszej klasy) — bez zmian, ten ticket konsumuje istniejący model.
- `Project Plan/02-plan-projektu-pim.md` — checkbox VIEW-07 do zaznaczenia po merge.
- `Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md` — sekcja 5 (detail page) referencja.
- `agent/current_status.md` — sekcja `## 2026-05-04: VIEW-07 view-first marathon`.
- `agent/lessons.md` — lessons z VIEW-07 (dirtyFields collection, dropdown vs pasek tabs, mock toast pattern, jak zlikwidować wizard bez utraty UX-u).
- `Project Plan/01-architektura-pim.md` — bez nowego ADR (decyzje produktowe nie architektoniczne).

---

**Wykonawca**: agent (claude-opus-4-7) · **Estymacja**: 8–12h · **PR**: TBD po implementacji.
