# Current Status

## Sub-faza: SPRINT-0 — 5/16 ticketów done, oczekiwanie na wybór następnego ticketu

## Ostatnie 3 akcje
1. **Ticket #12 (0.0.12) zamknięty** (PR #117 squash-merged do main 2026-04-27 jako `f705e1d`). `TenantIsolationTest` (4 case'y) waliduje izolację na application-layer: tenant A widzi tylko swoje 5 produktów (zero leak'ów BRAVO-), GET na IRI tenanta B → 404, PATCH na IRI tenanta B → 404, raw DBAL `SELECT COUNT(*)` widzi wszystkie 10 (świadomy boundary, RLS w fazie 1). Mechanizm test'u: flip `APP_DEFAULT_TENANT_CODE` przez `$_ENV`/`$_SERVER`/`putenv` + `ensureKernelShutdown()` po seedzie żeby świeży kontener resolvował nową wartość. Seed z `Product::assignTenant()` bez TenantContext (listener no-opuje przy non-null tenant). Po #4 (auth+JWT) env-var dance znika — test będzie autentykował się jako użytkownik z każdego tenanta.
2. **Ticket #3 (0.0.3) zamknięty** (PR #116 squash-merged do main 2026-04-27 jako `59a9226`). `Product` wystawiony w API Platform z operacjami `GetCollection`/`Get`/`Post`/`Patch`. Grupy serializacji `product:read` (lista pól z timestampami), `product:write` (tworzenie — z SKU), `product:patch` (update bez SKU — SKU immutable po POST). Walidatory `NotBlank`+`Length` na `sku`/`name`, `Length` na `description`/`brand`. Kursorowa paginacja (`paginationType: 'cursor'` + `OrderFilter`/`RangeFilter` po `id` DESC) — `id[lt]`/`id[gt]` zwracają `view.next`. Custom OpenAPI request body example dla POST. ApiTestCase z 6 case'ami (lista hydra, POST 201, POST 422 walidacja, GET item, PATCH zachowuje SKU, cursor view links) — wszystkie zielone. PHPUnit job dodany do `quality-php.yml` (Postgres 16 service, `dbname_suffix: '_test'` → schema bootstrap przez Foundry SchemaTool). Twig bundle dodany żeby Swagger UI renderował się na `/api/docs`. **Świadome odejście:** unique-SKU validator (`UniqueEntity['tenant', 'sku']`) skipped — wymaga custom validatora bo `tenant` jest stamped w PrePersist po fazie validacji. Postgres unique index nadal egzekwuje invariant na DB-level; pretty validator dochodzi w epiku 0.4 (#41+).
3. **Ticket #2 (0.0.2) zamknięty** (PR #115 squash-merged do main 2026-04-27). Encje `Tenant` (Identity) + `Product` (Catalog) z UUID v7 + Doctrine 3 attributes; pełne plumbing multi-tenancy: `TenantContext` (mutable holder), `TenantAware` interface, `CurrentTenantProvider` (security → env fallback), `TenantAssignmentListener` (PrePersist), `TenantFilter` (Doctrine SQLFilter), `TenantFilterConfigurator`, `RequestTenantSubscriber` (REQUEST/TERMINATE). Migracja `Version20260427070435` reversible. AppFixtures z 2 tenantami × 3 produkty. PHPUnit 12 + 4 test cases na listenerze (stamps/throws/idempotent/ignores). **QoL fix:** docker-compose.yml z bind mount `apps/api` + named volumes na `var/` i `vendor/` — iterowanie nad PHP nie wymaga już rebuild image. **Świadome odejścia:** PHPUnit 11→12 (sebastian/diff conflict), `Product::$tenant` nullable w PHP/NOT NULL w schemacie (scoped PHPStan ignore), bind mount apps/api jako QoL (lekkie scope creep ale eliminuje blocker).

