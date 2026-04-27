# Plan projektu PIM — fazy, milestones, backlog, ryzyka

**Wersja:** 1.0 (faza koncepcyjna)
**Data:** 2026-04-26
**Powiązany dokument:** `01-architektura-pim.md`
**Status:** zatwierdzony do realizacji

---

## 1. Streszczenie planu

Projekt PIM jest realizowany w czterech fazach. Każda faza ma zdefiniowane cele biznesowe, deliverables, milestones i kryteria zakończenia. Plan zakłada developmen iteracyjny z Claude Code jako głównym narzędziem produkcji kodu, z udziałem nie-eksperta programistycznie jako product ownera, code reviewera i operatora.

**Faza 0 — MVP** to minimalna wersja umożliwiająca pierwsze wdrożenie pilotażowe. **Po trzeciej rundzie review (Gemini/DeepSeek/Grok) i rozszerzeniu o Sprint 0 + walidację jakości:** Faza 0 = Sprint 0 (40-55h) + MVP Core (161-219h) = **201-274 realnych roboczogodzin pracy człowieka** dla pełnej wersji, **172-235h** dla okrojonej (bez agentic UX Full, dashboard, BYOK). Tabela porównawcza w sekcji 7.

**Faza 1 — Production-ready** to hardening, dodanie Magento i IdoSell, pierwsze wdrożenia produkcyjne. Cel: dodatkowe 100-140 godzin.

**Faza 2 — Agentic Pro** dodaje data-ops capabilities agenta, multi-tenant SaaS i marketplace integracji. Cel: dodatkowe 150-200 godzin.

**Faza 3 — Enterprise** to compliance, white-label, SSO i partner program. Cel: 300+ godzin.

## 2. Założenia metodologiczne

### 2.1 Model pracy

Praca prowadzona w trybie pair-programming człowiek + Claude Code, gdzie:

- **Claude Code** pisze kod (~80% wolumenu), generuje testy, proponuje refactory, dokumentuje.
- **Człowiek** specyfikuje wymagania per ticket, **definiuje gate'y akceptacji wykonywane przez automatyzację** (PHPStan max, Playwright E2E, benchmark perf, axe-core a11y), testuje manualnie kluczowe ścieżki, deployuje, podejmuje decyzje architektoniczne i biznesowe.

**Mnożnik produktywności (poprawiony po review DeepSeek):**

Przyjmujemy że jedna realna roboczogodzina człowieka = ok. 3-5 roboczogodzin tradycyjnego developmentu **dla osoby z podstawową umiejętnością czytania kodu** (PHP/TypeScript) i rozpoznawania typowych klas błędów LLM (halucynacje API, brak obsługi transakcji, niezamknięte zasoby, N+1).

**Disclaimer dla pełnego non-codera:**
Jeśli operator nie czyta kodu w ogóle, mnożnik spada do **1.5-2x** względem tradycyjnego developmentu, bo:
- LLM-generated code często ma subtelne błędy widoczne dopiero w runtime (memory leak w workerze, źle ustawione transakcje, nieprawidłowy index na JSONB).
- Bez podstawowej weryfikacji kodu, błędy pojawią się jako bug reports z pilota — i debugging wymaga albo eksperta (zlecone), albo długiego cyklu z LLM "co się tu psuje".
- **Realne implikacje czasowe: dolicz +30-50% buforu** do wszystkich estymacji w tym dokumencie jeśli operator jest pełnym non-coderem bez doświadczenia ani mentora.

**Mitigacja braku review przez non-codera (kluczowe — Gemini point 3):**
"Code review" wykonywany przez automatyzację, nie przez ludzkie oczy. Twardy zestaw bramek (sekcja 2.2) zastępuje subiektywne ludzkie review. Człowiek-non-coder weryfikuje **deliverable user-facing**: czy E2E test świeci się na zielono, czy demo działa, czy pilot widzi to co trzeba.

**Rekomendacja praktyczna:** dla ścieżek krytycznych (architektura, multi-tenant migracja, agent layer, security hardening) — zrezerwować 20-30 godzin mentora technicznego (zewnętrzny senior PHP/Symfony) jako safety net. Mentor nie pisze kodu, tylko robi review w kluczowych momentach. Koszt: ~5-10k PLN, zwrot: zminimalizowanie ryzyka subtelnych błędów które wybuchają w runtime.

### 2.2 Definicja "Done" per ticket — automation-first (zmiana po review Gemini)

Ticket uznajemy za zakończony gdy spełnione są **automatyczne gate'y** (nie ludzki "sprawdziłem i wygląda ok"):

