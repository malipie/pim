# Conversation Handoff — Cortex PIM (architecture session, 2026-05-23 → 2026-05-28)

**Typ dokumentu:** Brief startowy dla nowego agenta — pełen kontekst architektoniczny z wielodniowej rozmowy. Czytaj zanim podejmiesz decyzje dotyczące Modelowania, Relacji, listy uniwersalnej lub RBAC.
**Data utworzenia:** 2026-05-28
**Operator:** Marcin (solo dev, ~60% systemu zbudowane, tuż przed pierwszym klientem)

---

## 0. TL;DR — co musisz wiedzieć w 60 sekund

Marcin buduje **Cortex PIM** — agentic-first SaaS PIM (alternatywa dla Akeneo/Pimcore), single-tenant deploy / multi-tenant ready, MVP-Alpha tuż przed pilotem. W ciągu ostatnich dni przeszliśmy długą sesję projektową obejmującą: feature-list-advanced, exports, RBAC, modelowanie obiektów, relacje, universal list view, Moduły vs Obiekty reframe, oraz dwie sesje LLM Council.

**Najważniejszy outstanding action (jedyny blocker dalszych decyzji):**
**Zadzwoń do pierwszego klienta. 30 min.** Dwie niezależne rady LLM wskazały na to samo — każda decyzja architektoniczna w tej rozmowie jest spekulacją do czasu tej rozmowy.