## Bieżący stan
Sprint 0 = 5/16 ticketów done (#1 setup, #11 quality gates, #2 multi-tenancy + Product/Tenant, #3 ApiResource Product, #12 multi-tenant isolation smoke test).

Stack zatrzymany po sesji. Aby uruchomić: `pnpm stack:up` (lub `pnpm dev` w foreground).

Domain model:
- 2 entities (`Tenant`, `Product`) w bounded contexts `Identity` i `Catalog`
- Migracja zaaplikowana, schema z FK `products_tenant_fk` + unique `(tenant_id, sku)`
- Fixtures: 2 tenanty × 3 produkty (`pim:fixtures:load` lub `bin/console doctrine:fixtures:load`)
- Multi-tenancy plumbing: TenantContext + listener + SQL filter + RequestTenantSubscriber
- API: `Product` jako `#[ApiResource]` na `/api/products` (CRUD + cursor pagination + Swagger UI na `/api/docs`)
- Test coverage: TenantAssignmentListenerTest (4 cases) + ProductApiTest (6 cases) + TenantIsolationTest (4 cases)

Quality gates aktywne:
- **Lokalnie**: pre-commit hook + commit-msg hook (husky) — Biome + PHP-CS-Fixer + Conventional Commits
- **CI**: GitHub Actions na PR + push do main — PHPStan + PHP-CS-Fixer + PHPUnit; Biome + tsc + Vite build; composer + pnpm audit (nightly)

**Akcje po stronie operatora (do zrobienia w wolnej chwili, nie blocker):**
- Branch protection na `main` (Settings → Branches → Add rule):
  - Require status checks: `phpstan`, `php-cs-fixer`, `biome`, `typecheck`, `build`, `composer-audit`, `pnpm-audit`
  - Require branch up to date before merge
- Po pull: `pnpm install` żeby husky `prepare` zarejestrował hooki na świeżo sklonowanym repo.

Świadome odejścia od planu (do uzupełnienia w `06-sprint-0-findings.md` na koniec Sprintu 0):
1. `api-platform/api-platform` z Packagist to archiwalny skeleton z 2018 — pivot do `symfony/skeleton 7.4` + `composer require api-platform/symfony:^4.3`. (#1)
2. `/api/docs.json` nie odpowiada w API Platform 4 (tylko `.jsonld` + `.html`); healthchecki używają `/api`. (#1)
3. Psalm strict pominięty — `vimeo/psalm:dev-master` ma conflict z `psalm/psalm-plugin-api 0.1.0`. PHPStan max + strict-rules pokrywa zakres. (#11)
4. `git config core.fileMode = false` ustawione lokalnie (Synology Drive zmienia bits 644→755 między sync). (#11)
5. PHPUnit 11 → 12 (PHPUnit 11 wymaga `sebastian/diff ^6` ale lock fixował 8.x z phpstan). (#2)
6. `Product::$tenant` nullable w PHP (krótki window przed PrePersist) ale NOT NULL w schemacie — scoped PHPStan ignore. Listener tests + DB constraint potwierdzają invariant. (#2)
7. `docker-compose.yml` bind mount `apps/api` + named volumes na `var/` i `vendor/` (lekkie scope creep w #2 ale eliminuje rebuild ~1 min na każdą zmianę PHP). (#2)
8. `Product` `#[ApiResource]` wystawia entity bezpośrednio (bez DTO input/output) — w MVP-Alpha (epik 0.4 #41+) decyzja czy split na osobne DTO. Powód: 50× mniej kodu, AP4 grouping wystarczy. (#3)
9. `application/json` jako input/output format **nieaktywowany** — tylko `application/ld+json` (POST/GET) + `application/merge-patch+json` (PATCH). Plain JSON dochodzi w epiku 0.4 (#41) razem z decyzją o explicit DTO. (#3)
10. `UniqueEntity['tenant', 'sku']` validator pominięty — listener stempluje `tenant` w PrePersist po fazie validacji, więc validator widziałby zawsze `tenant=null`. Postgres unique index `products_tenant_sku_uniq` zachowuje invariant on DB level (HTTP 500 zamiast 422). Custom validator z dostępem do `TenantContext` dochodzi w #41+. (#3)
11. Twig dodany jako runtime dependency tylko po to żeby AP4 włączył Swagger UI (`enable_swagger_ui` defaultuje `class_exists(TwigBundle::class)`). Dla prod docs można lock'ować przez `enable_swagger_ui: false` env-aware. (#3)
12. Native SQL `SELECT COUNT(*) FROM products` w `TenantIsolationTest` widzi wszystkie tenanty — boundary application-layer'a, nie defekt. RLS w fazie 1 (sekcja 11.1a) zamknie. Bulk paths (COPY, raw INSERT) trzymają tenant scope w kodzie do tego czasu. (#12)
13. `APP_DEFAULT_TENANT_CODE` flip w testach przez `$_ENV` + `$_SERVER` + `putenv` + `ensureKernelShutdown()` — tymczasowe pre-auth. Po #4 (JWT) test będzie autentykował się jako użytkownik z każdego tenanta i dance znika. (#12)

## Aktywne blokery
- **Setup konta Shopify Partners** — potrzebny development store free na ticket #8 (0.0.8) i pełen Epic 0.9.
- **Anthropic API key** — potrzebny dla agent layer (ticket #6 / 0.0.6), z org-level cap $1000/mies. ustawionym w Anthropic Console przed pierwszym call.
- **Wybór hostingu / providera** — decyzja na pierwszy pilot (rekomendowane: OVH, Hetzner, mikr.us). Może być odłożone do MVP-Final.
- **Decyzja operacyjna:** wybór trybu wykonania Sprintu 0 — rekomendowane 1-2 tygodnie urlopu/skupienia (sekcja 7 planu).

## Następny krok
**Czeka na decyzję operatora który ticket podejmujemy następny.** Topologicznie odblokowane (po #1, #2, #3, #11, #12):

| # | Ticket | Komentarz |
|---|---|---|
| **#4 (0.0.4)** | Auth minimalny (statyczny user + JWT LexikJWT) | **Rekomendacja** — następny gate na vertical slice. Odblokowuje #5 (admin), #6 (agent), eliminuje env-fallback w `CurrentTenantProvider`, pozwala wymienić env-var dance w `TenantIsolationTest` na real auth. |
| #13 (0.0.13) | Benchmark FrankenPHP worker memory (5000 produktów import) | Wymaga AbstractBatchHandler stub + bulk import command — średni nakład. Niezależny od #4. |
| #15 (0.0.15) | pgBackRest + WAL stub w docker-compose + restore test | Niezależny. |
| #16 (0.0.16) | Audit CLAUDE.md + utworzenie `06-sprint-0-findings.md` | Można odpalić w połowie Sprintu — agreguje 13 świadomych odejść już zalogowanych. |
| #8 (0.0.8) | Klient Shopify GraphQL + Backoff stub | **Blocker:** wymaga konta Shopify Partners dev store. |

## Postęp po sub-fazach (cumulative h, MVP Core)
- [ ] Sprint 0 (gate decision) — **5/16 ticketów done** — issues #1-#16
- [ ] MVP-Alpha (epiki 0.1–0.6) — 0/46 — issues #17-#62
- [ ] MVP-Beta-Min (część 0.7) — 0/9 — issues #63-#71
- [ ] MVP-Final (epiki 0.8–0.11) — 0/36 — issues #72-#107
- [ ] MVP-Beta-Full (część 0.7, opcjonalnie) — 0/5 — issues #108-#112

## Postęp Sprint 0 ticketów
- [x] **#1 / 0.0.1** — Setup monorepo Turborepo + docker-compose + Caddy single-origin (PR #113 merged 2026-04-26)
- [x] **#2 / 0.0.2** — Encja Product + tenant_id + Doctrine TenantFilter (PR #115 merged 2026-04-27)
- [x] **#3 / 0.0.3** — ApiResource Product → /api/products (PR #116 merged 2026-04-27)
- [ ] #4 / 0.0.4 — Authentication minimalny + JWT
- [ ] #5 / 0.0.5 — Admin Refine + shadcn lista produktów
- [ ] #6 / 0.0.6 — Agent endpoint + tool create_attribute + limity 8.5
- [ ] #7 / 0.0.7 — Cmd+K placeholder UI
- [ ] #8 / 0.0.8 — Klient Shopify GraphQL + Backoff stub
- [ ] #9 / 0.0.9 — Manualny E2E Sprintu 0 + screencast
- [ ] #10 / 0.0.10 — Playwright E2E od dnia 1
- [x] **#11 / 0.0.11** — PHPStan max + PHP-CS-Fixer + Biome + husky + CI (PR #114 merged 2026-04-27)
- [x] **#12 / 0.0.12** — Smoke izolacji multi-tenant (PR #117 merged 2026-04-27)
- [ ] #13 / 0.0.13 — Benchmark FrankenPHP worker memory
- [ ] #14 / 0.0.14 — Profilowanie Blackfire/Tideways
- [ ] #15 / 0.0.15 — pgBackRest + WAL stub
- [ ] #16 / 0.0.16 — Audit CLAUDE.md + 06-sprint-0-findings.md

## Postęp epików (poza Sprintem 0 — zerowy)
- [ ] 0.1 Infrastructure i fundamenty — #17-#23
- [ ] 0.2 Identity & Access — #24-#30
- [ ] 0.3 Domain model — Catalog — #31-#40
- [ ] 0.4 API Platform — exposing entities — #41-#48
- [ ] 0.5 Search — Meilisearch — #49-#53
- [ ] 0.6 Admin UI — core CRUD — #54-#62
- [ ] 0.7 Agent layer — schema-add — #63-#71 (Beta-Min) + #108-#112 (Beta-Full)
- [ ] 0.8 Integracja BaseLinker — #72-#78
- [ ] 0.9 Integracja Shopify — #79-#89
- [ ] 0.10 API Configurator — #90-#95
- [ ] 0.11 Hardening, a11y, analytics, backup, BYOK — #96-#107

## Notatka dla Claude Code (next session boot)
Po starcie sesji:
1. Przeczytaj `CLAUDE.md` (auto-loaded).
2. Przeczytaj `agent/lessons.md` — szczególnie "Patterns to Avoid", "Package Quirks", "Toolchain quirks".
3. Sprawdź `Project Plan/02-plan-projektu-pim.md` sekcja 3.0 (Sprint 0 zakres) jeśli zaczynasz nowy ticket Sprint 0.
4. Lista pozostałych issues Sprint 0: `gh issue list --milestone "Sprint 0 — Vertical Slice" --state open`
5. Stack: **`pnpm stack:up`** (lub `pnpm dev` foreground), `https://pim.localhost` po akceptacji Caddy local CA.
6. Przed commit: hooki husky odpalą się automatycznie. Jeśli hook zfailuje przy pierwszym commit po pull, odpal `pnpm install` żeby `prepare` script zarejestrował hooki.
7. Quality gates są aktywne — każdy commit i PR przechodzi przez PHPStan max, PHP-CS-Fixer, PHPUnit, Biome strict, tsc, composer/pnpm audit. Nie pomijaj `--no-verify`.
8. **Iterowanie nad PHP nie wymaga `docker compose build api`** — apps/api jest bind-mounted. Po zmianie kodu wystarczy `docker compose restart api` (worker re-load) jeśli zmiana dotyczy services config; dla zwykłych zmian PHP po prostu hit refresh.
9. **Funkcjonalności MVP — `Project Plan/03-funkcjonalnosci-mvp.md`** (700 linii, dodane 2026-04-27 przez operatora). Zawiera archetyp pierwszego pilota (B2B technical, 50 MLN GMV/rok, 10-15k SKU, multimarka + własna marka), 5 person (Owner/Tomasz, Catalog Manager/Kasia jako #1, Marketing/Magda, IT-Integration/Piotr jako #1.5, Sales out-of-PIM), 10 user stories z kryteriami akceptacji + mapowaniem na epiki techniczne, success criteria pierwszego pilota. **Czytaj OBOWIĄZKOWO przed pracą nad ticketami:** 0.6 (Admin UI #54-#62), 0.7 (Agent UX #63-#71 + #108-#112), 0.8 (BaseLinker #72-#78), 0.9 (Shopify #79-#89), 0.10 (API Configurator #90-#95), 0.11 dashboard/a11y (#96-#107). Tickety czysto techniczne (Sprint 0, epiki 0.1-0.5) **nie wymagają** tego dokumentu — można pracować bez kontekstu funkcjonalnego.
10. Jeśli operator nie powiedział inaczej — rekomendacja na następny ticket: **#3 (0.0.3 ApiResource Product → /api/products)**.
