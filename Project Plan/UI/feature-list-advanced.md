# Feature — Lista produktów: filtry, wyszukiwarka, akcje zbiorcze, Cmd+K

## Status: 🟢 szczegół

> **Część epiku 02 Produkty** — *„advanced layer"* nad podstawową listą produktów.
> **Decyzje brainstormingowe** zamknięte 2026-05-11 w sesji Senior PM (4 fale, ~20 nowych decyzji ponad snapshot z 2026-04-30 w `epik-02-produkty.md` § 3).
> Plik rozszerza i precyzuje `epik-02-produkty.md` w obszarach: § 4 (List view), § 9 (Sortowanie multi-column), § 11 (edge cases bulk actions).

---

## 1. Cel feature'a

Operator (Kasia) potrzebuje listy produktów, która łączy **operator-grade gęstość informacji** (BaseLinker) z **workflow-grade czystością** (Akeneo) — *bez* Pimcore-style developer overhead. Lista jest jej *„cockpitem"* — 60% czasu pracy. Musi obsłużyć:

1. **Szybkie odnajdywanie** — quick search po SKU/name/EAN/brand/tags + filtry chip-based z operatorami.
2. **Zaawansowane wyszukiwanie** — Advanced filter panel z grid mode lub query mode (AND/OR).
3. **Masowe modyfikacje** — 13 akcji bulk z confirmation flow per typ + cascade warning + 24h rollback.
4. **Agentic interaction** — Cmd+K jako natural language interface dla schema-ops + bulk actions na zaznaczonych.
5. **AI smart filters (MVP rule-based)** — *„cross-locale completeness mismatch"* jako quick filter preset.

**Out of scope MVP:** AI semantic search, cross-channel value inconsistency detection (β/δ z killer feature), custom scripts mode (BaseLinker-style power user), per-channel completeness scoring, real-time collaboration na zaznaczeniach.

**Marketing flag:** *„AI smart filters"* w MVP **nie jest AI** — to rule-based completeness check. Pełen AI w Fazie 1+ (semantic similarity, cross-channel value comparison). Patrz § 11 Flag.

## 2. Persony

| Persona | Rola w liście | Częstość |
|---|---|---|
| **Kasia, 32** (Catalog Manager) | Primary — 60% czasu pracy, codziennie 20-50 modyfikacji + 5-10 bulk operacji | Codziennie 8h |
| **Magda, 29** (Marketing) | Secondary — bulk edit opisów SEO, kategoryzacja, kolekcje sezonowe | Tygodniowo 5-10h |
| **Marcin (dogfooding)** | First user — IdoSell + Shopify migracja, regression test dla każdego nowego feature | Codziennie 1-2h |
| **Tomasz** (Owner) | Sporadyczny — self-service edycja flagowych produktów, audit overview | Tygodniowo 1h |

## 3. Brainstorming decisions snapshot (2026-05-11)

Wszystkie 20 decyzji z 4 fal wywiadu, które kierują design'em. Ponad istniejący snapshot z `epik-02-produkty.md` § 3.

### Fala 1 — Filozofia i fundament

| Obszar | Decyzja |
|---|---|
| Filozofia listy | **Hybrid Akeneo + BaseLinker, bez Pimcore complexity** — operator cockpit (gęstość) + workflow workflow (completeness, preview diff) |
| Drzewo kategorii w liście | **BRAK** — kategoria jako dropdown w filtrach. Tree view tylko w epiku 10 Categories |
| Filter panel layout | **Magento/BaseLinker-style push-down** — rozsuwany w pionie (NIE prawy sidebar). Push'uje listę w dół |
| Search/Filter separation | **Pimcore-style** — quick search bar + osobny *„Filtruj po atrybucie"* dropdown jako 2 różne UX-y |
| Killer features | **Cmd+K agent + AI smart filters** — oba przez jeden shortcut (Cmd+K obsługuje action + filter intents) |

### Fala 2 — Wyszukiwarka

