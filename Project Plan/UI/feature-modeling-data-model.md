# Feature (mini-spec) — Model danych Modelowania: dystrybucja atrybutów + relacje

## Status: 🟢 szczegół (mini-spec) — kontrakt implementacyjny dla ADR-014

> **Mini-spec** realizujący [ADR-014](../01-architektura-pim.md) (*„Model dystrybucji atrybutów + relacje obiekt↔obiekt"*). Powstał z sesji burzy mózgów 2026-05-23 po wykryciu 4 nieścisłości w Modelowaniu przy ~60% systemu.
> **Rewiduje:** ADR-012 w 3 punktach (Brand built-in, `category_attribute_groups` semantyka, `EffectiveAttributeGroupResolver` źródło bazowe).
> **Wzorzec:** Akeneo (dziedziczenie grup po drzewie) + Pimcore (relacja jako typ pola, capability flags) + świadome uproszczenie (primary category zamiast osobnego bytu Family).

---

## 1. Cel

Naprawić niespójny model dystrybucji atrybutów. Root cause: system zahardkodował kategorię jako jedyny mechanizm przydziału atrybutów — działa dla Produktu, łamie się dla każdego nie-kategoryzowanego ObjectType. Mini-spec ustala **jeden spójny model** obejmujący: ObjectType base + primary category overlay, relacje obiekt↔obiekt, capability flags, naprawę objawu #3-#28, migrację „Marki".

## 2. Model dystrybucji atrybutów

### 2.1 Dwa źródła atrybutów

Każda instancja obiektu dostaje atrybuty z **dwóch warstw**:

```
WARSTWA 1 — ObjectType base (ZAWSZE, dla każdego ObjectType)
   Atrybuty / grupy atrybutów przypisane bezpośrednio do ObjectType.
   Działa identycznie dla Product, Category, Asset, Service, Brand — wszystkich.
   To naprawia objaw #3-#28: Category-jako-ObjectType renderuje swoje
   atrybuty bazowe (name, seo_title...) niezależnie od kategoryzacji.

WARSTWA 2 — Primary category overlay (TYLKO ObjectType z is_categorizable=true)
   Instancja ma 1 kategorię główną (primary). Jej ścieżka w drzewie
   kategorii dodaje kontekstowe grupy atrybutów — KUMULATYWNIE root→leaf.
```

### 2.2 Cumulative resolution po ścieżce drzewa

Produkt z primary category liść `Elektronika > RTV > Telewizory`:

```
ObjectType "Product" base:        name, sku, price, status        (warstwa 1)
+ kategoria "Elektronika":        gwarancja, marka                 (warstwa 2, root)
+ kategoria "RTV":                klasa_energetyczna                (warstwa 2)
+ kategoria "Telewizory":         przekatna, rozdzielczosc          (warstwa 2, liść)
─────────────────────────────────────────────────────────────────
= efektywny zestaw atrybutów produktu (suma)
```

Wspólne atrybuty definiowane wysoko w drzewie (DRY), specyficzne nisko. `EffectiveAttributeGroupResolver` (z ADR-012) parametryzowany **primary category path**, nie zbiorem wszystkich kategorii obiektu.

### 2.3 Primary vs secondary categories

- **Primary category** — dokładnie 1 per obiekt kategoryzowalny. Jest attribute-driverem (warstwa 2). Wybierana w modalu tworzenia obiektu (krok 1, wymuszone — zaczytuje atrybuty do formatki).
- **Secondary categories** — N dodatkowych. Wyłącznie klasyfikacja / nawigacja w drzewie. Zero wpływu na atrybuty.

### 2.4 Capability flags ObjectType

| Flaga | Znaczenie | Domyślnie |
|---|---|---|
| `expose_to_main_menu` *(canonical column name; we dropped `show_in_main_menu` rename — column existed since VIEW-08 / #427)* | Czy ObjectType ma własną pozycję w sidebar admina | `true` dla standalone (Product, Service) |
| `is_categorizable` | Czy instancje mają kategorię główną (warstwa 2 aktywna) | `true` Product, `false` Category/Asset/Brand |

**Brak flagi `is_relation_target`** — każdy ObjectType może być celem relacji domyślnie (decyzja: relacja jest własnością ObjectType-źródła, nie targetu).

## 3. Relacje obiekt↔obiekt

### 3.1 Relacja = typ atrybutu `relation`

Relacja **nie jest osobnym bytem** — to typ atrybutu obok `text`, `number`, `select`, `asset`. W Modelowaniu ObjectType dodajesz atrybut typu `relation` z konfiguracją:

```
Atrybut typu `relation`:
  ├─ target_object_type:  [Brand]  lub multi: [Product, Service]
  ├─ cardinality:         one (jedna referencja) | many (lista)
  ├─ advanced:            false (prosta referencja)
  │                       true  (relacja z metadanymi — własne pola na powiązaniu)
  └─ attribute_group:     dowolna grupa (np. "Powiązania")
```

### 3.2 Reverse relations (Pimcore-style)

Relacje są **dwukierunkowe w odczycie**. Product A ma `up_sell → Product B`. Obiekt B w zakładce „Powiązania" widzi auto-generowaną **read-only** sekcję *„Powiązania zwrotne: jestem up-sellem dla → [A]"*. Reverse generowany przez query po `object_relations`, nieedytowalny (edycja tylko po stronie źródła).

### 3.3 Advanced relations (metadane na powiązaniu)

Gdy `advanced=true`, powiązanie ma **własne pola** (np. Product → akcesoria, każde akcesorium z polem `priorytet` / `rekomendowane`). Metadane w `object_relations.metadata JSONB`. UI grid powiązań pozwala edytować metadane per wiersz.

### 3.4 Asset = osobny typ (nie relacja)

Typ atrybutu `asset` pozostaje **odrębny** od `relation`. Asset ma specjalizowany UX (DAM picker, miniaturka, kadrowanie, upload) — ujednolicanie z `relation` byłoby sztuczne. `main_image`, `gallery` to typ `asset`, nie `relation→Asset`.

### 3.5 Zakładka „Powiązania"

**Decyzja (ADR-014, Opcja 2 — MODR-01..10):** Atrybut typu `relation` to **zwykły atrybut**. Placement na karcie obiektu (zakładka vs. sekcja inline) wynika wyłącznie z `AttributeGroup` + jej `display_mode` na junction `object_type_attribute_groups` (kolumna `display_mode VARCHAR(8) NOT NULL DEFAULT 'tab' CHECK IN ('tab','stacked')` — MODR-01 #923). **Widget dobierany jest po typie atrybutu** (`relation` → picker + grid + preview card; `text` → input), ale samo umiejscowienie idzie po grupie. Brak hardcoded ścieżki dla typu `relation`.

Zakładka „Powiązania" = render seedowanej grupy `relations` (`is_system_group=true`, `display_mode='tab'`, MODR-02 #924) — operator może w wizardzie / detalu ObjectType (MODR-04 #926) przerzucić ją na `stacked`, wtedy renderuje się jako sekcja inline pod zakładką „Atrybuty". Tak samo działa zaktualizowana seedowana grupa `media` (MODR-02 #924) — wcześniej Multimedia była hardcoded jako osobna zakładka, teraz jest standardową AttributeGroup. Audit pozostaje `display_mode='stacked'` (MODR-03 #925 + migracja `Version20260524160000`) by zachować historyczny układ.

**Edge case widoczności zakładki „Powiązania"** (MODR-06 #928): tab jest widoczna gdy *(a)* seedowana grupa `relations` ma ≥ 1 atrybut **LUB** *(b)* obiekt ma ≥ 1 powiązanie **zwrotne** (`object_relations.target_object_id = ten obiekt`). Lekka probowa-flaga: `GET /api/objects/{id}/relations/reverse/count` → `{hasReverse: bool, count: int}`. Jeśli zakładka pojawia się wyłącznie z powodu reverse, renderuje wyłącznie read-only sekcję „Powiązania zwrotne".

**Konfigurator atrybutu `relation`** (MODR-07 #929) pre-zaznacza grupę `relations` jako domyślny target — leniwa ścieżka skupia relacje w jednej zakładce, świadoma ścieżka pozwala umieścić atrybut w dowolnej grupie (np. `producent` → grupa „Tożsamość").

**Rich preview card + inline edit** (MODR-08 #930 + MODR-10 #932): widget relacji renderuje powiązane obiekty jako karty (`code + name + ObjectType chip`), z batchowym fetchem `POST /api/objects/summaries`. Operator może rozwinąć kartę i edytować pola targetu w miejscu (`PATCH /api/{products|categories|assets}/{id}` z `expectedVersion` → 409 przy stale data; `objects.version` + Doctrine `@Version`).

## 4. Schema (delta)

> **Stan w kodzie (po MOD-01 / #893):** część schematu była już zaimplementowana wcześniejszymi ticketami. MOD-01 dodaje **tylko 4 nowe kolumny** (1 na `object_types`, 3 na `attributes`). Junction `object_categories` z `is_primary` istnieje od PCAT-01 (`Version20260510221123`). `AttributeType::Relation` enum case istniał od pierwszej iteracji backlogu (#31).

```sql
-- ObjectType — capability flags
-- show_in_main_menu reuses existing `expose_to_main_menu` column (VIEW-08 / #427); brak rename.
ALTER TABLE object_types ADD COLUMN is_categorizable BOOLEAN NOT NULL DEFAULT false;

-- Obiekt ↔ kategoria — primary flag (JUŻ ISTNIEJE od PCAT-01 / Version20260510221123)
-- ALTER TABLE object_categories ADD COLUMN is_primary BOOLEAN NOT NULL DEFAULT false;
-- CREATE UNIQUE INDEX object_categories_one_primary_per_object
--     ON object_categories(object_id) WHERE is_primary = true;

-- Atrybut typu relation — konfiguracja (MOD-01 / #893)
ALTER TABLE attributes ADD COLUMN relation_target_object_type_ids JSONB NOT NULL DEFAULT '[]'::JSONB;
ALTER TABLE attributes ADD COLUMN relation_cardinality VARCHAR(8) DEFAULT NULL;
ALTER TABLE attributes ADD CONSTRAINT attributes_relation_cardinality_chk
    CHECK (relation_cardinality IS NULL OR relation_cardinality IN ('one', 'many'));
ALTER TABLE attributes ADD COLUMN relation_advanced BOOLEAN NOT NULL DEFAULT false;
-- type='relation' już istnieje w enumie AttributeType (AttributeType.php:36) od #31

-- Placement grupy atrybutów per ObjectType (MODR-01 / #923)
ALTER TABLE object_type_attribute_groups
    ADD COLUMN display_mode VARCHAR(8) NOT NULL DEFAULT 'tab'
    CHECK (display_mode IN ('tab', 'stacked'));

-- Preview fields dla rich preview card relacji (MODR-08 / #930)
ALTER TABLE attributes ADD COLUMN relation_preview_fields JSONB NOT NULL DEFAULT '[]'::JSONB;

-- Optimistic locking dla inline-edit z relation widget (MODR-10 / #932)
ALTER TABLE objects ADD COLUMN version INTEGER NOT NULL DEFAULT 1;

-- Powiązania obiekt↔obiekt
CREATE TABLE object_relations (
    id                UUID PRIMARY KEY,
    tenant_id         UUID NOT NULL REFERENCES tenants(id),
    source_object_id  UUID NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
    target_object_id  UUID NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
    attribute_id      UUID NOT NULL REFERENCES attributes(id),  -- który atrybut relation
    position          INTEGER NOT NULL DEFAULT 0,               -- kolejność w grid (many)
    metadata          JSONB DEFAULT '{}'::JSONB,                 -- advanced relations
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_object_relations_source ON object_relations(source_object_id, attribute_id);
CREATE INDEX idx_object_relations_target ON object_relations(target_object_id);  -- reverse lookup
```

**Uwagi:**
- `idx_object_relations_target` — krytyczny dla reverse relations performance przy 200k+ SKU.
- **`object_associations` (ADR-009) zastępowane przez `object_relations`** (decyzja 2026-05-23). ADR-009 miał hardcoded enum `type` (cross_sell/up_sell/related/alternative/accessory). ADR-014 generalizuje: każdy typ → seedowany built-in atrybut typu `relation` na ObjectType Product. Migracja: utworzyć 5 atrybutów `relation`, przepisać wiersze `object_associations` (`type` → odpowiedni `attribute_id`) na `object_relations`, DROP `object_associations`.
- Orphaned values — brak zmiany schematu; `object_values` zachowuje wartości atrybutów spoza efektywnego modelu (filtrowane przy renderze formularza).

## 5. Kolizja kodów + konwencja nazewnicza

- Kod atrybutu **globalnie unikalny w obrębie ObjectType**. Atrybut jest albo bazowy (warstwa 1), albo kontekstowy (warstwa 2 — przypisany do kategorii) — nigdy oba.
- Walidacja w Modelowaniu **blokuje** dodanie atrybutu o kodzie który już istnieje w modelu ObjectType (bazowym lub w którejkolwiek kategorii dystrybuującej do tego ObjectType).
- **Konwencja:** atrybuty kontekstowe z sufiksem kategorii — `opis_telewizory`, `opis_buty`, `material_buty`. Zapobiega kolizjom, czyni model czytelnym (od razu widać do której kategorii atrybut należy).

## 6. Orphaned values (zmiana primary category)

Telewizor ma `przekatna=55"`. Zmiana primary category na „Pralki" → `przekatna` znika z efektywnego modelu. Wartość:
- **Pozostaje w `object_values`** — ukryta, nieedytowalna.
- Powrót do kategorii „Telewizory" → wartość wraca, widoczna.
- UI formularza nie renderuje orphaned values (są poza efektywnym modelem). Opcjonalnie: sekcja diagnostyczna *„wartości spoza modelu"* — Faza 1+, nie MVP.

## 7. UX

### 7.1 Modal tworzenia obiektu (kategoryzowalny ObjectType)

```
┌─ Nowy Produkt — krok 1: Kategoria główna ──────────────┐
│                                                         │
│  Wybierz kategorię główną (określa atrybuty):           │
│  [Elektronika > RTV > Telewizory          ▼]            │
│                                                         │
│  ℹ Kategoria główna zaczytuje kontekstowe atrybuty.     │
│    Dodatkowe kategorie przypiszesz po utworzeniu.       │
│                                                         │
│                                  [Anuluj]  [Dalej →]   │
└─────────────────────────────────────────────────────────┘
```

ObjectType **nie-kategoryzowalny** (Brand, Service bez kategorii) — modal pomija krok kategorii, od razu formularz z atrybutami bazowymi.

### 7.2 Formularz edycji — zakładki

- Zakładki = AttributeGroups efektywnego modelu (warstwa 1 + warstwa 2 cumulative).
- Atrybuty typu `relation` → zakładka „Powiązania":
  - `cardinality=one` → picker (jedna referencja, np. Brand)
  - `cardinality=many` → grid z listą, dodaj/usuń/reorder; advanced → edytowalne kolumny metadanych
  - Sekcja read-only *„Powiązania zwrotne"* — auto-generowana z reverse lookup

### 7.3 Modelowanie ObjectType

- Capability flags `expose_to_main_menu` + `is_categorizable` jako toggles w edycji ObjectType.
- Dodanie atrybutu typu `relation` → konfigurator: target ObjectType(s), cardinality, advanced toggle.
- `/modeling/categories` select „Object Type" — filtruje tylko do ObjectType z `is_categorizable=true`.

## 8. Migracja + naprawa objawów

| Objaw | Naprawa |
|---|---|
| #1 Marka built-in | Usunięcie Brand z puli built-in. Migracja istniejącej „Marki": jeśli używana jako ObjectType → zostaje jako custom ObjectType; jeśli nie używana → usuwana. Decyzja per stan tenanta w migration script. |
| #2 Brak relacji | Typ atrybutu `relation` + `object_relations` + zakładka „Powiązania" + reverse. |
| #3-#28 Category nie renderuje atrybutów | Fix `EffectiveAttributeGroupResolver` źródło (2) — zwraca atrybuty bazowe ObjectType **zawsze**, niezależnie od kategoryzacji. |
| #4 Każdy ObjectType kategoryzowany | Flaga `is_categorizable`; UI Modelowania i `/modeling/categories` reagują na flagę. |

## 9. Permissions (RBAC)

- Modelowanie ObjectType / atrybutów / relacji → permission `modeling.*` (z macierzy RBAC §3.2 — Modeler, Owner, Admin).
- Dodanie/zmiana relacji w instancji obiektu → permission edycji danego ObjectType (`products.edit` itd.).
- Capability flags edycja → `modeling.object_types.edit`.

## 10. API endpoints (delta)

| Endpoint | Metoda | Cel |
|---|---|---|
| `/api/object-types/{id}` | PATCH | Update capability flags (expose_to_main_menu, is_categorizable) |
| `/api/objects/{id}/form-schema` | GET | Efektywny model atrybutów (warstwa 1 + 2 cumulative) — istniejący, parametryzowany primary category |
| `/api/objects/{id}/categories` | PUT | Przypisanie kategorii (1 primary + N secondary) |
| `/api/objects/{id}/relations` | GET/PUT | Powiązania obiektu (per atrybut relation) |
| `/api/objects/{id}/relations/reverse` | GET | Powiązania zwrotne (read-only) |
| `/api/attributes` | POST/PATCH | Obsługa typu `relation` + walidacja unikalności kodu |

## 11. User stories

| ID | Persona | Story |
|---|---|---|
| US-MOD-001 | Adam | Tworzy custom ObjectType „Service", ustawia `is_categorizable=false` — instancje nie wymagają kategorii |
| US-MOD-002 | Adam | Dodaje atrybut `up_sell` typu `relation`, target=Product, cardinality=many — pojawia się zakładka „Powiązania" |
| US-MOD-003 | Kasia | Tworzy Produkt — modal wymusza wybór kategorii głównej, formatka zaczytuje atrybuty kumulatywnie ze ścieżki drzewa |
| US-MOD-004 | Kasia | Edytuje Telewizor — w zakładce „Powiązania" dodaje 3 produkty up-sell przez grid |
| US-MOD-005 | Kasia | Otwiera Produkt B — widzi read-only sekcję *„jestem up-sellem dla: A"* |
| US-MOD-006 | Adam | Tworzy atrybut `opis_telewizory` w kategorii Telewizory — walidacja przechodzi (unikalny kod) |
| US-MOD-007 | Adam | Próbuje dodać drugi atrybut o kodzie `opis` — walidacja blokuje (kolizja z bazowym) |
| US-MOD-008 | Kasia | Zmienia primary category Telewizora na „Pralki" — `przekatna` znika z formatki, wartość zachowana ukryta |
| US-MOD-009 | Adam | Edytuje ObjectType „Brand", ustawia `expose_to_main_menu=false` — Brand znika z sidebar, dostępny tylko jako target relacji |
| US-MOD-010 | Adam | Dodaje atrybut `akcesoria` typu `relation` advanced — każde powiązanie ma pole `priorytet` |

## 12. Out of scope (MVP)

- ❌ **Family jako osobny byt** — świadomie odrzucone (ADR-014 opcja Z). Primary category wystarcza.
- ❌ **Sekcja diagnostyczna orphaned values** w UI — Faza 1+. MVP: orphaned po prostu ukryte.
- ❌ **Relacje wielopoziomowe / przechodnie** (A→B→C auto-discovery) — Faza 2.
- ❌ **`visible_when` reguły composite** dla relacji — proste tak/nie z ADR-012, composite Faza 1.
- ❌ **Multi-tab dla relacji** (osobne „Powiązania handlowe" / „techniczne") — MVP: jedna zakładka „Powiązania". *(Uwaga: technicznie relacja jest atrybutem w grupie — rozszerzenie do wielu zakładek to zmiana seedu grup, nie architektury.)*

### 12.1 Świadomie odrzucone alternatywy ADR-014 / batch MODR-01..10 (rozstrzygnięte 2026-05-24)

- ❌ **Warstwa „Object Template"** (dodatkowa abstrakcja między ObjectType a Object) — model `EffectiveAttributeGroupResolver` + primary category + ad-hoc groups na obiekcie pokrywają potrzeby bez kolejnego pojęcia. Resolver pozostaje czystą abstrakcją źródeł nakładki.
- ❌ **Osobny krok „Objekty" w wizardzie ObjectType** — wizard tworzy *typ*, instancje powstają na własnej karcie create (MOD-11). Mieszanie typu i instancji w jednym wizardzie zaciemnia model.
- ❌ **Reguła „relacja zawsze zakładka"** — odrzucona przez MODR-01..03 (Opcja 2). Placement to wyłącznie `display_mode` na junction; relacja zachowuje się jak każdy atrybut, widget dobierany po typie. Jedyny default to seedowana grupa `relations` (MODR-02 #924) z `display_mode='tab'`, którą operator może świadomie zmienić.
- ❌ **Mechanizm embed / kompozycja (composition vs. association)** — odłożony. Trigger powrotu: byt, który **nigdy nie jest współdzielony** i nie ma własnej tożsamości (np. wewnętrzne pole adresowe z 3 sub-polami) — wtedy embed kontra normalne ObjectType+relation. Dziś każdy byt jest ObjectType + asocjacja.
- ❌ **Families à la Akeneo / Pimcore-classes** (równoległa warstwa „rodzina = predefiniowany zestaw atrybutów na ObjectType") — odrzucone. Resolver MODR'owy + primary category + ad-hoc grupy na ObjectType pokrywają tę samą semantykę bez wprowadzenia osobnej tabeli „families". Warunek powrotu: gdyby resolver przestał być czystą abstrakcją źródeł nakładki, można rozważyć families jako trzecie źródło (priorytet między ObjectType-global a category-overlay).

## 13. Estymacja

| Element | Estymacja |
|---|---|
| Schema delta (capability flags, is_primary, relation config, object_relations) | 6-8h |
| `EffectiveAttributeGroupResolver` — fix źródło (2) + primary category path param | 8-12h |
| Typ atrybutu `relation` — backend (config, walidacja, CRUD object_relations) | 12-16h |
| Reverse relations — query + endpoint + cache | 4-6h |
| Advanced relations — metadata JSONB + grid edit logika | 8-12h |
| Orphaned values — handling przy zmianie primary category | 4-6h |
| Walidacja unikalności kodów atrybutów | 3-4h |
| Migracja built-in „Marki" | 3-5h |
| Frontend — modal create (krok kategoria), capability flags toggles | 6-8h |
| Frontend — zakładka „Powiązania" (picker / grid / advanced / reverse) | 12-16h |
| Frontend — konfigurator atrybutu `relation` w Modelowaniu | 6-8h |
| Testy — unit + integration + E2E (cumulative resolution, relacje, orphaned) | 10-14h |
| **TOTAL** | **~82-115h** |

~12-15 ticketów, ~2-3 tygodnie solo dev. Wchodzi w epik UI-08 (Modelowanie) jako rewizja — blokujące dla dalszego CRUD-u obiektów.

## 14. Co dalej

1. Walidacja: czy `EffectiveAttributeGroupResolver` z ADR-012 jest już zaimplementowany (wtedy fix) czy nie (wtedy implementacja od zera).
2. ~~Decyzja: `object_associations` reused czy nowa tabela~~ — **rozstrzygnięte 2026-05-23: zastępowane przez `object_relations`** (patrz §4 Uwagi).
3. Update `epik-08-modelowanie.md` — capability flags + relacje + primary/secondary categories.
4. Migracja „Marki" — audyt stanu w bazie przed napisaniem migration script.
5. Wireframes — modal create (krok kategoria), zakładka „Powiązania" (picker/grid/reverse), konfigurator relacji.
6. Rozpisanie ticketów (~12-15) gdy decyzja o starcie implementacji — analogicznie do RBAC backlog.

---

**Cross-reference (MODR-11 #933):** Batch ticketów rozstrzygający §3.5 (Opcja 2) — [`feature-modeling-relations-ux-tickets.md`](feature-modeling-relations-ux-tickets.md) (MODR-01 #923 .. MODR-11 #933). Status: zaimplementowane 2026-05-24.

---

*Mini-spec wygenerowany 2026-05-23 jako kontrakt implementacyjny ADR-014. Status: 🟢 szczegół. Powstał z sesji burzy mózgów (4 fale) po wykryciu nieścisłości Modelowania przy ~60% systemu. Następna iteracja: walidacja stanu `EffectiveAttributeGroupResolver` + wireframes + rozpisanie ticketów.*
