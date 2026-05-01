# Epik 02 — Produkty

## Status: 🟢 szczegół

> **Drugi flagowy epik UI** (po Modelowaniu) — codzienne narzędzie pracy Kasi (Catalog Manager), gdzie spędza ~60% czasu. Lista + detail + bulk + quick edit + Excel-like editing + warianty + saved views.
> **Decyzje brainstormingowe** zamknięte 2026-04-30 w sesji Senior PM. Plik zastępuje placeholder z `Project Plan/UI/epik-02-produkty.md`.

---

## 1. Cel epiku

Pełen workflow zarządzania katalogiem produktów (`ObjectType=product`) — *core PIM-u*. Operator (Kasia / Magda / Marcin dogfooding) musi w tej zakładce:

1. **Znaleźć** szybko produkt (search + filtry + saved views).
2. **Zmasowo zmodyfikować** wiele produktów (bulk actions + Excel-like inline edit + drag-fill).
3. **Edytować pojedynczy produkt** w detail view z dynamicznym formularzem dziedziczonym z Modelowania.
4. **Ocenić jakość katalogu** (completeness pasek per produkt, sync status agregat).
5. **Zarządzać wariantami** (master + variants z toggle flat/tree).
6. **Klonować** produkty (Duplicate z opcjami).
7. **Triggerować akcje na kanałach** (Publish per kanał z 3-dot menu lub inline ikon).

Kasia po MVP-Final ma w pełni samowystarczalny workflow w obrębie tej zakładki bez zaglądania do Modelowania (które używa raz na 1-2 tyg.).

## 2. Persony

| Persona | Rola w tej zakładce | Częstość |
|---|---|---|
| **Kasia, 32** (Catalog Manager) | Primary — codzienna edycja, bulk actions, import flow | ~60% czasu pracy |
| **Magda, 29** (Marketing) | Secondary — opisy SEO, kategorie, multi-locale content | ~30% czasu pracy |
| **Marcin (founder dogfooding)** | First user — IdoSell + Shopify migracja katalogu | własny sklep |
| **Tomasz** (Owner/CEO) | Sporadyczny — self-service edycja flagowych produktów, audit | rzadkie wejścia |

## 3. Brainstorming decisions snapshot (2026-04-30)

Wszystkie ustalenia z 2-falowego brainstormingu, które kierują design'em:

