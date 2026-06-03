# Compact rozmowy — PIM Architecture Decisions (2026-04 → 2026-05-28)

> **Cel dokumentu:** pełen kontekst dla nowej sesji agenta — kto, co, dlaczego, gdzie jesteśmy, co dalej. Czytaj sekwencyjnie. Po przeczytaniu masz pełen pogląd bez konieczności wczytywania starych wątków.
>
> **Kontekst produktowy:** Cortex PIM — agentic-first SaaS PIM (alternatywa dla Akeneo/Pimcore). Solo dev (Marcin), ~60% zbudowany, tuż przed pierwszym klientem. Stack: PHP 8.4 + Symfony 7.4 LTS + API Platform 4 + Doctrine ORM 3.x + FrankenPHP worker mode; PostgreSQL 16 (JSONB+ltree+RLS), Meilisearch, Redis 7; React 19 + Vite 6 + Refine.dev + shadcn/ui; Mercure SSE; Anthropic SDK. Monorepo Turborepo (`apps/api`, `apps/admin`, `packages/shared-types`).
>
> **Autoryt sesyjny:** Senior Staff Backend/Full-Stack Engineer + architekt rozwiązań. CLAUDE.md to konstytucja, Project Plan to backlog, `agent/current_status.md` to single source of truth.

---

## 1. Filozofia operatora (utrwalona)

- **Uniwersalny system z klocków** — Product/Category/Asset to wbudowane instancje `ObjectType` (ADR-009), custom ObjectType jest bytem pierwszej klasy.
- **„Nie chcę być Pimcorem"** — predefiniowane = OK, hardcoded-immutable = problem. Sensible defaults to feature; magia za plecami usera to anti-pattern.
- **Agentic-first** — agent (Faza 2) musi móc modyfikować schemat naturalnym językiem. Każdy hardcoded byt = hardcoded ograniczenie agenta.
- **Solo dev przed pierwszym klientem** — minimal impact, find root causes, no over-engineering, no premature platformization.
- **Discipline testowa** — wszystkie testy zielone w DoD, PHPStan max, E2E Playwright bez wyjątku, SMOKE TEST RULE przed claim „działa", CLOSED MEANS CLOSED RULE.

---

## 2. ADR map (stan na 2026-05-28)

| ADR | Treść | Status |
|---|---|---|
| ADR-006 | Hybrid attribute model (`attributes` + junction + `object_values` + `attributes_indexed`) | Aktywne |
| ADR-009 | `ObjectType` jako koncept pierwszej klasy; Product/Category/Asset = `is_built_in=true`; custom kindy w bazie, feature flag off w MVP | Aktywne — bazowa decyzja |
| ADR-010 | Axis-driven variants | Aktywne |
| ADR-011 | Per-tenant locale fallback | Aktywne |
| ADR-012 | AttributeGroup first-class | Aktywne (zmodyfikowany przez ADR-014) |
| ADR-013 | Pełen RBAC od dnia 1 (MVP-Alpha) — 10 ról, 3-state per-attribute permissions, 7 phase'ów, 89 ticketów | Aktywne — w trakcie implementacji |
| ADR-014 | Model dystrybucji atrybutów + relacje obiekt↔obiekt; `provides_schema_overlay` capability; `object_relations` zastępuje `object_associations`; revert „Brand jako built-in" | Aktywne |

---

## 3. Chronologia kluczowych decyzji w rozmowie

### Faza A — PRD'y (na początku rozmowy)

1. **`feature-list-advanced.md`** dokończony, cross-referencowany z `epik-02-produkty.md`.
2. **`PRD-PIM-list-advanced.md`** — zaawansowana lista produktów (search, AdvancedFilterBuilder, SavedViews, Excel-like grid, bulk actions).
3. **`PRD-PIM-exports.md`** — export Excel round-trip (XLSX+CSV, SKU natural key, async hybrid <100 sync, MinIO storage, forever retention, two-pane attribute picker, saved profiles per-user).
4. **`PRD-PIM-rbac.md`** v2.1 — master spec RBAC po grilled-discovery. Macierz §3.2 pure ✓/✗, §3.5 3-state positive grants (`restricted/view/edit`) z resolution order atrybut → grupa → role-default. Multi-tenancy management UI poza MVP (DB/code hooks zostają). §3.7 Ownership, §3.8 workflow-state policy.
5. **`PRD-PIM-final-audit.md`** — placeholder na security/perf/quality audit (do uzupełnienia później).
6. **`feature-locales.md`** — mini-spec `/settings/locales`.