| Obszar | Decyzja |
|---|---|
| Quick search behavior | **NO typeahead** — search po Enter, full list filter. Bez Algolia/Spotlight dropdown |
| Search po polach | **SKU + name + EAN + brand + tags** (typuję *„Festo"* → znajduje wszystkie Festo) |
| Filtruj po atrybucie dropdown | **Favorite top 10** — user w settings wybiera, reszta w Advanced |
| Operatory w MVP | **Pełne od dnia 1** — `=`, `≠`, `>`, `<`, `BETWEEN`, `IS EMPTY`, `IS NOT EMPTY`, `STARTS WITH`, `CONTAINS` (Akeneo style) |
| Advanced filter panel layout | **Hybrid** — grid 3-4 kolumny atrybutów default + toggle do query mode (AND/OR brackets) |
| Advanced panel zachowanie | **Sticky-collapsible** — manual toggle, klient sam decyduje czy panel zwija/rozwija |
| Per-locale search | **Current locale only** — *„sensor"* nie znajdzie `name.pl=Czujnik`, klient musi przełączyć locale |
| AI smart filters w MVP | **(γ) Completeness mismatch ONLY** — `description.pl filled AND description.en empty` jako preset filter. Rule-based, **bez LLM** |
| AI smart filters Faza 1+ | Semantic comparison (β) + cross-channel inconsistency (δ) + AI quality dashboard (ε) |
| Filter persistence | **URL-based** — `?brand=Festo&completeness=lt50`. Shareable + refreshable. Cross-tab nav = reset |

### Fala 3 — Akcje zbiorcze

| Obszar | Decyzja |
|---|---|
| Zestaw akcji MVP | **13 akcji rozszerzony** (b) — set/clear/append/remove attribute + multi-attr edit + increment numeric + toggle enabled + add/remove/move category + publish/unpublish + delete + duplicate |
| Custom scripts mode | **Faza 3+ lub never** — defer |
| Confirmation flow | **Per typ akcji** — wizard diff dla edit attribute, inline toast dla low-risk, hard confirm typing dla delete, simple modal dla publish/duplicate |
| Cascade effects warning | **(a) Pełen impact summary modal** — *„cofnięcie publikacji z 3 kanałów + 144 variants + 89 cross-sell"* przed Confirm |
| Bulk rollback | **24h per-session** — analog imports, dla wszystkich bulk operations |
| Permission destructive | **Konsekwentnie brak gating** w MVP — audit log + rollback wystarczą. Spójne z Modelowaniem (Fali 1) |

### Fala 4 — Edge cases + Cmd+K

| Obszar | Decyzja |
|---|---|
| Rollback `bulk delete` | **(a2) Partial restore** — przywróć w PIM, klient ręcznie re-publish do kanałów |
| Rollback `bulk publish` | **(b1) Always unpublish wszystkie** — buyer może zobaczyć *„product not found"* w międzyczasie. Klient akceptuje risk |
| Cross-page selection | **BaseLinker style** — toolbar wybór: *„zaznacz na tej stronie"* vs *„zaznacz wszystkie wyniki"*. User decyduje świadomie |
| Per-attribute lock w bulk | **Skip locked + raport** — *„247 selected, 230 updated, 17 skipped (locked)"*. Bez force override w MVP |
| Cmd+K agent w MVP | **Schema-ops + bulk na zaznaczonych** — *„dla zaznaczonych 30 ustaw kategorię na Pneumatyka"*. NLP filter queries → Faza 2 |
| Cmd+K selection state | **Naturalne, bez UI komunikacji** — agent czyta selection z React context, interpretuje *„dla zaznaczonych jeśli >0"* |
| Bulk wizard preview | **(d) Hybrid** — sample 5 rows + aggregate counter na jednym screenie |

---

## 4. Mapping vs konkurencja per obszar

| Obszar | Pimcore | Akeneo | BaseLinker | **Cortex (nasz)** |
|---|---|---|---|---|
| **Layout listy** | ExtJS grid z 30+ kolumn, ColumnConfigurator | Grid 8-12 kolumn + sidebar kategorii + smart filter sidebar po prawej | Gęsty grid 15-20 kolumn + sidebar kategorii + chip filtry + Action Bar | **Density BaseLinker + completeness Akeneo, bez drzewa kategorii w liście, push-down filter panel** |
| **Quick search** | Quick search (full-text) + Advanced search per atrybut (Pimcore separation) | Single search across multiple fields (Akeneo unified) | Single keyword search + chip filters | **Pimcore-style separation** — quick search SKU/name/EAN/brand/tags + osobny *„Filtruj po atrybucie"* dropdown |
| **Filter UI** | Column filter per nagłówek + sidebar Object Class filter z operatorami | Smart Filter sidebar po prawej z operatorami `IS`, `IS NOT`, `IS EMPTY`, `STARTS WITH` | Chip filtry compact w toolbar (~10-15 popularnych atrybutów) | **Chip filtry compact (BaseLinker) + Advanced push-down panel (Magento style) z grid/query modes** |
| **Operatory** | Pełne (Akeneo-grade) | Pełne (`IS`, `IS NOT`, `IS EMPTY`, `STARTS WITH`, `CONTAINS`, `>=`, `<=`, `BETWEEN`) | Tylko `=` w chip, brak per-operator | **Pełne od dnia 1** — Akeneo-grade w grid mode + AND/OR query mode dla power users |
| **AI features** | Brak (developer-tool, brak AI native) | Akeneo PIM AI (paid Enterprise — auto-translate, content generation) | Brak (klasyczna integracja, scripts mode jako power user) | **Cmd+K agent + completeness-mismatch rule-based filter** (MVP) → **AI semantic + AI quality dashboard** (Faza 1-2) |
| **Cross-page selection** | Per-page select | Akeneo-style hybrid (per-page + *„Apply to filtered results"* z count) | Toolbar user choice — per-page vs select-all-matching | **BaseLinker style** — explicit toolbar choice |
| **Bulk actions liczba** | ~10 generic (delete, copy, change parent, change class, mass operations queue) | ~15 (mass edit attributes wizard, classify, change status, workflow submit, export) | ~30 per typ obiektu (operator cockpit) | **13 w MVP** + custom scripts Faza 3+ |
| **Confirm flow** | Context Menu actions + simple confirm | 3-step wizard z preview diff (Akeneo signature feature) | Simple modal z liczbą produktów | **Per typ akcji** — wizard diff dla destructive, inline toast dla low-risk |
| **Rollback** | Audit log only (no native bulk rollback) | Job history + manual reverse (limited) | Brak natywnego rollback | **24h per-session rollback** dla wszystkich bulk operations + cascade handling |
| **Permission** | Per-action role permissions | Per-action role permissions w Enterprise | Per-user permissions w admin | **Brak gating w MVP**, Faza 1 ADR-013 |
| **Cmd+K / Command palette** | Brak | Brak | Brak | **Killer feature MVP** — schema-ops + bulk actions z natural language |
| **Filter persistence** | Saved Views (JSON config) | Smart Views + URL params | LocalStorage per user | **URL-based only** — shareable + refreshable. Saved Views (z epiku 02) dla zachowania na dłużej |

**Bottom line:** Cortex bierze gęstość operator UX z BaseLinker, czystość workflow + completeness focus z Akeneo, agentic-first Cmd+K jako *unique* USP którego żaden z trzech nie ma natywnie. Pimcore developer-grade complexity świadomie *odrzucamy* — Kasia nie jest developerem.

---

## 5. Layout główny — toolbar + filter panel + grid

### 5.1 Layout bazowy (filter panel zwinięty)

```
┌─ Produkty ──────────────────────────────────────────────────────────────────┐
│ [🔍 Szukaj SKU/name/EAN/brand/tags...]   [+ Filtruj po atrybucie ▼]  [Filtry ↓]│
│                                                                              │
│ Aktywne filtry: [Brand = Festo ✕] [Completeness < 50% ✕] [+ Add filter]    │
│                                                                              │
│ [Show variants:  ◉ As tree  ○ Flat]    [+ New Product]  [⋮ Imports]  [Cmd+K]│
├──────────────────────────────────────────────────────────────────────────────┤
│ ☐ │ 🖼 │ SKU       │ Name           │ Brand │ Compl. │Sync│ Channels │ Status │⋮│
├──────────────────────────────────────────────────────────────────────────────┤
│ ☐ │ ▶ │ TST-001   │ Czujnik X-200  │ Festo │ ▓▓▓░░ 60% │ 🟢 │🟢🟢🔴   │ ✓ │⋮ │
│ ☐ │ 🖼 │ TBT-002  │ Taboret 3-noż. │ ProTec│ ▓▓▓▓▓ 100%│ 🟢 │🟢🟢🟢   │ ✓ │⋮ │
│ ... 1245 rows ...                                                            │
├──────────────────────────────────────────────────────────────────────────────┤
│ Showing 1-50 of 1247 products    Per page: [50 ▼]    [‹ Prev] [Next ›]      │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Toolbar sekcje (top-to-bottom):**
1. **Quick search bar** + *„Filtruj po atrybucie"* dropdown + *„Filtry ↓"* toggle (rozsuwa Advanced panel).
2. **Aktywne filtry** — chip area z X (kasuje filter).
3. **Action bar** — variants toggle, New Product, Imports shortcut, Cmd+K shortcut.

### 5.2 Layout z rozsuniętym Advanced filter panel (Magento-style push-down)

```
┌─ Produkty ──────────────────────────────────────────────────────────────────┐
│ [🔍 Szukaj...]   [+ Filtruj po atrybucie ▼]  [Filtry ▲ (rozwinięte)]        │
│                                                                              │
│ Aktywne filtry: [Brand = Festo ✕] [Completeness < 50% ✕]                   │
├──────────────────────────────────────────────────────────────────────────────┤
│ ╔══════════════════════════════════════════════════════════════════════╗   │
│ ║ ZAAWANSOWANE FILTRY                              [⚙ Query mode] [×] ║   │
│ ║                                                                       ║   │
│ ║ Atrybut          Operator         Wartość                            ║   │
│ ║ ─────────────────────────────────────────────────────────────────    ║   │
│ ║ [Brand ▼]       [IS ▼]            [Festo ▼]                          ║   │
│ ║ [Completeness ▼] [< ▼]            [50            ] %                 ║   │
│ ║ [Stock ▼]       [>= ▼]            [10            ]                   ║   │
│ ║ [Category ▼]    [IS NOT EMPTY ▼]                                     ║   │
│ ║                                                                       ║   │
│ ║ [+ Dodaj kolejny filtr]                                              ║   │
│ ║                                                                       ║   │
│ ║                       [Reset] [Zapisz jako Saved View] [Zastosuj]    ║   │
│ ╚══════════════════════════════════════════════════════════════════════╝   │
├──────────────────────────────────────────────────────────────────────────────┤
│ Grid produktów (push'ed niżej, sticky-collapsible panel pozostaje rozwinięty)│
│ ...                                                                          │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Panel zachowanie:**
- **Sticky-collapsible** — klient klika *„Filtry ↓/▲"* manual toggle.
- **Default state** — zwinięty.
- **Po zapisaniu Saved View** — panel zostaje rozwinięty (user nadal w filter mode).
- **Po Reset** — zwija się + chip area czysta.

### 5.3 Layout z Query mode (AND/OR brackets dla power users)

```
┌─ Produkty ──────────────────────────────────────────────────────────────────┐
│ ...                                                                          │
│ ╔══════════════════════════════════════════════════════════════════════╗   │
│ ║ ZAAWANSOWANE FILTRY — QUERY MODE                  [⚙ Grid mode] [×] ║   │
│ ║                                                                       ║   │
│ ║  ┌─ AND ─────────────────────────────────────────┐                   ║   │
│ ║  │ Brand IS Festo                                │                   ║   │
│ ║  │ ┌─ OR ────────────────────────────────────┐  │                   ║   │
│ ║  │ │ Stock >= 10                             │  │                   ║   │
│ ║  │ │ Completeness >= 80                      │  │                   ║   │
│ ║  │ └─────────────────────────────────────────┘  │                   ║   │
│ ║  │ Category IS NOT EMPTY                          │                  ║   │
│ ║  └────────────────────────────────────────────────┘                  ║   │
│ ║                                                                       ║   │
│ ║  [+ AND condition]  [+ OR group]                                     ║   │
│ ║                                                                       ║   │
│ ║                       [Reset] [Zapisz jako Saved View] [Zastosuj]    ║   │
│ ╚══════════════════════════════════════════════════════════════════════╝   │
```

**Toggle Grid ↔ Query:** klient może migrować filter z grid mode do query mode (zachowanie wartości). Migration tylko gdy grid → query daje uproszczenie (każdy grid filter = AND condition).

### 5.4 Bulk actions toolbar (gdy >0 zaznaczonych)

```
┌─ Produkty ──────────────────────────────────────────────────────────────────┐
│ ... (filter chips + grid header) ...                                         │
├──────────────────────────────────────────────────────────────────────────────┤
│  ✓ 30 zaznaczone na tej stronie z 1247 wyników                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  [Zaznacz wszystkie 1247 wyniki?]  [Wyczyść zaznaczenie]             │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  [Bulk edit ▼]  [Zmień kategorię]  [Publish ▼]  [Duplicate]  [⚠ Usuń]      │
│                                                                              │
│  ☐ Show only selected (filter)                                              │
├──────────────────────────────────────────────────────────────────────────────┤
│  Grid (with selected rows highlighted)                                       │
└──────────────────────────────────────────────────────────────────────────────┘
```

**BaseLinker-style toolbar:**
- *„30 zaznaczone na tej stronie z 1247 wyników"* — jasna informacja co user widzi vs co system ma.
- *„Zaznacz wszystkie 1247 wyniki?"* — explicit upgrade do select-all-matching (BaseLinker pattern).
- Bulk actions buttons inline (nie ukryte w dropdown chyba że >7).
- *„Show only selected"* checkbox filter — zaznaczone produkty visible, reszta hidden.

---

## 6. Wyszukiwarka — szczegół

### 6.1 Quick search behavior

**Pole search:**
- Top-left toolbar, max 360 px wide.
- Placeholder: *„Szukaj SKU, nazwy, EAN, marki, tagów..."* (informuje user'a po jakich polach search'uje).
- Submit po **Enter** lub po debounce 800 ms (gdy user przestaje pisać).
- **Brak typeahead dropdown** (decyzja Fali 2).

**Algorytm match w MVP:**
- Strict prefix/exact (decyzja epiku 02).
- Case-insensitive.
- Diacritic-insensitive (Festo = fésto = FESTO).
- Polish character handling — `ą`, `ć`, `ę`, `ł`, `ń`, `ó`, `ś`, `ź`, `ż` matchowane jako ich łacińskie equivalenty (`a`, `c`, `e`, `l`, `n`, `o`, `s`, `z`).

**Search po polach:**
```
SKU starts_with(query)         OR
name like(%query%)             OR
EAN starts_with(query)         OR
brand.name like(%query%)       OR
tags contains(query)
```

Implementacja: Meilisearch index z `searchable_attributes: [sku, name, ean, brand_name, tags_concat]`. Latency <50ms na 200k SKU per architektury sekcji 6.2.

**Search w current filter context:**
- Active filters limit base set (np. *„Brand = Festo"*).
- Search dalej zawęża w tym set'cie.
- Counter visible: *„247 of 1247 matching"* po search.

**Empty state:**
- *„Brak wyników dla 'BSC123'"*.
- Suggestion: *„Spróbuj wyłączyć filtry [Brand = Festo ✕]"* (gdy active filters).
- Link: *„Pokaż wszystkie produkty"*.

### 6.2 *„Filtruj po atrybucie"* dropdown

**Wygląd dropdown:**

```
┌─ + Filtruj po atrybucie ─────────────────────────┐
│ 🔍 Szukaj atrybutu...                            │
├──────────────────────────────────────────────────┤
│ ULUBIONE (Twoje top 10)                          │
│ ├─ Brand                                          │
│ ├─ Kategoria                                      │
│ ├─ Completeness                                   │
│ ├─ Status (enabled/disabled)                     │
│ ├─ Cena                                           │
│ ├─ Stock                                          │
│ ├─ Provenance                                     │
│ ├─ Sync status                                    │
│ ├─ EAN                                            │
│ └─ Date created                                   │
├──────────────────────────────────────────────────┤
│ INNE ATRYBUTY (klik → modal pełen filter builder)│
│ ├─ Description PL                                 │
│ ├─ Description EN                                 │
│ ├─ IP Class                                       │
│ ├─ Voltage                                        │
│ ├─ ... ~200 atrybutów total                       │
├──────────────────────────────────────────────────┤
│ [⚙ Zarządzaj ulubionymi]                          │
└──────────────────────────────────────────────────┘
```

**Po wyborze atrybutu** (np. *„Brand"*):
- System inferuje typ z `Attribute.type`.
- Dla `select` lub `relation`: dropdown z wartościami (`Festo`, `Bosch`, `SMC`, ...).
- Dla `number`: input z operator picker (`=`, `≠`, `>`, `<`, `BETWEEN`).
- Dla `text`: input z operator picker (`STARTS WITH`, `CONTAINS`, `IS EMPTY`, `IS NOT EMPTY`).
- Dla `date`: date range picker.
- Dla `boolean`: toggle.

**Po wpisaniu wartości i Apply:**
- Chip pojawia się w toolbar: `[Brand IS Festo ✕]`.
- Klik na chip → otwiera popover z operator + value (edit).
- Klik na ✕ → kasuje chip.

**Ulubione settings:**
- Default top 10 (z `Attribute.is_default_favorite=true`): Brand, Kategoria, Completeness, Status, Cena, Stock, Provenance, Sync, EAN, Date created.
- User w settings (`/settings/list-filters`) wybiera własne top 10 z full list.
- Persisted per user (analog do Saved Views z epiku 02).

### 6.3 Operatory pełne (Akeneo-grade) od dnia 1

| Typ atrybutu | Operatory dostępne |
|---|---|
| `text`, `textarea`, `wysiwyg` | `=`, `≠`, `IS EMPTY`, `IS NOT EMPTY`, `STARTS WITH`, `ENDS WITH`, `CONTAINS`, `NOT CONTAINS` |
| `number`, `metric` | `=`, `≠`, `>`, `<`, `>=`, `<=`, `BETWEEN`, `IS EMPTY`, `IS NOT EMPTY` |
| `date`, `datetime` | `=`, `≠`, `>` (after), `<` (before), `BETWEEN`, `IS EMPTY`, `IS NOT EMPTY` |
| `select` | `=`, `≠`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY` |
| `multiselect` | `CONTAINS`, `NOT CONTAINS`, `IS EMPTY`, `IS NOT EMPTY` |
| `boolean` | `=` (TRUE/FALSE) |
| `relation` | `=`, `≠`, `IN`, `NOT IN`, `IS EMPTY`, `IS NOT EMPTY` |
| `asset` (image, file) | `IS EMPTY`, `IS NOT EMPTY` |

**Edge cases:**
- **Localized attributes** (`description.pl` vs `description.en`) — operator applies do *current locale only* (z decyzji Fali 2).
- **Scopable attributes** (`description.shopify` vs `description.baselinker`) — chip pokazuje channel: `[description.shopify CONTAINS "premium" ✕]`. Domyślnie filter w aktualnie wybranym channel sub-tab.
- **JSONB values** — Postgres `@>` operator dla `CONTAINS`, `?` dla `IS NOT EMPTY`. GIN index z ADR-006.

### 6.4 Per-locale search (current locale only)

**Decyzja:** klient z `language=PL` szuka *„sensor"* (EN word) → **NIC nie znajduje** (jeśli `name.pl=Czujnik`).

**UX consequence:**
- W toolbar wskazuje aktualny locale: *„🔍 Szukaj... (PL)"*. Klient wie w jakim language szuka.
- Locale picker (gdy klient ma tenant z >1 locales) — globalny w top bar, zmiana wpływa na search + grid display + filter values.
- **Cross-locale search** (Faza 1 candidate) — checkbox *„Szukaj we wszystkich językach"* w toolbar dropdown.

---

## 7. Filtry — szczegół

### 7.1 Aktywne filter chips area

**Layout:**
- Pod toolbar'em, max 1 linia (overflow → wrap do 2 linii).
- Każdy chip: `[Attribute OP Value ✕]`.
- Klik na chip body → otwiera popover z edit (operator + value).
- Klik na ✕ → kasuje filter.
- `+ Dodaj filtr` button na końcu (otwiera *„Filtruj po atrybucie"* dropdown).
- *„Wyczyść wszystkie"* link gdy >2 chip'y.

**Chip color coding:**
- 🟣 Default chip (purple-100 bg, accent text) — *„Brand IS Festo"*.
- 🔴 Special chip dla destructive filter (np. *„Marked for deletion"*) — light red bg.
- 🟢 Special chip dla AI smart filter (np. *„AI: completeness mismatch"*) — light green bg z `✨` icon.

### 7.2 Advanced filter panel (Magento push-down)

**Grid mode (default):**

```
┌─ ZAAWANSOWANE FILTRY ───────────────────────────────  [⚙ Query mode] [×] ─┐
│                                                                             │
│  Atrybut              Operator             Wartość                          │
│  ──────────────────────────────────────────────────────────────────────    │
│  [Brand ▼]            [IS ▼]               [Festo ▼ (multi)]               │
│  [Completeness ▼]     [< ▼]                [50           ] %               │
│  [Stock ▼]            [>= ▼]               [10           ]                 │
│  [Date created ▼]     [BETWEEN ▼]          [2026-01-01] – [2026-05-11]    │
│  [Description.pl ▼]   [IS NOT EMPTY ▼]                                     │
│                                                                             │
│  [+ Dodaj kolejny filtr]                                                   │
│                                                                             │
│  Logika: WSZYSTKIE warunki (AND)                                            │
│                                                                             │
│  [Reset] [Zapisz jako Saved View] [Zastosuj filtry]                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Mechanika:**
- Każdy row: attribute picker + operator picker + value input.
- Wszystkie warunki łączone AND (najpopularniejszy use case).
- Max 20 warunków (UX limit, dla większych przełącz na Query mode).
- *„Reset"* czyści wszystko.
- *„Zapisz jako Saved View"* — modal z nazwą (analog do epik 02 § 9.1).
- *„Zastosuj filtry"* — apply, chip area refresh, grid filter, panel pozostaje rozwinięty (sticky).

**Query mode (toggle dla power users):**

Jak w wireframe § 5.3. Power user feature dla AND/OR brackets, NOT logic, nested conditions.

**Migration grid ↔ query:**
- Grid → Query: każdy grid row staje się AND condition. Lossless.
- Query → Grid: tylko jeśli query *jest* płaską AND-chain. Jeśli ma OR/NOT/brackets → Grid mode blokowany z message *„Filter jest zbyt skomplikowany dla Grid mode. Pozostań w Query mode lub zresetuj."*.

### 7.3 AI smart filter — Completeness mismatch (rule-based MVP)

**Predefined filter preset** w toolbar:

```
┌─ + Filtruj po atrybucie ─────────────────────────┐
│ ...                                               │
├──────────────────────────────────────────────────┤
│ ✨ SMART FILTERS                                  │
│ ├─ Niespójne opisy (PL filled, EN empty)         │
│ ├─ Niepełne SEO (description but no meta_desc)   │
│ ├─ Brakujące zdjęcia (no main_image)              │
│ ├─ ... (4-5 predefined presets w MVP)             │
├──────────────────────────────────────────────────┤
```

**Mechanika MVP:**
- Każdy preset = predefined query against `attributes_indexed JSONB`.
- Klik → apply jako chip *„Niespójne opisy ✕"* w toolbar.
- Implementacja: serwer-side, computed na bieżącym filter context.

**Przykład preset query:**
```sql
-- "Niespójne opisy (PL filled, EN empty)"
WHERE attributes_indexed->'description'->>'pl' IS NOT NULL
  AND attributes_indexed->'description'->>'en' IS NULL
```

**Marketing positioning** (z flag § 11):
- W UI: 🟢 chip `✨ AI Smart Filter` (badge dla wow-effect).
- W pitch'u/sales: ❌ NIE *„AI smart filters"* — to brzmi misleading. Tak: *„Cross-locale completeness detection"* lub *„Rule-based quality filters"*.
- Pełen *„AI smart filters"* z LLM semantic comparison → Faza 1-2 (β + δ + ε z killer feature listy).

### 7.4 Filter persistence — URL-based

**Format URL params:**

```
/products?
  search=festo
  &brand=Festo,Bosch         (multi-value: comma-separated)
  &completeness=lt:50         (operator:value format)
  &date_created=between:2026-01-01,2026-05-11
  &description_pl=is_not_empty
  &page=2
  &per_page=50
  &sort=completeness,asc&sort=sku,asc
```

**Mechanika:**
- Każdy filter chip = URL param.
- Każdy sort = URL param.
- Pagination state = URL params.
- Reload strony → filter zachowany.
- Share link via Copy URL (epik 02 § 4.7 quick action) — kolega Magda otwiera, widzi te same produkty.
- Cross-tab navigation (Produkty → Multimedia → Produkty) — URL reset, filter pusty. Klient używa Saved View dla zachowania długoterminowego.

**Saved Views jako persisted filter sets** (z epik 02 § 9):
- Klient klika *„Zapisz jako Saved View"* → nazwa → URL z filtrami saved jako entity `saved_views`.
- *„Moje Saved Views"* dropdown w toolbar — kliknij view → URL się resetuje na zapisany.

---

## 8. Akcje zbiorcze — szczegół

### 8.1 Pełna lista 13 akcji MVP

| # | Akcja | Confirm UX | Typ ryzyka |
|---|---|---|---|
| 1 | **Set attribute value** (jeden atrybut, nowa wartość dla wszystkich) | Wizard 3-step z preview diff | Medium |
| 2 | **Clear attribute** (wyczyść wartość) | Wizard 3-step z preview diff | Medium |
| 3 | **Append to multi-value** (dodaj tag, kategorię do listy) | Wizard 3-step z preview diff | Low |
| 4 | **Remove from multi-value** (usuń tag, kategorię) | Wizard 3-step z preview diff | Medium |
| 5 | **Bulk edit multiple attributes** (zmień 3+ atrybuty naraz w 1 wizardzie) | Wizard 3-step z preview diff | Medium |
| 6 | **Increment numeric** (+10%, +50 PLN, -5%) | Wizard 3-step z preview diff | Medium |
| 7 | **Toggle enabled/disabled** | Inline toast + Undo 5s | Low |
| 8 | **Add to category** | Inline toast + Undo 5s | Low |
| 9 | **Remove from category** | Inline toast + Undo 5s | Low |
| 10 | **Move to category** (replace existing) | Wizard z preview diff | Semi-destructive |
| 11 | **Publish to channels** (multi-select kanałów) | Simple modal z opcjonalnym preview per kanał | Medium |
| 12 | **Unpublish from channels** | Simple modal | Medium |
| 13 | **Delete** | Hard confirm — typing liczby produktów | **Destructive** |
| 14 | **Duplicate** | Simple modal z opcjami (z assetami / z relacjami / bez) | Low |

(Razem 14 akcji — *„13 rozszerzony"* z Fali 3 plus duplicate z epiku 02.)

**Out of MVP, Faza 1+:**
- 15. Find & replace text w description/name z regex — Faza 2 (rules engine).
- 16. Change workflow state (`draft → review → published`) — Faza 1 (po Symfony Workflow z epiku 06).
- 17. Lock / unlock fields — Faza 1.
- 18. Change family / ObjectType — Faza 1 (wymaga migration handler).
- 19. Force resync — Faza 1.
- 20. Bulk replace main image / Bulk add to gallery — Faza 1.
- 21. Export to Excel / CSV — Faza 1 (w epiku 04 Publikacje).

**Out of scope (Faza 3+ lub never):**
- 22. Custom scripts mode (BaseLinker-style power user) — defer.

### 8.2 Wizard 3-step (Akeneo-style) dla Set/Clear/Append/Remove/Increment attribute

**Step 1: Wybór atrybutu i operacji**

```
┌─ Bulk edit — Step 1: Wybór atrybutu ─────────────────────────────────┐
│  ●○○  Atrybut  Wartość  Preview & Apply                              │
│                                                                       │
│  Zaznaczone: 247 produktów                                            │
│                                                                       │
│  Atrybut:    [Brand ▼]   (search w dropdown gdy >20 atrybutów)       │
│                                                                       │
│  Operacja:                                                            │
│    ◉ Ustaw wartość (zastąp wszystko)                                 │
│    ○ Wyczyść wartość (set NULL)                                       │
│    ○ Dodaj do listy (tylko multi-value)                              │
│    ○ Usuń z listy (tylko multi-value)                                │
│    ○ Inkrementuj (tylko number) — +/- wartość lub %                  │
│                                                                       │
│                                          [Anuluj]  [Dalej →]         │
└───────────────────────────────────────────────────────────────────────┘
```

**Step 2: Wprowadzenie wartości**

```
┌─ Bulk edit — Step 2: Wartość ─────────────────────────────────────────┐
│  ●●○  Atrybut  Wartość  Preview & Apply                              │
│                                                                       │
│  Atrybut: Brand                                                       │
│  Operacja: Ustaw wartość (zastąp)                                     │
│                                                                       │
│  Nowa wartość:                                                        │
│  [Festo ▼]   (dropdown z available brands w bazie)                   │
│                                                                       │
│  Opcje:                                                               │
│  ☑ Respect attribute locks (pomiń produkty z lockowanym `brand`)     │
│  ☐ Apply tylko gdy obecna wartość jest pusta (set if empty)          │
│                                                                       │
│                              [← Wstecz]  [Anuluj]  [Dalej →]         │
└───────────────────────────────────────────────────────────────────────┘
```

**Step 3: Preview & Apply** — Hybrid (sample 5 + aggregate)

```
┌─ Bulk edit — Step 3: Preview ─────────────────────────────────────────┐
│  ●●●  Atrybut  Wartość  Preview & Apply                              │
│                                                                       │
│  Operacja: Set `brand` = "Festo" dla 247 produktów                    │
│                                                                       │
│  ─── Statystyki ──────────────────────────────────────────           │
│  ✓ 230 produktów zostanie zmienionych                                 │
│  ⚠ 17 produktów pominiętych (locked `brand`)                          │
│                                                                       │
│  Rozkład obecnych wartości:                                           │
│  ┌──────────────────────┬──────────────┐                            │
│  │ Obecna wartość       │ Liczba prod. │                             │
│  ├──────────────────────┼──────────────┤                            │
│  │ Bosch                │ 89           │                             │
│  │ SMC                  │ 67           │                             │
│  │ (NULL / brak marki)  │ 54           │                             │
│  │ Festo                │ 20           │ ← już są, no-op            │
│  │ ... (inne)           │ 17           │                             │
│  └──────────────────────┴──────────────┘                            │
│                                                                       │
│  ─── Sample (pierwsze 5 produktów) ──────────────────────────         │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ SKU       │ Name           │ Przed → Po                     │   │
│  ├─────────────────────────────────────────────────────────────┤   │
│  │ BSC-001   │ Czujnik X-200  │ Bosch → Festo                 │   │
│  │ BSC-002   │ Zawór Y-100    │ Bosch → Festo                 │   │
│  │ BSC-003   │ Kabel KS-50    │ (brak) → Festo                │   │
│  │ SMC-101   │ Zawór SMC-A    │ SMC → Festo                   │   │
│  │ SMC-102   │ Pneumatyk B    │ SMC → Festo                   │   │
│  └─────────────────────────────────────────────────────────────┘   │
│  [Pokaż wszystkie 247 produktów]                                     │
│                                                                       │
│                              [← Wstecz]  [Anuluj]  [▶ Zastosuj]      │
└───────────────────────────────────────────────────────────────────────┘
```

**Po Apply:**
- Async via Symfony Messenger jeśli >100 produktów (z PRD § 11.2).
- Mercure SSE progress bar w toast bottom-right.
- Po success: toast *„247 produktów zaktualizowanych [Wycofaj — 24h window]"*.
- W tle: każdy produkt updated z `provenance=manual, bulk_session_id=X`.

### 8.3 Cascade impact summary modal (dla destructive)

**Bulk delete 247 produktów — modal:**

```
┌─ ⚠ Usuwasz 247 produktów ───────────────────────────────────────────┐
│                                                                      │
│  System wykrył następujące zależności:                              │
│                                                                      │
│  ─── 📤 Publikacja na kanałach ─────────────────────              │
│  • 198 produktów było published do Shopify (cofnięta publikacja)    │
│  • 220 produktów było published do BaseLinker (cofnięta)            │
│  • 147 produktów było published do Allegro (cofnięta)               │
│                                                                      │
│  ─── 🔗 Variants i relacje ──────────────────────────                │
│  • 12 produktów to master z variants (cascade: 144 variants usun.)  │
│  • 89 produktów ma cross-sell relations (89 broken relations)       │
│  • 23 produkty to variant — master pozostanie (broken variant ref)  │
│                                                                      │
│  ─── 📷 DAM (zdjęcia) ───────────────────────────────                │
│  • 1820 zdjęć NIE zostanie usuniętych (zachowane w DAM)             │
│  • Linki product↔asset zostaną zerwane                               │
│                                                                      │
│  ─── 📜 Workflow ────────────────────────────────────                │
│  • 156 produktów w stanie `approved` (zostanie usunięte)            │
│  • 78 produktów ma pending changes od agent (zostaną odrzucone)     │
│                                                                      │
│  ─── ↶ Rollback ─────────────────────────────────────                │
│  • Rollback dostępny przez 24h (do 2026-05-12 14:30)                │
│  • Partial restore: produkty w PIM, ale re-publikacja ręczna        │
│                                                                      │
│  Wpisz "247" aby potwierdzić:                                        │
│  [_____________]                                                     │
│                                                                      │
│                                   [Anuluj]  [⚠ USUŃ 247 produktów]   │
└──────────────────────────────────────────────────────────────────────┘
```

**Apply tylko dla:**
- `bulk delete` (z hard confirm typing).
- `bulk change family` (semi-destructive — variant attribute migration).

**NIE dla:**
- `bulk set attribute` (preview diff w wizard wystarczy).
- `bulk publish` (modal pokazuje per-channel impact, ale nie cascade).
- `bulk toggle enabled` (inline toast).

### 8.4 Rollback session i 24h window

**Mechanika:**

```sql
-- Każda bulk operation tworzy session
INSERT INTO bulk_sessions (id, tenant_id, user_id, action_type, target_count, ...)
VALUES ('uuid-X', 'tenant-A', 'kasia', 'set_attribute', 247, ...);

-- Każdy produkt updated z bulk_session_id
UPDATE objects
SET attributes_indexed = jsonb_set(...),
    bulk_session_id = 'uuid-X',
    updated_at = NOW()
WHERE id IN (...);

-- W tabeli bulk_logs zapisujemy stare wartości (dla rollback)
INSERT INTO bulk_logs (bulk_session_id, object_id, attribute_id, old_value, new_value)
VALUES ('uuid-X', 'prod-1', 'brand', '"Bosch"', '"Festo"'), ...;
```

**UI rollback:**
- Toast po bulk operation: *„247 produktów zaktualizowanych. [Wycofaj — dostępne do jutra 14:30]"*.
- Klik *„Wycofaj"* → confirm modal *„Cofnąć zmiany dla 247 produktów?"*.
- Po confirm: system iteruje `bulk_logs`, przywraca old_value per produkt.
- Async via Symfony Messenger (jeśli >100), progress bar.

**Rollback specific edge cases:**

**`bulk delete` rollback (partial restore):**
- Soft delete → un-soft delete produktów w PIM.
- **NIE** re-publish do kanałów automatycznie.
- Toast po rollback: *„247 produktów przywrócono w PIM. Aby ponownie opublikować, użyj 'Publish to channels'."*.

**`bulk publish to channels` rollback (always unpublish):**
- Trigger unpublish workers per kanał.
- 247 unpublish requests do Shopify / BaseLinker / Allegro.
- Progress bar: *„Cofnięcie publikacji: 156/247 ukończone"*.
- Po success: toast *„Publikacja cofnięta z 3 kanałów. 12 błędów (sprzedaż w międzyczasie / API timeout) — sprawdź raport."*.
- **Buyer aspect** — produkty mogą sprzedawać się w międzyczasie (12h window). System NIE blokuje rollback'u — klient akceptuje *„buyer może zobaczyć 'product not found'"* per decyzja Fali 4.

**Window expiration:**
- Po 24h: `bulk_sessions.expires_at < NOW()` → rollback button disabled w UI.
- Hard delete `bulk_logs` po 7 dniach (cleanup).

### 8.5 Per-attribute lock — skip + raport

**Mechanika:**
- `Object.locked_attributes JSONB` — list of `attribute_id` zablokowanych ręcznie.
- Bulk edit attribute → handler sprawdza per produkt: `IF attribute_id IN object.locked_attributes THEN skip`.
- Raport po bulk:
  - *„247 selected"*
  - *„230 updated"*
  - *„17 skipped (locked)"* — z list of SKUs jako sub-link.
- **Bez force override w MVP** — klient ręcznie un-lock'uje w detail view per produkt, potem powtarza bulk.

**Lock UX (z epiku 02 § 5.1):**
- 🔓 / 🔒 icon obok pola w detail view.
- Klik → toggle locked stan.
- Provenance update: `lock_changed_at`, `lock_changed_by`.

### 8.6 Cross-page selection (BaseLinker style)

**Layout w toolbar gdy >0 selected:**

```
┌──────────────────────────────────────────────────────────────────────────┐
│  ✓ 30 zaznaczone na tej stronie z 1247 wyników                          │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  [Zaznacz wszystkie 1247 wyniki?]    [Wyczyść zaznaczenie]       │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

**Mechanika:**
- Default: selection per-page (zaznacz na current page).
- Przy zmianie strony: selection per-page NIE persist (resetuje się przy navigation).
- Toolbar shows *„30 zaznaczone na tej stronie z 1247 wyników"* — jasna informacja.
- *„Zaznacz wszystkie 1247 wyniki?"* — upgrade do select-all-matching:
  - Po klik: counter staje się *„1247 zaznaczone (cały filter set)"*.
  - Bulk actions apply do całego filter set (nie tylko 30).
  - *„Show only selected"* checkbox staje się no-op (filter już aktywny).
- *„Wyczyść zaznaczenie"* — clear selection state.

**Edge cases:**
- Klient zaznacza 30 na page 1, zmienia stronę → selection resetuje się (per-page model).
- Klient klika *„Zaznacz wszystkie 1247"* → selection persist cross-pages.
- Klient z `select-all-matching=true` zmienia filter (np. dodaje *„Brand=Bosch"*) → automatyczny reset selection (filter change unieważnia *„zaznacz wszystkie"*).

---

## 9. Cmd+K agent integration (USP MVP)

### 9.1 Trigger i layout

**Trigger:**
- Global keyboard shortcut: `⌘K` (Mac) / `Ctrl+K` (Win/Linux).
- Button w toolbar listy (mobile-friendly fallback).
- Available w każdym widoku admin (nie tylko liście produktów).

**Layout palette (modal):**

```
┌─ ⌘K Cortex Agent ────────────────────────────────────────────────────┐
│                                                                       │
│  ⌘K [Co chcesz zrobić?                                           ]   │
│                                                                       │
│  ─── KONTEKST ────────────────────────────────────────────────       │
│  📌 30 produktów zaznaczone (filter: Brand=Festo)                    │
│                                                                       │
│  ─── PODPOWIEDZI ─────────────────────────────────────────────       │
│  💡 "ustaw kategorię na Pneumatyka dla zaznaczonych"                 │
│  💡 "wyczyść tag promo dla zaznaczonych"                             │
│  💡 "podbij cenę o 10% dla zaznaczonych"                             │
│  💡 "dodaj nowy atrybut waga_opakowania (number, kg) do rodziny"    │
│                                                                       │
│  ─── OSTATNIE KOMENDY ────────────────────────────────────────       │
│  🕐 "dodaj atrybut IP_class do rodziny Czujniki"  (wczoraj 14:30)   │
│  🕐 "ustaw enabled=false dla wszystkich Bosch"   (2 dni temu)       │
│                                                                       │
│                                                  [Esc do zamknięcia] │
└───────────────────────────────────────────────────────────────────────┘
```

### 9.2 Selection context — naturalne, bez UI komunikacji

**Per decyzji Fali 4:** agent czyta selection state z React context, interpretuje *„dla zaznaczonych jeśli >0"*.

**Mechanika:**
- React state: `selectedIds: string[]`, `selectionMode: 'per-page' | 'all-matching'`, `filterContext: FilterQuery`.
- Agent prompt input:
  ```
  Current selection: 30 products from page 1 (per-page mode)
  Active filters: brand=Festo, completeness<50
  Total matching filter: 247 products
  User selected SKUs: [BSC-001, BSC-002, ...]
  
  User command: "ustaw kategorię na Pneumatyka dla zaznaczonych"
  ```
- Agent interpretuje *„dla zaznaczonych"* jako 30 zaznaczonych. Jeśli user pominie *„zaznaczonych"* — agent pyta clarification *„Czy ustawić dla 30 zaznaczonych, dla 247 z filtrem, czy dla wszystkich w katalogu?"*.

**UI nie pokazuje explicit selection chip w palette** (decyzja Fali 4 — naturalne). Ale palette **ma sekcję KONTEKST** (wireframe wyżej) która informuje user'a co agent widzi. To kompromis — bez explicit *„dla zaznaczonych"* hint, ale z transparency co agent rozumie.

### 9.3 Scope Cmd+K w MVP — schema-ops + bulk actions

**Akceptowane intents w MVP (z epiku 08 § MVP Beta-Demo + tej decyzji):**

| Intent | Przykład command | Mapped tool | Status |
|---|---|---|---|
| `create_attribute` | *„dodaj atrybut IP_class do rodziny Czujniki"* | `tool:create_attribute` | MVP (z epiku 08) |
| `set_bulk_attribute` | *„ustaw kategorię na Pneumatyka dla zaznaczonych"* | `tool:bulk_edit_attribute` | **MVP (ten dokument)** |
| `toggle_bulk_enabled` | *„wyłącz wszystkie zaznaczone"* | `tool:bulk_toggle_enabled` | MVP |
| `increment_bulk_numeric` | *„podbij cenę o 10% dla zaznaczonych"* | `tool:bulk_increment_numeric` | MVP |
| `add_remove_category` | *„dodaj do kategorii Promocja dla zaznaczonych"* | `tool:bulk_add_to_category` | MVP |
| `publish_bulk` | *„opublikuj zaznaczone na Shopify"* | `tool:bulk_publish` | MVP |

**Faza 1+:**

| Intent | Przykład command | Status |
|---|---|---|
| `filter_products` | *„pokaż mi produkty z niespójnymi opisami"* | Faza 1 (NLP filter) |
| `delete_bulk` | *„usuń wszystkie zaznaczone"* | Faza 1 (cautious — destructive bez full UI flow) |
| `bulk_translate` | *„przetłumacz description dla zaznaczonych z PL na EN"* | Faza 2 (data-ops agent) |
| `bulk_generate_seo` | *„wygeneruj opis SEO dla zaznaczonych z atrybutów"* | Faza 2 |

### 9.4 Approval flow dla agent w bulk actions

**Każda bulk action z Cmd+K przechodzi przez ten sam flow co manual:**

1. User wpisuje *„ustaw kategorię na Pneumatyka dla zaznaczonych"*.
2. Agent (Claude) parsuje intent, generuje plan:
   - Tool call: `bulk_edit_attribute`
   - Args: `{attribute: "category", value: "Pneumatyka", target_ids: [30 selected SKUs]}`
3. **Preview** — agent pokazuje **bulk wizard Step 3 preview diff** (jak w manual flow § 8.2).
4. User klika *„Zastosuj"* → identyczne handler'y backend, identyczny rollback flow.

**Spójność:** Cmd+K NIE jest *„bypass"* dla normalnego flow — jest *„alternative input method"*. Wszystkie security/preview/rollback gates działają.

### 9.5 Anthropic koszt + BYOK

**Per command:**
- ~500-2000 tokens input (current state + selection + filter context).
- ~200-500 tokens output (tool call JSON).
- Claude Sonnet 4.5 cost: ~$0.002-0.008 per command.

**Limits z architektury § 8.5:**
- 50 tool calls/h/user.
- $20/dzień/tenant.
- BYOK domyślnie — klient płaci swój Anthropic key (Pro/Enterprise), Marcin's key tylko dla testów + demo.

---

## 10. Edge cases — szczegółowo

### 10.1 Variants w bulk operations

**Z epiku 02 § 11.1:**
- **Bulk action na master w tree mode** — confirm modal: *„Apply to master only / master + all variants / variants only?"*.
- **Default:** *„master + variants"* (cascade).
- **Bulk delete master** — blokuje jeśli master ma >0 active variants. Confirm: *„Delete master + N variants?"*.
- **Bulk change family** dla master — propagacja do variants automatyczna. Variants z `level=variant` atrybutami które nie istnieją w nowej family → warning.

### 10.2 Excel-like editing edge cases

**Z epiku 02 § 11.2:**
- Paste do read-only kolumny (Family, Categories) — alert *„Read-only column. Use detail view to edit."*.
- Paste niewłaściwego typu (text → number column) — inline validation, błąd per komórka, skip invalid rows.
- Paste do localizable column — wkleja do *current locale tab* (czyli jakiego klient widzi). NIE w wszystkich locales.
- Paste do scopable column — j.w. dla aktualnego channel sub-tab.
- Drag-fill na różnych typach komórek — działa tylko gdy wszystkie komórki w drag są tego samego typu.

### 10.3 Filter chip edge cases

- **Filter na removed attribute** (Adam usunął atrybut z Modelowania) — chip pokazuje *„Atrybut nie istnieje. [Usuń filter]"*.
- **Filter na archived value** (np. brand "Bosch" archived w Modelowaniu) — chip aktywny, ale value picker nie pokazuje. Klient może edit chip, value zostaje historyczny.
- **Conflicting filters** — *„Brand IS Festo AND Brand IS Bosch"* → empty result. UI ostrzega: *„Brak wyników. Filtry są sprzeczne."*.

### 10.4 Search edge cases

- **Empty search** — pokazuje wszystko (default).
- **Search w current filter context** — search zawęża aktualnie filtrowany zestaw, nie całość.
- **Search > 200 wyników** — pokazuje top 50 (paginacja standardowa) + warning *„200+ matches, refine your search"*.
- **Search po locked attribute** — pokazuje matches niezależnie od lock state (lock dotyczy edit, nie visibility).

### 10.5 Bulk publish channels + sales w międzyczasie

**Scenario:** Klient publish 247 produktów na Shopify o 14:30. 3 produkty (SKU A, B, C) sprzedały się w ciągu 12h. Klient o 02:30 (next day) klika *„Wycofaj publikację"*.

**System behavior (per Fali 4):**
- Trigger unpublish workers per kanał (Shopify, BaseLinker, Allegro).
- Per produkt: `DELETE` request do Shopify Admin API.
- **Shopify response dla SKU sprzedanych** — sukces unpublish (product removed from storefront). Order #ABC123 zawiera SKU A jako *„archived line item"*. Buyer email już wysłany przy purchase, kopia produktu w order. Order pozostaje OK.
- **Result toast** *„Cofnięcie publikacji: 244/247 sukcesów. 3 błędy (sprawdź raport)."*.
- **Raport:**
  ```
  SKU,channel,status,note
  A,shopify,partial,"Product unpublished. Existing order #ABC123 retains line item."
  B,shopify,partial,"Product unpublished. Existing order #ABC457 retains line item."
  C,shopify,partial,"Product unpublished. Existing order #ABC998 retains line item."
  ```

**Komunikacja w UI:**
- Toast po rollback z linkiem do raportu.
- W raporcie *„partial"* status to NIE *„błąd"* — to *„informacja: produkt cofnięty, ale order historyczny pozostaje"*.
- Klient akceptuje (decyzja Fali 4).

### 10.6 Cmd+K agent — out of context queries

**Scenario:** Klient w liście Produkty wpisuje w Cmd+K *„dodaj nowego użytkownika"* (Settings activity, nie list activity).

**Behavior:**
- Agent rozpoznaje że nie jest list/schema-ops intent.
- Response: *„Ta akcja jest w zakładce Ustawienia → Users. Czy przekierować?"*.
- Confirm → navigacja do Settings + zachowanie selection state? Pewnie nie zachowanie (user-context resetuje się przy nawigacji).

**Scenario:** Klient wpisuje *„usuń wszystkie produkty"* bez zaznaczenia.

**Behavior:**
- Agent ostrzega *„Brak zaznaczenia. Czy chcesz usunąć WSZYSTKIE 1247 produktów z bieżącego filtra? [Tak] [Nie]"*.
- Po confirm → hard confirm modal (typing liczby) jak manual flow.
- Spójność: destructive bulk zawsze przez hard confirm, niezależnie czy z Cmd+K czy manual.

---

## 11. ⚠️ Krytyczna nota — AI smart filters w MVP NIE jest AI

**Co marketing/sales materials NIE mogą używać dla MVP:**
- ❌ *„AI-powered smart filters"*
- ❌ *„AI quality check"*
- ❌ *„Semantic search"*
- ❌ *„LLM-driven product analysis"*

**Co MOŻE używać dla MVP:**
- ✅ *„Cross-locale completeness detection"*
- ✅ *„Rule-based smart filters"*
- ✅ *„Predefined quality filters"*
- ✅ *„Quick filter presets"*

**Background:** Marcin w Fali 1 wybrał (δ) *„AI smart filters"* jako killer feature. W Fali 2 wybrał (γ) *„completeness mismatch only"* jako MVP scope — co jest **rule-based SQL query**, nie AI. Pełen AI (β semantic + δ cross-channel inconsistency + ε quality dashboard) → Faza 1-2.

**Konsekwencja dla pitch deck (cortex-pim-pitch-deck-v1):**
- Slajd 4 (USP #1 Agentic-first) — *jest* AI killer (Cmd+K agent z Anthropic).
- Slajd 5 (USP #2 API Configurator) — *jest* differentiator (nie AI).
- *„AI smart filters"* — **nie wymieniać w pitch jako USP MVP**. Wymienić jako *„coming in Faza 1"* gdy klient zapyta o roadmap.

---

## 12. User stories

### 12.1 Wyszukiwarka + filtry

| ID | Persona | Story |
|---|---|---|
| US-LIST-001 | Kasia | Wpisuje *„BSC"* w quick search → znajduje produkty zaczynające się od `BSC` w SKU lub name |
| US-LIST-002 | Kasia | Wpisuje *„Festo"* w quick search → znajduje wszystkie produkty z `brand=Festo` (bez explicit filter chip) |
| US-LIST-003 | Magda | Klika *„+ Filtruj po atrybucie"* → wybiera `Description PL` z dropdown → operator `IS EMPTY` → chip w toolbar |
| US-LIST-004 | Kasia | Otwiera Advanced filter panel (push-down) → konfiguruje 4 filtry w grid mode → klika *„Zapisz jako Saved View"* (*„Festo niski completeness"*) |
| US-LIST-005 | Tomasz | Otwiera Saved View Kasi przez URL share → widzi te same produkty + filtry |
| US-LIST-006 | Magda | Toggle do Query mode w Advanced panel → buduje query *„Brand IS Festo AND (Stock >= 10 OR Completeness >= 80)"* |
| US-LIST-007 | Kasia | Klika preset *„✨ Niespójne opisy (PL filled, EN empty)"* → chip w toolbar, lista filtrowana |
| US-LIST-008 | Kasia | Filter URL `/products?brand=Festo` → reload strony → filter zachowany |
| US-LIST-009 | Kasia | W settings (`/settings/list-filters`) wybiera 10 ulubionych atrybutów z full list |

### 12.2 Akcje zbiorcze

| ID | Persona | Story |
|---|---|---|
| US-LIST-010 | Kasia | Zaznacza 30 produktów na page 1 → klika *„Zaznacz wszystkie 1247 wyniki?"* → bulk delete z hard confirm typing *„1247"* |
| US-LIST-011 | Magda | Bulk edit `description.pl` dla 247 produktów Festo → wizard 3-step → preview diff z sample 5 + aggregate counter → Apply |
| US-LIST-012 | Kasia | Bulk publish 50 produktów na Shopify + BaseLinker → simple modal → async progress z Mercure SSE |
| US-LIST-013 | Marcin (dogfooding) | Omyłkowo bulk delete 1000 produktów → toast z *„[Wycofaj — dostępne do jutra 14:30]"* → klika → partial restore w PIM (bez re-publish) |
| US-LIST-014 | Magda | Bulk publish 247 produktów na Shopify, 3 sprzedały się w 12h, klika *„Wycofaj"* → unpublish 244 sukces + 3 partial z linkiem do raportu |
| US-LIST-015 | Kasia | Bulk edit attribute `description.pl` z 17 produktów lockowanymi → wizard pokazuje *„17 skipped (locked)"* w preview |
| US-LIST-016 | Kasia | Increment numeric `price *= 1.1` dla 50 produktów → wizard pokazuje rozkład *„avg before: 245.50, avg after: 270.05"* |
| US-LIST-017 | Magda | Bulk duplicate 20 produktów z opcjami *„z assetami / bez relacji"* → 20 nowych produktów stworzonych z `provenance=duplicate, source_id=X` |
| US-LIST-018 | Kasia | Bulk delete master + 12 variants (cascade) → cascade impact modal pokazuje *„12 master + 144 variants do usunięcia"* |

### 12.3 Cmd+K agent

| ID | Persona | Story |
|---|---|---|
| US-LIST-019 | Kasia | Cmd+K → *„dla zaznaczonych 30 ustaw kategorię na Pneumatyka"* → agent generuje tool call → preview diff → Apply |
| US-LIST-020 | Magda | Cmd+K → *„podbij cenę o 10% dla zaznaczonych"* → agent generuje increment numeric tool call → preview rozkład → Apply |
| US-LIST-021 | Kasia | Cmd+K bez zaznaczenia → *„usuń wszystkie produkty z brand Bosch"* → agent ostrzega *„Brak zaznaczenia. Usunąć wszystkie 89 produktów z bieżącego filtra Bosch?"* → confirm → hard confirm typing *„89"* |
| US-LIST-022 | Marcin (dogfooding) | Cmd+K → *„dodaj atrybut waga_opakowania (number, kg) do rodziny Elektronika"* → schema-ops tool call (z epiku 08) → modelowanie update |

---

## 13. Business rules

### 13.1 Performance

- **Filter query latency** — Postgres GIN index z ADR-006 + Meilisearch dla quick search. Target: p95 <300ms na 200k SKU.
- **Bulk operations async threshold** — >100 produktów → async via Symfony Messenger. <100 → sync z spinner.
- **Cmd+K response time** — Anthropic Claude Sonnet 4.5, target: <3s end-to-end (input + reasoning + tool call).
- **Filter chip render** — debounce 200ms (nie re-fetch przy każdym keystroke).

### 13.2 Permission rules (MVP brak gating)

- Wszyscy user'zy mogą:
  - Wyszukiwać i filtrować bez ograniczeń.
  - Bulk edit/delete/publish bez explicit role check.
- Audit log każdej akcji (Doctrine AuditBundle z epiku 0.11.4):
  - User_id, timestamp, action, target_ids[], old_values, new_values.
  - Rollback path zachowany przez 7 dni (potem hard delete logs).

### 13.3 Concurrency rules

- **Same user, multiple bulk ops** — allow + warn *„Już jeden bulk job w toku"*. Drugi w queue.
- **Different users on same products** — allow concurrent edits. Last-write-wins. Audit log shows order.
- **Bulk + manual edit race** — pesimistic locking per object_id przy bulk handler write. Manual edit czeka.

### 13.4 Saved Views integration

- Filter z Advanced panel → *„Zapisz jako Saved View"* → entity `saved_views` (z epiku 02 § 9).
- Saved Views = solo only (per user, z epiku 02).
- URL share z Saved View name → odbiorca widzi filter, ale nie ma save'd entity. Może *„Zapisz jako swoją Saved View"*.

---

## 14. Dependency na backend

### 14.1 Encje + tabele (delta vs aktualnego planu)

```sql
-- Sesje bulk operations (analog do import_sessions z feature-imports.md)
CREATE TABLE bulk_sessions (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID NOT NULL REFERENCES users(id),
    action_type VARCHAR(64) NOT NULL,   -- set_attribute, delete, publish_channels, etc.
    target_object_ids UUID[] NOT NULL,
    target_count INTEGER NOT NULL,
    success_count INTEGER NOT NULL DEFAULT 0,
    skipped_count INTEGER NOT NULL DEFAULT 0,  -- locked attrs
    error_count INTEGER NOT NULL DEFAULT 0,
    action_payload JSONB NOT NULL,        -- attribute_id, new_value, channels, etc.
    rollback_available_until TIMESTAMPTZ,
    rolled_back_at TIMESTAMPTZ,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ,
    source VARCHAR(16) NOT NULL DEFAULT 'manual', -- manual, cmd_k_agent
    cmd_k_command TEXT  -- jeśli source=cmd_k_agent, oryginał command
);

-- Logi per produkt w bulk (dla rollback)
CREATE TABLE bulk_logs (
    id UUID PRIMARY KEY,
    bulk_session_id UUID NOT NULL REFERENCES bulk_sessions(id) ON DELETE CASCADE,
    object_id UUID NOT NULL REFERENCES objects(id),
    attribute_id UUID REFERENCES attributes(id),  -- NULL dla destructive ops
    old_value JSONB,
    new_value JSONB,
    level VARCHAR(8) NOT NULL,  -- info, warning, error
    message TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_bulk_logs_session ON bulk_logs(bulk_session_id);
CREATE INDEX idx_bulk_logs_object ON bulk_logs(object_id);

-- Update: objects
ALTER TABLE objects ADD COLUMN bulk_session_id UUID REFERENCES bulk_sessions(id);
CREATE INDEX idx_objects_bulk_session ON objects(bulk_session_id) WHERE bulk_session_id IS NOT NULL;
ALTER TABLE objects ADD COLUMN locked_attributes JSONB DEFAULT '[]'::JSONB;
CREATE INDEX idx_objects_locked_attrs ON objects USING GIN(locked_attributes);

-- Saved filter presets (rule-based AI smart filters)
CREATE TABLE smart_filter_presets (
    id UUID PRIMARY KEY,
    tenant_id UUID,                       -- NULL = global preset (system-shipped)
    user_id UUID REFERENCES users(id),    -- NULL = tenant-shared (Faza 1+)
    name JSONB NOT NULL,                  -- {"pl": "Niespójne opisy", "en": "Inconsistent descriptions"}
    icon VARCHAR(16),
    query JSONB NOT NULL,                 -- filter query w naszym DSL
    is_built_in BOOLEAN NOT NULL DEFAULT false,
    sort_order INTEGER DEFAULT 0
);

-- Seed: 4-5 built-in presets dla MVP
INSERT INTO smart_filter_presets (id, name, icon, query, is_built_in)
VALUES
  ('uuid-1', '{"pl":"Niespójne opisy"}', '🌐', '{"description.pl":"IS_NOT_EMPTY","description.en":"IS_EMPTY"}', true),
  ('uuid-2', '{"pl":"Brakujące zdjęcia"}', '📷', '{"main_image":"IS_EMPTY"}', true),
  ('uuid-3', '{"pl":"Niepełne SEO"}', '🔍', '{"description":"IS_NOT_EMPTY","meta_description":"IS_EMPTY"}', true),
  ('uuid-4', '{"pl":"Czerwone (<50% complete)"}', '🔴', '{"completeness_pct":{"op":"<","value":50}}', true),
  ('uuid-5', '{"pl":"Bez kategorii"}', '📂', '{"category":"IS_EMPTY"}', true);

-- User favorite filter attributes (top 10 dropdown)
CREATE TABLE user_filter_favorites (
    user_id UUID NOT NULL REFERENCES users(id),
    attribute_id UUID NOT NULL REFERENCES attributes(id),
    sort_order INTEGER NOT NULL,
    PRIMARY KEY (user_id, attribute_id)
);

-- Default top 10 dla każdego nowego user'a (seed via listener po user create)
```

### 14.2 API endpoints

| Endpoint | Metoda | Cel |
|---|---|---|
| `/api/products/search` | GET | Quick search z params `q`, `filters`, `sort`, `page` |
| `/api/products/filter-presets` | GET | Lista smart filter presets (built-in + user) |
| `/api/products/filter-presets/{id}/apply` | POST | Apply preset → returns matching product IDs |
| `/api/products/bulk-actions/edit-attribute` | POST | Body: `{target_ids[], attribute_id, operation, value, respect_locks}` |
| `/api/products/bulk-actions/delete` | POST | Hard confirm required (count in body) |
| `/api/products/bulk-actions/publish` | POST | Body: `{target_ids[], channels[]}` |
| `/api/products/bulk-actions/unpublish` | POST | j.w. |
| `/api/products/bulk-actions/duplicate` | POST | Body: `{target_ids[], with_assets, with_relations}` |
| `/api/bulk-sessions` | GET | Lista user's bulk sessions z filters |
| `/api/bulk-sessions/{id}` | GET | Status + counts + logs |
| `/api/bulk-sessions/{id}/rollback` | POST | Trigger rollback (within 24h) |
| `/api/bulk-sessions/{id}/report.csv` | GET | Download raport CSV |
| `/api/user/filter-favorites` | GET/PUT | CRUD user's top 10 atrybutów |
| `/api/cmd-k/parse` | POST | Body: `{command, context}` — Anthropic call, returns tool call JSON + preview |
| `/api/cmd-k/execute` | POST | Body: `{tool_call_json}` — execute pre-validated tool call |

### 14.3 Symfony Messenger handlers

- `BulkEditAttributeHandler` extends `AbstractBatchHandler` (`EntityManager::clear()` per chunk N=200).
- `BulkDeleteHandler` — soft delete + cascade unpublish channels (Faza 1+).
- `BulkPublishHandler` — per-channel sync workers + Mercure progress.
- `BulkRollbackHandler` — iteruje `bulk_logs`, restore old values, audit entries.
- `CmdKAgentHandler` — Anthropic API call + tool dispatch.

### 14.4 Mercure SSE channels

- `bulk-operations.{user_id}` — wszystkie bulk operations tego usera (lista live updates).
- `bulk-operations.{session_id}` — pojedyncza operacja progress (subscribed gdy user otwiera detail).

### 14.5 Doctrine listeners

- `ProductCompletenessIndexListener` — przy `Object` postUpdate, recompute completeness + per-locale checks. Update `attributes_indexed.completeness_per_locale JSONB`.
- `BulkSessionAuditListener` — przy każdy `Object` change w bulk, log do `bulk_logs`.
- `FilterFavoriteSeedListener` — przy user creation, seed default top 10 atrybutów.

---

## 15. Komponenty Refine + shadcn

### 15.1 Refine resources

- `Refine.Resource("products")` — extends z epiku 02.
- `Refine.Resource("bulk-sessions")` — list, show, rollback action.
- `Refine.Resource("smart-filter-presets")` — list, apply action.
- Custom hooks:
  - `useFilterUrlState()` — URL-based filter persistence (params ↔ filter state sync).
  - `useBulkOperation(actionType)` — generic bulk handler z async + progress.
  - `useSelectionState()` — per-page vs all-matching selection model.
  - `useCmdKAgent()` — Cmd+K trigger + Anthropic call wrapper.

### 15.2 shadcn components

- `Tabs`, `Form`, `Input`, `Select`, `Combobox`, `Switch`, `Button`, `Badge`, `Tooltip`, `Card`, `Dialog`, `Sheet`, `Popover`, `DropdownMenu`, `Progress`, `Toast` (sonner), `Skeleton`.
- Plus `Command` (shadcn-cmdk wrapper) dla Cmd+K palette.

### 15.3 Custom components

| Komponent | Rola |
|---|---|
| `ProductListPage` | Root z layoutem toolbar + filter panel + grid |
| `ListToolbar` | Search bar + Filtruj dropdown + Filtry toggle + action bar |
| `QuickSearchInput` | Z debounce 800ms + Enter submit, Meilisearch backend |
| `FilterByAttributeDropdown` | Favorite top 10 + *„Inne atrybuty"* link do modal |
| `OperatorPicker` | Per-type operator dropdown (`=`, `≠`, `STARTS WITH`, etc.) |
| `FilterChipArea` | Aktywne chip filters z edit popover + ✕ kasuje |
| `AdvancedFilterPanel` | Push-down sticky-collapsible, hybrid grid/query mode |
| `FilterGridMode` | Grid 3-4 kolumny attr+op+value |
| `FilterQueryMode` | AND/OR brackets builder |
| `SmartFilterPresetsList` | Sekcja w *„Filtruj"* dropdown z built-in presets |
| `BulkSelectionToolbar` | BaseLinker style — *„30 zaznaczone z 1247"* + upgrade button |
| `BulkActionsToolbar` | Sticky-bottom z 13 akcjami |
| `BulkEditWizard` | 3-step z preview diff |
| `BulkPreviewDiff` | Sample 5 + aggregate counter |
| `CascadeImpactModal` | Pełen impact summary (channels + variants + relations + DAM + workflow) |
| `HardConfirmModal` | Typing N produktów dla destructive |
| `BulkProgressToast` | Mercure SSE progress + cancel |
| `BulkRollbackToast` | Z 24h window countdown + undo button |
| `CmdKPalette` | Modal z input + kontekst sekcja + podpowiedzi |
| `CmdKContextSection` | Pokazuje *„30 zaznaczone, filter: Brand=Festo"* w palette |
| `CmdKSuggestions` | Predefined + last-used commands |
| `LockedAttributesBadge` | 🔒 / 🔓 icon w detail view obok pola |
| `FilterUrlSerializer` | Helpers do URL ↔ filter state ↔ Saved View |

---

## 16. Open questions

- [ ] **Bundle size** — Advanced filter panel + Cmd+K + bulk wizard razem ~150-200 KB. Code-split per feature (lazy load Advanced panel + Cmd+K).
- [ ] **Operator pełne w Faza 1 czy MVP** — Marcin wybrał Akeneo-style pełne od dnia 1 (β). Implementacja: ~10-14h. Czy *wszystkie* operatory per type w MVP, czy *core* (`=`, `≠`, `IS EMPTY`, `IS NOT EMPTY`) w MVP + reszta Faza 1?
- [ ] **Query mode UX validation** — power user feature, ale UX nie trywialny. POC w Sprint? Może użyć biblioteki (react-querybuilder, MIT, ~50 KB)?
- [ ] **Multi-channel filter** — *„description scopable na Shopify ma X"* — jak UX-owo? Default current channel sub-tab + override przez chip?
- [ ] **Smart filter presets — user-defined?** — w MVP built-in only. Faza 1: user może *„Zapisz Saved View jako Smart Preset"* (analog Saved Views ale dla filter previews).
- [ ] **Cmd+K NLP filter — Faza 1 release** — kiedy *„pokaż mi produkty z niespójnymi opisami"* jako natural language (vs preset click)? Faza 1 z plate-ai integration?
- [ ] **Bulk rollback dla `publish` — sales sync** — czy mamy `orders` table w bazie (sync z kanałów)? Jeśli nie, *„skip sold-since-publish"* niemożliwe → must accept *„best effort + raport"* approach.
- [ ] **Per-attribute lock force override** — w MVP brak. Faza 1: super_admin może force override z confirmation?
- [ ] **Select-all-matching limit** — jeśli filter result = 50000 produktów, klient klika *„Zaznacz wszystkie"* → bulk operation na 50k rows. Limit: 10k? 100k? Niewskazane?
- [ ] **Search performance edge cases** — query z 5+ słowami: *„czujnik indukcyjny Festo 24V IP67"* — strict prefix nie zadziała. Klient potrzebuje *„at least one word matches"* — Meilisearch domyślnie tak działa, ale to nie jest prefix. Reconfirm semantic.
- [ ] **Mobile UX** — z PRD § 4.4 epiku 02 brak responsive, scroll horizontal. Cmd+K na mobile (touch device) — button w toolbar zamiast keyboard shortcut.

---

## 17. Wpływ na backend roadmap

Feature wpływa na:

- **Epik 0.4 (API Platform)** — dochodzą endpointy: `/products/search`, `/products/bulk-actions/*`, `/bulk-sessions/*`, `/cmd-k/*`, `/user/filter-favorites`. **Estymacja: +14-20h** ponad obecny scope.
- **Epik 0.5 (Search Meilisearch)** — search po 5 polach (SKU+name+EAN+brand+tags) + diacritic-insensitive. **Estymacja: +4-6h**.
- **Epik 0.6 (Admin UI)** — Advanced filter panel + bulk wizard + cascade modal + Cmd+K + smart filter presets. **Estymacja: +95-130h** (dominant cost).
- **Epik 0.7 (Agent layer)** — Cmd+K rozszerzenie z schema-ops (epik 08 MVP demo) na bulk actions na zaznaczonych. **Estymacja: +12-16h** ponad obecny Beta-Demo scope.
- **Epik 0.11 (Hardening)** — bulk_sessions encja, rollback worker, audit integration. **Estymacja: +6-10h**.

**Total impact na Fazę 0:** **+131-182h**.

Aktualny budżet z PRD ~310-440h (po MVP-Beta-Demo + ADR-009/010/011/012 + epik 02 + epik 08 + feature-imports). Po dodaniu tego feature'a: **~440-620h**.

**Marcin akceptuje +50-80h scope (PRD § 12.1).** Po tej iteracji jesteśmy **~3-4× ponad limit**. To jest **świadoma decyzja** Marcina (*„nie spieszę się, robimy żeby było dobrze"*), ale trzeba to flag'ować w PRD § 7 wycena update.

**Drivery scope'u:**
- Advanced filter panel (grid + query mode) z operatorami pełnymi: ~30-40h.
- Bulk actions toolbar + 13 akcji handlers: ~40-50h.
- Bulk wizard 3-step z preview diff: ~16-22h.
- Cascade impact modal + hard confirm + rollback: ~12-16h.
- Cmd+K agent integration (rozszerzenie Beta-Demo): ~12-16h.
- Smart filter presets (rule-based): ~6-8h.
- URL-based filter persistence: ~4-6h.
- Cross-page selection BaseLinker-style: ~4-6h.
- Per-attribute lock UX w bulk: ~4-6h.

---

## 18. Co dalej

1. **Walidacja koncepcji** z Marcinem — czy Advanced filter panel hybrid (grid + query) UX-owo działa, czy POC w Sprint 1.
2. **POC operatory pełne od dnia 1** — Akeneo-grade implementacja w Sprint 1 z benchmark complexity vs `=` only.
3. **Wireframes w Figma** — przekazać external UX designer'owi (PRD § 13.5).
4. **POC Cmd+K w liście** — sprint 1 zbudować Cmd+K palette z mock agent (rules-based intent parsing) zanim Anthropic integration.
5. **Decyzja scope: cuts** — Marcin akceptuje +131-182h, ale można rozważyć:
   - (a) Query mode AND/OR — Faza 1 (oszczędność ~12-16h MVP).
   - (b) Cmd+K bulk actions — Faza 1 (oszczędność ~12-16h MVP). Pozostaje schema-ops only w MVP (epik 08).
   - (c) Increment numeric + multi-attr bulk edit — Faza 1 (oszczędność ~8-10h MVP).
6. **Aktualizacja `Project Plan/02-plan-projektu-pim.md`** epik 0.4/0.5/0.6/0.7/0.11 estymacji + nowe ryzyko *„R-30 List feature scope creep"*.
7. **Aktualizacja `Project Plan/03-funkcjonalnosci-mvp.md`** — dodać user stories US-LIST-001 do US-LIST-022.
8. **Update `epik-02-produkty.md`** — add link do tego dokumentu w § 4 List view + § 11 edge cases.

---

*Plik wersjonowany w `Project Plan/UI/`. Status: szczegół. Następna iteracja: walidacja z Marcinem + Figma wireframes + POC operatory + POC Cmd+K mock.*