| Obszar | Decyzja |
|---|---|
| Excel-like editing | (b) Drag-fill + multi-cell select + copy-paste między komórkami. ~20-30h. |
| Filtry zaawansowane | (c) Hybrid — chip'y default + builder dla advanced |
| Related products rules engine | **Out of scope MVP.** Faza 1+ kandydat (epik 09). |
| Quick search | (a) Strict prefix/exact w MVP. Fuzzy → Faza 1, AI semantic → Faza 2 |
| Quick edit | (a) Single-attribute click-on-cell → popover → save |
| Status indicators w liście | **1 pasek completeness (flat formula 80/100=80%)** + **1 ikona sync agregat** + **inline ikony per kanał** (klikalne View on X) |
| Provenance / workflow / lock w liście | **Out of list, tylko w detail view** |
| Saved Views | **Solo only** (każdy user własne). Save: filter + sort + columns + page size |
| Sortowanie | Per kolumna click + multi-column Shift+click |
| Quick actions per row | 8 akcji w 3-dot menu (poniżej) + inline ikony channel (View on X) |
| Variants prezentacja | (c) **Toggle widok** — checkbox *„Show variants flat / Show as tree"* w toolbar |
| Bulk import/export | **Out of scope epiku 02.** W epiku 04 Publikacje (sub-tab Imports/Exports/Integracje) |
| AI assist (Cmd+K *„zaznacz 30 Festo i ustaw kategorię"*) | **Faza 2 data-ops agent.** W MVP tylko schema-add (Beta-Demo z epiku 08) |
| Empty state | CTA *„Dodaj pierwszy / Sklonuj z istniejącego"* — Import w Publikacje |
| Permissions | MVP brak gating, Faza 1 ADR-013 |
| Mobile | **Brak responsive**, scroll poziomy. Tomasz Dashboard (epik 01) jest mobile-friendly |

---

## 4. List view — główny ekran

### 4.1 Layout

```
┌─ Produkty ────────────────────────────────────────────────────────────────┐
│                                                                            │
│ [🔍 Search SKU/name/EAN]  [▼ Saved Views]  [+ New Product]  [⋮ Imports]  │
│                                                                            │
│ Filtry: [Brand: Festo ✕] [Completeness: <50% ✕] [+ Add filter] [Advanced] │
│                                                                            │
│ ☐ Show variants:  ◉ As tree  ○ Flat                                        │
│                                                                            │
│ ┌────────────────────────────────────────────────────────────────────────┐ │
│ │ ☐ │ 🖼  │ SKU       │ Name           │ Brand │ Compl. │Sync│ Channels │⋮│ │
│ ├────────────────────────────────────────────────────────────────────────┤ │
│ │ ☐ │ ▶ │ TST-001    │ Czujnik X-200  │ Festo │ ▓▓▓░░ 60%│ 🟢 │🟢🟢🔴   │⋮│ │
│ │ ☐ │ 🖼 │ ↳ TST-001-A│ X-200 PNP M12 │ Festo │ ▓▓▓▓░ 80%│ 🟢 │🟢🟢🟢   │⋮│ │
│ │ ☐ │ 🖼 │ ↳ TST-001-B│ X-200 NPN M8  │ Festo │ ▓▓░░░ 40%│ 🟡 │🟢🔴🟢   │⋮│ │
│ │ ☐ │ 🖼 │ TBT-002    │ Taboret 3-noż.│ ProTec│ ▓▓▓▓▓ 100%│🟢│🟢🟢🟢   │⋮│ │
│ │ ☐ │ 🖼 │ ZWR-003    │ Zawór hydr.   │ Bosch │ ▓░░░░ 20%│ 🔴 │🔴🔴⚪   │⋮│ │
│ │ ... │                                                                  │ │
│ └────────────────────────────────────────────────────────────────────────┘ │
│                                                                            │
│ Showing 1-50 of 1247 products       [‹ Prev] [Next ›]   Per page: [50 ▼] │
└────────────────────────────────────────────────────────────────────────────┘
```

**Toolbar (top to bottom):**
1. **Search bar** — strict prefix/exact po SKU / name / EAN. Meilisearch backend, latency <50ms.
2. **Saved Views dropdown** — *„Domyślny widok"* (default), *„Festo niski completeness"*, *„Czerwone produkty"*, *„Ostatnio edytowane"* (system templates) + user-saved.
3. **[+ New Product]** button — prawym-górny róg, otwiera Create wizard.
4. **[⋮ Imports menu]** — link do epiku 04 (Publikacje → Imports/Exports). Tylko shortcut, faktyczny widok jest w innej zakładce.
5. **Filter chips** — aktywne filtry jako chip'y z X (kasują filter). Default chip'y: Brand / Family / Categories / Completeness / Status.
6. **[+ Add filter]** — dropdown z popularnymi atrybutami.
7. **[Advanced]** — otwiera advanced filter builder (modal lub right Sheet) z access do *wszystkich* atrybutów ObjectType.
8. **Variants toggle** — radio *„As tree"* (default) vs *„Flat"*.
9. **Bulk actions toolbar** — pojawia się po zaznaczeniu pierwszego rekordu (sticky overlay).

### 4.2 Kolumny — defaultowe

| Kolumna | Width | Edytowalna w Excel-like? |
|---|---|---|
| Checkbox (bulk select) | 40px | nie |
| Thumbnail (mini, 32×32) | 50px | nie (klik → DAM) |
| SKU | 140px | tak |
| Name (z localizable badge jeśli >1 locale) | 280px | tak |
| Brand | 120px | tak (relation picker) |
| Family / ObjectType | 120px | nie (zmiana family = duża operacja) |
| Categories (tags compact) | 200px | nie (drag-drop tylko w detail) |
| **Completeness** (pasek + procent) | 100px | nie (computed) |
| **Sync** (1 ikona agregat: 🟢/🟡/🔴) | 60px | nie (computed) |
| **Channels inline** (per-kanał ikony klikalne) | 120px | nie (klik → View on X) |
| Status (Enabled toggle) | 80px | tak (single-click toggle) |
| 3-dot menu | 40px | nie |

**Configurable** — klient w *„View settings"* dodaje / usuwa kolumny. Można dodać każdy atrybut z ObjectType.

### 4.3 Excel-like editing (na konfigurowalnych kolumnach)

**Scope (b) — drag-fill + multi-cell select + copy-paste:**

- **Drag-fill:** zaznacz komórkę z wartością → przeciągnij za róg dolny-prawy → wartość kopiuje się w dół (lub inkrementuje, jeśli numeryczne, np. `100, 200, 300...` rozpoznaje wzorzec).
- **Multi-cell select:** Shift+click między dwoma komórkami → selekcja prostokąta, Ctrl+click dodaje pojedyncze komórki, klikany row = cały wiersz.
- **Copy-paste:** Ctrl+C → kopiuje selekcję (z TSV w clipboard). Ctrl+V → wkleja na zaznaczoną komórkę. Działa z external Excel paste (TSV format = tab-separated values).
- **Inline validation** podczas wpisywania — np. EAN-13 wymaga 13 cyfr, regex check.
- **Auto-save** — pojedyncza komórka po blur lub Enter. Bulk paste → confirm modal *„Update N produktów?"* z preview.

**Tylko TEKST / NUMBER / SELECT / BOOLEAN atrybuty** są edytowalne w Excel mode. Relation, asset, richtext — open in detail view.

**Decyzja techniczna (na poziom implementacji):** AG Grid Community (MIT, ~200KB do bundle, full Excel-like out-of-box) vs custom (lighter, ale 30-40h pracy). Rekomendacja: AG Grid Community jeśli scope >25h dla custom; własny komponent jeśli <25h.

### 4.4 Variants — toggle flat / tree

**Tree mode (default):**
- Master jako wiersz z ▶ icon przed SKU.
- Klik ▶ → expand (pokazuje variants jako child rows z indentacją 24px + ↳ symbol).
- Master compl./sync/channels = agregat z variants.
- Bulk action na master = action na master + wszystkie children (z confirm modal).
- Filter *„show only masters"* w advanced filters (default off).

**Flat mode:**
- Każdy variant + master jako równorzędne wiersze.
- Filter *„variant level"* (Master only / Variants only / All) w advanced filters.
- Sortowanie po compl./SKU działa cross-master (mieszanka master+variant).

**Toggle radio w toolbar** — preference zapisana per saved view.

### 4.5 Status indicators — kompaktowy layout

**Completeness pasek:**
- Width 80px, height 8px.
- Kolor: czerwony <50%, żółty 50-90%, zielony >90%.
- **Formula flat:** `wypełnione_pola / wszystkie_pola_w_family * 100%` (zgodnie z PRD § 7.2). 80/100 = 80%.
- Tooltip on hover: *„60% — wypełnione 12/20 atrybutów"*.
- Kliknięcie → drill-down do detail view + auto-scroll do *„Niewypełnione atrybuty"* sekcji (nice-to-have).

**Sync agregat ikona:**
- 🟢 zielona kropka — wszystkie aktywne kanały OK.
- 🟡 żółta — co najmniej 1 kanał *„partial"* lub *„pending"*.
- 🔴 czerwona — co najmniej 1 kanał *„failed"*.
- ⚪ szara — produkt nie syncowany do żadnego kanału (`enabled=false` lub po prostu nie chciany na kanałach).
- Tooltip on hover: lista kanałów + status każdego.
- Kliknięcie → drill-down do *„Publication"* tab w detail view.

**Channels inline ikony (per kanał, klikalne View on X):**
- Ikony per kanał (Shopify logo, BaseLinker logo, Allegro logo, Custom).
- Każda ikona z dot status'em w prawym-dolnym (🟢/🟡/🔴/⚪).
- Klik → otwiera *„View on Shopify / BaseLinker / Allegro"* w nowej zakładce (bezpośredni link do żywego sklepu).
- Maks. **5 ikon** w wierszu (więcej → *„+3 more"* dropdown).

### 4.6 Bulk actions toolbar

Pojawia się sticky-bottom (lub inline-top) gdy >0 zaznaczonych:

```
┌─────────────────────────────────────────────────────────────────────┐
│ ✓ 247 selected                                          [Clear]    │
│                                                                      │
│ [Bulk edit attribute ▼] [Add to category ▼] [Remove from category ▼]│
│ [Toggle enabled/disabled] [Publish to ▼] [Delete] [Duplicate]      │
│                                                                      │
│ Show only: [Selected ✓ Show selected only]                          │
└─────────────────────────────────────────────────────────────────────┘
```

**Show selected only** — useful przy bulk action 200 produktów; klient widzi tylko zaznaczone, sprawdza, potwierdza akcję.

**Async dla >100 produktów** — Symfony Messenger handler, progress bar w UI, klient może zamknąć kartę i wrócić.

### 4.7 Quick actions per row (3-dot menu)

```
Edit                          (otwórz detail view)
Quick edit                    (popover dla single attribute click on cell)
Duplicate                     (klonuj + opcje "z assetami / bez", "z relacjami / bez")
─────────
Toggle enabled/disabled       (jednoclick, bez modal)
Publish to channels...        (submenu: All / Shopify / BaseLinker / Allegro)
─────────
View audit log                (modal: ostatnie 20 zmian z możliwością "Show full")
Copy product URL              (do clipboard, share — kopiuje URL admin produktu)
─────────
Delete                        (z confirm modal)
```

Plus **inline channel ikony** w kolumnie *Channels* (View on Shopify / BaseLinker / Allegro) — bezpośredni link do żywego sklepu.

### 4.8 Empty state z CTA

Gdy klient ma 0 produktów (nowy tenant, fresh install):

```
┌─────────────────────────────────────────────────────────┐
│                                                          │
│                       📦                                  │
│                                                          │
│              Brak produktów w katalogu                  │
│                                                          │
│   Wybierz, jak chcesz zacząć:                          │
│                                                          │
│   [+ Dodaj pierwszy produkt]                            │
│   [📋 Sklonuj z istniejącego]   (gdy >0 inny ObjectType)│
│   [📥 Importuj z Excel/CSV →]    (link do epiku 04)     │
│                                                          │
│   💡 Lub uruchom Cmd+K i powiedz agentowi:              │
│       *„dodaj produkt sku=ABC123 family=Czujniki"*      │
│      (Beta-Demo, MVP)                                    │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 4.9 Sortowanie multi-column

- **Click na kolumnie** — sort asc; click drugi — sort desc; click trzeci — clear.
- **Shift+click drugą kolumnę** — multi-column sort (np. najpierw Brand asc, then SKU asc).
- Wskaźnik wizualny: ikony `1 ▲` `2 ▲` przy nagłówkach.
- Reset all przez *„Clear sort"* w View settings dropdown.

### 4.10 Pagination

- **Cursor-based** (z architektury sekcji 6.2 — `pageBefore` / `pageAfter`).
- Page size: 25 / 50 (default) / 100 / 250.
- Pokazuje *„1-50 of 1247"* counter.
- Buttony *„Prev"* / *„Next"* + *„Jump to page N"* dla power users.

---

## 5. Detail view

### 5.1 Layout

```
┌─ Detail: TST-001 — Czujnik X-200 ─────────────────────────────────────────┐
│ Sticky header                                                              │
│ SKU: TST-001 | Czujnik X-200 | Family: Czujniki | Completeness: 60%       │
│ Sync: 🟢🟢🔴 (Shopify ✓ BaseLinker ✓ Allegro ✗) | Status: Enabled ✓        │
│ [Save Cmd+S] [Discard] [Publish] [Duplicate] [⋮ More actions]              │
│                                                                            │
├─────┬───────────────────────────────────────────────────────┬────────────┤
│Nav  │ Main Content                                          │ Right side │
│     │                                                       │            │
│⊙Id  │ ┌─ Identyfikacja ─────────────────────────────────┐  │ 📷 Images │
│     │ │ SKU: [TST-001        ] 🟢manual                 │  │ ┌────────┐│
│ Mar │ │ Name (PL/EN tabs):                              │  │ │  IMG  ││
│     │ │   PL: [Czujnik X-200] 🟢manual              🔓 │  │ └────────┘│
│ Tec │ │   EN: [Sensor X-200] 🔵import 17.04.2026   🔓 │  │ Galeria 4 │
│     │ │ Brand: [Festo ▼] 🟢manual                       │  │            │
│ Cat │ └─────────────────────────────────────────────────┘  │ 🔗 Related │
│     │                                                       │ 5 produkty │
│ Cha │ ┌─ Marketing ──────────────────────────────────────┐ │ ───────────│
│     │ │ Description (PL/EN tabs):                        │ │ 📜 History │
│ Loc │ │ ... rich text editor ...                         │ │ Ostatnie 5 │
│     │ │ Tags: [czujnik, ATEX, IP67]                     │ │ ───────────│
│ Hist│ └─────────────────────────────────────────────────┘ │ 🔗 Channels│
│     │                                                       │ Sync stats │
│     │ ┌─ Technical specifications ──────────────────────┐  │            │
│     │ │ Voltage (V): [24] 🔵import                       │  │            │
│     │ │ IP Class: [IP67 ▼] 🟢manual                      │  │            │
│     │ │ ...                                              │  │            │
│     │ └─────────────────────────────────────────────────┘  │            │
│     │                                                       │            │
│     │ [+ Show all groups]                                   │            │
│     │                                                       │            │
└─────┴───────────────────────────────────────────────────────┴────────────┘
```

**Lewy sidebar nav** — sekcje z efektywnej listy Attribute Groups (z `EffectiveAttributeGroupResolver` z epiku 08). Per produkt może mieć 5-15 sekcji.

**Dynamiczny formularz** — generowany na podstawie ObjectType + kategoria (efektywna lista grup).

**Provenance badges** przy każdym polu — kolory: 🟢 manual / 🔵 import / 🟣 agent (Faza 2) / ⚫ integration. Klikalne tooltip z source.

**Lock icons** (🔓 / 🔒) obok pól — *„zablokuj przed nadpisaniem importem"*. MVP: granularne per pole.

**Localizable tabs** (PL/EN/...) dla atrybutów z `localizable=true`.

**Channel sub-tabs** (Web/BaseLinker/Datasheet) dla atrybutów z `scopable=true`.

**Auto-save 3s debounce** + Cmd+S manual. Save state indicator w sticky header (*„Saving... / Saved / Failed"*).

**Diff modal przed save** dla zmian wpływających na publikację (kanały aktywne).

### 5.2 Right sidebar — kontekst

| Sekcja | Co pokazuje |
|---|---|
| 📷 **Images** | Główne zdjęcie + thumbnail galerii (klik → DAM widok dla edytowania) |
| 🔗 **Related products** | Lista 5 powiązanych produktów (read-only w MVP, edytowalne w epiku 09 Faza 1+) |
| 📜 **History** | Ostatnie 5 zmian (kto/kiedy/co) z linkiem do *„Show full audit log"* |
| 🔗 **Channels** | Per kanał: status sync, last sync timestamp, link *„View on X"* |

---

## 6. Quick edit popover

```
[Click on cell w liście — np. Brand cell]
                                              ↓
┌─────────────────────────────────────┐
│ Edit: Brand                          │
│                                      │
│ [Festo ▼] (relation picker)         │
│   - Festo                            │
│   - Bosch                            │
│   - SMC                              │
│   - + Add new brand...               │
│                                      │
│ [Cancel ESC]  [Save Enter]          │
└─────────────────────────────────────┘
```

**Flow:**
1. Klik na komórkę → popover otwiera się przy komórce.
2. Auto-focus na input.
3. Inline validation przy wpisywaniu.
4. **Enter** lub blur → save (auto-save, bez confirm).
5. **Esc** → cancel.
6. Komórka aktualizuje się in-place bez reload listy.
7. Provenance updated do *„manual"* z user_id i timestamp.

**Edge case:** quick edit nie pozwala na bulk (klik = pojedynczy produkt). Bulk = bulk actions toolbar.

---

## 7. Create product wizard

```
Step 1: Wybierz family + kategorię
  - Family: [Czujniki ▼] (z filter z ObjectType)
  - Category: [Pneumatyka / Czujniki indukcyjne ▼] (drzewo)
  → Effective attribute groups preview (z epiku 08): "9 grup atrybutów"

Step 2: Required attributes
  - SKU: [_________] (required, auto-generate option)
  - Name: [_________] (required)
  - Brand: [Festo ▼] (required jeśli w family wymagany)
  - ... (z group "Identyfikacja")

Step 3: Confirm + Create
  - Preview: jak wygląda formularz dla tego produktu.
  - [Create + Continue editing]  [Create + New another]
```

**Cmd+K shortcut (Beta-Demo MVP, ekspansja Faza 2):**
- *„stwórz produkt sku=ABC123 family=Czujniki"* — agent parsuje, otwiera Create wizard z pre-fill.
- Faza 2: full conversational create (*„dodaj nowy czujnik ATEX z marka Bosch, IP67, 24V"*) — agent fills wszystkie atrybuty z chat'u.

---

## 8. Variants management

### 8.1 Lista (toggle flat / tree)

Patrz § 4.4.

### 8.2 Variants tab w detail view

Master product ma sekcję *„Variants"* w left sidebar nav. Zawartość:

```
┌─ Variants ──────────────────────────────────────────────┐
│                                                          │
│ Axes: [color] × [size]                       [Edit axes]│
│                                                          │
│ Generator: [Generate variants from axes]                 │
│   → Auto-creates 12 variants (3 colors × 4 sizes)       │
│                                                          │
│ ┌──────────────────────────────────────────────────┐   │
│ │ Variant SKU      | color  | size | EAN      | …  │   │
│ ├──────────────────────────────────────────────────┤   │
│ │ TST-001-RED-S    | red    | S    | 590...   | ⋮  │   │
│ │ TST-001-RED-M    | red    | M    | 590...   | ⋮  │   │
│ │ TST-001-RED-L    | red    | L    | 590...   | ⋮  │   │
│ │ ...                                                │   │
│ └──────────────────────────────────────────────────┘   │
│                                                          │
│ Per-variant override pól level=variant.                  │
│ Master attributes (level=master) inherited automatically.│
└──────────────────────────────────────────────────────────┘
```

**Edit axes** — modal do dodania/usunięcia osi (np. dorzucenie *„finish"* jako 3-cia oś → matrix expansion 36 variants).

**Per-variant cells edytowalne** w Excel-like mode (price, EAN, stock_qty — typowe `level=variant` atrybuty).

---

## 9. Saved Views (solo)

### 9.1 Save current view

Klient klika *„Save current view"* (button w toolbar lub View dropdown):

```
┌─ Save view ──────────────────────────────────────┐
│ Name: [Festo niski completeness            ]    │
│ Description: [optional, dla siebie...      ]    │
│                                                  │
│ Includes:                                        │
│   ✓ Filters (Brand=Festo, Completeness<50%)     │
│   ✓ Sort (Completeness asc, SKU asc)            │
│   ✓ Visible columns (12 of 14)                  │
│   ✓ Page size (50)                              │
│   ✓ Variants toggle (Tree)                      │
│                                                  │
│ [Cancel]  [Save view]                           │
└──────────────────────────────────────────────────┘
```

### 9.2 Manage views

```
┌─ My saved views ─────────────────────────────────┐
│                                                  │
│ ⭐ Default (system)                       [view]│
│ Festo niski completeness                  [⋮]   │
│ Czerwone produkty                         [⋮]   │
│ Ostatnio edytowane (last 7 days)          [⋮]   │
│                                                  │
│ [+ Save current as new view]                     │
└──────────────────────────────────────────────────┘
```

**Per view actions (3-dot):** Edit name / Update with current state / Set as default / Delete.

**URL routing:** `/produkty?view=festo-niski-completeness` — share linkiem (działa tylko dla owner'a, bo solo-only).

---

## 10. User stories

### 10.1 Z `Project Plan/03-funkcjonalnosci-mvp.md`

| ID | Persona | Story |
|---|---|---|
| US-002 | Kasia | Edycja atrybutów pojedynczego produktu z dynamicznym formularzem |
| US-003 | Kasia | Sprawdzenie completeness — które produkty są niepełne |
| US-004 | Kasia | Bulk edit atrybutów dla wielu produktów |
| US-006 | Kasia / Magda | Pisanie opisów SEO i treści marketingowych per locale |

### 10.2 Nowe (z brainstormingu 2026-04-30)

| ID | Persona | Story |
|---|---|---|
| US-EP02-001 | Kasia | Excel-like editing — drag-fill na 50 produktach Festo, multi-cell select + bulk paste z external Excel |
| US-EP02-002 | Kasia | Quick search prefix po SKU — wpisuje *„TST-001"* → znajduje produkt + 3 variants natychmiast |
| US-EP02-003 | Kasia | Saved Views — *„moje czerwone Festo"* — zapisuje + reuses codziennie |
| US-EP02-004 | Magda | Multi-column sort (Brand asc, SKU asc) z Shift+click dla audytu marki |
| US-EP02-005 | Kasia | Quick edit popover — klik na kategorię w komórce → zmiana → save → bez load detal'u |
| US-EP02-006 | Kasia / Marcin | Toggle variants flat/tree — rano analiza per master, po południu praca per variant |
| US-EP02-007 | Kasia | Quick actions per row — Duplicate produktu *„Czujnik X-200"* → opcja *„z assetami"* + *„bez relacji"* |
| US-EP02-008 | Kasia | Inline channel ikony — klik *„View on Shopify"* w wierszu → otwiera żywy produkt w nowej zakładce |
| US-EP02-009 | Tomasz | View audit log dla flagowego produktu — *„kto zmienił cenę 3 dni temu"* |
| US-EP02-010 | Marcin (dogfooding) | Empty state CTA — pierwszy raz w PIM, wybiera *„Sklonuj z istniejącego"* (z IdoSell migracji) |
| US-EP02-011 | Kasia | Bulk delete 30 archiwalnych produktów z 200-row preview *„Show only selected"* |
| US-EP02-012 | Magda | Cmd+K (Faza 2 data-ops) — *„zaznacz 30 produktów Festo i ustaw kategorię na Pneumatyka"* |

---

## 11. Business rules / edge cases

### 11.1 Variants i bulk actions

- **Bulk action na master w tree mode** — confirm modal: *„Apply to master only / master + all variants / variants only?"*. Default: master + variants.
- **Bulk delete master** — blokuje jeśli master ma >0 active variants. Confirm: *„Delete master + N variants?"*.
- **Bulk change family** dla master — propagacja do variants automatyczna. Variants z `level=variant` atrybutami które nie istnieją w nowej family → warning.

### 11.2 Excel-like editing edge cases

- **Paste do read-only kolumny** (Family, Categories) — alert *„Read-only column. Use detail view to edit."*.
- **Paste niewłaściwego typu** (text → number column) — Inline validation, błąd per komórka, skip invalid rows.
- **Paste do localizable column** — wkleja do *current locale tab* (czyli jakiego klient widzi). Nie wkleja w wszystkich locales.
- **Paste do scopable column** — j.w. dla aktualnego channel sub-tab.
- **Drag-fill na różnych typach komórek** — działa tylko gdy wszystkie komórki w drag są tego samego typu.

### 11.3 Saved Views edge cases

- **View używa removed attribute** (Adam usunął atrybut z modelu) — view shows warning *„Column 'old_attr' no longer exists. Remove from view?"*.
- **View używa removed category filter** — j.w.
- **Default view** — jeden per user. Auto-load on tab open.

### 11.4 Quick search edge cases

- **Empty search** — pokazuje wszystko (default).
- **Search w current filter context** — search zawęża aktualnie filtrowany zestaw, nie całość.
- **Search > 200 wyników** — pokazuje tylko top 50 (paginacja standardowa) + warning *„200+ matches, refine your search"*.

### 11.5 Sync ikona agregat — formula

```
Channels enabled for product = list
  ∀ channel.last_sync_status:
    if all == "ok":            🟢 green
    elif any == "failed":      🔴 red
    elif any == "partial":     🟡 yellow
    elif any == "pending":     🟡 yellow
    else (no syncs yet):       ⚪ gray
```

Cache w `objects.sync_status_aggregate VARCHAR(8)` z invalidacją po każdym sync event.

### 11.6 Inline channel ikony

- **Pokazuje TYLKO enabled channels** dla danego produktu (`product_channel_enabled` flag).
- Maks 5 ikon w wierszu, więcej → *„+3 more"* dropdown.
- Klik ikony → otwiera żywy URL (Shopify product page, BaseLinker product page, ...) — *nie* admin URL nasz.
- *„View on X"* link wymaga published produktu na danym kanale; jeśli nie published, ikona z ⚠ *„Pending publication"*.

### 11.7 Provenance ostatniej zmiany

- Provenance per *pole* w detail view (ADR-006).
- W liście — *out of view* (per Marcin's decision). Klient widzi w detail tylko.
- Wyjątek: gdyby klient w *„View settings"* dodał kolumnę *„Last changed by"* → pokazuje ostatnią zmianę z provenance badge.

---

## 12. Dependency na backend

### 12.1 ADR-y wymagane

- **ADR-006** (Hybrid attribute model) — `object_values` + `attributes_indexed JSONB` z indeksem GIN.
- **ADR-009** (Generic ObjectType) — Product jako `kind=product`.
- **ADR-010** (Axis-Driven Variants) — encja `Variant` z `master_object_id`.
- **ADR-011** (Per-tenant locale fallback chain).
- **Proponowany ADR-012** (Attribute Group as first-class entity) — z epiku 08 — *kluczowa zależność* dla dynamicznego formularza w detail view.

### 12.2 Endpointy API (delta vs aktualnego planu)

| Endpoint | Status | Komentarz |
|---|---|---|
| `GET /api/products` | MVP | Lista z filters + sort + cursor pagination (z API Platform 4 box) |
| `GET /api/products/{id}` | MVP | Detail z all values + provenance |
| `POST /api/products` | MVP | Create |
| `PATCH /api/products/{id}` | MVP | Partial update |
| `DELETE /api/products/{id}` | MVP | Soft delete (Faza 1) lub hard delete |
| `POST /api/products/bulk-edit` | MVP | Custom — bulk update attribute na N produktach (async via Symfony Messenger) |
| `POST /api/products/{id}/duplicate` | MVP | Custom — z opcjami `with_assets`, `with_relations` |
| `POST /api/products/{id}/publish` | Faza 1 | Custom — publish per channels |
| `GET /api/products/quick-search?q=...` | MVP | Meilisearch endpoint, prefix/exact |
| `GET /api/products/{id}/audit-log` | MVP | Ostatnie 20 zmian (z DoctrineAuditBundle) |
| `GET /api/products/{id}/effective-attribute-groups` | MVP | Dla detail view + create wizard (z `EffectiveAttributeGroupResolver` z epiku 08) |
| `GET /api/products/{id}/channels-status` | MVP | Per-channel sync status agregat |
| `POST /api/saved-views` | MVP | CRUD dla saved views |
| `GET /api/saved-views` | MVP | Lista user'a saved views |

### 12.3 Doctrine listeners

- `attributes-indexed-rebuild` (z ADR-006) — async dla bulk path.
- `completeness-pct-update` — Doctrine listener `postUpdate` na `ObjectValue`, recompute `objects.completeness_pct`. Flat formula 80/100 = 80%.
- `sync-status-aggregate-update` — listener po każdym sync event, recompute `objects.sync_status_aggregate`.

### 12.4 Frontend libraries (delta vs aktualnego stack)

| Library | Cel |
|---|---|
| **TanStack Table** (już w stacku) | Base DataTable z sortowaniem multi-col, virtualization |
| **AG Grid Community** (kandydat — opcja, MIT) | Excel-like editing (drag-fill + multi-cell + paste). Decyzja techniczna na poziom implementacji. ~200KB do bundle. |
| **react-arborist** (już z epiku 08) | Tree dla variants tree mode (jeśli używamy) |
| **react-i18next** (już w stacku) | Localizable tabs |
| **react-hook-form + zod** (już w stacku) | Formularz detail view |
| **Mercure SDK** | Live updates sync status w liście |

---

## 13. Komponenty Refine + shadcn — lista

### 13.1 Refine resources

- `Refine.Resource` per ObjectType (auto-generated z `object_types` dynamic — patrz epik 03 Usługi).
- Hooks: `useTable`, `useForm`, `useShow`, `useCreate`, `useUpdate`, `useDelete`, `useDuplicate` (custom).

### 13.2 shadcn components

- `Tabs`, `Form`, `FormField`, `Input`, `Select`, `Combobox`, `Switch`, `Button`, `Badge`, `Tooltip`, `Card`, `Dialog`, `Sheet` (right sidebar), `Popover` (quick edit), `DropdownMenu` (3-dot menu + bulk actions), `Progress` (completeness bar), `Skeleton` (loading).

### 13.3 Custom components

| Komponent | Rola |
|---|---|
| `ProductDataTable` | TanStack Table z konfiguracją kolumn + sortowanie multi-col + variants tree toggle |
| `ExcelLikeGrid` | AG Grid Community wrapper lub custom — drag-fill + multi-cell + copy-paste |
| `QuickEditPopover` | Single-attribute popover with type-aware input |
| `CompletenessBadge` | Progress bar + percentage z color coding |
| `SyncAggregateIcon` | Single-icon agregat per produkt |
| `ChannelInlineIcons` | Per-kanał ikony klikalne *„View on X"* |
| `BulkActionsToolbar` | Sticky toolbar pojawiający się po selekcji |
| `ProductFilterChips` | Aktywne filtry jako chip'y z X |
| `AdvancedFilterBuilder` | Modal/Sheet z full filter builder (po wszystkich atrybutach) |
| `SavedViewsDropdown` | View picker + Save current as new + Manage views |
| `VariantsToggle` | Radio toolbar *„As tree / Flat"* |
| `VariantsMatrixGenerator` | Modal *„Generate variants from axes"* |
| `DuplicateProductDialog` | *„Klonuj produkt"* z opcjami |
| `AuditLogModal` | Ostatnie 20 zmian + *„Show full"* link |
| `ProvenanceBadge` (z epiku 08) | Reused — manual / import / agent / integration |
| `EmptyStateProducts` | CTA dla 0 produktów |

---

## 14. Open questions

- [ ] **AG Grid Community vs custom** dla Excel-like editing — decyzja techniczna na poziom implementacji. Trigger: jeśli custom skip 25h, używamy AG Grid.
- [ ] **Bulk action *„Add to category"* UX** — modal z drzewem kategorii vs autocomplete picker?
- [ ] **Bulk publish** — auto-publish do *all enabled channels* vs submenu *„Publish to: All / Shopify / BaseLinker"*?
- [ ] **Variants tree expand state persistence** — pamiętamy expand/collapse między reload listy (per user)?
- [ ] **Show only selected** w bulk mode — toggle filter czy permanent stan w toolbar?
- [ ] **Categories drag-drop produktów** — czy w liście można drag produkt do drzewa kategorii? (kandydat na Faza 1, zbyt złożony dla MVP listy)
- [ ] **Cmd+K agent w Beta-Demo MVP** — tylko *„dodaj atrybut"* (z epiku 08) czy też *„stwórz produkt sku=..."* (rozszerzenie)?
- [ ] **Default columns set** — które kolumny default visible (12 of 14 z § 4.2 listy)? Klient skonfiguruje per saved view.
- [ ] **Inline channel ikony max** — 5 ikon w wierszu z *„+3 more"* dropdown, czy 3 + always *„+ more"*?
- [ ] **Audit log retention dla MVP** — 90 dni? 1 rok? Zgodnie z epik 0.11.4 z planu.
- [ ] **Quick edit popover na rich text** — wyłączamy w Excel mode, force open detail view?
- [ ] **Saved view conflict** — dwa user'zy mają saved view tej samej nazwy. Solo-only więc nie ma konflictu (każdy w własnej przestrzeni).

---

## 15. Wpisanie do roadmapy backend (delta vs `Project Plan/02-plan-projektu-pim.md`)

Epik 02 Produkty wpływa na:

- **Epik 0.4** (API Platform — exposing entities) — dochodzą custom endpointy: `bulk-edit`, `duplicate`, `quick-search`, `effective-attribute-groups`, `channels-status`, `audit-log`, `saved-views` CRUD. **Estymacja: +12-16h** ponad obecny scope.
- **Epik 0.5** (Search — Meilisearch) — quick search prefix/exact wymaga indeksu Meilisearch z `searchable_attributes: [sku, name, ean]`. Już planowane.
- **Epik 0.6** (Admin UI — core CRUD) — dochodzi pełen list view + detail view + quick edit + Excel-like editing + variants management + saved views + bulk actions. **Estymacja: +30-40h** ponad obecny scope (z 20-26h plan'a do 50-66h).
- **Epik 0.11** (Hardening) — saved views per user (Faza 1+ — share + templates), permissions ADR-013.

**Total impact na Faza 0:** **+42-56h**. Aktualny budżet z PRD ~270-380h (po MVP-Beta-Demo + ADR-009/010/011) → **~310-440h**. Wciąż w zakresie *„pełen MVP"* (Marcin akceptuje +50-80h scope, PRD § 12.1).

**Drivery scope'u:**
- Excel-like editing: ~20-30h (dominant cost epiku 02).
- Saved Views: ~6-10h.
- Variants toggle + matrix generator: ~8-12h.
- Quick actions per row + popover: ~6-10h.

---

## 16. Co dalej

1. **Walidacja koncepcji** z Marcinem (lub Adam'em jeśli zatrudniony) — czy układ list view jest intuicyjny.
2. **Wireframes w Figma** — przekazać external UX designer'owi (z PRD § 13.5).
3. **Decision techniczna AG Grid Community** — POC w Sprint 0+1 dla tej biblioteki, validacja czy wystarczy out-of-box, czy konieczne custom.
4. **Klikalny prototyp** — przed implementacją, walidacja flow z Kasią/Marcinem.
5. **Aktualizacja `Project Plan/02-plan-projektu-pim.md`** epik 0.6 estymacji.
6. **Aktualizacja `Project Plan/03-funkcjonalnosci-mvp.md`** — dodać user stories US-EP02-001 do US-EP02-012.

---

*Plik wersjonowany w `Project Plan/UI/`. Status: szczegół. Następna iteracja: walidacja z Marcinem + Figma wireframes + POC AG Grid Community.*