**Aktualny stan implementacyjny (per system reminders):**
- Epik UP (UP-00..UP-11) **shipped 2026-05-25** — uniwersalny list/detail/create dla wszystkich ObjectType
- MOD-01..14 (ADR-014 implementation) — issues #893+ utworzone
- MODR-01..11 (placement/UX relacji) — issues #923-933 utworzone, **MODR-02/06/07 częściowo superseded** przez MODRC-01..03 (#1080-1082, decyzja 2026-05-28)
- RBAC Phase 1-7 — 89 ticketów rozpisane (Issues #640-#728, milestones #9-#15)

---

## 1. Kontekst projektu (na potrzeby handoff)

**Cortex PIM** — patrz `CLAUDE.md` w root i `dev/PIM/CLAUDE.md`. Stack:
- Backend: PHP 8.4 + Symfony 7.4 LTS + API Platform 4 + Doctrine ORM 3.x + FrankenPHP 2.x worker mode
- DB: PostgreSQL 16 (JSONB+ltree+RLS), Meilisearch, Redis 7
- Frontend: TypeScript 5 + React 19 + Vite 6 + Refine.dev + shadcn/ui
- Agent layer: Anthropic SDK PHP (Faza 2)

**Filozofia operatora (POWTARZAJĄCY SIĘ MOTYW — KRYTYCZNY):**
- „Uniwersalny system z klocków, ale **NIE Pimcore**" (Pimcore = zbyt złożony, stroma krzywa uczenia)
- „**Predefiniowane = OK, hardcoded-immutable = problem**"
- Każda nowa flaga / koncept architektoniczny wymaga świadomej decyzji, nie odruchu
- Agent-first w modelu mentalnym, choć agent layer fizycznie Faza 2

**Reguły operacyjne (NIENEGOCJOWALNE — z CLAUDE.md):**
- EPIK MARATHON RULE — gdy mówi „przez cały epik" = każdy ticket = osobny PR, bez deferrowania
- SMOKE TEST RULE — przed claim „działa" w PR opisie wymagany manual smoke test na żywym stacku
- CLOSED MEANS CLOSED RULE — `gh issue close` wymaga live-stack smoke testu jako proof w close comment
- Testy zielone w DoD — backend PHPUnit + ApiTestCase, frontend Vitest + Playwright E2E (bez E2E ticket NIE jest done)

---

## 2. Mapa plików utworzonych/zmodyfikowanych w tej rozmowie

### PRD-y (`Project Plan/PRD/`)
| Plik | Status |
|---|---|
| `PRD-PIM-list-advanced.md` | Utworzony, finalny — feature-PRD listy zaawansowanej (16 sekcji) |
| `PRD-PIM-exports.md` | Utworzony, finalny — po 5-wave wywiadzie (Excel round-trip, SKU key, XLSX+CSV, async hybrid <100, MinIO, forever retention) |
| `PRD-PIM-rbac.md` | Utworzony v2.1 — master spec RBAC. §3.2 macierz pure ✓/✗, §3.5 rewrite na 3-state positive grants (`restricted`/`view`/`edit`) z resolution order attribute→group→role-default |
| `PRD-PIM-final-audit.md` | Placeholder (security/performance/quality audit, nic teraz) |

### Mini-speci i tickety modelowania (`Project Plan/UI/`)
| Plik | Status |
|---|---|
| `feature-locales.md` | Utworzony — mini-spec `/settings/locales` |
| `feature-modeling-data-model.md` | Utworzony, finalny — realizacja ADR-014. §3.5 wymaga aktualizacji per Opcja Y (MODRC-04) |
| `feature-modeling-data-model-tickets.md` | MOD-01..14, ~73-104h, issues #893+ utworzone |
| `feature-universal-object-list.md` | **Shipped via Epik UP (UP-00..UP-11) 2026-05-25.** ULV epik świadomie odrzucony przez operatora jako „półśrodek" |
| `feature-modeling-relations-ux-tickets.md` | MODR-01..11, ~47-70h, issues #923-933. **MODR-02 (#924), MODR-06 (#928), MODR-07 (#929) superseded przez MODRC-01..03** |
| `feature-modeling-relations-option-y-tickets.md` | **NAJNOWSZY (2026-05-28).** MODRC-01..04, ~13-20h. Decyzja Option Y — pełne odseedowanie grupy Powiązania |

### RBAC backlog (`Project Plan/`)
| Plik | Status |
|---|---|
| `07-rbac-implementation-plan.md` | v3.1 — 7 faz, testing strategy (4 layers), security tooling |
| `08-rbac-tickets-phase-1.md` ... `14-rbac-tickets-phase-7.md` | 89 ticketów RBAC, Issues #640-#728, milestones #9-#15 |

### Architektura i sterujące (`Project Plan/`)
- `01-architektura-pim.md` — **ADR-014 dodany** („Model dystrybucji atrybutów + relacje obiekt↔obiekt"). Definiuje `object_relations` jako zastępca `object_associations`
- `02-plan-projektu-pim.md` — backlog i estymacje (utrzymywane atomowo)

---

## 3. Chronologia decyzji architektonicznych — co się działo

### Wątek A — feature-list-advanced + exports + RBAC + locales
1. Operator poprosił o ukończenie `feature-list-advanced.md` + cross-reference z `epik-02-produkty.md`
2. `/to-prd-md-pim` → `PRD-PIM-list-advanced.md`
3. `/grill-me-prd-pim` dla eksportu (5 fal) → `PRD-PIM-exports.md`. Kluczowe decyzje: Excel round-trip primary, SKU as natural key, XLSX+CSV, two-pane attribute picker, saved profiles per-user, async hybrid threshold <100 sync, MinIO storage, forever retention
4. `/grill-me-prd-pim` dla RBAC. **Konstraint operatora:** multi-tenancy management UI POMINIĘTY w MVP (ale DB/code hooks gdyby były potrzebne); podstawowe permissions
5. Operator pyta: kiedy implementować RBAC? Wniosek: **TERAZ, full scope, „nic nie dzielimy na fazy, wszystko teraz, raz a dobrze, iterujemy tyle razy ile trzeba"**
6. Operator wskazał: chcę Super Admina do zarządzania tenantami. Nie dzielić na fazy.
7. Po refleksji nad macierzą §3.2: usunięto (own)/(view)/(per-X) magic notation — czyste ✓/✗
8. Operator: workflow nie tworzymy, ale uprawnienia na atrybutach mają to wspierać (Jan dodaje, Maria uzupełnia tylko cenę)
9. Po pokazaniu screenshotu — operator zaproponował przełączenie z negatywnego blacklistingu na **3-state positive grants** (restricted/view/edit), pogrupowane w grupy z komunikatem „Uprawnienia częściowe"
10. Akceptacja: A. Macierz first → C. Inherit z macierzy → A. Visible w current view → B. Preview modal
11. **`feature-locales.md`** — mini-spec dla `/settings/locales` (Opcja c Polish + b)

### Wątek B — Modelowanie (sesja burzy mózgów 2026-05-23)
Operator zaczął: „powstał kawał systemu, zaczynam widzieć nieścisłości w Modelowaniu". Wykryto 4 nieścisłości przy ~60% systemu:
1. Marka jako built-in (po co?)
2. Brak modelu relacji obiekt↔obiekt
3. Category-jako-ObjectType nie renderuje swoich atrybutów (#3-#28)
4. Niejasność kategoryzacji

**Decyzje z Wave 1-4 wywiadu:**
- Marka — REVERT z built-in, tenant decyduje (Y Hybrid)
- Wzorzec dystrybucji: **(b) Y Hybrid — kategoria DODAJE, nie zastępuje** (cumulative po ścieżce)
- Relacje — typ atrybutu (Pimcore-style), reverse generated, advanced=metadata na powiązaniu
- Asset — pozostaje odrębny typ (a)
- Fixed Powiązania tab (a) — z uwagą implementacyjną
- Primary category zamiast Family (operator: „nie chcę Family, zbyt skomplikowane")
- Orphaned values — wartości zostają w bazie, ukryte, reaktywowalne (a)
- Walidacja unikalności kodów: blokuje (a) — trzeba dodać unikalne kody (`opis_telewizory`)
- **ADR-014** utworzone + `feature-modeling-data-model.md` mini-spec + 14 ticketów MOD-01..14

**Krytyczna decyzja architektoniczna:** `object_associations` (ADR-009 hardcoded enum cross_sell/up_sell/related/alternative/accessory) **ZASTĄPIONE przez `object_relations`** (relacja = typ atrybutu). 5 enum types → seedowane built-in `relation` attributes na Product.

### Wątek C — Pytanie o Families (Akeneo) + Pimcore comparison
- Czy gdyby drzewo nie działało, można zrobić Families à la Akeneo?
- **Odpowiedź:** TAK, w dowolnym momencie. Scenariusz 1 (przed produkcją) = czysta zmiana kodu, ~tydzień. Scenariusz 2 (klienci) = backfill family_id per tenant, orphaned values, performance, RLS — kilka dni roboty migracyjnej + sam feature
- **Tania polisa:** trzymać `EffectiveAttributeGroupResolver` jako single chokepoint (MOD-03 to robi). Plus opcjonalnie zarezerwować pustą migrację `objects.family_id`
- **Pimcore comparison:** Pimcore ma Classification Store + Object Bricks + Field Collections. Klucz: w Pimce dystrybucja atrybutów jest ZDEKUPLOWANA od kategorii. To wyróżnik, ale Pimcore jest też notorycznie skomplikowany. Cortex może mieć podobną elastyczność BEZ złożoności — pod warunkiem że resolver pozostaje czystą abstrakcją źródeł nakładki

### Wątek D — Kryteria rewizji modelu (tripwires)
Operator: „kryterium 2h onboarding jest złe, bo wyjdzie przy 100k atrybutów i 200 kategorii". Wniosek: **4 metryki + 5-ta jako kontrola**:
1. Eksplozja liczby efektywnych atrybutów na liściu (p95 >150 = formularz nie do użycia)
2. Ten sam atrybut potrzebny w niespokrewnionych gałęziach (>30% grup = źle)
3. Churn kategoryzacji (mediana zmian primary >1-2 w 30 dni = walka z systemem)
4. Completeness systematycznie niski (rozkład garbi się przy 60-70%)
5. **Przyrost atrybutów ad hoc** (jeśli >200/mc = model nietrafiony)

**Trigger Family:** 2 metryki nad progiem na ≥2 niezależnych tenantach.

**Pułapka do zapamiętania:** atrybuty ad hoc MASKUJĄ pozostałe tripwire'y (user zamiast narzekać klepie ad hoc). Czytać metrykę „przyrost ad hoc" RAZEM z pozostałymi, nie zamiast nich.

### Wątek E — Ad hoc atrybuty (escape hatch + diagnostyka)
- Pozwalają userowi dodawać atrybuty per-product (Akeneo/Pimcore tego nie mają)
- ZALETY: rozładowuje eksplozję, lowers onboarding friction, jest zaworem bezpieczeństwa dla rigid modelu
- WADY bez governance: fragmentacja danych, completeness break, channel export bypass, RBAC bypass, kula u nogi
- Governance: badge widoczny, NOT counted to completeness, NOT in global filters, opt-in w eksporcie, separate permission do tworzenia, **ścieżka promocji** — gdy N produktów ma ten sam ad hoc, system sugeruje promocję

### Wątek F — Salon Sprzedaży modeling (przykład relacji)
Operator pyta: jak zorganizować „Salony Sprzedaży" jako część „Samochody"?
- **Kluczowa korekta modelu mentalnego:** Salon NIE jest „zawarty w" samochodzie. Jest WSPÓŁDZIELONY (composition vs association)
- **Architektura:** Salon = własny ObjectType z `show_in_main_menu=true` (lub false jeśli purely target), własne atrybuty (nazwa, adres, lokalizacja=geo, zdjęcia=asset gallery)
- Na Samochodzie atrybut typu `relation` → target=Salon, cardinality=many
- **Pokazane diagramem** (POZIOM MODELU + POZIOM INSTANCJI + object_relations row jako edge)
- **Luka:** `geo`/map attribute type może nie istnieć — osobny ticket

### Wątek G — Universal list view (uniwersalna lista każdego ObjectType)
- Operator: tylko Product ma `/products`. Custom ObjectType (Samochody) nie ma. Cel: dowolny ObjectType ze `show_in_main_menu=true` → ten sam advanced list view co Produkty
- Utworzony `feature-universal-object-list.md` (16 sekcji, ULV-01..12, ~67-97h)
- **Krytyczny insight (peer review w Council):** `is_built_in=true` JUŻ istnieje od ADR-009. Refactor sidebar to ~2h, nie schema change
- **STATUS: Shipped via Epik UP** (UP-00..UP-11) 2026-05-25. `/products/{list,show,create}` wydzielone do `UniversalListPage`/`UniversalDetailPage`/`UniversalCreatePage`, mountowane na `/products` ORAZ `/objects/:slug`. ULV epik świadomie odrzucony jako „półśrodek"

### Wątek H — LLM Council Round 1 (hardcoded attributes / Category)
**Pytanie:** które hardkody naprawiać teraz, które odłożyć?
- **Najmocniejsza: B (First Principles)** — 4/5. Diagnoza: „Category robi 2 rzeczy — taksonomia + schema overlay — sklejone z legacy Akeneo/Pimcore. Cumulative overlay nie jest cechą Category, tylko cechą *relacji* `provides_schema_overlay`"
- **Największy blind spot: A (Expansionist)** — sprzedaje viral demo moment bez dowodu, że pierwszy klient tego chce
- **Chairman recommendation:** schema-level genericzność TERAZ (silnik) + UI universalizacja PÓŹNIEJ + **zadzwoń do klienta**
- Blind spots wyłapane przez peer review: RBAC Phase 3 interaction, asymetria DB-schema vs app-code hardcodes, reversibility cheap (bo `is_built_in` istnieje), false dichotomy (refactor vs CSV hardening)

### Wątek I — Reframe „Moduły vs Obiekty" (operator's counter-proposal)
Operator po refleksji: usunąć Kategorie/Zasoby z „Modelowania → Obiekty"; potraktować Kategorie/Multimedia/Katalogi PDF jako osobne nieedytowalne **MODUŁY** (włącz/wyłącz per tenant, własne UI). W ObjectType tylko **capability flags** (`is_categorizable`, `has_media`, `has_variants`) → zakładki pojawiają się.

### Wątek J — LLM Council Round 2 (Moduły vs Obiekty)
- **Najmocniejsza: D (Executor) — 5/5 UNANIMOUS.** Konkretny plan: 2h sidebar split + 4h capability flags, NIE cofać UP, NIE encja „Module", używać istniejącego `is_built_in`
- **Największy blind spot: E (Expansionist) — 5/5 UNANIMOUS.** Marketplace/pricing tiers/Module Registry = premature platformization 24h po shipping UP, bez ani jednego pilota
- **Chairman recommendation:** STOP refactor, zadzwoń do klienta, potem D-zmodyfikowane przez Reviewer 4 (~2-6h zamiast 16h, używa istniejącego `is_built_in`)
- **Nowe insighty z peer review:**
  - `is_built_in=true` ALREADY EXISTS → refactor ~2h, NIE schema change
  - RBAC × capability flags collision (dwa źródła prawdy: ObjectType flag vs per-attribute permission)
  - Asymetria DB-schema vs app-code hardcodes
  - Nikt nie pyta o pierwszego klienta

### Wątek K — Capability flags bounded pattern + Audyt/Powiązania
Operator pyta o pułapkę flag proliferation (`has_audit`, `has_pricing`...). Rozstrzygnięcie:
- **Powiązania** = AttributeGroup (po MODR-02), nie wymaga flagi
- **Audyt** = NIE flaga, ALE **RBAC permission `audit.view`** (system view, nie modeling)
- **Multimedia** = droga A (AttributeGroup) ALBO droga B (Module Library) — **operator wybrał B**
- **Bounded pattern:** flaga dozwolona TYLKO gdy znaczy „integracja z System Module". Liczba flag = liczba Modułów, nie liczba feature'ów. Naturalna bariera

### Wątek L — Opcja Y dla Powiązań (finalna decyzja)
Operator nie zaakceptował „seed everywhere + hide when empty" (Option Z) — odsłonił problem discoverability („skąd user ma wiedzieć że jak doda atrybut to magicznie pojawi się zakładka").

**OPCJA Y (finalna):**
- **Pełne odseedowanie grupy „Powiązania"** — żadnej seedowanej grupy, żadnej built-in
- User świadomie tworzy grupę z `display_mode=tab` + wrzuca atrybuty `relation`
- **Reverse relations** = systemowa wirtualna zakładka „Powiązania zwrotne" — jedyna magia, uzasadniona (user nie może z góry zaprojektować grupy dla powiązań, które inni dopiero stworzą)
- Konfigurator atrybutu relation: bez defaultu grupy + akcja „+ Utwórz nową grupę" inline
- **Spójność z resztą systemu:** każda grupa istnieje bo user ją utworzył, każda zakładka istnieje bo grupa ma `display_mode=tab`. Zero wyjątków
- **`feature-modeling-relations-option-y-tickets.md` — MODRC-01..04** (~13-20h), supersedes MODR-02 (#924), MODR-06 (#928), MODR-07 (#929)

---

## 4. Kluczowe decyzje architektoniczne — kompendium

### W kodzie (wprowadzone lub planowane)
| # | Decyzja | Status |
|---|---|---|
| 1 | ObjectType = byt pierwszej klasy (ADR-009); Product/Category/Asset = built-in instancje | W systemie od dawna |
| 2 | `is_built_in=true` flag różnicuje system entities od user entities | W systemie |
| 3 | Capability flags na ObjectType: `show_in_main_menu`, `is_categorizable`, `has_variants`, (planowane: `has_media`) | Częściowo wdrożone (ADR-014) |
| 4 | Hybrid attribute model (ADR-006) parametryzowany per ObjectType (ADR-009): base + cumulative primary category overlay | W systemie |
| 5 | `object_relations` zastępuje `object_associations` (ADR-014) | MOD-02 #894 |
| 6 | Relacja = typ atrybutu `relation` (Pimcore-style), placement po AttributeGroup + `display_mode` (Opcja 2) | MODR-01 #923 + MODR-03 #925 |
| 7 | **Opcja Y dla Powiązań** — full un-seed, system reverse section | MODRC-01..04 (najnowsze) |
| 8 | Universal list view: jeden `UniversalListPage` dla wszystkich ObjectType | **Shipped Epik UP** |
| 9 | RBAC: 10 ról, 3-state per-attribute permissions, full scope w MVP-Alpha (ADR-013) | RBAC Phase 1-7 w toku |
| 10 | RBAC permission model: generyczne `object.{view,create,edit,delete,export}` scope'owane per ObjectType | W planie (Phase 3) |
| 11 | Audyt = RBAC permission `audit.view`, NIE flaga | Decyzja |
| 12 | Multimedia → Droga B (Media Library Module), nie AttributeGroup | Decyzja, spec do zrobienia |

### Filozoficzne (governance)
| # | Reguła |
|---|---|
| 1 | „Predefiniowane ≠ hardcoded" — seedowane defaulty są OK, immutable code paths nie |
| 2 | Flaga na ObjectType DOZWOLONA tylko gdy znaczy „integracja z System Module" (Kategorie, Media, Warianty, w przyszłości Cennik/Tłumaczenia). NIE „ma cechę X" |
| 3 | Liczba flag = liczba Modułów (bounded), nie liczba feature'ów (unbounded slippery slope) |
| 4 | Każda nowa flaga / koncept = świadoma decyzja po realnym sygnale, nie odruch |
| 5 | „Magia" widoczności w UI = anty-wzorzec discoverability. Każde renderowanie ma świadomy „dlaczego" (user-defined) |
| 6 | Wyjątek od reguły 5: systemowe widoki (reverse relations, audit log) — bo user nie może z góry projektować |
| 7 | Sensible defaults > zero-config > magic — przy seedowaniu Producta na day-1 |

---

## 5. ADR-y dotknięte/utworzone w tej rozmowie

| ADR | Status |
|---|---|
| ADR-009 (ObjectType first-class) | Bez zmian, ale interpretowany szerzej |
| ADR-010 (axis-driven variants) | Bez zmian |
| ADR-011 (per-tenant locale fallback) | Bez zmian |
| ADR-012 (AttributeGroup first-class) | **REVERTed w 3 punktach przez ADR-014** |
| ADR-013 (RBAC from day 1) | Bez zmian |
| **ADR-014** | **UTWORZONY** — „Model dystrybucji atrybutów + relacje obiekt↔obiekt". REVERT Brand as 4th built-in, `category_attribute_groups` scoped to primary category, `EffectiveAttributeGroupResolver` base source dla każdego ObjectType. `object_relations` zastępuje `object_associations` |
| ADR-014 update (planowany w MODRC-04) | Nota o Opcji Y (full un-seed Powiązań) |

---

## 6. Batche ticketów — status i zależności

### MOD-01..14 (`feature-modeling-data-model-tickets.md`)
Issues #893+ utworzone. Realizacja ADR-014. ~73-104h, ~2-3 tygodnie. Status per Sprint planning.

**Krytyczne:**
- MOD-01 #893 — schema delta (`is_categorizable`, relation config) ← `EffectiveAttributeGroupResolver` JUŻ istnieje
- MOD-02 #894 — `object_relations` + drop `object_associations` (zero data, dormant tables)
- MOD-03 — resolver fix #3-#28 (Category renderuje atrybuty)

### MODR-01..11 (`feature-modeling-relations-ux-tickets.md`)
Issues #923-933 utworzone. Opcja 2 (group decides placement). ~47-70h.

**STATUS:** MODR-02 (#924), MODR-06 (#928), MODR-07 (#929) — **częściowo SUPERSEDED przez MODRC-01..03**. Pozostałe (01, 03, 04, 05, 08, 09, 10, 11) — wciąż ważne.

### MODRC-01..04 (`feature-modeling-relations-option-y-tickets.md`)
Issues #1080-1082 utworzone (MODRC-04 docs TBD). Decyzja Option Y. ~13-20h.

- MODRC-01 #1080 — kasuj seed grupy Powiązania (refactor MODR-02)
- MODRC-02 #1081 — konfigurator relacji bez defaultu + inline create grupy (refactor MODR-07)
- MODRC-03 #1082 — system section „Powiązania zwrotne" (refactor MODR-06)
- MODRC-04 — docs §3.5 + ADR-014 + lessons.md (rozszerza MODR-11)

### ULV-01..12 (`feature-universal-object-list.md`)
**Świadomie odrzucony epik** — Marcin uznał za „półśrodek". Zamiast tego:

### Epik UP (UP-00..UP-11) — **SHIPPED 2026-05-25**
`/products/{list,show,create}` wydzielone do `UniversalListPage` + `UniversalDetailPage` + `UniversalCreatePage`. Mountowane na `/products` ORAZ `/objects/:slug`. ADR-009 spłacony. Patrz `agent/current_status.md` per-PR record.

### RBAC Phase 1-7 (89 ticketów, Issues #640-#728, milestones #9-#15)
- Phase 1 (Foundation, milestone #9) — 10 ticketów: tooling, ADR-013, schema 10 tabel, seed, IdentityBundle skeleton, PHPStan rules
- Phase 2 (Backend Auth, milestone #10) — 14 ticketów: JWT, email/password, API tokens, Tenant Context, Postgres RLS, Permission Resolver, MFA, SSO
- Phase 3 (Permission Engine, milestone #11) — 14 ticketów: Voters, `#[RequiresPermission]`, 3-state attribute permissions, per-locale/channel scope, workflow-state policy, field-level filtering, audit, Super Admin bypass
- Phase 4 (Frontend Core, milestone #12) — 13 ticketów
- Phase 5 (Settings UI, milestone #13) — 22 tickety
- Phase 6 (Refactor + Hardening, milestone #14) — 10 ticketów: retrofit `#[RequiresPermission]` do ~60 endpointów, CI gates lockdown
- Phase 7 (Pentest + Launch, milestone #15) — 6 ticketów: red-team Marcina (15-point), optional external pentest, fix critical, user-facing docs (privacy/RODO), soft launch z 1-2 design partnerami

---

## 7. Sesje LLM Council — pełen rezultat

### Round 1 (hardcoded attributes / Category)
**Pytanie:** które bolączki naprawiać teraz vs odłożyć (Multimedia/Powiązania/Audyt hardcoded + Category cumulative overlay)?

**Tally:**
- Najmocniejsza: B (First Principles) — 4/5
- Największy blind spot: D (Contrarian) — 3/5; A (Expansionist) — 2/5

**Chairman verdict:**
1. Multimedia → seedowana AttributeGroup. **TERAZ.** 1-2 dni. Jednogłośny kierunek
2. Category overlay → **schema-level genericzność TERAZ (silnik `provides_schema_overlay`), UI universalizacja PÓŹNIEJ**. Tania rozdzielczość DB-schema vs UI
3. AttributeGroups Audyt/Powiązania → ODŁÓŻ
4. **Zadzwoń do klienta. 30 min.** Jedyna rzecz, której nikt z 5 doradców nie zrobił

### Round 2 (Moduły vs Obiekty reframe + shipped UP)
**Pytanie:** czy reframe jest dobrym rozwiązaniem czy regresją? Co z UP? Najmniejszy realny refactor?

**Tally — UNANIMOUS:**
- Najmocniejsza: D (Executor) — **5/5**
- Największy blind spot: E (Expansionist) — **5/5**

**Chairman verdict:**
1. **STOP refactor. Zadzwoń do klienta.** Trzy pytania:
   - Czy „Kategorie"/„Zdjęcia" w jego głowie to MIEJSCA obok Produktów, czy typy obiektów?
   - Jakie inne „moduły" widzi w pierwszych 6 miesiącach (Cennik? Tłumaczenia? PDF?)?
   - Czy ma znaczenie, że Kategorie są w sekcji „Modelowanie"?
2. Jeśli klient potwierdza intuicję — D-zmodyfikowane przez Reviewer 4: ~4-6h zamiast 16h. Wykorzystaj istniejący `is_built_in=true`, NIE dodawaj `system_module` enum
3. NIE rób: encji „Module", Module Registry, marketplace, pricing tierów. ADR-014 nie pisz — wystarczy notka w lessons.md
4. **Koordynacja z RBAC Phase 3** — capability flag decyduje czy zakładka istnieje, RBAC decyduje co user widzi

---

## 8. Świadomie odrzucone alternatywy (zbiorczo)

| # | Odrzucone | Powód |
|---|---|---|
| 1 | Warstwa „Object Template" nad ObjectType | Potworek duplikujący ObjectType, który już JEST template'em |
| 2 | Osobny krok „Objekty" w wizardzie | Re-rozdziela relację od atrybutu (re-otwiera babol „Smoke test") |
| 3 | Reguła „relacja zawsze zakładka" | Łamie Producent-inline use case (relacja do Brand w identity group) |
| 4 | Mechanizm embed/kompozycji w MVP | YAGNI. Trigger: byt nigdy współdzielony bez własnej tożsamości |
| 5 | Families à la Akeneo/Pimcore Classes teraz | Nie teraz. Trigger: tripwires (4+1 metryki). Resolver pozostaje czystą abstrakcją |
| 6 | Flaga `has_relations` na ObjectType | Proliferacja flag → Pimcore Classes anti-pattern |
| 7 | Seed grupy „Powiązania" jako built-in | Anti-pattern discoverability — magiczne tabs |
| 8 | „Magiczna" widoczność zakładki przy populacji atrybutami | User nie ma jak odkryć tej reguły |
| 9 | Encja „Module" / Module Registry / marketplace / pricing tiers w MVP | Premature platformization 24h po UP-ship bez pilota |
| 10 | ADR-014 jako osobny ADR dla decyzji Module vs Object | Wystarczy nota w ADR-009 + lessons.md |
| 11 | Multi-tab dla relacji (osobne „Powiązania handlowe"/"techniczne") | MVP: jedna zakładka. Furtka: zmiana seedu grup, nie architektury |
| 12 | Multi-tenancy management UI w MVP | Świadoma decyzja operatora; DB/code hooks zachowane |
| 13 | Mechanizm embed inline expand/edit jako default | Jako tryb opt-in widgetu relacji, nie default model |
| 14 | „Audyt" jako capability flag | Audyt = RBAC permission, nie modeling decision |
| 15 | Pełne uniwersalizowanie Category overlay w MVP (Wave A z R1) | UI-level repositioning + schema-level genericity OK, ale full universal odłożone |

---

## 9. Outstanding actions — co realnie czeka

### Numer 1, blocker wszystkiego (powtórzone z 2 rad)
**ZADZWOŃ DO PIERWSZEGO KLIENTA. 30 minut. Trzy pytania:**
1. Jakie typy obiektów oprócz Produktów chce zarządzać w PIM w pierwszych 6 miesiącach?
2. Czy ma use case użycia overlay atrybutów po kategorii dla czegoś poza produktem?
3. Czy widoczność „Multimedia"/„Kategorie" jako stałych zakładek/sekcji vs konfigurowalnych ma dla niego znaczenie?

Bez tej rozmowy każdy refactor jest spekulacją. Powtórzone 2x przez 2 niezależne rady.

### Numer 2 — Multimedia jako Module Library
Operator zdecydował: **Droga B**. Spec do zrobienia osobnym dokumentem. Module Library w sidebarze („Moduły → Multimedia"), pliki współdzielone między ObjectType, podpinane przez relację. `has_media` flag = integracja z Modułem.

### Numer 3 — Kategorie jako System Module
Analogicznie do Multimediów, Kategorie jako osobny Moduł (drzewo w sidebarze), nie jako jeden z ObjectType w „Modelowanie → Obiekty". Spec do zrobienia.

### Numer 4 — Audyt jako RBAC permission
Permission `audit.view` w PRD-PIM-rbac. Implementacja w RBAC Phase 3.

### Numer 5 — Capability flag × RBAC collision resolution
Zanim wprowadzisz `has_variants`/`has_media` jako flagi, ustal hierarchię w voterze: **capability flag decyduje czy zakładka istnieje, RBAC attribute permission decyduje co user widzi wewnątrz**. 1-2h decyzji designerskiej. Koordynacja z Phase 3 Permission Engine.

### Numer 6 — `geo`/map attribute type
Potencjalna luka. Zweryfikować czy `AttributeType` enum ma `geo`. Jeśli nie — osobny ticket (lokalizacja na mapie dla Salonu, dowolnego obiektu).

### Numer 7 — MODRC-01..04 implementation
4 tickety, ~13-20h, ~3-4 dni solo dev. Po fakcie: update §3.5 + ADR-014 + cross-ref w starym pliku ticketów.

---

## 10. Pułapki / gotchas dla nowego agenta

1. **NIE confunduj „hardcoded" z „seedowane built-in".** Operator wielokrotnie wracał do tego. Seedowane = OK (Product to też seed). Hardcoded immutable code path = źle. Test: czy user może edytować zawartość? Czy może w przyszłości usunąć? Jeśli tak — to seed, nie hardcode

2. **Jeśli sugerujesz seedowanie czegoś jako built-in — zastanów się czy nie powtarzasz Option Z** (seed + hide when empty). Operator JEDNOZNACZNIE odrzucił to jako anti-pattern discoverability. Option Y wymaga świadomego tworzenia przez usera

3. **Capability flag bounded pattern:** flaga = integracja z System Module, NIE „ma cechę X". Każda nowa flaga wymaga, żeby pierwej powstał odpowiadający Moduł (z własnym UI/storage/API namespace). Inaczej wracasz do Pimcore Classes

4. **NIE proponuj rozwiązań platformowych** (Module Registry, marketplace, pricing tiers, plugin system) **pre-MVP-Alpha bez pilota**. To E (Expansionist) blind spot — 5/5 unanimously flagged. Operator odrzuci. ADR-013 i CLAUDE.md zwalczają over-engineering

5. **Operator MA RACJĘ co do flag proliferation.** Jeśli zauważasz w swoim rozwiązaniu trzecią flagę typu `has_X` — STOP. Możliwe, że proponujesz Pimcore-trap

6. **Test discoverability każdej propozycji UX:**
   - Skąd user ma wiedzieć, że X istnieje?
   - Jak go odkryje? (eksploracja, dokumentacja, przypadek?)
   - Jeśli „przypadek" — to anty-wzorzec

7. **`is_built_in` JUŻ JEST** od ADR-009. Nie dodawaj `system_module` enum / nowej kolumny dla rozdzielenia system vs user entities. Reuse istniejący flag

8. **UP epik shipped. NIE cofaj.** `/products` to `<UniversalListPage objectTypeId={productTypeId} />`. Dorób cienką warstwę nawigacji jeśli trzeba, ale silnik zostaje

9. **Operator ma EPIK MARATHON RULE w CLAUDE.md.** Gdy mówi „przez cały epik" — każdy ticket osobny PR, bez deferrowania, bez bundle'owania. Powtarzające się ostrzeżenie z lessons.md (UI-02 marathon źródło)

10. **SMOKE TEST RULE i CLOSED MEANS CLOSED RULE — NIENEGOCJOWALNE.** Przed claim „działa" w PR — manual smoke test. `gh issue close` — proof live-stack w close comment. Lessons z 2026-05-18 (RBAC re-audit, 9/14 ticketów źle zamkniętych, ~25h dorobionej pracy)

11. **Zadzwoń do klienta = REAL blocker.** Jeśli operator nie zadzwonił, nie podejmuj kolejnych decyzji architektonicznych dotykających Modułów. Pchaj go do tego telefonu jak czarny pies

12. **PRD-PIM-rbac §3.5 — 3-state positive grants** (restricted/view/edit). NIE proponuj negative blacklisting. Resolution order: attribute → group → role-default. Custom role builder w Phase 5

13. **Multimedia idzie Drogą B** (Module Library), NIE A (AttributeGroup). Operator wyraźnie zdecydował. Niezależnie od ostatecznych decyzji o Powiązaniach

14. **Komunikuj po polsku** (komentarze GH, dokumentacja, rozmowa z operatorem). Kod, branche, commits w angielskim (Conventional Commits, sekcja CLAUDE.md). Brak `Co-Authored-By` AI

15. **Pliki utrzymywane atomowo** (per CLAUDE.md): `agent/current_status.md`, `agent/lessons.md`, `Project Plan/02-plan-projektu-pim.md`, `Project Plan/01-architektura-pim.md` (ADR), `Project Plan/06-sprint-0-findings.md`, `docs/api-spec/v{version}.json`, `Project Plan/UI/Wdrozenie_grafiki/`, RBAC backlog 08-14

---

## 11. Tripwires for model revision (do referencji)

Kiedy podjąć decyzję o Families (lub innym refactorze podstawowej architektury):

| # | Metryka | Tripwire |
|---|---|---|
| 1 | p95 liczby efektywnych atrybutów na obiekt | >150 |
| 2 | % grup atrybutów podpiętych w wielu niespokrewnionych węzłach | >30% |
| 3 | Mediana liczby zmian primary category obiektu w pierwszych 30 dniach | >1-2 |
| 4 | Rozkład completeness per ObjectType | garbi się przy 60-70% |
| 5 | Tempo tworzenia atrybutów ad hoc | >200/miesiąc/tenant |

**Trigger refactoru:** ≥2 metryki nad progiem u ≥2 niezależnych tenantów.

**Pułapka:** Ad hoc atrybuty (metryka 5) MASKUJĄ pozostałe. Czytać razem, nie zamiast.

**Stress test przed produkcją** (świadoma decyzja operatora):
Zamiast czekać na klienta — wygeneruj syntetyczny katalog: 200 kategorii, drzewo 5-6 poziomów, 100k+ atrybutów. Załaduj, otwórz formularz produktu na najgłębszym liściu. Mierz 4 metryki. Jeśli p95 wybucha — wiesz przed pierwszym klientem.

---

## 12. Mapa nazw / glossariusz

| Termin | Znaczenie |
|---|---|
| ObjectType | byt pierwszej klasy (ADR-009); Product/Category/Asset = built-in instancje (`is_built_in=true`) |
| ObjectType custom | user-defined ObjectType (Samochody, Dostawcy itp.); `is_built_in=false` |
| AttributeGroup | grupa atrybutów; renderuje się jako zakładka (`display_mode=tab`) lub sekcja inline (`stacked`) |
| `object_type_attributes` | junction ObjectType×Attribute; nosi `display_mode`, `show_in_list`, `list_position` |
| `object_type_attribute_groups` | junction ObjectType×AttributeGroup; nosi `display_mode` per kontekst |
| `object_relations` | tabela powiązań obiekt↔obiekt; zastępuje `object_associations` (ADR-014) |
| `object_associations` | DEPRECATED — usuwane przez MOD-02 |
| `EffectiveAttributeGroupResolver` | single chokepoint liczący efektywny zbiór grup atrybutów dla obiektu. Implementowany, czysty, nie hardcodować |
| Primary category | jedyna kategoria z `is_primary=true` na obiekcie; sterowca cumulative overlay |
| Secondary categories | dodatkowe kategorie obiektu; NIE wpływają na schemat |
| Cumulative overlay | atrybuty z primary category dodawane kumulatywnie po ścieżce w drzewie |
| Built-in flag | `is_built_in=true` — predefiniowane byty (Product, Category, Asset, system groups). Chronione przed deletion, ale edytowalne w zawartości |
| Capability flag | flaga na ObjectType wyrażająca integrację z System Module (`is_categorizable`, `has_media`, `has_variants`) |
| System Module | predefiniowany podsystem z własnym UI (Kategorie, Media Library, w przyszłości Cennik). Włączany per tenant. Wyłącznie kontrolowany przez kod platformy, nie przez usera |
| User Entity | byt definiowany przez usera (Product instance, custom Samochód instance, AttributeGroup utworzona przez usera) |
| System Entity | predefiniowany byt platformowy (Tenant, User, Role, Category jako Module, Asset jako Module) |
| Provenance | pole `manual|import|agent|integration` na `object_values` z meta JSONB. UI pokazuje badge'e |
| Powiązania zwrotne (reverse relations) | automatycznie generowane z `object_relations` po `target_object_id`. Read-only. Po Opcji Y: systemowa wirtualna zakładka |
| Opcja 2 | placement po AttributeGroup + display_mode. Relacja to zwykły typ atrybutu. Decyzja z §3.5 mini-speca |
| Opcja Y | rozszerzenie Opcji 2 — pełne odseedowanie grupy Powiązania. User świadomie tworzy grupę. Jedyna magia = system reverse section |

---

## 13. Pliki kluczowe do przeczytania PRZED kolejną decyzją

Krytyczne (must read):
1. `CLAUDE.md` (root + dev/PIM) — konstytucja projektu
2. `Project Plan/01-architektura-pim.md` — ADR-014, sekcja 14 Roadmap
3. `Project Plan/UI/feature-modeling-data-model.md` — mini-spec ADR-014 (UWAGA: §3.5 nieaktualne przed MODRC-04)
4. `Project Plan/UI/feature-modeling-relations-option-y-tickets.md` — najnowsze tickety MODRC
5. `Project Plan/PRD/PRD-PIM-rbac.md` v2.1 — RBAC master spec
6. `agent/current_status.md` — gdzie jesteśmy

Pomocnicze:
- `Project Plan/UI/feature-universal-object-list.md` — shipped status UP
- `Project Plan/UI/feature-modeling-data-model-tickets.md` — MOD-01..14
- `Project Plan/UI/feature-modeling-relations-ux-tickets.md` — MODR-01..11 (z notą supersede)
- `Project Plan/07-rbac-implementation-plan.md` + `08-rbac-tickets-phase-1.md..14-rbac-tickets-phase-7.md`
- `agent/lessons.md` — wzorce sukcesu i porażki

---

## 14. Co teraz — recommended next steps

W kolejności:

1. **TELEFON DO KLIENTA** (30 min, 3 pytania, dziś)
2. Update `feature-modeling-data-model.md` §3.5 + ADR-014 + lessons.md — Opcja Y (MODRC-04, 2-3h)
3. Implementacja MODRC-01 (kasuj seed grupy Powiązania, 2-4h)
4. Implementacja MODRC-02 + MODRC-03 (frontend, równolegle, 9-13h)
5. Rozstrzygnięcie capability flag × RBAC collision (1-2h designerskie, koordynacja z Phase 3)
6. Mini-spec dla Multimedia jako Media Library Module (jeśli klient potwierdza)
7. Mini-spec dla Kategorii jako System Module (jeśli klient potwierdza)
8. Powrót do RBAC Phase 2 / Phase 3 implementacji

---

## 15. Sygnały, że nowy kierunek jest dobry/zły

**Dobre sygnały:**
- Nowa decyzja redukuje liczbę konceptów (nie dodaje)
- Wykorzystuje istniejący `is_built_in`, `display_mode`, capability flags zamiast nowych kolumn
- User widzi w UI bez dokumentacji JAK to działa (discoverable)
- Nie wprowadza encji „X-as-a-platform" przed pilotem
- Spójne z istniejącym wzorcem (np. jeśli wszystkie grupy są user-defined, ta też)

**Czerwone alarmy:**
- Propozycja: czwarta flaga typu `has_X` w ciągu sesji
- Propozycja: nowa encja architektoniczna („Module", „Template", „Plugin", „Registry")
- Propozycja: „magicznie pojawia się" / „automatycznie wyświetla" / „intuicyjnie zrozumie"
- Refactor shipped epiku bez sygnału z produkcji
- Estymata >40h dla refactoru pre-MVP-Alpha bez pilota
- Decyzja podejmowana 24h po shipping bez nowych danych

---

*Handoff utworzony 2026-05-28. Czytaj sekcję 0 i 9 minimum. Reszta na zawołanie.*
