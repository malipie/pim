# Feature (mini-spec) — Uniwersalny widok listy dla każdego ObjectType

**Typ dokumentu:** Mini-spec implementacyjny (kontrakt) — gotowy do rozpisania na GitHub Issues przez agenta kodującego
**Status:** Draft
**Data:** 2026-05-25
**Powiązane:**
- [ADR-009](../01-architektura-pim.md) — *„ObjectType jako koncept pierwszej klasy"* (ten feature jest jego bezpośrednią konsekwencją)
- [`PRD-PIM-list-advanced.md`](../PRD/PRD-PIM-list-advanced.md) — spec zaawansowanej listy (wyszukiwarka, filtry, saved views, Excel-like grid)
- [`PRD-PIM-rbac.md`](../PRD/PRD-PIM-rbac.md) — RBAC (§3.2 macierz uprawnień, §3.5 scope per atrybut/locale/channel)
- [`feature-modeling-data-model.md`](feature-modeling-data-model.md) — capability flags ObjectType (`show_in_main_menu`, `is_categorizable`, `has_variants`)
- [`epik-08-modelowanie.md`](epik-08-modelowanie.md), [`epik-02-produkty.md`](epik-02-produkty.md) — epiki nadrzędne

---

## 1. Cel

Dziś tylko wbudowany ObjectType **Product** ma widok listy (`/products`). Każdy inny ObjectType — wbudowany (Category, Asset) lub custom (np. „Samochody") — nie ma uniwersalnego widoku listy instancji.

**Cel:** dowolny ObjectType z flagą `show_in_main_menu=true` pojawia się w menu i renderuje **ten sam** zaawansowany widok listy co Produkty — wyszukiwarka, filtry, akcje zbiorcze, saved views. Jeden uniwersalny komponent `ObjectListView` sparametryzowany `objectTypeId`. **Product przestaje być wyróżniony** — `/products` staje się jednym z wywołań tego komponentu. Każda przyszła zmiana w widoku listy obejmuje z definicji wszystkie ObjectType, bo istnieje jeden byt, nie kopie.

## 2. Problem / root cause / przeramowanie

**Root cause:** widok `/products` został zbudowany z założeniami specyficznymi dla Produktu wbudowanymi w kod (kolumny, hooki danych, typowanie). To sprzeczne z ADR-009 — skoro Product/Category/Asset to tylko wbudowane instancje `ObjectType`, a custom ObjectType jest bytem pierwszej klasy, to widok listy instancji też musi być uniwersalny. Product-specjalna lista jest anomalią, nie feature'em.

**Przeramowanie:** to **nie jest nowy feature**. To realizacja [`PRD-PIM-list-advanced.md`](../PRD/PRD-PIM-list-advanced.md) **sparametryzowana przez ObjectType** + odwiązanie istniejących komponentów epiku UI-02 (`ExcelLikeGrid`, `AdvancedFilterBuilder`, `SavedViewsDropdown`, `CreateWizard`) od Produktu. Większość pracy to refaktor parametryzujący, nie budowa od zera.

## 3. Model rozwiązania — decyzje

1. **Jeden komponent `ObjectListView`** z propem `objectTypeId`. `/products` = `<ObjectListView objectTypeId={productTypeId} />`. Kryterium odbioru całości: w logice listy nie istnieje słowo „product" poza seedem i aliasem route.
2. **Kolumny per ObjectType.** Stały zestaw kolumn systemowych (identyfikator, completeness, status, zmodyfikowano) + kolumny atrybutowe sterowane flagą `show_in_list` na junction `object_type_attributes` (kontekstowo, analogicznie do `display_mode` z MODR-01). Saved Views nakładają per-view override kolumn.
3. **Routing generyczny** `/objects/{slug}`. `/products`, `/categories`, `/assets` zostają jako **aliasy/redirecty** dla trójki built-in — spójność z sugar-paths API z ADR-009 + zakładki userów.
4. **Meilisearch — jeden indeks `objects`** z facetem `object_type_id`. Jeśli dziś są indeksy per-typ — konsolidacja jest prerekwizytem.
5. **RBAC sparametryzowany ObjectType** — żadnego hardkodowanego `products.*`. Patrz §8.
6. **Funkcje warunkowe per capability flag** — warianty tylko gdy `has_variants`, sidebar drzewa kategorii tylko gdy `is_categorizable`. Uniwersalny ≠ identyczny: jeden komponent renderujący warunkowo.
7. **Menu** — ObjectType z `show_in_main_menu=true` renderuje pozycję w sidebarze (label z ObjectType, link do `/objects/{slug}`), o ile użytkownik ma uprawnienie `object.view` na tym ObjectType.

## 4. Schema delta

```sql
-- Kolumny listy — które atrybuty pokazać i w jakiej kolejności (kontekstowe per ObjectType)
ALTER TABLE object_type_attributes ADD COLUMN show_in_list  BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE object_type_attributes ADD COLUMN list_position INTEGER NOT NULL DEFAULT 0;
```

**Do weryfikacji w pre-flight (jeśli brak — dodać w ULV-01):**
- `object_types.slug` — URL-safe identyfikator do routingu `/objects/{slug}`. Jeśli istnieje `code`/`handle` spełniający wymóg — reuse.
- `saved_views.object_type_id` — Saved Views muszą być scope'owane per ObjectType (widok dla „Samochodów" nie ma sensu dla „Produktów"). Jeśli kolumny brak — dodać + migracja istniejących views Produktu.

## 5. Backend — zapytanie listy + wyszukiwanie

- **Endpoint listy:** generyczny `GET /api/objects?objectType={id}` (lub generalizacja istniejącego endpointu produktów) — cursor-based pagination (>1000, ADR/§9 architektury), filtry, sort, full-text search. RFC 7807 Problem Details dla błędów.
- **List-schema:** `GET /api/object-types/{id}/list-schema` — zwraca kolumny (systemowe + atrybutowe z `show_in_list`), atrybuty filtrowalne, atrybuty wyszukiwalne. Analog `form-schema`.
- **Meilisearch:** jeden indeks `objects`, dokument zawiera `object_type_id` + `tenant_id` jako filtrowalne facety. Wyszukiwanie listy = query do indeksu z filtrem `object_type_id = X AND tenant_id = T`. Jeśli stan obecny to indeksy per-typ — ULV-02 konsoliduje (reindex).
- **Worker mode** — zapytania listowe i reindex zgodne z regułą `EntityManager::clear()` w batchach (FrankenPHP worker, sekcja 3.10 architektury).

## 6. Routing + menu

- Route generyczny: `/objects/{slug}` → `<ObjectListView objectTypeId={...} />`.
- Aliasy: `/products`, `/categories`, `/assets` → redirect/alias do generycznego (zachowanie sugar-paths).
- Sidebar: pozycje menu generowane z ObjectType o `show_in_main_menu=true`, filtrowane uprawnieniem `object.view`. Brak uprawnienia → brak pozycji w menu (nie wyszarzona — ukryta).
- Deep-link do listy ObjectType bez uprawnień → 403 (strona), nie ciche przekierowanie.

## 7. Kolumny i konfiguracja

- **Kolumny systemowe (zawsze):** identyfikator obiektu, completeness, status workflow, data modyfikacji. Renderowane niezależnie od ObjectType.
- **Kolumny atrybutowe:** atrybuty z `show_in_list=true`, kolejność wg `list_position`.
- **Konfiguracja:** w wizardzie ObjectType (krok „Atrybuty") — toggle „pokaż w liście" + pozycja per atrybut. Bez osobnego „designera listy" (YAGNI).
- **Saved Views** (z `PRD-PIM-list-advanced`) nakładają per-view override kolumn/filtrów/sortu — warstwa ponad domyślnym zestawem.
- **Field-level:** kolumna atrybutowa nie renderuje się, jeśli użytkownik ma na tym atrybucie uprawnienie `restricted` (§8).

## 8. RBAC i uprawnienia

**Decyzja:** uprawnienia listy to **generyczne czasowniki scope'owane per ObjectType** — `object.view`, `object.create`, `object.edit`, `object.delete`, `object.export` — gdzie scope = konkretny ObjectType (analogicznie do scope per-locale/channel z `PRD-PIM-rbac` §3.5). Built-in `products.*` / `categories.*` / `assets.*` zostają jako aliasy/scope na ObjectType Product/Category/Asset.

- **Rejestracja uprawnień:** utworzenie ObjectType rejestruje jego zestaw uprawnień (lub permissions są rozwiązywane dynamicznie po `object_type_id`). Wymaga koordynacji z RBAC Phase 3 (Permission Engine, milestone #11) — patrz §16.
- **Voter parametryzowany ObjectType** — każdy check (`view`/`create`/`edit`/`delete`/`export`) przyjmuje ObjectType jako część scope. Zero hardkodowanego `products.*` w logice listy.
- **Field-level filtering** — serializer wiersza listy stosuje 3-state attribute permissions (`restricted`/`view`/`edit`) z `PRD-PIM-rbac` §3.5: atrybut `restricted` nie pojawia się jako kolumna ani w danych wiersza. Ta sama logika co field-level dla formularza (RBAC Phase 3).
- **Akcje zbiorcze** — każda akcja re-weryfikuje uprawnienie **po stronie serwera** per ObjectType, nie tylko gating UI. Bulk delete 1000 obiektów → check `object.delete` zanim cokolwiek zostanie usunięte.
- **Super Admin bypass** — zgodnie z RBAC Phase 3 (ticket Super Admin bypass), bez specjalnej obsługi tutaj.

## 9. Bezpieczeństwo

- **Izolacja tenanta** — każde zapytanie listy filtrowane `tenant_id` przez Doctrine `TenantFilter` + Postgres RLS (defence in depth). Meilisearch — filtr `tenant_id` obowiązkowy w każdym query. Smoke-test izolacji: 2 tenanty, lista ObjectType jednego nie zwraca instancji drugiego (0 wyników).
- **IDOR** — `/objects/{slug}` dla ObjectType bez uprawnienia `object.view` → 403/404, nigdy ciche zwrócenie danych.
- **Injection** — `AdvancedFilterBuilder` generuje zapytania wyłącznie przez parametryzowane query (Doctrine QueryBuilder / parametryzowane filtry Meilisearch). Żadnego raw SQL ani konkatenacji stringów filtrów z inputu użytkownika. Wejście filtra walidowane względem `list-schema` (atrybut musi być filtrowalny).
- **Eksport** — bulk export respektuje field-level permissions: atrybuty `restricted` wykluczone z pliku eksportu. Zgodne z `PRD-PIM-exports`.
- **Rate / payload** — bulk actions z limitem rozmiaru selekcji; operacje masowe (>1000) idą async przez Messenger handler z `EntityManager::clear()` w batchach.
- **Audit** — akcje destrukcyjne (bulk delete, bulk status change) logowane do audit logu (RBAC Phase 3 audit), z `object_type_id` + liczbą affected.

## 10. UI / UX

- `ObjectListView` — jeden komponent: header z nazwą ObjectType, wyszukiwarka, `AdvancedFilterBuilder`, `SavedViewsDropdown`, grid (`ExcelLikeGrid`), pasek akcji zbiorczych, paginacja cursor-based.
- Pusty stan — gdy ObjectType nie ma jeszcze instancji: CTA „Utwórz" (przez `CreateWizard`, gating uprawnieniem `object.create`).
- Funkcje warunkowe: kolumna/expander wariantów tylko gdy `has_variants`; sidebar drzewa kategorii jako filtr tylko gdy `is_categorizable`.
- i18n — wszystkie stringi przez `t()` (react-i18next), klucze angielskie, tłumaczenia `pl`/`en`. Nazwa ObjectType i etykiety atrybutów z JSONB wielojęzycznego.
- a11y — komponent waliduje się axe-core (grid, filtry, akcje); shadcn/Radix daje bazę, customowe części wymagają sprawdzenia.

## 11. API

| Endpoint | Metoda | Opis |
|---|---|---|
| `/api/objects?objectType={id}` | GET | Lista instancji ObjectType — cursor pagination, filtry, sort, search |
| `/api/object-types/{id}/list-schema` | GET | Kolumny (systemowe + atrybutowe), atrybuty filtrowalne/wyszukiwalne |
| `/api/objects/bulk` | POST | Akcje zbiorcze (delete, change-status, assign-category, export) — re-check uprawnień per akcja |
| `/api/object-types/{id}/attributes` | PATCH | `show_in_list` + `list_position` per atrybut |
| `/api/saved-views` | GET/POST/PATCH/DELETE | Saved views scope'owane `object_type_id` (z `PRD-PIM-list-advanced`) |

Wszystko przez API Platform tam, gdzie wystarcza; custom REST tylko gdy AP4 nie wystarcza (reguła implementacyjna #3). API jest produktem first-class — integratorzy używają tych samych endpointów.

## 12. Testowanie i CI

Definicja „Done" = zielone bramki automatyczne (CLAUDE.md sekcja 2.2). Każdy ticket w DoD ma **wszystkie testy zielone — co się da pokryć**:

- **Backend unit (PHPUnit)** — ≥80% nowej logiki: voter parametryzowany, list-schema builder, walidacja filtrów, field-level filtering.
- **Backend integration (ApiTestCase + realny Postgres/testcontainers)** — endpointy listy / bulk / list-schema; izolacja tenanta (cross-read = 0); RBAC (brak uprawnienia → 403); migracje UP/DOWN.
- **Frontend unit (Vitest)** — `ObjectListView` z różnymi `objectTypeId`, dynamiczne kolumny, funkcje warunkowe per capability flag.
- **E2E (Playwright)** — bez E2E ticket NIE jest done: lista custom ObjectType renderuje się z filtrami i akcjami; Product przez `ObjectListView` bez regresji; menu pokazuje/ukrywa ObjectType wg uprawnień.
- **Manual smoke test** na żywym stacku (`https://pim.localhost`) per SMOKE TEST RULE — przed claim „działa" w PR: login → klik w pozycję menu ObjectType → status 200 → lista renderuje dane → brak czerwonych błędów w Console.
- **Regresja** — pełne E2E listy Produktów jako baseline PRZED refaktorem i zielone PO (ULV-11). Product wychodzi z refaktora bez zmiany wizualnej ani funkcjonalnej.
- **Security** — testy izolacji tenanta + IDOR + field-level filtering w warstwie integration; `composer audit` / `npm audit`.
- **CI** — PHPStan max + Biome strict + cały pipeline zielony przed merge.

## 13. User stories

| ID | Persona | Story |
|---|---|---|
| US-ULV-001 | Modeler | Tworzy ObjectType „Samochody", zaznacza `show_in_main_menu` — pozycja „Samochody" pojawia się w sidebarze |
| US-ULV-002 | Edytor | Wchodzi w „Samochody", widzi listę z wyszukiwarką, filtrami i akcjami zbiorczymi — identyczną jak Produkty |
| US-ULV-003 | Modeler | W wizardzie ObjectType zaznacza atrybuty `pokaż w liście` + ustawia kolejność — kolumny listy odzwierciedlają wybór |
| US-ULV-004 | Edytor | Zapisuje Saved View „Samochody premium" — widok scope'owany do ObjectType Samochody, niedostępny w Produktach |
| US-ULV-005 | Użytkownik bez uprawnień | Nie ma `object.view` na „Samochody" — pozycja menu ukryta, deep-link → 403 |
| US-ULV-006 | Edytor z atrybutem restricted | Atrybut „Marża" `restricted` — kolumna nie pojawia się na liście, dane wiersza jej nie zawierają |
| US-ULV-007 | Edytor | Bulk delete 50 obiektów — serwer re-weryfikuje `object.delete`, akcja w audit logu |

## 14. Poza zakresem (MVP)

- ❌ Graficzny designer układu listy — `show_in_list` + Saved Views wystarczają.
- ❌ Custom akcje zbiorcze per ObjectType — MVP ma generyczny zestaw (delete, status, kategoria, export).
- ❌ Nowe funkcje listy ponad `PRD-PIM-list-advanced` — ten feature parametryzuje istniejący scope, nie rozszerza go.
- ❌ Widoki inne niż lista (kanban, kalendarz) — Faza 2+.

## 15. Backlog — kandydaci na tickety

Prefiks `ULV`. Agent kodujący: utwórz GitHub Issues (tytuł angielski Conventional Commits, opis polski, labels `object-list` + `epik-ui-08` + `adr-009`). Per ticket >3 plików — Plan Mode przed implementacją.

| Ticket | Typ | Zakres | Estymacja | Dependencies |
|---|---|---|---|---|
| ULV-01 | `feat(catalog)` | Schema delta: `show_in_list` + `list_position` na junction; weryfikacja/dodanie `object_types.slug` i `saved_views.object_type_id` | 3-4h | foundation |
| ULV-02 | `refactor(catalog)` | Meilisearch: jeden indeks `objects` z facetem `object_type_id` + reindex (jeśli dziś per-typ) | 6-10h | foundation |
| ULV-03 | `feat(catalog)` | Uniwersalny endpoint listy `GET /api/objects?objectType=` + `list-schema`, cursor pagination, filtry, search | 8-12h | ULV-01, ULV-02 |
| ULV-04 | `feat(identity)` | RBAC: generyczne `object.*` scope'owane per ObjectType; voter parametryzowany; field-level column filtering | 8-12h | ULV-03; koordynacja RBAC Phase 3 |
| ULV-05 | `feat(catalog)` | Uniwersalne akcje zbiorcze `POST /api/objects/bulk` + server-side re-check uprawnień + audit | 6-8h | ULV-03, ULV-04 |
| ULV-06 | `feat(admin)` | Komponent `ObjectListView` (prop `objectTypeId`) — odwiązanie komponentów UI-02 od Produktu | 10-14h | ULV-03 |
| ULV-07 | `feat(admin)` | Dynamiczne kolumny (systemowe + `show_in_list`) + override z Saved Views | 6-8h | ULV-06 |
| ULV-08 | `feat(admin)` | Route generyczny `/objects/{slug}` + aliasy `/products|categories|assets` + sidebar z ObjectType (`show_in_main_menu` + gating uprawnieniem) | 5-7h | ULV-06 |
| ULV-09 | `feat(admin)` | Funkcje warunkowe per capability flag (warianty `has_variants`, filtr drzewa kategorii `is_categorizable`) | 4-6h | ULV-06 |
| ULV-10 | `feat(admin)` | UI konfiguracji kolumn w wizardzie ObjectType (toggle `pokaż w liście` + pozycja per atrybut) | 5-7h | ULV-01 |
| ULV-11 | `refactor(admin)` | Cutover `/products` na `ObjectListView` + E2E regresji (baseline przed/po) | 4-6h | ULV-06..ULV-09 |
| ULV-12 | `docs` | Update `epik-02`/`epik-08` + cross-ref do tej mini-spec; nota w ADR-009 jeśli potrzebna | 2-3h | równolegle |
| | | **TOTAL** | **~67-97h** | ~2-3 tygodnie solo dev |

**Sugerowana kolejność:** ULV-01 + ULV-02 (foundation) → ULV-03 → ULV-04 → ULV-05 → ULV-06 → ULV-07 / ULV-08 / ULV-09 → ULV-10 → ULV-11 → ULV-12.

## 16. Co dalej / pre-flight przed estymacją finalną

Trzy rzeczy do zweryfikowania w pre-flight (mogą przesunąć estymacje):
1. **Indeks Meilisearch** — jeden `objects` czy per-typ? Jeśli per-typ, ULV-02 rośnie o reindex całego datasetu.
2. **Obecny `/products`** — jak mocno komponenty UI-02 są związane z Produktem (hooki, typowanie)? Określa realny koszt ULV-06.
3. **RBAC** — czy macierz `PRD-PIM-rbac` §3.2 obejmuje tylko built-in 3, czy ma już mechanizm per-ObjectType? Jeśli tylko built-in 3 — ULV-04 wymaga rozszerzenia Permission Engine (koordynacja z milestone #11, RBAC Phase 3) i może być osobnym blokiem prac, nie pojedynczym ticketem.

Decyzja architektoniczna: feature **nie wymaga nowego ADR** — to realizacja ADR-009. Wystarczy krótka nota w ADR-009 (ULV-12), że widok listy instancji jest uniwersalny per ObjectType.

---

*Mini-spec wygenerowany 2026-05-25. Kontrakt dla agenta kodującego — §15 to kandydaci na GitHub Issues. Każdy ticket: wszystkie testy zielone w DoD, RBAC + izolacja tenanta + field-level filtering obowiązkowe, CI green przed merge (CLAUDE.md sekcja 2.2 + SMOKE TEST RULE + CLOSED MEANS CLOSED RULE).*
