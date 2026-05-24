# Tickety — Modelowanie: model dystrybucji atrybutów + relacje (ADR-014)

**Typ dokumentu:** Backlog ticketów — ready-to-paste GitHub Issues
**Status:** Draft — gotowe do utworzenia GitHub Issues przez agenta kodującego
**Powiązane:**
- [ADR-014](../01-architektura-pim.md) — *„Model dystrybucji atrybutów + relacje obiekt↔obiekt"*
- [`feature-modeling-data-model.md`](feature-modeling-data-model.md) — mini-spec implementacyjny (kontrakt)
- [`epik-08-modelowanie.md`](epik-08-modelowanie.md) — epik UI-08 (nadrzędny)

> **Cel:** rewizja modelu Modelowania per ADR-014. Naprawia 4 nieścisłości wykryte przy ~60% systemu: Marka built-in, brak modelu relacji, Category nie renderuje atrybutów (#3-#28), niejasność kategoryzacji.
> **Blokujące** dla dalszego CRUD-u obiektów w epiku UI-08.
> **14 ticketów, ~75-105h** (~2-3 tygodnie solo dev). Estymacja niższa niż mini-spec §13 bo `EffectiveAttributeGroupResolver` JEST już zaimplementowany i merged — MOD-03 to fix/refactor, nie implementacja od zera.

---

## Konwencje

- Numer: `MOD-NN`. Po utworzeniu w GitHub: dopisany numer issue (`MOD-01 / #NNN`).
- Pola: Typ (Conventional Commits) / Epik / Estymacja / Dependencies / Risk flags / Cel / Scope / Acceptance criteria / Files affected / Testing / DoD.
- Tytuł issue po angielsku (Conventional Commits), opis po polsku.
- Labels: `modeling` + `adr-014` + `epik-ui-08` + risk flag jeśli dotyczy.

## Graf zależności

```
MOD-01 (schema: capability flags + is_primary + relation config) ── foundation
MOD-02 (schema: object_relations + migracja object_associations) ── foundation
        │
        ├── MOD-03 (EffectiveAttributeGroupResolver fix — naprawa #3-#28)
        ├── MOD-04 (walidacja unikalności kodów atrybutów)
        ├── MOD-05 (typ atrybutu `relation` — backend config)
        │       └── MOD-06 (object_relations CRUD)
        │               ├── MOD-07 (reverse relations)
        │               └── MOD-08 (advanced relations — metadata)
        ├── MOD-09 (orphaned values handling)
        └── MOD-10 (migracja built-in „Marki")
        │
        ▼
MOD-11 (frontend: modal create + capability flags)   ── po MOD-01, MOD-03
MOD-12 (frontend: zakładka „Powiązania")             ── po MOD-06, MOD-07, MOD-08
MOD-13 (frontend: konfigurator atrybutu `relation`)  ── po MOD-05
MOD-14 (docs: update epik-08-modelowanie.md)         ── równolegle
```

---

## MOD-01 / #893: feat(catalog): schema delta — capability flags + primary category + relation attribute config

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **4-6h** (zredukowane do ~2-3h po scope reconciliation 2026-05-24 — patrz „Scope adjustment" niżej).

**Dependencies:** Blocks MOD-03..MOD-13. Blocked by — brak (foundation).

**Scope adjustment (2026-05-24, plan-mode reconciliation z istniejącym kodem):**
- `show_in_main_menu` *(spec)* = istniejące `expose_to_main_menu` *(kod, VIEW-08 / #427)* — **reuse**, ADR-014 + mini-spec zaktualizowane do canonical name.
- `ObjectCategory.is_primary BOOLEAN` + partial unique index — **już istnieje** od PCAT-01 / `Version20260510221123`, MOD-01 nie dotyka.
- `AttributeType::Relation` enum case — **już istnieje** od #31 (linia 36 w `AttributeType.php`), MOD-01 nie dotyka enum/walidacji.
- Aktualny MOD-01 scope: 1 nowa kolumna na `object_types` (`is_categorizable`) + 3 nowe kolumny na `attributes` (`relation_target_object_type_ids`, `relation_cardinality`, `relation_advanced`).

**Risk flags:**
- `is_primary` partial unique index — dokładnie 1 primary per obiekt. Błąd = produkt z 2 primary = ambiguous attribute set. *(Mitigacja: index istnieje już od PCAT-01 — MOD-01 nie zmienia.)*
- `object_categories` może już istnieć (ADR-009 schema) — migracja dodaje kolumnę, nie tworzy tabeli od zera. *(Confirmed: istnieje, MOD-01 nie dotyka.)*

**Cel:** Schema delta dla ADR-014 — capability flags na ObjectType, primary flag na junction obiekt↔kategoria, konfiguracja atrybutu typu `relation`.

**Scope:**
```sql
ALTER TABLE object_types ADD COLUMN show_in_main_menu BOOLEAN NOT NULL DEFAULT true;
ALTER TABLE object_types ADD COLUMN is_categorizable  BOOLEAN NOT NULL DEFAULT false;

ALTER TABLE object_categories ADD COLUMN is_primary BOOLEAN NOT NULL DEFAULT false;
CREATE UNIQUE INDEX idx_object_categories_primary
    ON object_categories(object_id) WHERE is_primary = true;

ALTER TABLE attributes ADD COLUMN relation_target_object_type_ids JSONB DEFAULT '[]'::JSONB;
ALTER TABLE attributes ADD COLUMN relation_cardinality VARCHAR(8)
    CHECK (relation_cardinality IN ('one', 'many'));
ALTER TABLE attributes ADD COLUMN relation_advanced BOOLEAN NOT NULL DEFAULT false;
```
- Dodać `relation` do dozwolonych typów atrybutu (enum/walidacja).
- Seed built-in ObjectType (Product/Category/Asset) z poprawnymi flagami: Product `is_categorizable=true, show_in_main_menu=true`; Category/Asset `is_categorizable=false`.
- Entity classes (ObjectType, Attribute, ObjectCategory) — nowe properties + getters.

**Acceptance criteria:**
- [ ] AC-1: Kolumny dodane, migracja UP+DOWN testowana w testcontainers
- [ ] AC-2: Partial unique index — próba 2 primary dla 1 obiektu → constraint violation
- [ ] AC-3: Built-in ObjectType mają poprawne flagi po seed
- [ ] AC-4: `doctrine:schema:validate` pass
- [ ] AC-5: Typ `relation` akceptowany przez walidację atrybutu

**Files affected:** `src/Catalog/Doctrine/Migrations/Version*.php`, `src/Catalog/Entity/{ObjectType,Attribute,ObjectCategory}.php`

**Testing:** Unit (entity) + integration (migracja UP/DOWN + constraint).

**DoD:** AC + PHPStan max + CI green + migracja rollback test.

---

## MOD-02 / #894: feat(catalog): schema — object_relations table + migracja object_associations

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **4-6h**

**Dependencies:** Blocks MOD-05, MOD-06. Blocked by — brak (foundation, równolegle z MOD-01).

**Scope adjustment (2026-05-24, plan-mode reconciliation):**
- **Skip data migration** — pre-flight grep wykazał ZERO consumerów Association infrastructure (brak controllerów, serwisów, frontend ma tylko MockBadge tooltip). Tabele `object_associations` + `association_types` są dormant od #35; brak danych do back-portu. Migracja drop'uje bez data preservation.
- **Cleanup ripple:** usunięto 12 plików (Association/AssociationType entities + repos + interfaces + Doctrine impls + ORM XML + ApiPlatform Resource + Serializer + voter + 2 testy + seeder), zaktualizowano 5 plików (AppFixtures, RbacMatrix, PermissionOpenApiFactory, dh_auditor.yaml, AuditLogTest).

**Risk flags:**
- ~~**Migracja danych**~~ — N/A, brak danych do migracji (potwierdzone pre-flight).
- `object_associations` DROP — operacja destrukcyjna ale na pustych tabelach.
- **RbacMatrix `'association'` resource removed** — clean break, permissions `association.*` znikają z matrix. Phase 6 retrofit DB sync usunie orphan permissions na deploy.

**Cel:** Utworzyć `object_relations` (per ADR-014) zastępujące `object_associations`. Hardcoded enum typów → seedowane built-in atrybuty `relation`.

**Scope:**
```sql
CREATE TABLE object_relations (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    source_object_id UUID NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
    target_object_id UUID NOT NULL REFERENCES objects(id) ON DELETE CASCADE,
    attribute_id UUID NOT NULL REFERENCES attributes(id),
    position INTEGER NOT NULL DEFAULT 0,
    metadata JSONB DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_object_relations_source ON object_relations(source_object_id, attribute_id);
CREATE INDEX idx_object_relations_target ON object_relations(target_object_id);
```
- Seed 5 built-in atrybutów typu `relation` na ObjectType Product: `cross_sell`, `up_sell`, `related`, `alternative`, `accessory` (cardinality=many, target=Product, advanced=false).
- Migracja danych: per wiersz `object_associations` → znajdź odpowiadający seedowany atrybut po `type`, wstaw wiersz `object_relations` z `attribute_id`.
- DROP `object_associations` po migracji danych.
- Entity `ObjectRelation`.

**Acceptance criteria:**
- [ ] AC-1: Tabela `object_relations` utworzona z indeksami
- [ ] AC-2: 5 built-in atrybutów `relation` seedowanych na Product
- [ ] AC-3: Istniejące wiersze `object_associations` przepisane na `object_relations` (test: insert sample association → migracja → wiersz w object_relations z poprawnym attribute_id)
- [ ] AC-4: `object_associations` DROP-nięte po migracji
- [ ] AC-5: Migracja DOWN przywraca `object_associations` (rollback path)
- [ ] AC-6: Cross-tenant — `tenant_id` na object_relations, TenantFilter applied

**Files affected:** `src/Catalog/Doctrine/Migrations/Version*.php`, `src/Catalog/Entity/ObjectRelation.php`

**Testing:** Integration (migracja danych UP — sample associations przepisane; DOWN — przywrócone).

**DoD:** AC + migracja danych smoke test (paste przed/po) + CI green.

---

## MOD-03 / #895: fix(catalog): EffectiveAttributeGroupResolver — base attributes for every ObjectType + primary category path

**Typ:** `fix` | **Epik:** UI-08 | **Estymacja:** **5-8h**

**Dependencies:** Blocks MOD-11. Blocked by MOD-01.

**Risk flags:**
- `EffectiveAttributeGroupResolver` JEST zaimplementowany i merged (potwierdzone) — to **refactor istniejącego**, nie implementacja od zera. Zmiana logiki resolvera dotyka renderowania KAŻDEGO formularza obiektu — regresja = wszystkie formatki broken.
- Cache resolvera (Redis 5min TTL z ADR-012) — invalidation musi uwzględnić nowy primary category param.

**Cel:** Naprawić objaw #3-#28. Resolver musi zwracać atrybuty bazowe ObjectType **zawsze** (źródło 2, niezależnie od kategoryzacji) + parametryzować dziedziczenie po **primary category path** zamiast zbioru wszystkich kategorii.

**Scope:**
- Źródło (2) *„globalne dla ObjectType"* — zwraca grupy bazowe dla każdego ObjectType (Product, Category, Asset, custom) niezależnie od `is_categorizable`. To naprawia Category-jako-ObjectType nie renderujący swoich atrybutów.
- Źródło (3) *„dziedziczone po drzewie"* — parametryzowane **primary category path** (root→leaf cumulative), nie zbiorem wszystkich kategorii obiektu. Sekundarne kategorie ignorowane w resolverze.
- Dla ObjectType `is_categorizable=false` — źródło (3) zwraca pusty zbiór (brak kategorii = brak overlay).
- Cache key uwzględnia primary category id (nie listę wszystkich kategorii).
- Endpoint `GET /api/objects/{id}/form-schema` — bez zmiany sygnatury, zmiana wewnętrznej logiki resolvera.

**Acceptance criteria:**
- [ ] AC-1: ObjectType Category — formularz instancji renderuje atrybuty bazowe (name, seo_title, seo_description, main_image) — **naprawa #3-#28**
- [ ] AC-2: Product z primary category `Elektronika > RTV > Telewizory` dostaje atrybuty kumulatywnie ze ścieżki (3 węzły)
- [ ] AC-3: Product secondary categories — NIE wpływają na atrybuty
- [ ] AC-4: ObjectType `is_categorizable=false` — resolver zwraca tylko warstwę bazową
- [ ] AC-5: Cache invalidation działa przy zmianie primary category obiektu
- [ ] AC-6: Existing form-schema testy nadal pass (brak regresji)

**Files affected:** `src/Catalog/Domain/Service/EffectiveAttributeGroupResolver.php`, `src/Catalog/EventListener/ObjectFormSchemaListener.php`

**Testing:** Unit (resolver — base layer dla każdego ObjectType, cumulative path) + integration (form-schema endpoint per ObjectType) + regresja istniejących testów.

**DoD:** AC + smoke test: utwórz kategorię → formularz pokazuje name/seo_title (objaw #3-#28 naprawiony) + CI green.

---

## MOD-04 / #896: feat(catalog): walidacja unikalności kodów atrybutów w obrębie ObjectType

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **3-4h**

**Dependencies:** Blocked by MOD-01.

**Risk flags:**
- Walidacja musi sprawdzać kod przeciw **całemu efektywnemu modelowi** ObjectType (bazowe + wszystkie kategorie dystrybuujące do tego ObjectType) — nie tylko bezpośrednim atrybutom.

**Cel:** Kod atrybutu globalnie unikalny w obrębie ObjectType. Atrybut albo bazowy, albo kontekstowy — nigdy oba. Walidacja blokuje duplikat.

**Scope:**
- Walidator przy create/update atrybutu — sprawdza czy kod już istnieje w modelu docelowego ObjectType (warstwa bazowa + grupy kategorii dystrybuujących do tego ObjectType).
- Błąd RFC 7807 Problem Details — `field: "code", reason: "duplicate_in_object_type", existing_location: "base|category:X"`.
- Konwencja nazewnicza dla atrybutów kontekstowych — UI hint przy tworzeniu atrybutu w kategorii: sufiks kategorii sugerowany (`opis_telewizory`). Nie hard-enforced, ale podpowiedziane.

**Acceptance criteria:**
- [ ] AC-1: Próba dodania atrybutu o kodzie istniejącym w warstwie bazowej → 422 z reason
- [ ] AC-2: Próba dodania atrybutu o kodzie istniejącym w kategorii dystrybuującej do tego ObjectType → 422
- [ ] AC-3: Ten sam kod w innym ObjectType (niepowiązanym) → dozwolone
- [ ] AC-4: UI hint sufiksu kategorii przy tworzeniu atrybutu kontekstowego

**Files affected:** `src/Catalog/Validator/AttributeCodeUniquenessValidator.php`, `src/Catalog/Controller/AttributeController.php`

**Testing:** Unit (walidator — kolizje base/category/cross-objecttype) + integration (endpoint 422).

**DoD:** AC + PHPStan max + CI green.

---

## MOD-05 / #897: feat(catalog): typ atrybutu `relation` — backend konfiguracja w Modelowaniu

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **6-8h**

**Dependencies:** Blocks MOD-06, MOD-13. Blocked by MOD-01, MOD-02.

**Risk flags:**
- `relation_target_object_type_ids` JSONB — walidacja że wskazane ObjectType istnieją w tenant.
- Zmiana target ObjectType na istniejącym atrybucie relation z danymi → orphaned object_relations (powiązania do ObjectType już niedozwolonego).

**Cel:** Obsłużyć typ atrybutu `relation` w Modelowaniu — definicja, konfiguracja (target, cardinality, advanced), walidacja.

**Scope:**
- Atrybut typu `relation` — config: `relation_target_object_type_ids` (1+ ObjectType), `relation_cardinality` (one/many), `relation_advanced` (bool).
- Walidacja: target ObjectType istnieją; przy `cardinality=one` — max 1 powiązanie per obiekt-źródło.
- Walidacja zmiany konfiguracji na atrybucie z danymi — ostrzeżenie/blokada gdy zmiana target unieważnia istniejące powiązania.
- API: `POST/PATCH /api/attributes` obsługuje typ `relation` + jego config.

**Acceptance criteria:**
- [ ] AC-1: Utworzenie atrybutu `relation` z target=[Brand], cardinality=one → zapisane
- [ ] AC-2: Utworzenie z target=[Product], cardinality=many, advanced=true → zapisane
- [ ] AC-3: Target wskazujący nieistniejący ObjectType → 422
- [ ] AC-4: Zmiana target na atrybucie z istniejącymi powiązaniami → ostrzeżenie z liczbą affected
- [ ] AC-5: cardinality=one — drugie powiązanie dla tego samego źródła → odrzucone

**Files affected:** `src/Catalog/Entity/Attribute.php`, `src/Catalog/Validator/RelationAttributeValidator.php`, `src/Catalog/Controller/AttributeController.php`

**Testing:** Unit (walidacja config) + integration (CRUD atrybutu relation).

**DoD:** AC + PHPStan max + CI green.

---

## MOD-06 / #898: feat(catalog): object_relations CRUD — backend endpointy

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **6-8h**

**Dependencies:** Blocks MOD-07, MOD-08, MOD-12. Blocked by MOD-05.

**Risk flags:**
- Cardinality enforcement — `one` pozwala max 1 wiersz `object_relations` per (source, attribute).
- `position` reorder — atomic update przy drag-drop wielu wierszy.
- Cross-tenant — powiązanie tylko między obiektami tego samego tenant.

**Cel:** CRUD powiązań obiekt↔obiekt — endpointy do dodawania/usuwania/reorder relacji per atrybut.

**Scope:**
- `GET /api/objects/{id}/relations` — powiązania obiektu pogrupowane per atrybut relation.
- `PUT /api/objects/{id}/relations/{attributeCode}` — ustaw powiązania dla danego atrybutu (lista target_object_id + position). Atomic.
- `DELETE /api/objects/{id}/relations/{attributeCode}/{targetId}` — usuń pojedyncze powiązanie.
- Cardinality enforcement (one → max 1).
- Cross-tenant guard — target musi być w tym samym tenant.
- Walidacja — target ObjectType zgodny z `relation_target_object_type_ids` atrybutu.

**Acceptance criteria:**
- [ ] AC-1: PUT relations dla atrybutu many → lista powiązań zapisana z position
- [ ] AC-2: PUT dla atrybutu one z 2 targetami → 422
- [ ] AC-3: Powiązanie do obiektu spoza dozwolonego ObjectType → 422
- [ ] AC-4: Powiązanie cross-tenant → 403/404
- [ ] AC-5: DELETE pojedynczego powiązania → usunięte, reszta nienaruszona
- [ ] AC-6: Reorder (PUT z nową kolejnością position) → atomic

**Files affected:** `src/Catalog/Controller/ObjectRelationController.php`, `src/Catalog/Domain/Service/ObjectRelationService.php`

**Testing:** Integration (CRUD + cardinality + cross-tenant).

**DoD:** AC + cross-tenant test + CI green.

---

## MOD-07 / #899: feat(catalog): reverse relations — query + endpoint

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **4-6h**

**Dependencies:** Blocks MOD-12. Blocked by MOD-06.

**Risk flags:**
- Reverse lookup przy 200k+ SKU — `idx_object_relations_target` (z MOD-02) krytyczny dla performance.
- Reverse to read-only — żadnej edycji po stronie targetu.

**Cel:** Powiązania zwrotne — obiekt-target widzi read-only kto na niego wskazuje.

**Scope:**
- `GET /api/objects/{id}/relations/reverse` — lista *„obiekt X wskazuje na mnie przez atrybut Y"*.
- Grupowanie per (source ObjectType, atrybut) — np. *„up-sell dla: [Product A, Product B]"*.
- Read-only — brak endpointów modyfikujących reverse.
- Performance — query po `idx_object_relations_target`.

**Acceptance criteria:**
- [ ] AC-1: Obiekt B (target up_sell z A) — `GET .../reverse` zwraca A z atrybutem `up_sell`
- [ ] AC-2: Reverse pogrupowane per atrybut źródłowy
- [ ] AC-3: Brak powiązań zwrotnych → pusta lista (nie błąd)
- [ ] AC-4: Performance — reverse lookup <100ms na dataset 50k+ powiązań

**Files affected:** `src/Catalog/Controller/ObjectRelationController.php` (rozszerzenie), `src/Catalog/Domain/Service/ObjectRelationService.php`

**Testing:** Integration (reverse lookup) + performance (50k+ powiązań benchmark).

**DoD:** AC + benchmark w PR + CI green.

---

## MOD-08 / #900: feat(catalog): advanced relations — metadata na powiązaniu

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **8-12h**

**Dependencies:** Blocks MOD-12. Blocked by MOD-06.

**Risk flags:**
- Advanced relation podwaja złożoność — `metadata JSONB` ma własny schemat per atrybut (pola metadanych zdefiniowane w konfiguracji atrybutu relation).
- Walidacja metadanych — typy pól, required.

**Cel:** Relacje z metadanymi — powiązanie ma własne pola (np. `priorytet`, `rekomendowane`).

**Scope:**
- Konfiguracja atrybutu `relation` z `advanced=true` — definicja pól metadanych (kod, typ, label, required) w config atrybutu.
- `object_relations.metadata JSONB` — przechowuje wartości pól metadanych per powiązanie.
- Walidacja metadanych przy PUT/PATCH relations — zgodność z definicją pól.
- Endpoint `PUT /api/objects/{id}/relations/{attributeCode}` — body powiązań zawiera `metadata` per target.

**Acceptance criteria:**
- [ ] AC-1: Atrybut relation advanced z polami metadanych [priorytet:number, rekomendowane:boolean]
- [ ] AC-2: PUT relations z metadata per powiązanie → zapisane w `object_relations.metadata`
- [ ] AC-3: Walidacja — metadata niezgodne z definicją (zły typ) → 422
- [ ] AC-4: Required field metadanych pusty → 422
- [ ] AC-5: Atrybut relation `advanced=false` — metadata ignorowane/odrzucone

**Files affected:** `src/Catalog/Domain/Service/ObjectRelationService.php`, `src/Catalog/Validator/RelationMetadataValidator.php`

**Testing:** Unit (walidacja metadanych) + integration (CRUD z metadata).

**DoD:** AC + PHPStan max + CI green.

---

## MOD-09 / #901: feat(catalog): orphaned values — handling przy zmianie primary category

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by MOD-01, MOD-03.

**Risk flags:**
- Orphaned values **NIE są kasowane** — pozostają w `object_values`, ukryte. Błąd = utrata danych klienta przy zmianie kategorii.

**Cel:** Zmiana primary category → atrybuty spoza nowego modelu znikają z formularza, ale wartości zostają w bazie (ukryte, reaktywowalne).

**Scope:**
- Przy zmianie primary category obiektu — przelicz efektywny model (resolver MOD-03).
- Wartości atrybutów spoza nowego efektywnego modelu — **pozostają** w `object_values`, oznaczone jako poza-modelem (filtrowane przy renderze form-schema).
- Powrót do poprzedniej primary category → wartości wracają widoczne.
- Brak zmiany schematu — logika filtrowania w resolverze/serializerze form-schema.
- Completeness — orphaned values NIE liczą się do completeness (poza modelem).

**Acceptance criteria:**
- [ ] AC-1: Produkt z `przekatna=55"`, zmiana primary category na „Pralki" → `przekatna` znika z form-schema, wartość zostaje w `object_values`
- [ ] AC-2: Powrót do „Telewizory" → `przekatna=55"` wraca widoczne
- [ ] AC-3: Orphaned value nie liczy się do completeness
- [ ] AC-4: form-schema nie renderuje orphaned values

**Files affected:** `src/Catalog/Domain/Service/EffectiveAttributeGroupResolver.php` (filtrowanie), `src/Catalog/Domain/Service/CompletenessCalculator.php`

**Testing:** Integration (zmiana kategorii → orphaned → powrót).

**DoD:** AC + smoke test (zmiana kategorii, wartość zachowana) + CI green.

---

## MOD-10 / #902: feat(catalog): migracja built-in „Marki" — usunięcie z puli built-in

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by MOD-01.

**Risk flags:**
- Migracja destrukcyjna jeśli źle — Marka może być używana przez tenantów. Audyt stanu PRZED migration script.
- Per-tenant decyzja — Marka używana jako ObjectType (zostaje custom) vs nieużywana (usuwana).

**Cel:** Usunąć Brand z puli built-in ObjectType (REVERT ADR-012). Migracja istniejącej „Marki".

**Scope:**
- Audyt: czy/jak „Marka" istnieje w bazie (built-in ObjectType? atrybut? per tenant).
- Migration script:
  - Marka jako ObjectType **używany** (ma instancje) → konwersja na custom ObjectType (`is_built_in=false`).
  - Marka jako ObjectType **nieużywany** (0 instancji) → usunięcie.
  - Built-in pula → wyłącznie Product/Category/Asset.
- Seed/fixtures — usunąć Brand z built-in seed.

**Acceptance criteria:**
- [ ] AC-1: Audyt stanu „Marki" udokumentowany w PR description
- [ ] AC-2: Marka z instancjami → custom ObjectType, instancje zachowane
- [ ] AC-3: Marka bez instancji → usunięta
- [ ] AC-4: Built-in pula = Product/Category/Asset (test: query is_built_in)
- [ ] AC-5: Migracja DOWN — rollback path (lub udokumentowane czemu nieodwracalna)

**Files affected:** `src/Catalog/Doctrine/Migrations/Version*.php`, seed/fixtures built-in ObjectType

**Testing:** Integration (migracja per scenariusz: używana/nieużywana).

**DoD:** AC + audyt w PR + smoke test + CI green.

---

## MOD-11 / #903: feat(admin): modal create obiektu (krok primary category) + capability flags toggles

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by MOD-01, MOD-03.

**Risk flags:**
- Modal create rozgałęzia się per `is_categorizable` — kategoryzowalny ma krok kategorii, nie-kategoryzowalny pomija.

**Cel:** Frontend — modal tworzenia obiektu z krokiem wyboru primary category (dla kategoryzowalnych) + toggles capability flags w edycji ObjectType.

**Scope:**
- Modal create obiektu kategoryzowalnego — krok 1: wybór primary category (wymuszone, zaczytuje atrybuty), info hint.
- Modal create obiektu nie-kategoryzowalnego — pomija krok kategorii, od razu formularz.
- Edycja ObjectType — toggles `show_in_main_menu` + `is_categorizable`.
- Sidebar — reaguje na `show_in_main_menu` (ObjectType z `false` znika z menu).
- `/modeling/categories` select „Object Type" — filtruje do `is_categorizable=true`.

**Acceptance criteria:**
- [ ] AC-1: Tworzenie Produktu → modal wymusza primary category, formatka zaczytuje atrybuty cumulative
- [ ] AC-2: Tworzenie nie-kategoryzowalnego ObjectType → modal bez kroku kategorii
- [ ] AC-3: Toggle `show_in_main_menu=false` → ObjectType znika z sidebar
- [ ] AC-4: `/modeling/categories` select pokazuje tylko `is_categorizable=true`
- [ ] AC-5: E2E — pełen flow tworzenia produktu z kategorią

**Files affected:** `apps/admin/src/components/modeling/CreateObjectModal.tsx`, `apps/admin/src/components/modeling/ObjectTypeEditor.tsx`, `apps/admin/src/components/Sidebar.tsx`

**Testing:** Unit (Vitest) + E2E Playwright (create flow per ObjectType type).

**DoD:** AC + E2E + CI green.

---

## MOD-12 / #904: feat(admin): zakładka „Powiązania" — picker / grid / advanced / reverse

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **12-16h**

**Dependencies:** Blocked by MOD-06, MOD-07, MOD-08.

**Risk flags:**
- Najcięższy ticket frontendowy. 4 tryby renderu (one/many/advanced/reverse) w jednej zakładce.
- Grid reorder drag-drop + edycja metadanych inline (advanced).

**Cel:** Frontend — zakładka „Powiązania" w formularzu edycji obiektu, renderująca atrybuty typu `relation`.

**Scope:**
- Zakładka „Powiązania" = AttributeGroup z atrybutami typu `relation`.
- Atrybut `cardinality=one` → picker (jedna referencja, modal wyboru obiektu).
- Atrybut `cardinality=many` → grid: lista powiązań, dodaj (picker)/usuń/reorder drag-drop.
- Atrybut `advanced=true` → grid z edytowalnymi kolumnami metadanych.
- Sekcja read-only *„Powiązania zwrotne"* — auto-generowana z `GET .../relations/reverse`.
- Picker obiektu — search + filtr po dozwolonych ObjectType.

**Acceptance criteria:**
- [ ] AC-1: Atrybut relation one → picker, wybór jednego obiektu
- [ ] AC-2: Atrybut relation many → grid, dodawanie/usuwanie/reorder
- [ ] AC-3: Atrybut advanced → grid z edycją metadanych per wiersz
- [ ] AC-4: Sekcja „Powiązania zwrotne" renderuje reverse, read-only
- [ ] AC-5: Picker filtruje obiekty do dozwolonych target ObjectType
- [ ] AC-6: E2E — dodanie up-sell, sprawdzenie reverse u targetu

**Files affected:** `apps/admin/src/components/object-editor/RelationsTab.tsx`, `RelationPicker.tsx`, `RelationGrid.tsx`, `ReverseRelationsSection.tsx`

**Testing:** Unit (Vitest) + E2E Playwright (dodanie relacji + reverse — analog US-MOD-004/005).

**DoD:** AC + E2E + accessibility (axe-core) + CI green.

---

## MOD-13 / #905: feat(admin): konfigurator atrybutu `relation` w Modelowaniu

**Typ:** `feat` | **Epik:** UI-08 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by MOD-05.

**Risk flags:**
- Konfigurator advanced — definicja pól metadanych (dynamiczna lista kod/typ/label/required).

**Cel:** Frontend — UI dodawania/edycji atrybutu typu `relation` w Modelowaniu.

**Scope:**
- W edytorze atrybutu — wybór typu `relation` odsłania konfigurator:
  - Target ObjectType — multi-select z listy ObjectType tenanta.
  - Cardinality — radio one/many.
  - Advanced — toggle; gdy ON → edytor pól metadanych (lista: kod, typ, label, required).
- Walidacja po stronie UI + obsługa błędów 422 z backendu (MOD-05).

**Acceptance criteria:**
- [ ] AC-1: Wybór typu `relation` → konfigurator widoczny
- [ ] AC-2: Multi-select target ObjectType działa
- [ ] AC-3: Toggle advanced → edytor pól metadanych
- [ ] AC-4: Zapis → atrybut relation z config zapisany (integracja z MOD-05)
- [ ] AC-5: Błąd 422 z backendu → czytelny komunikat

**Files affected:** `apps/admin/src/components/modeling/AttributeEditor.tsx`, `RelationConfigPanel.tsx`

**Testing:** Unit (Vitest) + E2E (utworzenie atrybutu relation — analog US-MOD-002).

**DoD:** AC + E2E + CI green.

---

## MOD-14 / #906: docs(modeling): update epik-08-modelowanie.md — capability flags + relacje + primary category

**Typ:** `docs` | **Epik:** UI-08 | **Estymacja:** **2-3h**

**Dependencies:** Równolegle (brak blokad).

**Cel:** Zaktualizować `epik-08-modelowanie.md` o decyzje ADR-014 — capability flags, relacje jako typ atrybutu, primary/secondary categories, naprawa #3-#28.

**Scope:**
- Sekcje epiku dotyczące modelu atrybutów — dopisać warstwę base + primary category overlay cumulative.
- Dodać sekcję relacji (typ atrybutu `relation`, reverse, advanced).
- Dodać capability flags ObjectType.
- Cross-reference do ADR-014 + `feature-modeling-data-model.md`.
- Usunąć/poprawić wzmianki o Brand jako built-in (jeśli są).

**Acceptance criteria:**
- [ ] AC-1: Epik zawiera model dwuwarstwowy + cumulative
- [ ] AC-2: Sekcja relacji dodana
- [ ] AC-3: Capability flags udokumentowane
- [ ] AC-4: Cross-reference do ADR-014 + mini-spec
- [ ] AC-5: Brak nieaktualnych wzmianek o Brand built-in

**Files affected:** `Project Plan/UI/epik-08-modelowanie.md`

**Testing:** N/A (dokumentacja) — review spójności z ADR-014.

**DoD:** AC + review + merge.

---

## Podsumowanie

| Ticket | Estymacja | Warstwa |
|---|---|---|
| MOD-01 schema flags | 4-6h | Backend foundation |
| MOD-02 object_relations + migracja | 4-6h | Backend foundation |
| MOD-03 resolver fix (#3-#28) | 5-8h | Backend |
| MOD-04 walidacja kodów | 3-4h | Backend |
| MOD-05 typ relation config | 6-8h | Backend |
| MOD-06 relations CRUD | 6-8h | Backend |
| MOD-07 reverse relations | 4-6h | Backend |
| MOD-08 advanced relations | 8-12h | Backend |
| MOD-09 orphaned values | 4-6h | Backend |
| MOD-10 migracja Marki | 3-5h | Backend |
| MOD-11 frontend modal + flags | 6-8h | Frontend |
| MOD-12 frontend Powiązania | 12-16h | Frontend |
| MOD-13 frontend konfigurator relation | 6-8h | Frontend |
| MOD-14 docs epik-08 | 2-3h | Docs |
| **TOTAL** | **~73-104h** | 14 ticketów |

~2-3 tygodnie solo dev. Blokujące dla dalszego CRUD-u obiektów w epiku UI-08.

**Sugerowana kolejność:** MOD-01 + MOD-02 (foundation, równolegle) → MOD-03/04/05/09/10 → MOD-06 → MOD-07/08 → MOD-11/13 → MOD-12 → MOD-14 (równolegle w dowolnym momencie).

---

*Backlog wygenerowany 2026-05-23 jako realizacja ADR-014. Agent kodujący: utwórz GitHub Issues z tych ticketów (tytuł = nagłówek `## MOD-NN:` bez prefiksu, body = treść ticketu, labels `modeling`+`adr-014`+`epik-ui-08`). Per ticket dotykający >3 plików — Plan Mode przed implementacją (CLAUDE.md).*