### Faza B — RBAC implementation plan (89 ticketów)

7. **`07-rbac-implementation-plan.md`** v3.1 — 7 phase'ów, ~330-445h, 17-19 tygodni, testing strategy 4 layers, security tooling stack, red-team checklist.
8. **`08-rbac-tickets-phase-1.md` … `14-rbac-tickets-phase-7.md`** — 89 ticketów (Issues #640-#728), milestones #9-#15.
9. **Decyzja sekwencyjna:** RBAC robimy TERAZ w MVP-Alpha (nie odkładamy), pełen scope, „raz a dobrze", iterujemy ile trzeba. Trigger zmiany kierunku przeniósł RBAC z Fazy 1 do MVP-Alpha (ADR-013).

### Faza C — Modeling burza mózgów (ADR-014)

10. Operator zgłosił 4 nieścisłości w Modelowaniu przy ~60% systemu:
    - Marka jako built-in ObjectType (niepotrzebna).
    - Brak modelu relacji obiekt↔obiekt.
    - Category jako ObjectType nie renderuje swoich atrybutów (#3-#28).
    - Niejasność kategoryzowalności custom ObjectType.
11. **ADR-014 dodany** do `Project Plan/01-architektura-pim.md`: REVERT Brand built-in; `category_attribute_groups` scoped do primary category; `EffectiveAttributeGroupResolver` bazowe źródło dla każdego ObjectType. Decyzja: **Opcja Y — Hybrid** (ObjectType base + primary category overlay cumulative). `object_relations` zastępuje `object_associations` (ADR-009 hardcoded enum → seedowane built-in atrybuty `relation`).
12. **`feature-modeling-data-model.md`** — mini-spec, 14 sekcji. Schema delta: `object_types.show_in_main_menu`, `is_categorizable`; `object_categories.is_primary`; `attributes.relation_target_object_type_ids`, `relation_cardinality`, `relation_advanced`; tabela `object_relations`.
13. **`feature-modeling-data-model-tickets.md`** — MOD-01..14, ~73-104h. Zrealizowane przez agenta kodującego (część zmergowana, część w toku).

### Faza D — §3.5 relacje placement (MODR-01..11, Opcja 2)

14. Operator wykrył babol: atrybut `relation` „Smoke test" wyrenderował się inline zamiast w zakładce „Powiązania". Diagnoza: §3.5 zawierał sprzeczność „relacja = atrybut" vs „fixed Powiązania tab".
15. **Decyzja: Opcja 2** — placement zawsze decyduje AttributeGroup + `display_mode`. Relacja to zwykły atrybut. Zero hardkodu.
16. **`feature-modeling-relations-ux-tickets.md`** — MODR-01..11 (~47-70h):
    - MODR-01 #923: `display_mode` (`tab|stacked`) na junction ObjectType×AttributeGroup.
    - MODR-02 #924: od-hardkodowanie Multimedia + Powiązania jako seedowane grupy.
    - MODR-03 #925: renderer placement po grupie + `display_mode`.
    - MODR-04 #926: przełącznik tab/stacked w wizardzie.
    - MODR-05 #927: ikona/badge relacji inline.
    - MODR-06 #928: widoczność zakładki „Powiązania" dla reverse.
    - MODR-07 #929: konfigurator relacji — domyślna grupa „Powiązania".
    - MODR-08/09/10 #930/931/932: widget relacji (rich-preview-card, inline create, inline expand/edit).
    - MODR-11 #933: docs.

### Faza E — Universal ObjectListView (shipped jako Epik UP)

17. Operator zauważył: tylko Product ma widok listy (`/products`). Custom ObjectType (Samochody) nie ma. Wyróżnianie Producta sprzeczne z ADR-009.
18. **`feature-universal-object-list.md`** — mini-spec, ULV-01..12 (~67-97h):
    - Jeden komponent `ObjectListView(objectTypeId)`.
    - Route generyczny `/objects/{slug}`, aliasy `/products|categories|assets`.
    - Meilisearch single index `objects` z facetem `object_type_id`.
    - Kolumny per ObjectType: systemowe + atrybutowe z `show_in_list` flag na junction `object_type_attributes`.
    - RBAC sparametryzowany ObjectType (`object.view/create/edit/delete/export`).
    - Funkcje warunkowe per capability flag.
19. **Zrealizowane przez Epik UP** (UP-00..UP-11, 2026-05-25) — `UniversalListPage` + `UniversalDetailPage` + `UniversalCreatePage` mountowane na `/products` ORAZ `/objects/:slug`. ULV epik (parallel MVP) świadomie odrzucony jako „półśrodek".

### Faza F — LLM Council Round 1 (hardcoded attributes + Category overlay)

20. Operator pyta: które hardkody (wbudowane AttributeGroups Multimedia/Powiązania/Audyt; specjalna rola Category) naprawiać teraz vs odłożyć, i jak ująć „kategorię"?
21. **Council Round 1 verdict:**
    - Strongest: B (First Principles), 4/5 votes. Diagnoza: „Category robi 2 rzeczy — taksonomia + schema overlay — sklejone z legacy Akeneo/Pimcore. Schemat ≠ taksonomia. Cumulative overlay to capability `provides_schema_overlay` na relacji, nie hardcoded magic."
    - Biggest blind spot: D (Contrarian), 3/5.
    - Blind spots peer review: reversibility cheap (`is_built_in` + `kind` już istnieją), RBAC Phase 3 collision, DB schema vs app code asymmetry, nikt nie pytał klienta.
    - **Chairman recommendation:** Hybryda B + Reviewer 4. Multimedia → seedowana AttributeGroup TERAZ. Category overlay → schema-level genericzność TERAZ (silnik `provides_schema_overlay`), UI universalizacja PÓŹNIEJ. AttributeGroups „Audyt"/„Powiązania" odłożyć. **Najpierw zadzwoń do klienta.**

### Faza G — Moduły vs Obiekty reframe + Council Round 2

22. Operator zaproponował reframe: usunąć Kategorie/Zasoby z „Modelowania → Obiekty", potraktować Kategorie/Multimedia/PDF jako osobne nieedytowalne **Moduły** (włączane per tenant, własne UI), w ObjectType tylko capability flags (`is_categorizable`, `has_media`, `has_variants`).
23. **Council Round 2 verdict:**
    - Strongest: D (Executor), **5/5 unanimous**. Plan: 2h schema (`system_module` enum lub reuse `is_built_in`) + 2h sidebar split + 4h capability flags. UP zostaje. Bez encji „Module", bez plugin systemu, bez ADR-014.
    - Biggest blind spot: E (Expansionist marketplace/pricing tiers), **5/5 unanimous**.
    - Blind spots peer review: brak feedbacku usera, RBAC × capability flags collision, **`is_built_in=true` JUŻ istnieje** → refactor ~2h sidebar filter (nie schema change), ADR-009 supersession needed if going schema route.
    - **Chairman recommendation:** STOP refactor. Zadzwoń do klienta 30 min, 3 pytania (czy „Kategorie"/„Zdjęcia" to MIEJSCA w głowie usera; inne moduły w 6 miesiącach; czy widoczność Multimedia/zakładki ma znaczenie). Jeśli potwierdzi → **D zmodyfikowane przez Recenzenta 4: ~4-6h zamiast 16h** (sidebar split po `is_built_in` + capability flags). Multimedia odłożona do osobnej iteracji.

### Faza H — Operator's UX worries → Option Y (Powiązania bez seedu)

24. Operator wskazał: „magiczne" pojawianie się zakładki przy populacji grupy to anti-pattern discoverability. User nie ma jak odkryć tej reguły. Albo flaga na ObjectType, albo pełna user-freedom (sam tworzy grupę). Wolał drugie — większa elastyczność i spójność.
25. Agent przyznał rację: Opcja Y (pełne odseedowanie) jest lepsza. Spójność z resztą systemu (każda grupa istnieje bo user ją utworzył), brak magii, brak flag.
26. **`feature-modeling-relations-option-y-tickets.md`** — MODRC-01..04 (~13-20h):
    - MODRC-01: kasuj seed grupy „Powiązania" (supersedes MODR-02 dla Powiązań).
    - MODRC-02: konfigurator atrybutu `relation` bez pre-selectu grupy + akcja „+ Utwórz nową grupę" inline (supersedes MODR-07).
    - MODRC-03: systemowa sekcja „Powiązania zwrotne" jako wirtualna zakładka (jedyna magia, uzasadniona — user nie zaprojektuje grupy dla powiązań, które dopiero inni do niego stworzą) (supersedes MODR-06).
    - MODRC-04: docs §3.5 + ADR-014 + lessons.md.
27. **MODR-02 #924, MODR-06 #928, MODR-07 #929 → superseded przez MODRC-01 #1080, MODRC-03 #1082, MODRC-02 #1081** (numery issue dopisane przez agenta kodującego).

### Faza I — Multimedia → Module Library (decyzja B potwierdzona)

28. Operator potwierdził: **Multimedia → Droga B (Module Library)**, jak Pimcore Assets. Centralna biblioteka mediów w sidebarze „Moduły → Multimedia", pliki współdzielone między ObjectType, podpinane przez relację. `has_media` flag na ObjectType = integracja z Modułem.
29. **Spec do zrobienia osobno** — nie ma jeszcze dedykowanego mini-speca dla Media Library Module.

### Faza J — Bounded capability flags pattern

30. Operator obawiał się proliferacji flag (`has_pricing`, `has_translations` itd. → Pimcore Classes anti-pattern).
31. **Wzorzec bounded:** flaga na ObjectType dozwolona TYLKO gdy znaczy „integracja z System Module" — nie „ma cechę X". Liczba flag = liczba Modułów, nie liczba feature'ów. Audyt → RBAC permission, nie flaga.
32. Trzy klasy bytów:
    - **Editable AttributeGroup** (Powiązania po Y, jakakolwiek user-defined): brak flagi, user tworzy świadomie.
    - **System view/panel** (Audyt): RBAC permission `audit.view`, brak flagi.
    - **System Module** (Kategorie/Media/Warianty/przyszłe Cennik/Translations): flaga `is_categorizable`/`has_media`/`has_variants` = integracja.

---

## 4. Pliki utworzone w rozmowie (mapa)

### W `Project Plan/PRD/`
- `PRD-PIM-list-advanced.md` — zaawansowana lista.
- `PRD-PIM-exports.md` — Excel round-trip export.
- `PRD-PIM-rbac.md` — master spec RBAC v2.1.
- `PRD-PIM-final-audit.md` — placeholder audit.

### W `Project Plan/`
- `07-rbac-implementation-plan.md` v3.1.
- `08-rbac-tickets-phase-1.md` … `14-rbac-tickets-phase-7.md` — 89 ticketów.
- **`conversation-compact-2026-05-28.md`** — ten plik.

### W `Project Plan/UI/`
- `feature-list-advanced.md` (rozbudowany).
- `feature-locales.md` — mini-spec `/settings/locales`.
- `feature-modeling-data-model.md` — mini-spec ADR-014 (14 sekcji).
- `feature-modeling-data-model-tickets.md` — MOD-01..14 (~73-104h).
- `feature-modeling-relations-ux-tickets.md` — MODR-01..11 (~47-70h), **MODR-02/06/07 superseded**.
- `feature-modeling-relations-option-y-tickets.md` — MODRC-01..04 (~13-20h), supersedes MODR-02/06/07.
- `feature-universal-object-list.md` — mini-spec ULV (~67-97h), **zrealizowane przez Epik UP**.

### Edytowane
- `Project Plan/01-architektura-pim.md` — ADR-014 dodany, nota o object_associations → object_relations.
- `Project Plan/UI/epik-02-produkty.md` — cross-references.
- `Project Plan/UI/00-plan-ui.md` — linki do feature-list-advanced + feature-imports.

---

## 5. Stan ticketów (kompendium)

### Active (w toku albo do zrobienia)

| Batch | Plik | Status |
|---|---|---|
| MOD-01..14 | `feature-modeling-data-model-tickets.md` | W toku — część zmergowana (MOD-01 jako #893, MOD-02 jako #894, MOD-12 jako #904 itd.) |
| MODR-01, 03, 04, 05, 08, 09, 10, 11 | `feature-modeling-relations-ux-tickets.md` | Active — pozostałe MODR-y nieosobane Y |
| MODRC-01..04 | `feature-modeling-relations-option-y-tickets.md` | Issues #1080-#1082+ utworzone, do implementacji |
| ULV-01..12 | `feature-universal-object-list.md` | **Zrealizowane** jako Epik UP (UP-00..UP-11) 2026-05-25 |
| 89 RBAC ticketów Phase 1-7 | `08-rbac-tickets-phase-1.md` … `14-rbac-tickets-phase-7.md` | W toku — milestones #9-#15 |

### Superseded
- **MODR-02 #924** → MODRC-01 #1080 (kasacja seedu Powiązań; Multimedia idzie osobno przez Module Library).
- **MODR-06 #928** → MODRC-03 #1082 (systemowa sekcja „Powiązania zwrotne").
- **MODR-07 #929** → MODRC-02 #1081 (konfigurator relacji bez defaultu grupy + inline create).
- **MODR-11 #933** → rozszerzone przez MODRC-04.

---

## 6. Decyzje świadomie odrzucone (zarejestrowane w lessons.md / mini-specach)

| Odrzucone | Powód |
|---|---|
| Warstwa „Object Template" nad ObjectType | Potworek duplikujący ObjectType; ObjectType już JEST template |
| Osobny krok „Objekty" w wizardzie ObjectType | Re-rozdziela relację od atrybutu, otwiera babola „Smoke test" |
| Reguła „relacja zawsze zakładka" | Łamie Producent-inline use case (Brand relation w grupie tożsamości) |
| Mechanizm embed/kompozycja w MVP | YAGNI; warianty/asset już mają dedicated mechanizmy |
| Families à la Akeneo | Resolver `provides_schema_overlay` jako capability na relacji rozwiązuje to bez nowego bytu |
| Flaga `has_relations` na ObjectType | Proliferacja flag → Pimcore Classes anti-pattern |
| Seed grupy „Powiązania" jako built-in | Discoverability anti-pattern — magiczne pojawianie się zakładki |
| „Magiczna" widoczność zakładki przy populacji | User nie ma jak odkryć tej reguły |
| Encja „Module" / Module Registry / marketplace | Premature platformization pre-MVP-Alpha; brak ICP validation |
| Pricing tiers / marketplace partnerów | Fantasy product strategy na podstawie 24h refleksji bez klienta |

---

## 7. Otwarte akcje (do zrobienia, w kolejności priorytetu)

### Najważniejsza pojedyncza akcja (wskazana przez 2 niezależne rady)

**Zadzwoń do pierwszego klienta. 30 minut. 3 pytania:**
1. Czy „Kategorie" i „Zdjęcia" w jego głowie to MIEJSCA obok Produktów, czy typy obiektów wśród innych obiektów?
2. Jakie inne „moduły" widzi w pierwszych 6 miesiącach (Cennik? Tłumaczenia? Katalogi PDF?)?
3. Czy potrafi sensownie zamodelować swój katalog w 2h onboardingu?

Bez tego telefonu każda decyzja architektoniczna jest spekulacją. Pół godziny zmieni pewność z 60% na 95%.

### Po telefonie — implementacja decyzji

- **Sidebar split „Obiekty" / „Moduły"** — filtr po `is_built_in` (juz istnieje od ADR-009). ~2h.
- **Capability flags** `is_categorizable`, `has_media`, `has_variants` na ObjectType. ~2-4h.
- **MODRC-01..04** — kasacja seedu Powiązań, systemowa sekcja reverse, konfigurator bez defaultu. ~13-20h.
- **Multimedia → Media Library Module mini-spec** + tickets. Do napisania.
- **RBAC × capability flags interakcja** — ustalić w voterze hierarchię: capability flag decyduje czy zakładka istnieje, RBAC decyduje co user widzi wewnątrz. 1-2h decyzji designerskiej.

### Kontynuacja istniejących batchy

- MOD-01..14 (Modelowanie data model + relacje backend) — w toku.
- MODR-01, 03, 04, 05, 08, 09, 10, 11 (relations UX, części aktualne) — w toku.
- 89 RBAC ticketów Phase 1-7 — w toku, MVP-Alpha + część MVP-Final.

---

## 8. Kluczowe wzorce do utrzymania

### Z CLAUDE.md (konstytucja)

- **EPIK MARATHON RULE** — gdy operator mówi „przez cały epik", agent nie deferuje, nie skipuje, nie bundle'uje.
- **SMOKE TEST RULE** — bez manual smoke test na żywym backendzie nie wolno claim „działa" w PR.
- **CLOSED MEANS CLOSED RULE** — `gh issue close` wymaga live-stack smoke test proof w comment.
- **Memory management FrankenPHP** — każdy Messenger handler dziedziczy `AbstractBatchHandler` lub woła `EntityManager::clear()` po `flush()` w batchach.
- **Single-origin przez Caddy** — nigdy nie konfiguruj CORS. Cały ruch przez `pim.localhost` / `pim.example.com`.
- **Multi-tenancy** — `tenant_id UUID NOT NULL` na każdej tabeli domenowej. Doctrine TenantFilter + Postgres RLS jako defence in depth.
- **Shopify throttling** — Exponential Backoff only, Leaky Bucket dopiero w Fazie 1.
- **Agent security limits** — 50 tool calls/h, 10/run, 100k tokens/run, 500k/dzień, $20/dzień/tenant, $300/miesiąc.

### Z PIM architecture (Reguły implementacyjne)

1. Bounded Contexts: Catalog, Channel, Asset, Integration, Identity, Agent, ApiConfigurator.
2. Każda integracja = bundle z Adapter/Client/MessageHandler/Webhook/ConfigForm.
3. API jest produktem first-class — admin używa tych samych endpointów co integratorzy. Wszystko przez API Platform.
4. Hybrid attribute model parametryzowany per ObjectType (ADR-006 + ADR-009). JSONB shapes per `docs/api/jsonb-schemas.md`.
5. Provenance pole obowiązkowe: `manual | import | agent | integration` + meta JSONB.
6. Approval flow dla agenta — `pending_changes` table, UI inbox/diff.
7. Brak hardkodowanych URL/kluczy/sekretów w kodzie.
8. i18n — wszystkie stringi UI przez `t()`. Label/help atrybutów jako JSONB `{"pl": ..., "en": ...}`.
9. Cursor pagination dla list >1000. RFC 7807 Problem Details.
10. ObjectType pierwszej klasy (ADR-009).

### Z lessons.md per discovery

- **„Sensible defaults to feature, hardcoded-immutable to problem"** — wzorzec rozróżnienia seedowanych built-in od hardkodów.
- **Capability flag tylko jako integracja z System Module** — nie „ma cechę X".
- **Każdy babol § wykryty w praktyce → reframe spec, nie patch kodu** — np. „Smoke test" babol odsłonił sprzeczność w §3.5.
- **2 niezależne rady wskazują na to samo = zignorowany blocker** — jeśli rada drugi raz mówi „zadzwoń do klienta", to nie przypadek.
- **Reversibility check** — sprawdź czy `is_built_in`/`kind` foundation już istnieje przed nową kolumną w schemacie.

---

## 9. Specyficzne case'y rozwiązane w rozmowie

### Case: Salon Sprzedaży jako część Samochody

- **Architektura:** Salon Sprzedaży = własny ObjectType z atrybutami (nazwa, adres, lokalizacja, zdjęcia). `show_in_main_menu=true`. Na Samochodzie atrybut typu `relation` → target Salon, cardinality `many`.
- **Dwa poziomy:** Model (definicja atrybutu na ObjectType) + Instancja (wiersz w `object_relations` per powiązanie). Reverse dostajesz gratis.
- **UX:** Widget relacji z rich-preview-card (nazwa + miasto + miniaturka), inline create targetu, inline expand/edit. Brak nowego konceptu.

### Case: Powiązania ad-hoc

- Ad hoc attributes = escape hatch + diagnostic signal (5th tripwire metric — przyrost ad hoc = sygnał brakującego modelu).
- Governance: ścieżka promocji (gdy ten sam atrybut ad hoc pojawia się N razy → sugestia „dodać na stałe").
- Pułapka: ad hoc maskuje pozostałe tripwire'y — user nie narzeka, tylko po cichu klepie ad hoc.

### Case: tripwires dla rewizji modelu (kiedy rozważyć Families)

4 metryki + 5-ta (ad hoc growth):
1. **Eksplozja atrybutów na liściu** — mediana/p95 efektywnych atrybutów na obiekt. Próg: p95 > 150.
2. **Ten sam atrybut/grupa w niespokrewnionych gałęziach** — % grup podpiętych w wielu miejscach. Próg: >30%.
3. **Churn kategoryzacji** — liczba zmian primary category na obiekt w 30 dniach. Próg: mediana >1-2.
4. **Completeness systematycznie niski** — rozkład per ObjectType. Próg: garbi się przy 60-70%.
5. **Przyrost atrybutów ad hoc** — sygnał brakującego modelu.

**Trigger Family:** 2 metryki nad progiem na ≥2 niezależnych tenantach.

### Case: Pimcore comparison (gdy operator pytał czy Pimcore lepszy)

- Pimcore: Class + Field Collections + Object Bricks + Classification Store. 4 koncepty, stroma krzywa, ale schema decoupled od category.
- Akeneo: Family obowiązkowy per produkt, kategoria czysto nawigacyjna. Prostsze, mniej elastyczne.
- Nasz: ObjectType base + primary category overlay cumulative. Pomiędzy — pod warunkiem że resolver pozostaje czystą abstrakcją źródeł nakładki.
- Wniosek: nie kopiuj Pimcore (zbyt złożony), ale przejmij dekupling overlay od pojedynczego nośnika.

---

## 10. Co czytać dalej (jeśli nowy agent potrzebuje głębi)

W kolejności priorytetu:

1. **`CLAUDE.md`** (oba folderowe warianty — `/Users/mlipieclocal/dev/PIM/CLAUDE.md` i workspace) — konstytucja, twarde reguły.
2. **`Project Plan/01-architektura-pim.md`** — pełen ADR list (1-14), reguły implementacyjne, schemat bazy.
3. **`Project Plan/02-plan-projektu-pim.md`** — backlog i estymacje, checkboxy ticketów.
4. **`agent/current_status.md`** — gdzie dokładnie jesteśmy (aktualna sub-faza, ticket, ostatnie akcje, blokery).
5. **`agent/lessons.md`** — Patterns to Follow / Avoid / Decyzje świadome + per-ticket lessons.
6. **Mini-speci w `Project Plan/UI/`** — `feature-modeling-data-model.md`, `feature-modeling-relations-option-y-tickets.md`, `feature-universal-object-list.md`.
7. **`Project Plan/PRD/PRD-PIM-rbac.md`** — RBAC master spec.

---

## 11. Quick reference — najnowszy stan (one-liner per topic)

- **Modeling placement relacji:** Option Y — user świadomie tworzy grupę, brak seedu, system reverse section gratis. MODRC-01..04 w toku.
- **Multimedia:** Droga B (Module Library) — `has_media` flag = integracja. Spec do napisania.
- **Kategorie:** osobny System Module w sidebarze, `is_categorizable` flag = integracja. ~2h sidebar split.
- **Universal list:** **DONE** via Epik UP (UP-00..UP-11) 2026-05-25.
- **RBAC:** Phase 1-7, 89 ticketów, w toku w MVP-Alpha + część MVP-Final.
- **Capability flags bounded:** dozwolone TYLKO jako „integracja z System Module".
- **Open critical action:** zadzwoń do klienta (3 pytania, 30 min) PRZED dalszymi decyzjami architektonicznymi.

---

*Compact wygenerowany 2026-05-28. Stan po: shipped Epik UP, supersedowaniu MODR-02/06/07 przez MODRC-01..03, decyzji Multimedia → Droga B (Moduł), bounded capability flags pattern. Dla nowej sesji: zacznij od tego pliku, potem CLAUDE.md, potem current_status.md.*