**Bramki obowiązkowe (CI blocker — bez nich PR nie merge'uje się):**
- ✅ Kod kompiluje się i przechodzi static analysis: **PHPStan level max (8) + Psalm strict + Biome strict**.
- ✅ Unit testy istnieją dla nowej logiki domenowej (coverage tej logiki ≥80%, mierzony **PHPUnit** — bez Pest).
- ✅ Integration testy istnieją dla nowych endpointów API (**API Platform `ApiTestCase`** zamiast Behat — wbudowany w API Platform 4, oparty na PHPUnit, pełen DSL do asercji JSON-LD/Hydra).
- ✅ **Playwright E2E test istnieje dla każdej widocznej user-facing zmiany** (od dnia 1, nie odkładany na epik 0.11) — to jest twoja jedyna ludzka warstwa walidacji. Bez E2E, ticket nie jest done.
- ✅ Audit log działa (jeśli zmiana dotyka encji domenowej).
- ✅ Dokumentacja inline (PHPDoc / TSDoc) — wymagane na publicznych metodach service'ów.
- ✅ Composer audit + npm audit pass (brak nowych vulnerable dependencies).
- ✅ Manual smoke przez operatora — **5 minut: kliknij happy path w admin, sprawdź czy działa to co miało**. Nie udawaj review kodu — udawaj usera.

**Bramki kontekstowe (jeśli applicable):**
- a11y axe-core scan zielone (dla ticketów dotykających UI).
- Benchmark perf (dla ticketów dotykających queries / endpointów krytycznych).
- Multi-tenant izolacja test (dla ticketów dotykających zapytań domenowych).
- Memory benchmark FrankenPHP worker (dla ticketów dotykających Messenger handlerów lub batch operations).

**Co NIE jest definicją done:**
- ❌ "Operator przeczytał kod i wydaje się ok" — review LLM-generated kodu przez non-codera to fikcja, która uśpi czujność. Nie polegamy na tym.

**Świadomy minimalizm narzędzi testowych (polerka po finalnym review Gemini):**
Stack testowy w MVP to **dwa narzędzia, nie cztery**:

1. **PHPUnit** — testy jednostkowe (logika domenowa, walidatory, value objects) **i** testy integracyjne API (przez `ApiPlatform\Symfony\Bundle\Test\ApiTestCase`, który zastępuje Behat — daje DSL do asercji JSON-LD, Hydra, OpenAPI, autoryzacji, kodów odpowiedzi).
2. **Playwright** — testy E2E (klikanie po Refine.dev w prawdziwej przeglądarce).

**Świadomie odrzucone:**
- **Pest** — drugi runner unit testów obok PHPUnit. Daje ładniejszą składnię, ale dla LLM-generated kodu to dodatkowe narzędzie do nauki, kolejny config, kolejny CI step. Korzyść kosmetyczna, koszt poznawczy realny.
- **Behat** — Gherkin-based BDD framework. Świetny gdy product manager pisze scenariusze, **bezsensowny gdy operator pracuje z agentem LLM**. `ApiTestCase` z API Platform pokrywa 100% przypadków integracyjnych z lepszym lock-inem do framework'u.

Powód redukcji: każde dodatkowe narzędzie to kolejny kontekst, w którym Claude Code może się zaciąć generując boilerplate. Mniej rozproszenia, mniej konfliktów wersji w composer, krótszy CI run, jeden styl testów dla całego backendu.

### 2.3 Definicja "Done" per faza

Faza uznawana za zakończoną gdy:
- Wszystkie tickety w backlogu fazy są zamknięte.
- Aplikacja deployowalna jednym poleceniem (`docker-compose up`).
- Smoke test E2E przechodzi (Playwright dla admin, **PHPUnit + ApiTestCase** dla API).
- Dokumentacja architektury i runbook zaktualizowane.
- Retrospektywa fazy przeprowadzona z dokumentowaniem nauczek.

## 3. Faza 0 — MVP

> ### REWIZJA ZAKRESU MVP (2026-04-27, po zamknięciu #5)
>
> **Decyzja operatora:** "agentic management jest całkowicie jako dodatek, musimy zrobić pełną bazę, dobry UX frontu, więc to wypadnie finalnie jeszcze dalej". W praktyce oznacza to:
>
> - **Cały epik 0.7 (Agent layer — schema-add, Beta-Min + Beta-Full)** → Faza 2. Hooks runtime (`pending_changes` table, `provenance` enum, lifecycle events) zostają w MVP — sam agent dochodzi po stabilnym pilocie z prawdziwymi user stories.
> - **Epiki 0.8 (BaseLinker) + 0.9 (Shopify)** → Faza 1. Pierwszy klient produkcyjny dostaje BaseLinker + Shopify razem po stabilizacji katalogu w MVP, nie jako część gate-decision'u.
> - **Tickety Sprint 0 #6 (Agent endpoint), #7 (Cmd+K placeholder), #8 (Shopify GraphQL stub)** → przeniesione do Fazy 1/2. Sprint 0 zamykamy 6 pozostałymi ticketami: #9 demo, #10 Playwright, #13 benchmark FrankenPHP, #14 profilowanie, #15 pgBackRest, #16 audit + findings.
> - **Layout admina #54** rewriten — Cmd+K placeholder usunięty z scope, sam shell layoutu (już startowy ze #5) doczołguje się do pełni.
> - **Provenance badge #61** — wariant `agent` (purple) odłożony do Fazy 2; MVP pokazuje tylko `manual` / `import` / `integration`.
>
> **Powód:** pierwszy pilot (B2B technical, 50 MLN GMV/rok per `Project Plan/03-funkcjonalnosci-mvp.md`) ocenia "działający katalog 5k SKU + niezawodny sync" wyżej niż "rozmawiaj z systemem". Materiał na pilot szybciej, ryzyka R-25/R-27/R-28 wychodzą z MVP. Warunek: `provenance` w `product_values` od dnia 1 i `pending_changes` jako pusta tabela w bazie — koszt 4-6h, oszczędza 30-40h migracji w Fazie 2.
>
> **Nowa kolejność wykonania po Sprincie 0:**
> 1. MVP-Alpha epiki 0.1, 0.2, 0.3 (fundament)
> 2. (decyzja) Epik 0.3a — Categories / taxonomy (kandydat do dodania w MVP)
> 3. Epik 0.4 + 0.5 (API extensions + Meilisearch)
> 4. Epik 0.6 (Admin UI core CRUD — atrybuty + dynamiczny formularz produktu)
> 5. Epik 0.10 + 0.11 (API Configurator + hardening / a11y / analytics / backup / BYOK)
> 6. Demo pilot → decyzja gate-decision o gotowości
> 7. **Faza 1**: BaseLinker + Shopify
> 8. **Faza 2**: Agent layer (epik 0.7) + Magento + IdoSell

### 3.0 Sprint 0 — Vertical Slice (walidacja architektury, ~40-55h)

Przed wejściem w pełen backlog MVP wykonujemy **wąski plaster pionowy** całej architektury dla jednej encji end-to-end. Cel: zwalidować że stack faktycznie działa razem zanim zainwestujemy ~200h w pełen scope. Sprint 0 to ubezpieczyciel projektu — inspirowane sugestiami z drugiej i trzeciej rundy review.

**Tryb realizacji (kluczowa decyzja po review Gemini):**
Sprint 0 musi być wykonany w trybie **maksymalnego skupienia: pełen tydzień lub dwa intensywnej pracy**, nie 4-5 tygodni dorywczo po 10h. Powód: stack jest złożony (FrankenPHP + Symfony + API Platform + Refine + Mercure + Anthropic SDK + Shopify GraphQL + Postgres JSONB + Meilisearch — 9 ruchomych części). Rozłożenie na miesiąc oznacza że co tydzień traci się i odbudowuje kontekst, a w międzyczasie aktualizują się pakiety npm/composer. **Zarezerwuj urlop lub blok 2 tygodni jeśli to możliwe — to inwestycja, która zwraca się w fazie MVP.**

**Zakres Sprintu 0 (rozszerzony po review Gemini/DeepSeek/Grok):**

Bazowy stack:
- 0.0.1 Setup **monorepo Turborepo** (`apps/api`, `apps/admin`, `packages/shared-types`) + docker-compose w minimalnej formie (FrankenPHP 2.x + Symfony 7.4 + Postgres 16 + Redis 7 + Meilisearch + MinIO + Mercure + Mailpit). **Krytyczne — Caddy single-origin proxy (sekcja 3.10a architektury):** Caddyfile w FrankenPHP routuje `/api/*` do Symfony i całą resztę do `vite:5173` jako reverse proxy. **Nie konfigurujemy CORS, nie wystawiamy frontend pod osobnym portem na host.** To eliminuje godzinną pętlę debugowania CORS gdy Claude Code próbuje pogodzić `localhost:5173` z `api.localhost`.
- 0.0.2 Jedna encja: `Product` z kilkoma core polami (sku, name, description, brand) — bez pełnego modelu atrybutów scopable/localizable, ale **z** kolumną `tenant_id` i prostym Doctrine filterem (przygotowanie pod multi-tenant test).
- 0.0.3 ApiResource w API Platform — endpoint `/api/products` działa (list, get, post, patch) z OpenAPI auto-generowanym.
- 0.0.4 Authentication minimalny (jeden statyczny user + JWT przez LexikJWT).
- 0.0.5 Admin Refine + shadcn — jeden ekran: lista produktów + create/edit z dynamicznym formularzem.
- ~~0.0.6 Agent endpoint `/api/agent/run`~~ → **przeniesione do Fazy 2** (rewizja 2026-04-27).
- ~~0.0.7 Cmd+K w admin~~ → **przeniesione do Fazy 2** (rewizja 2026-04-27).
- ~~0.0.8 Najprostszy klient Shopify GraphQL Admin API~~ → **przeniesione do Fazy 1** razem z całym epikem 0.9 (rewizja 2026-04-27).
- 0.0.9 Manualny test end-to-end: utwórz produkt w admin → edytuj → sprawdź izolację multi-tenant. (Bez agenta i Shopify w demo Sprint 0 po rewizji.)

Walidacja jakości i bezpieczeństwa (nowe, kluczowe — bez tego Sprint 0 jest niekompletny):
- 0.0.10 **Playwright E2E test od dnia 1**: jeden test happy path "user loguje się → tworzy produkt → wysyła do Shopify". Każdy nowy ticket w MVP **musi** dostać E2E test razem z kodem (Gemini point: review przez non-codera = automatyzacja, nie ręczne czytanie).
- 0.0.11 **PHPStan max level (8)** + Psalm strict mode skonfigurowane w CI od dnia 1, żeby od początku wymuszać dyscyplinę typów.
- 0.0.12 **Test izolacji multi-tenant** (smoke): tworzymy 2 tenanty, próbujemy odczytać produkty drugiego, oczekujemy 0 wyników. Walidacja że Doctrine filter działa (RLS odkładamy do fazy 2).
- 0.0.13 **Benchmark FrankenPHP worker memory**: skrypt importuje 5000 produktów w pętli, monitorujemy memory consumption per worker (Prometheus). Walidacja że `EntityManager::clear()` w abstract handlerze faktycznie zapobiega wyciekom.
- 0.0.14 **Profilowanie Blackfire (lub Tideways) na małej skali** — uruchom load test `/api/products?page=1` z 1000 produktami, sprawdź p95, zidentyfikuj wąskie gardła zanim urośniemy do 50k.
- 0.0.15 **pgBackRest + WAL stub w docker-compose** — backup co 1h podczas Sprintu 0, jeden test restore przed końcem.
- 0.0.16 **Claude Code system prompt** — dokument `.claude/CLAUDE.md` z twardymi wytycznymi (memory management FrankenPHP, batch handler pattern, RLS test obligatoryjny, E2E coverage każdego ticketu, PHPStan max, **single-origin przez Caddy — nigdy nie konfigurujemy CORS**, **testowanie tylko PHPUnit + Playwright** — sekcja 2.2). Pisany ręcznie przed pierwszym ticketem MVP — to jest "konstytucja" projektu.

**Definicja sukcesu Sprintu 0 (gate decision):**

| Walidacja | Próg sukcesu |
|---|---|
| End-to-end happy path działa | Tak/Nie |
| FrankenPHP worker memory | < 256 MB po 5000 produktów import |
| Latency `/api/products?page=1` | p95 < 200ms na 1000 produktów |
| Multi-tenant izolacja smoke test | 0 cross-tenant leak |
| Playwright E2E test | passing on CI |
| PHPStan max 8 + Psalm strict | passing on CI |
| pgBackRest restore test | passing |
| Anthropic agent jeden tool call | działa, koszt < $0.05 |
| Shopify create test product | działa, throttling respektowany |

**Decyzja po Sprincie 0:** kontynuujemy pełen backlog MVP **lub** korygujemy architekturę zanim zainwestujemy więcej. Wnioski zapisujemy w `06-sprint-0-findings.md` jako załącznik do dokumentacji architektury (potencjalne korekty ADR-ów).

**Co Sprint 0 świadomie NIE obejmuje:**

- Pełen model atrybutów (rodziny, scope, locale, completeness).
- Pełen agentic UX (streaming SSE, schema diff modal, approval inbox, provenance badges).
- Drugi integrator (BaseLinker odkładamy na Epic 0.8).
- Pełny multi-tenancy (smoke test izolacji wystarcza, RLS w fazie 2).
- RBAC granularny (jedna rola — admin).
- Audit log (DoctrineAuditBundle dochodzi w MVP).

**Estymacja:** 40-55 realnych godzin pracy człowieka (rozszerzone z 30-40h po dodaniu walidacji jakości — Playwright, benchmark memory, profilowanie, backup test, system prompt). Wynik: zielony lub czerwony. Jeśli czerwony — wracamy do fazy koncepcyjnej z konkretnymi danymi co nie działa, korygujemy stack lub scope, dopiero potem do pełnego MVP.

### 3.1 Cele biznesowe

Pierwsza wdrażalna wersja systemu PIM, zdolna do pilota u jednego klienta. System ma poprawnie obsłużyć 50 000 produktów z trzema poziomami atrybutów (rodziny, atrybut groups, atrybuty), syndykować dane do **BaseLinker i Shopify** (Magento przesunięty do fazy 1 — sekcja 4.2), oraz mieć agentic admin pozwalający na rozszerzanie schematu przez chat.

### 3.2 Out of scope dla MVP

Aby utrzymać Fazę 0 w realnym budżecie (172-274h, sekcja 7), świadomie wykluczamy z MVP:

- IdoSell (przesunięte na fazę 1).
- Workflow engine (manualne stany, bez state machine).
- Wersjonowanie produktów (tylko aktualna wersja).
- DAM zaawansowane (transformacje, AI metadata) — w MVP plain upload.
- Bidirectional sync z integracjami (tylko PIM → external).
- Data-ops agenta (tylko schema-add).
- Multi-tenant deployment (single tenant, ale model gotowy).
- White-label / theming admin UI.
- Public docs portal (OpenAPI udostępnione, ale bez dedykowanego portalu).

### 3.3 Backlog MVP — Epiki i tickety

#### Epik 0.1: Infrastructure i fundamenty (16-22h)

- 0.1.1 Setup repo (mono lub multi-repo decyzja), .gitignore, README, CONTRIBUTING.
- 0.1.2 Docker Compose: FrankenPHP, PostgreSQL 16, Redis 7, Meilisearch, MinIO, Mailpit (dev mail).
- 0.1.3 Skeleton Symfony 7 + API Platform 4, podstawowy bundle layout (Catalog, Channel, Asset, Integration, Identity, Agent).
- 0.1.4 Skeleton frontend admin: Vite + React 19 + TypeScript + Refine + shadcn/ui + routing.
- 0.1.5 GitHub Actions CI: PHPStan, Psalm, PHP-CS-Fixer, PHPUnit, Biome dla frontendu.
- 0.1.6 Pre-commit hooks (husky / pre-commit framework).
- 0.1.7 Doctrine migrations baseline + initial schema.

#### Epik 0.2: Identity & Access (10-14h)

- 0.2.1 Encja User, Role, Permission, Tenant.
- 0.2.2 Symfony Security z JWT (LexikJWT) + form login dla admin UI.
- 0.2.3 Voters dla głównych zasobów (Product, Attribute, Family, Channel).
- 0.2.4 RBAC seeder z domyślnymi rolami (super_admin, catalog_manager, integration_manager, viewer).
- 0.2.5 Endpoint /api/auth/login, /api/auth/refresh, /api/auth/me.
- 0.2.6 Auth provider w Refine + przechowywanie tokenów (httpOnly cookie).
- 0.2.7 Multi-tenant fundament: kolumna tenant_id wszędzie, Doctrine listener filtrujący query, Postgres RLS policies.

#### Epik 0.3: Domain model — Catalog (16-20h)

- 0.3.1 Encja Attribute + AttributeGroup + AttributeOption (z enum typów: text, number, select, multiselect, date, boolean, asset, relation, price, metric).
- 0.3.2 Encja Family + FamilyAttribute z required_for_completeness.
- 0.3.3 Encja Category z ltree (custom Doctrine type dla ltree).
- 0.3.4 Encja Product + ProductValue + denormalized attributes_indexed.
- 0.3.5 Encja Association (cross/up-sell/related).
- 0.3.6 Encja Channel + Locale + Currency + ChannelAttributeMapping.
- 0.3.7 Encja Asset + AssetVariant z Flysystem do MinIO.
- 0.3.8 Doctrine event listenery: maintain attributes_indexed, maintain completeness_pct.
- 0.3.9 Symfony Validator constraints per typ atrybutu.
- 0.3.10 Migrations + seeders dla example data (testowy katalog: 100 SKU, 20 atrybutów, 3 rodziny, 5 kategorii, 1 channel, 2 locale).

#### Epik 0.4: API Platform — exposing entities (10-14h)

- 0.4.1 ApiResource adnotacje na encjach Catalog (Product, Family, Attribute, Category, Channel, Asset, Association).
- 0.4.2 Grupy serializacji per-context (admin, integration, public).
- 0.4.3 Custom filtry (search po SKU, filtry po atrybutach, filtry po kategorii z descendants).
- 0.4.4 Custom paginator (cursor-based dla list >1000).
- 0.4.5 Custom data transformers (np. denormalizacja atrybutów do/z product_values).
- 0.4.6 OpenAPI customization (przykłady w dokumentacji, security schemes).
- 0.4.7 Mercure publisher dla zdarzeń domenowych (product.created, product.updated).
- 0.4.8 Rate limiter per-endpoint (Symfony RateLimiter).

#### Epik 0.5: Search — Meilisearch (6-8h)

- 0.5.1 Bundle do indeksowania (services, configuration).
- 0.5.2 Doctrine event listener → Symfony Messenger message → worker pisze do Meili.
- 0.5.3 Initial reindex command (`pim:search:reindex`).
- 0.5.4 Endpoint /api/products/search z facetingiem.
- 0.5.5 UI w Refine: search box z autocomplete + faceted filtry.

#### Epik 0.6: Admin UI — core CRUD (20-26h)

- 0.6.1 Layout admina (sidebar, top bar, command bar Cmd+K placeholder, content area).
- 0.6.2 Resource Products: list (table + filters + bulk actions), show (detail), create, edit (z dynamicznym formularzem na podstawie atrybutów).
- 0.6.3 Resource Attributes: list, show, create, edit + przypisanie do grup.
- 0.6.4 Resource Families: list, show, create, edit + przypisanie atrybutów.
- 0.6.5 Resource Categories: tree view (drag-and-drop), create, edit.
- 0.6.6 Resource Channels: list, show, create, edit + mapping atrybutów.
- 0.6.7 Resource Assets: list (z preview), upload (drag-and-drop), edit metadata.
- 0.6.8 Provenance badges na polach (manual/import/agent/integration).
- 0.6.9 i18n (pl + en).

#### Epik 0.7: Agent layer — schema-add (25-35h, podzielony na MVP-Alpha minimum + MVP-Beta extras)

**Reestymacja po review Grok:** poprzednio 16-22h było zaniżone o ~30-50% — pełen agentic UX z streaming SSE, schema diff modal, approval inbox, provenance i obsługą stanów błędów to realnie 25-35h. Aby nie rozdąć fazy 0, dzielimy epik na **MVP-Beta-Min** (musi być w MVP) i **MVP-Beta-Full** (pełen agentic UX, opcjonalnie do MVP-Final).

**MVP-Beta-Min — minimum agentic UX dla MVP (12-16h):**
- 0.7.1 Bundle Agent + service AgentSession + AgentRun encja.
- 0.7.2 Anthropic SDK PHP integration + system prompt dla schema-ops + **twarde limity kosztów (sekcja 8.5 architektury): rate limit, hard cap tool calls, dziennie/miesięcznie budget cap z alertami**.
- 0.7.3 Tool definitions: create_attribute, create_attribute_group, assign_attribute_to_family, create_family, create_category, preview_changes.
- 0.7.4 Tool execution layer z walidacją argumentów + autoryzacją przez Voters.
- 0.7.5 Pending changes queue (encja PendingChange) + mechanizm approve/reject.
- 0.7.7 UI: Cmd+K command bar w admin layout.
- 0.7.8a UI: prosty chat panel (Sheet w shadcn) — non-streaming, klient czeka na pełną odpowiedź (akceptowalny UX dla MVP).
- 0.7.9a UI: prosty preview-changes modal (lista zmian + accept/reject, bez bogatego schema diff).
- 0.7.11 Audit logging wszystkich akcji agenta.

**MVP-Beta-Full — pełen agentic UX (13-19h, opcjonalnie do MVP-Final, decyzja po MVP-Beta-Min):**
- 0.7.6 SSE streaming odpowiedzi przez Mercure.
- 0.7.8b UI: chat panel ze streaming responses (token-by-token rendering).
- 0.7.9b UI: bogaty schema diff modal z accept/modify/reject, pokazuje przed/po dla każdej zmiany, kolorowanie semantyczne.
- 0.7.10 UI: agent inbox z pending changes (queue widget z aktywnymi zatwierdzeniami).
- 0.7.12 UI: provenance badges przy polach które ostatnio zmienił agent (z tooltipem "zmienione przez agent X dniu Y").

**Powód podziału (Grok point):** ryzyko że pełen agentic UX rozpłaszczy MVP — klient pilotażowy potrzebuje dowodu że agent działa, nie potrzebuje od razu pixel-perfect demo wszystkich pattern'ów. MVP-Beta-Min wystarczy do walidacji koncepcji; MVP-Beta-Full robimy gdy MVP-Final ma 1-2 wolne tygodnie zanim pójdzie do pilota.

#### Epik 0.8: Integracja BaseLinker (12-16h)

- 0.8.1 Bundle Integration\BaseLinker.
- 0.8.2 Client (Symfony HttpClient) z retry, circuit breaker.
- 0.8.3 Adapter mapujący Product PIM → BaseLinker product format.
- 0.8.4 Konfiguracja w admin UI (klucz API, mapowania atrybutów, mapowania kategorii).
- 0.8.5 Command full-sync + incremental sync via Doctrine event listener.
- 0.8.6 Sync_jobs UI: lista, status, podgląd błędów, retry per-item.
- 0.8.7 Testy integracyjne z BaseLinker sandbox.

#### Epik 0.9: Integracja Shopify (14-18h)

**Decyzja produktowa:** Shopify zamiast Magento w MVP — Shopify to większy rynek (~4.6M sklepów globalnie vs ~150k Magento), znacząco niższy próg wejścia dla SMB/mid-market klientów, silny ekosystem D2C i międzynarodowy rozwój marek polskich. Magento przesuwamy do fazy 1 (lub fazy 2 jeśli pipeline nie pokaże popytu).

- 0.9.1 Bundle Integration\Shopify.
- 0.9.2 Klient Shopify Admin GraphQL API (preferowany w 2026 nad REST) z OAuth 2.0 dla Shopify Partners apps lub Custom App access token dla enterprise.
- 0.9.3 Adapter PIM → Shopify mapujący atrybuty na **metafields** (z namespace per tenant), warianty na Shopify Variants, kategorie na Shopify Collections.
- 0.9.4 Multi-store support: Shopify Markets dla międzynarodowych wariantów cen/locale; Shopify Plus stores jako osobne profile integracyjne.
- 0.9.5 Konfiguracja w admin UI: token, sklep, mapowania atrybut→metafield (z namespace), mapowania kategorii→collections.
- 0.9.6 **Bulk sync paczkami po 250 elementów przez zwykłe mutacje GraphQL** (decyzja po review Gemini/DeepSeek — Bulk Operations API odkładamy do fazy 1). Implementacja: Symfony Messenger handler bierze paczki po 250 produktów z bazy, woła `productCreate`/`productUpdate` mutations w pętli z `EntityManager::clear()` po każdej paczce. **Throttling — najprostszy działający algorytm: Exponential Backoff (sekcja 7.3 architektury, polerka po finalnym review Gemini)** — bez współdzielonego stanu Redis, bez własnej matematyki na `extensions.cost.throttleStatus`, bez Symfony RateLimiter z custom strategy. Pętla: wyślij request, jeśli 429 / `THROTTLED` → `sleep(Retry-After ?? 2 * 2^retry_count)`, retry max 5 razy.
- 0.9.7 Sync_jobs UI dla Shopify ze statusem (running / paused / completed / failed) + per-product errors + retry button.
- 0.9.8 Webhooks (opcjonalnie w MVP, pełne w fazie 1): `products/update`, `inventory_levels/update` — nasłuchiwane na endpoint `/webhook/shopify/{tenant_code}` z weryfikacją HMAC-SHA256.
- 0.9.9 **Exponential Backoff jako jedyny mechanizm throttlingu w MVP (polerka Gemini)** — w `Integration\Shopify\GraphQLClient`: po wysłaniu mutacji jeśli odpowiedź to HTTP 429 lub `errors[].extensions.code === 'THROTTLED'`, pobieramy `Retry-After` z nagłówka odpowiedzi (lub fallback `2^retry_count` sekund, max 60s), `sleep`, retry. Max 5 prób, potem ticket idzie do dead-letter queue z błędem widocznym w UI sync_jobs. **Świadome odejście od Leaky Bucket + Redis shared state** — algorytm bucket'a wymaga liczenia tokenów rate × cost per query × współdzielenia stanu między workerami — to skomplikowana matematyka stanu rozproszonego, na której LLM się zacina. Exponential backoff jest 5-liniowy, deterministyczny, samoreparujący się i wystarczy dla 50k SKU. **Punkt powrotu do Leaky Bucket:** w fazie 1 razem z migracją na Bulk Operations, gdy benchmark pokaże że backoff marnuje >20% slot'ów dostępnego rate limitu Shopify (sekcja 0.9.11 i sekcja 7.3 architektury).
- 0.9.10 Testy integracyjne z Shopify Partners development store (free) — happy path + scenariusze błędów (throttling, niewłaściwy token, metafield over limit).
- 0.9.11 **Punkty rozważenia migracji na Bulk Operations + Leaky Bucket w fazie 1**: (a) 90-percentyl czasu pełnego sync 50k SKU przekroczy 60 min, (b) klient enterprise zażąda <30 min full sync, lub (c) backoff marnuje >20% dostępnego rate limitu Shopify (mierzone przez `extensions.cost.throttleStatus.currentlyAvailable` zalogowane w sync_jobs po każdym requeście — proste zliczenie bez aktywnego sterowania).

#### Epik 0.10: API Configurator (8-12h)

- 0.10.1 Encja ApiProfile + ApiKey z scopes.
- 0.10.2 UI w admin: lista profiles, create, edit.
- 0.10.3 UI: wybór atrybutów do publikacji per profile + format output.
- 0.10.4 UI: webhook configuration (event → URL).
- 0.10.5 Backend: ApiProfileVoter wpinający się w API Platform serializer context.
- 0.10.6 Endpoint testowy /api/profiles/{code}/test pokazujący przykładową odpowiedź.

#### Epik 0.11: Hardening, a11y, analityka i testy (24-34h, rozszerzony po review DeepSeek)

- 0.11.1 2FA dla admin (TOTP via scheb/2fa-bundle).
- 0.11.2 Rate limiting na auth endpoints (anti-bruteforce).
- 0.11.3 Security headers via Caddy (CSP, HSTS, X-Frame-Options, etc.).
- 0.11.4 Audit log MVP (DoctrineAuditBundle aktywny dla głównych encji).
- 0.11.5 Pełna suite Playwright E2E (uzupełniona o nowe ścieżki — bazowy E2E test rusza już w Sprint 0): login, create product, edit attribute, run agent, sync to BaseLinker, sync to Shopify, multi-tenant izolacja smoke.
- 0.11.6 **PHPUnit + `ApiTestCase`** testy dla głównych endpointów API (auth, products CRUD, search, agent run, integration sync) + property-based tests dla logiki domenowej krytycznej (completeness rules).
- 0.11.7 Composer audit + npm audit w CI + dependabot config.
- 0.11.8 README, **runbook DR (PITR przez pgBackRest, rotacja kluczy LLM, BYOK provisioning)**, CONTRIBUTING.
- **0.11.9 (NOWE) WCAG 2.1 AA accessibility review głównych ścieżek admin (4-6h):** keyboard navigation full coverage, kontrast wszystkich elementów, aria labels na ikonach, focus management w modalach (Cmd+K, schema diff, approval). shadcn/ui na Radix daje solidną bazę za darmo, ale customowe komponenty (dynamiczne formy atrybutów, agent panel) wymagają walidacji axe-core w CI + manualnego sprawdzenia z czytnikiem (NVDA na Windows, VoiceOver na macOS).
- **0.11.10 (NOWE) Prosty dashboard analityczny dla operatora (6-8h):** strona startowa admina pokazuje: liczba produktów / atrybutów / rodzin / kategorii (live z DB), status ostatnich 5 synchronizacji per integration (BaseLinker, Shopify) z kolorowym statusem, prosty wykres "produkty dodane/zmodyfikowane w ostatnich 30 dniach" (Recharts lub Chart.js), liczba pending changes w agent inbox, status backupów (last successful, RPO indicator). To znacząco podnosi wartość demo i odpowiada na pytanie pilota "skąd wiem co się dzieje?".
- **0.11.11 (NOWE) pgBackRest + WAL archiving production setup (4-6h):** już w Sprint 0 zrobiliśmy stub w docker-compose (sekcja 0.0.15) — tutaj dochodzą: konfiguracja pełnych backupów co tygodnia + differential codziennie + WAL co 5 min, retention policy, automatyczny weekly restore test (cron job + Slack alert), runbook PITR. Dla pierwszego pilota różnica RPO 24h → 5min jest argumentem sprzedażowym.
- **0.11.12 (NOWE) BYOK (Bring Your Own Key) implementacja dla agenta (4-6h):** pole "Anthropic API Key" w konfiguracji tenanta, szyfrowane AES-256-GCM przy zapisie, runtime resolver wybiera klucz tenanta (jeśli BYOK włączony) lub klucz platformy (default). UI dla admina z testem klucza ("zrób próbny call do Anthropic, pokaż balans tokenów"). Mitiguje główne ryzyko biznesowe (R-27 — wyciek/abuse klucza platformy → kosztownej faktury).

### 3.4 Milestones MVP

**Uwaga (poprawione po review DeepSeek):** "Faza 0" obejmuje **Sprint 0 (40-55h) + MVP Core (epiki 0.1–0.11)**. W tabeli poniżej cumulative h dotyczy MVP Core (od końca Sprintu 0). Total Faza 0 = Sprint 0 + ostatni wiersz tabeli.

| ID | Milestone | Kryterium odbioru | Cumulative h (MVP Core) |
|---|---|---|---|
| M0.A | Infrastructure ready (z monorepo Turborepo) | `pnpm dev` startuje cały stack, baseline CI green | 16-22 |
| M0.B | Auth + tenants | Można zalogować się, użytkownik widzi swój tenant, smoke test izolacji passing | 26-36 |
| M0.C | Domain model + API CRUD | Wszystkie encje Catalog mają działające API CRUD | 52-70 |
| M0.D | Search działa | Endpoint /search zwraca wyniki w <500ms na 50k testowych SKU | 58-78 |
| M0.E | Admin UI core | Można w UI: utworzyć rodzinę, atrybut, produkt, przypisać kategorie | 78-104 |
| M0.F | Agent schema-add (MVP-Beta-Min) | Można przez Cmd+K dodać atrybut z opcją approve (non-streaming) | 90-120 |
| M0.G | BaseLinker działa | Bulk sync 1000 produktów do BaseLinker zakończony sukcesem | 102-136 |
| M0.H | Shopify działa | Sync 1000 produktów do Shopify development store przez **zwykłe GraphQL z Exponential Backoff throttling** (sekcja 7.3 architektury) | 116-154 |
| M0.I | API Configurator | Klient zewnętrzny może zalogować się kluczem i pobrać dane (per-profile filter + cache + rate limit) | 124-166 |
| M0.J | Agentic UX full (MVP-Beta-Full, opcjonalnie) | Streaming SSE, schema diff modal, agent inbox, provenance | 137-185 |
| M0.K | Hardening + a11y + analytics + backup | E2E green, WCAG AA pass, dashboard działa, pgBackRest restore test pass | 161-219 |
| M0.L | MVP Done | Wszystkie milestone'y zaliczone, deployment u pilota możliwy | 161-219 |

**Estymacje (po trzeciej rundzie review — Gemini/DeepSeek/Grok):**
- **Sprint 0:** 40-55h (rozszerzony o Playwright od dnia 1, RLS smoke, Blackfire, monorepo, FrankenPHP 2.x walidacja)
- **MVP Core (epiki 0.1–0.11):** 161-219h (uwzględnia nowe tickety w 0.11: a11y, dashboard, pgBackRest, BYOK; Epic 0.7 reestymacja; podział na MVP-Beta-Min i Full)
- **Faza 0 total:** **201-274h**

Skąd wzrost względem poprzedniej estymacji 138-186h (drugiej rundy):
- Epic 0.7 reestymacja: +9-13h
- Epic 0.11 nowe tickety (a11y, dashboard, pgBackRest, BYOK): +18-26h

**Tryb okrojonego MVP** (jeśli budżet czasu jest twardszy niż jakość):

| Skrót | Oszczędność |
|---|---|
| Epic 0.7 tylko MVP-Beta-Min, bez Full (streaming, diff modal, inbox) | -13-19h |
| Epic 0.9 bez webhooków (tylko push z PIM) | -2h |
| Epic 0.10 API Configurator do 1 profile testowy, bez webhooków | -4h |
| Epic 0.11 bez analytics dashboard | -6-8h |
| Epic 0.11 bez BYOK (zostaje w fazie 1) | -4-6h |

Tak okrojony MVP: **132-180h** MVP Core + Sprint 0 (40-55h) = **172-235h** total dla fazy 0. Akceptowalne dla pierwszego pilota; pełen MVP-Final z analytics + a11y + agentic UX Full to wersja "dla demo i sprzedaży".

#### Grupowanie w sub-fazy MVP — Alpha / Beta-Min / Beta-Full / Final (4 sub-fazy po review)

Dla porządku zarządczego MVP dzielimy na 4 sub-fazy z gate decision po każdej. To umożliwia early stopping jeśli któraś z warstw nie spełni oczekiwań, bez konieczności przepisywania architektury.

**MVP-Alpha — Backend + API + minimal admin (epiki 0.1–0.6, ~80-110h)**
Cel: działający backend z pełnym domain modelem, REST/GraphQL API, podstawowy admin Refine z CRUD ale bez agentic UX. Gate: API responses < cele wydajnościowe na 50k SKU testowych, admin pozwala na pełen workflow operatora bez agenta.

**MVP-Beta-Min — Minimum agentic UX (część epiku 0.7, ~12-16h)**
Cel: dodanie podstawowego Cmd+K + chat + approval flow (non-streaming, prosty preview). Gate: agent działa i można pokazać demo.

**MVP-Final — Integracje + API config + hardening + a11y + analytics (epiki 0.8–0.11, ~70-94h)**
Cel: BaseLinker + Shopify działają, API Configurator, smoke testy E2E green, WCAG AA pass, dashboard, pgBackRest production. Gate: deployment u pilota możliwy.

**MVP-Beta-Full — Pełen agentic UX (część epiku 0.7, ~13-19h, OPCJONALNIE)**
Cel: streaming SSE, schema diff modal, inbox, provenance. Decyzja po MVP-Final: czy mamy zapas czasu/budget przed deploy do pilota? Jeśli tak — robimy. Jeśli nie — odkładamy do fazy 1.

Każda sub-faza powinna kończyć się **5-minutowym screencastem demo** dla samego siebie/inwestorów. Nawet jeśli jest jeden odbiorca — disciplinowane demo na koniec sub-fazy wymusza realne ukończenie.

### 3.5 Sequencing zalecony

```
Tydzień 1-2:  Epik 0.1, 0.2 (infrastructure + auth)
Tydzień 3-5:  Epik 0.3, 0.4 (domain model + API)
Tydzień 5-6:  Epik 0.5 (search)
Tydzień 6-9:  Epik 0.6 (admin UI core)
Tydzień 9-11: Epik 0.7 (agent layer)
Tydzień 11-13: Epik 0.8, 0.9 (integracje BaseLinker + Shopify)
Tydzień 13-14: Epik 0.10, 0.11 (API config + hardening)
```

Założenie: 10h pracy człowieka tygodniowo (część etatu). Przy pełnym etacie (40h/tydzień) → MVP w 4-5 tygodni.

## 4. Faza 1 — Integracje (BaseLinker + Shopify) + production-ready

> **Rewizja 2026-04-27:** w nowej kolejności (post-#5) Faza 1 zaczyna się od **integracji BaseLinker (epik 0.8) i Shopify (epik 0.9)** — przeniesionych z MVP. Pełen hardening / RLS / monitoring zostaje w Fazie 1 jako równoległy track. Magento i IdoSell przesunięte do Fazy 2 razem z agentem.

### 4.1 Cele

Pierwsza integracja produkcyjna: katalog z MVP łączy się z BaseLinker i Shopify. Hardening do poziomu produkcyjnego, aktywacja Postgres RLS przed multi-tenantem, pierwsze 2-3 wdrożenia u realnych klientów, monitoring full stack, dokumentacja API publiczna.

### 4.2 Backlog (high-level)

**Track A — Integracje (epiki 0.8 + 0.9, ~100-140h, przeniesione z MVP):**
- **Epik 0.8 BaseLinker (#72-#78)** — bundle, klient HTTP + retry + circuit breaker, adapter PIM→BaseLinker, UI konfiguracji, full-sync CLI + incremental Messenger handler, sync_jobs UI z Mercure live, testy integracyjne na sandbox.
- **Epik 0.9 Shopify (#79-#89)** — bundle (rozszerzenie #8), klient GraphQL Admin API + OAuth, adapter PIM→Shopify (metafields/variants/collections), multi-store (Markets + Plus), UI konfiguracji, bulk sync 250-element batch z AbstractBatchHandler memory safe, sync_jobs UI z `currentlyAvailable` telemetry, webhooks + HMAC verification, **Exponential Backoff jako jedyny throttling** (sekcja 7.3 architektury), testy integracyjne na Partners dev store, telemetria do decyzji o migracji na Bulk Operations + Leaky Bucket.

**Track B — Hardening + RLS + observability (~80-110h):**
- **Postgres RLS aktywacja przed multi-tenantem (16-24h, sekcja 11.1a architektury):** generowanie polityk RLS ze schematów Doctrine, app user bez `BYPASSRLS`, COPY guard, dedykowany pen-test izolacji.
- Bidirectional sync z BaseLinker i Shopify (12-18h).
- DAM transformacje obrazów (Imagick, presety) i lazy loading w UI (8-12h).
- Workflow engine (Symfony Workflow z stanami: draft, review, approved, published) (10-14h).
- Wersjonowanie produktów (history view, restore) (6-10h).
- Public OpenAPI portal (Swagger UI lub Stoplight Elements) (4-6h).
- Performance tuning po profilingu (Tideways/Blackfire) (8-12h).
- Pełna observability stack (Prometheus + Grafana + Loki + Tempo + Sentry) (8-12h).
- Backup automation hardening (rozszerzenie pgBackRest na MinIO replication, multi-region) (4-6h).
- Bezpieczeństwo: penetration test (zewnętrzny, koszt) + remediation (8-12h human time).
- **PHPUnit + ApiTestCase** full coverage głównych user stories (8-12h).
- Documentation: customer-facing docs, integrator handbook (6-10h).
- **Opcjonalnie:** Shopify Bulk Operations migration (jeśli telemetry z MVP wymagają, 4-8h).

### 4.3 Milestones fazy 1

- M1.A: BaseLinker + Shopify w produkcji, sync stabilny dla pierwszego klienta
- M1.B: Bidirectional sync (przynajmniej dla atrybutów konfigurowanych jako bidirectional)
- M1.C: Pełen monitoring stack uruchomiony w środowisku staging
- M1.D: Pierwszy klient produkcyjny żyje w systemie (50k SKU, 5 atrybutów konfiguracji)
- M1.E: Pen-test pozytywny, raport bez krytycznych ryzyk
- M1.F: Public API docs portal dostępny, integratorzy mogą pracować bez kontaktu z developerem
- M1.G: RLS aktywne, audyt izolacji multi-tenant przed wdrożeniami SaaS

## 5. Faza 2 — Agent layer + dodatkowe konektory

> **Rewizja 2026-04-27:** Faza 2 obejmuje teraz całość agentic features (epik 0.7 Beta-Min + Beta-Full przeniesione z MVP) plus konektory **Magento + IdoSell** (też przeniesione z Fazy 1). Hooks runtime (`pending_changes` table, `provenance` enum, lifecycle events) są w MVP, więc kod agenta dochodzi bez migracji danych.

### 5.1 Cele

Dojrzałość agenta — od "schema-add" przez "asystent" do "operator danych". Dodatkowe konektory (Magento, IdoSell) dla klientów spoza ekosystemu BaseLinker/Shopify. Multi-tenant SaaS jako opcja deploymentowa. Pierwsze 10+ wdrożeń.

### 5.2 Backlog (+200-260h)

**Track A — Agent layer baseline (epik 0.7 Beta-Min, ~50-70h, przeniesione z MVP):**
- **#63 Bundle Agent + AgentSession + AgentRun** — orchestration warstwy z lifecycle (start / step / end / error), cost guards inline.
- **#64 Anthropic SDK PHP integration** — system prompt, twarde limity 8.5 (50 tool calls/h/user, 100k tokens/run, $20/dzień/tenant), BudgetService z hardstop'em.
- **#65 Tool definitions** — `create_attribute`, `create_attribute_group`, `create_family`, `create_category`, `preview_changes` (read-only).
- **#66 Tool execution layer** — walidacja args, Voters per tool, handlers idempotentne, audit events.
- **#67 Pending changes queue** — endpoints approve/reject + TTL 24h + Mercure live updates.
- **#68 UI Cmd+K command bar** — pełna integracja w admin layout (rozszerzenie #54).
- **#69 UI prosty chat panel (Sheet)** — non-streaming, history persistowana.
- **#70 UI prosty preview-changes modal** — accept all / reject all / per-item.
- **#71 Audit logging wszystkich akcji agenta** — append-only, retention 1y.

**Track B — Agent layer rich UX (epik 0.7 Beta-Full, ~13-19h, przeniesione z MVP):**
- **#108 SSE streaming odpowiedzi przez Mercure** (token-by-token).
- **#109 UI chat panel ze streaming + cancel + reconnect**.
- **#110 UI bogaty schema diff modal** — before/after / modify / accept selected.
- **#111 UI agent inbox** — list pending changes + bulk approve + Mercure live.
- **#112 UI provenance badge `agent`** — purple variant + link do agent run + recent changes filter (rozszerzenie #61).

**Track C — Dodatkowe konektory (~30-50h, przeniesione z Fazy 1):**
- **Magento 2 integracja (10-14h)** — REST/GraphQL, attribute set mapping (sekcja 7.4b architektury).
- **IdoSell integration (12-16h)**.
- Marketplace integracje v1 (20-30h): Allegro, WooCommerce.

**Track D — Agent data-ops (~40-60h):**
- Bulk update atrybutów z preview ("dla wszystkich Nike, ustaw kategorię główną").
- Generowanie opisów produktów z atrybutów (LLM tekst).
- Mapowanie kolumn CSV/Excel przy imporcie.
- Translation memory (auto-tłumaczenie nazw na locale).
- Anomaly detection (atrybut wygląda nietypowo dla rodziny).

**Track E — SaaS aktywacja + advanced features (~50-70h):**
- Workflow engine advanced (16-22h): customowe workflow per tenant, approval chains, SLA tracking.
- DAM advanced (16-24h): AI metadata extraction (vision), variants generation, asset library z folder structure.
- Multi-tenant SaaS aktywacja (20-30h): signup flow, tenant subdomeny, billing (Stripe), limity per plan, onboarding wizard.
- Dashboard analytics (12-16h): completeness charts, sync status overview, agent usage stats, top edited products.

### 5.3 Milestones fazy 2

- M2.A: Agent baseline (epik 0.7 Beta-Min) działa — schema-add przez chat z approval flow
- M2.B: Magento + IdoSell w produkcji
- M2.C: Agent data-ops dostępne, prowadzi bulk update z approval
- M2.D: SaaS signup uruchomiony, pierwszy multi-tenant klient
- M2.C: 3 nowe integracje w marketplace
- M2.D: Analytics dashboard z 5+ wykresami

## 6. Faza 3 — Enterprise

### 6.1 Cele

Gotowość do sprzedaży klientom enterprise (>1B PLN obrót), białe etykiety dla partnerów wdrożeniowych, compliance w kierunku ISO/SOC 2.

### 6.2 Backlog (+300h+)

- SSO/SAML 2.0 + LDAP/AD (24-32h)
- White-label theme system + custom domeny (16-24h)
- Audit log advanced (export, retention policies, immutable storage) (8-12h)
- Data residency options (EU, US, custom) (12-18h)
- Compliance documentation (ISO 27001 readiness, SOC 2 Type 1) (40-60h głównie human-time, audit ekspert + remediations)
- Customer portal (kontrakty, faktury, support tickets) (24-32h)
- Advanced syndication (8-12h):
  - PDF datasheets per product
  - Printable catalogs
  - Spec sheets w formatach klienta
- Partner program (16-24h):
  - Partner portal
  - Klucze API z provisioning
  - Revenue sharing
- Performance enterprise tier (16-24h):
  - Read replicas Postgres
  - Postgres partitioning po tenant_id
  - Meilisearch sharding
  - K8s HPA + autoscaling
- Enterprise integrations (na zamówienie):
  - SAP, Microsoft Dynamics, Oracle Netsuite, Comarch ERP — każda 40-80h

## 7. Wycena (wisienka na torcie)

Estymacja godzinowa, jako orientacyjny budżet — nie zobowiązanie. Po trzeciej rundzie review (Gemini/DeepSeek/Grok):

| Faza | Estymacja realnych roboczogodzin człowieka | Kalendarzowo (przy 10h/tydz) | Kalendarzowo (full-time, intensywnie) |
|---|---|---|---|
| Sprint 0 — Vertical Slice (rozszerzony) | 40-55 h | **trzeba zrobić w 1-2 tygodniach urlopu/skupienia, nie dorywczo** | 1-2 tygodnie |
| MVP Core okrojony (132-180h) | 132-180 h | 13-18 tygodni | 3-5 tygodni |
| MVP Core pełny (161-219h) | 161-219 h | 16-22 tygodni | 4-6 tygodni |
| **Faza 0 — okrojony MVP z Sprintem 0** | **172-235 h** | **17-24 tygodnie (5-6 mies. dorywczo)** | **4-6 tygodni full-time** |
| **Faza 0 — pełny MVP z Sprintem 0** | **201-274 h** | **20-27 tygodni (5-7 mies. dorywczo)** | **5-7 tygodni full-time** |
| Faza 1 — Production-ready (Magento + IdoSell + RLS aktywacja + hardening) | 100-140 h | 10-14 tygodni | 3 tygodnie |
| Faza 2 — Agentic Pro | 150-200 h | 15-20 tygodni | 4-5 tygodni |
| Faza 3 — Enterprise (do v1) | 300+ h | 30+ tygodni | 8+ tygodni |

**Disclaimer mnożnika produktywności (sekcja 2.1):** powyższe estymacje zakładają, że operator ma **podstawową umiejętność czytania kodu** (PHP/TypeScript) i potrafi rozpoznać typowe klasy błędów LLM. Dla pełnego non-codera bez doświadczenia ani mentora — dolicz **+30-50% buforu** do wszystkich pozycji.

**Tryb realizacji:** mocno rekomendowane jest, aby Sprint 0 był wykonany w **trybie skupienia 1-2 tygodni intensywnej pracy** (urlop, blok kalendarzowy), nie dorywczo. Reszta MVP może być rozłożona, ale długi timeline (5-7 miesięcy dorywczo) niesie ryzyko stack drift (R-26) — dlatego co 2 epiki zalecany "maintenance ticket" (1-2h) aktualizujący patche.

**Wartość biznesowa po każdej fazie:**

- Po MVP: produkt do demo i pilotażu — można pokazać, ale nie sprzedać enterprise.
- Po fazie 1: sprzedawalny SMB i mid-market, można oferować jako "Pimcore-light".
- Po fazie 2: konkurencyjny z Akeneo SaaS dla firm ~50-500 SKU.
- Po fazie 3: sprzedawalny do enterprise, white-label dla partnerów wdrożeniowych.

## 8. Rejestr ryzyk

### 8.1 Ryzyka projektowe (faza 0-1)

| ID | Ryzyko | Prawdopodobieństwo | Wpływ | Mitygacja |
|---|---|---|---|---|
| R-01 | Zakres MVP rozdęty, niedotrzymanie 172-274h Fazy 0 | Wysokie | Średni | Twardy backlog cut z opcją okrojonego MVP (132-180h MVP Core), agent layer ograniczony do schema-add (MVP-Beta-Min), Magento + IdoSell w fazie 1, gate decision po Sprincie 0 z konkretnymi metrykami (sekcja 3.0), 4 sub-fazy MVP-Alpha/Beta-Min/Final/Beta-Full z możliwością early stopping |
| R-02 | Claude Code generuje kod niskiej jakości w wybranym stacku | Niskie | Wysoki | Code review każdego PR, PHPStan max + Psalm w CI, refactoring sessions co 2-3 epiki |
| R-03 | Człowiek (non-expert) nie radzi sobie z review LLM-generated code | Średnie | Wysoki | Zaczynamy od epików prostszych (infra, auth), escalujemy złożoność stopniowo; w razie blokad — pair-programming z mentorem (10-20h sponsor zewnętrzny) |
| R-04 | API Platform za mało elastyczne dla customowych endpointów PIM | Niskie | Średni | Backup plan: dodać "warstwę kontrolerów" obok ApiResource; kontrolery klasyczne Symfony zawsze możliwe |
| R-05 | Meilisearch nieadekwatny dla 200k+ SKU z complex faceting | Średnie | Średni | Abstrakcja w warstwie SearchProvider, fallback do Elasticsearch w fazie 1 jeśli benchmark wskaże |
| R-06 | Wybór Refine.dev okazuje się ograniczeniem dla custom UX agenta | Niskie | Średni | shadcn/ui jest niezależny od Refine; w razie potrzeby można porzucić Refine zachowując UI components |
| R-07 | Performance < oczekiwań na 200k SKU | Średnie | Wysoki | Profiling early (faza 1), FrankenPHP worker mode od początku, second-level cache Doctrine, plan optymalizacji w fazie 1 |
| R-08 | Bezpieczeństwo niespełniające standardów enterprise | Średnie | Wysoki | Pen-test w fazie 1, lista kontrolna OWASP Top 10 weryfikowana per release, security audit zewnętrzny przed pierwszym enterprise klientem |
| R-09 | Postgres RLS źle skonfigurowany przez kod generowany przez LLM → ryzyko cross-tenant data leak po aktywacji multi-tenancy | Średnie | **Krytyczny** | (a) **Mandatorne testy izolacji RLS w CI** — każda migracja dodająca tabelę z `tenant_id` musi mieć integration test sprawdzający izolację (utwórz dane jako tenant A, zaloguj jako tenant B, oczekuj zero wyników). (b) W MVP single-tenant deployment jest pierwszą linią obrony, RLS to defence in depth — realne ryzyko wycieku w MVP znikome (jeden tenant, jedno wdrożenie). (c) Przed aktywacją multi-tenant w fazie 2 — dedykowany pen-test na izolację tenantów. (d) Code review każdej migracji RLS przez człowieka, nie tylko LLM-generated diff. |
| R-25 | **FrankenPHP worker mode + Doctrine memory leaks** — brak `EntityManager::clear()` w workerach Messengera prowadzi do OOM przy bulk imporcie 50k SKU | Wysokie (gdy Claude pisze handlery bez jawnej dyscypliny) | **Krytyczny** | (a) Sekcja 3.10 architektury z twardymi wytycznymi memory management. (b) `AbstractBatchHandler` jako baza dla wszystkich Messenger handlerów — wymusza `clear()`. (c) Custom PHPStan rule blokująca handlery flushujące w pętli bez `clear()`. (d) Prometheus alert na `frankenphp_worker_memory_bytes > 256MB`. (e) Benchmark memory w Sprincie 0 (sekcja 0.0.13). (f) System prompt Claude Code wymusza pattern. |
| R-26 | **Stack drift przy długim timeline MVP** — przy 10h/tydzień MVP zajmie 5-7 miesięcy; w międzyczasie aktualizują się Refine, API Platform, Anthropic SDK, Symfony patches → ciągłe rozjeżdżanie kontekstu i konflikty wersji | Wysokie | Średni | (a) Sprint 0 wykonany w trybie skupienia 1-2 tygodnie urlopu (sekcja 3.0). (b) Lockfiles ścisłe (composer.lock, pnpm-lock.yaml) z polityką "nie aktualizujemy w trakcie fazy bez powodu". (c) Co 2 epiki: dedykowany "maintenance ticket" (1-2h) który aktualizuje patch versions i sprawdza CI. (d) Renovate / Dependabot z automergem patch tylko, manual review minor/major. (e) Snapshot-based dev environment (Docker images z pinned tags) — łatwy "powrót do tego co działało". |
| R-27 | **Agent cost runaway** — kompromitacja klucza API Anthropic, agent w pętli, abuse usera → faktura $1000-10000 | Niskie | **Krytyczny** | (a) Twarde limity sekcja 8.5 architektury (tool calls/godz, tokens/run, $/dzień, $/miesiąc). (b) BYOK opcja dla enterprise (ticket 0.11.12) — klient płaci za swoje LLM. (c) Org-level monthly cap w Anthropic Console ($1000 dla MVP-prod) — niezależny hardstop. (d) Klucz w Vault z rotacją 90 dni. (e) Anomaly detection (5× wzrost tool calls/h vs średnia tygodniowa → flag). (f) Compromise response runbook. |
| R-28 | **Symfony 7.4 LTS upgrade** — wsparcie do listopada 2028/2029, wymuszony upgrade na kolejny LTS (8.x lub 9.x) | Pewne (w 2-3 lata) | Średni | (a) Already factored — przewidziane w roadmapie fazy 3. (b) API Platform jest stabilny i zwykle podąża za Symfony LTS w 1-2 release. (c) Test upgrade na branch w Q3 2028, full migration ~40-60h. (d) FrankenPHP 2.x worker API śledzimy w Sprintach maintenance. |

### 8.2 Ryzyka biznesowe (faza 2+)

| ID | Ryzyko | Prawdopodobieństwo | Wpływ | Mitygacja |
|---|---|---|---|---|
| R-10 | Konkurencja: Akeneo Cloud / PIMcore SaaS oferuje agresywne pricing | Wysokie | Wysoki | Wyróżnik agentic-first, skupienie na rynku PL/CEE z lokalnym customer success, niższa cena startowa |
| R-11 | Koszty LLM API rosną szybciej niż przychody | Średnie | Wysoki | Limity per-tenant w MVP, model routing (Sonnet domyślnie, Opus tylko dla schema-ops), opcja BYOK (klient dostarcza własny klucz API) |
| R-12 | Anthropic zmienia ceny / wycofuje model | Średnie | Średni | Abstrakcja LLMProvider z fallbackiem na OpenAI/Mistral w fazie 2 |
| R-13 | Klient enterprise wymaga on-prem deployment z auditem kodu | Wysokie | Średni | Stack 100% open source, brak BSL, on-prem deployment od dnia 0 jest scenariuszem podstawowym |
| R-14 | Wymagania ISO/SOC 2 odsuwają sprzedaż enterprise o 6-12 miesięcy | Wysokie | Wysoki | Faza 3 zaplanowana z compliance w scope, czas + budżet na audyt zewnętrzny |
| R-15 | Brak partnerów wdrożeniowych = wąskie gardło sprzedażowe | Wysokie | Wysoki | Partner program w fazie 3, dokumentacja jak dla zewnętrznych developerów od fazy 1, certyfikacje partnerów |

### 8.3 Ryzyka techniczne długoterminowe

| ID | Ryzyko | Prawdopodobieństwo | Wpływ | Mitygacja |
|---|---|---|---|---|
| R-20 | Symfony 7 wycofany na rzecz Symfony 8/9 — nadciąga wymuszony upgrade | Wysokie (w 5-7 lat) | Średni | LTS migrations co 2 lata, plan budżetu na 1-2 sprinty migracji co LTS |
| R-21 | PostgreSQL przestaje wspierać używaną wersję | Średnie (5+ lat) | Średni | Plan upgrade Postgres co 3-5 lat, backups/restore testowane przed każdą migracją |
| R-22 | Meilisearch zmienia licencję na BSL/SSPL | Niskie | Średni | Abstrakcja SearchProvider, możliwość swap na Typesense (MIT) lub Elasticsearch |
| R-23 | shadcn/ui przestaje być utrzymywane | Niskie | Niski | Komponenty są w naszym repo (kopiowane, nie importowane), utrzymujemy samodzielnie jeśli trzeba |
| R-24 | Refine.dev rozwija się w niepożądanym kierunku | Średnie | Niski | Refine to cienka warstwa hooków; w ostateczności zastąpimy własnymi hookami (~30-40h pracy) |

## 9. Wymagania zespołu

### 9.1 Faza 0 — MVP

- 1 product owner / operator (autor: Marcin) — pełna alokacja w czasie pracy nad MVP, ~10-40h/tydz
- Claude Code jako virtual senior developer (instance subscriber, Claude Code IDE integration)
- (Opcjonalnie) Mentor zewnętrzny / sparring partner — 10-20h support na pair-programming i decyzje techniczne, gdy product owner natrafia na blokady
- (Opcjonalnie) UX designer — 10h dla pierwszego wireframe agentic-first UX i system designu w shadcn

### 9.2 Faza 1 — Production-ready

- Powyższy zespół + 1 senior PHP developer (Symfony) na 0.25 etatu — code review, performance tuning, security
- Pen-tester zewnętrzny (kontraktowy, 1 audit)
- DevOps support — provider chmury / firma hostingowa (np. ovh, hetzner, mikr.us)

### 9.3 Faza 2-3

- Pełnoetatowy backend developer
- Pełnoetatowy frontend developer
- Designer (kontraktowy lub na pół etatu)
- Sales / customer success (gdy będzie pierwsza paczka klientów SaaS)
- (W fazie 3) Compliance officer / konsultant ISO/SOC 2

## 10. Plan komunikacji i zarządzania

### 10.1 Sprinty

Praca w 2-tygodniowych sprintach. Każdy sprint zamyka 2-3 ticketów z backlogu MVP. Demo na koniec sprintu (nawet do "samego siebie") — nagranie 5-10min screencast pokazujący co zostało zrobione.

### 10.2 Decyzje architektoniczne

Każda decyzja, która wpływa na architekturę (ADR-eligible), jest dokumentowana jako nowy wpis w ADR sekcji `01-architektura-pim.md`. Format: kontekst, opcje, decyzja, konsekwencje.

### 10.3 Backlog grooming

Co tydzień przegląd backlogu: re-priorytetyzacja, doprecyzowanie ticketów, identyfikacja blokad. Przy zmianach scope — aktualizacja tego dokumentu.

### 10.4 Stakeholder updates

Po każdej fazie — krótki update (1-2 strony) dla potencjalnych inwestorów / partnerów / pierwszych klientów: co zrobiono, co dalej, kiedy demo.

## 11. Lista deliverables fazy koncepcyjnej (zamknięcie)

Faza koncepcyjna kończy się dwoma dokumentami:

1. **`01-architektura-pim.md`** — pełna architektura, stack, model danych, integracje, agent, security, performance, ADR.
2. **`02-plan-projektu-pim.md`** — ten dokument: fazy, milestones, backlog, ryzyka, wycena.

Następne deliverables (faza implementacji, w trakcie produkcji):

- `03-api-spec.openapi.yaml` — formalna specyfikacja API generowana z API Platform.
- `04-data-dictionary.md` — słownik pól domenowych z opisami biznesowymi.
- `05-runbook.md` — procedury operacyjne (deploy, backup, restore, troubleshooting).
- `06-customer-handbook.md` — dokumentacja dla klientów wdrożeniowych.
- `07-integrator-handbook.md` — dokumentacja dla developerów integracji.

## 12. Kryteria sukcesu projektu

Projekt uznajemy za sukces, jeśli:

**Po fazie 0 (MVP):**
- Działa i jest deployowalny u klienta pilotażowego.
- Agent layer zachwyca w demo (Cmd+K → "dodaj atrybut" → diff → approve → działa).
- 2 integracje sprawnie syndykują dane.

**Po fazie 1:**
- 2-3 klientów produkcyjnych korzysta dziennie.
- p95 latency < celów z dokumentu architektury.
- Pen-test bez krytycznych ryzyk.

**Po fazie 2:**
- 10+ klientów (mix on-prem i SaaS).
- Pierwszy partner wdrożeniowy zewnętrzny.
- Agentic-first jest tym co kupujący wymieniają jako powód wyboru.

**Po fazie 3:**
- Pierwszy enterprise klient (>500k SKU lub >1B PLN obrotu).
- ISO 27001 lub SOC 2 Type 1 certyfikat lub plan na 12 miesięcy.
- Marketplace integracji 10+ pozycji, kilka od partnerów zewnętrznych.

---

*Koniec planu projektu. Dokument żyjący — aktualizowany co fazę.*
