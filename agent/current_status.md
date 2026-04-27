# Current Status

## Sub-faza: SPRINT-0 — 10/13 ticketów done (po rewizji zakresu), 3 do gate decision

## Ostatnie 3 akcje
1. **Ticket #13 (0.0.13) zamknięty** (PR #124 squash-merged do main 2026-04-27 jako `95d7936`). Benchmark FrankenPHP worker memory + `AbstractBatchHandler` szkielet + Prometheus metric endpoint. **Manual-smoke fix w trakcie review** (drugi commit do tej samej PR): (a) demo admin email `admin@pim.localhost` → `admin@demo.localhost` (spójny pattern `admin@<tenant_code>.localhost` dla obu tenantów; aktualizowane fixtures + 2× ApiTestCase + Playwright helper); (b) `Product` GetCollection dostał `order: ['id' => 'DESC']` — `paginationViaCursor` deklaruje kierunek kursora ale nie wymusza domyślnego ORDER BY; bez explicit `?order[id]=desc` Postgres zwracał wiersze w fizycznej kolejności insertu → nowo utworzony produkt lądował poza pierwszą stroną (zwłaszcza po zaśmieceniu bazy 71 406 wierszami `bench-*` z dev-env benchmarków, których cleanup OOM-ował przy `BacktraceDebugDataHolder` leaku). **Wynik benchmarku w prod env (target):** 5 000 produktów = 14 MiB peak (próg 256 MiB), 50 000 produktów = 14 MiB peak FLAT (memory nie rośnie z liczbą rzędów!) z clear; bez clear 50 000 = 150 MiB rosnąco + 6× wolniej. **Pattern R-25 zwalidowany twardymi liczbami.** Komponenty: `App\Messaging\AbstractBatchHandler` (abstract base z `flushAndClear()` + `shouldFlush()`), `App\Benchmark\BulkImportBenchmarkCommand` (`pim:benchmark:bulk-import` z opcjami `--count/--batch-size/--tenant/--no-clear/--keep`), `App\Observability\Http\MetricsController` (`GET /api/metrics` Prometheus 0.0.4 format), `apps/api/tests/Unit/Messaging/AbstractBatchHandlerTest.php` (3 testy). **Świadome odejścia:** (a) custom PHPStan rule blokująca flush() bez clear() przeniesiona do follow-up #123 (DoD ticketu explicite dopuszcza), (b) `/api/metrics` unauthenticated w MVP (epik 0.11 hardening), (c) benchmark CLI ≠ pełna symulacja worker-mode (Messenger consumer test dochodzi z async transport w epiku 0.1). **Najważniejsze odkrycie:** Symfony Profiler middleware (`BacktraceDebugDataHolder`) jest osobnym leak source — `doctrine.dbal.logging: false` go nie wyłącza. Benchmarki/workery memory MUSZĄ iść w `APP_ENV=prod APP_DEBUG=0`. Dev env w 50k INSERT OOM-uje pod 512 MiB cap **mimo poprawnego clear pattern'u**. Spisane do lessons + findings #27.
2. **Ticket #10 (0.0.10) zamknięty** (PR #122 squash-merged do main 2026-04-27 jako `c3f6f26`). Pierwszy Playwright E2E od dnia 1: 9 testów (5 auth, 4 CRUD) targetujących `https://pim.localhost` przez full Caddy + FrankenPHP + Postgres + Vite stack. CI job `e2e` w `quality-frontend.yml` z dwustopniowym startup (database+redis → api → migrate+fixtures → --wait reszta → playwright). 3 CI debug fixes: chicken-egg api healthcheck, `--wait` vs `restart: no` minio-init, Caddy HTTPS-only healthcheck.
3. **Ticket #16 (0.0.16) zamknięty** (PR #121 jako `19f1740`). `Project Plan/06-sprint-0-findings.md` (8 sekcji, 25 świadomych odejść). `CLAUDE.md` — 4 drobne korekty. Reorganizacja backloga (`bedf1ae`): 35 ticketów przeniesionych do Faza 1/2, milestone'y Beta-Min/Beta-Full zamknięte.

## Bieżący stan
Sprint 0 = 10/13 ticketów done (#1 setup, #2 multi-tenancy, #3 ApiResource Product, #4 LexikJWT, #5 admin Refine, #10 Playwright E2E, #11 quality gates, #12 isolation smoke, #13 memory benchmark, #16 audit + findings). Pozostałe: #9 (manual demo), #14 (perf profile), #15 (pgBackRest).

Stack zatrzymany po sesji. Aby uruchomić: `pnpm stack:up` (lub `pnpm dev` w foreground).

Domain model:
- 3 entities (`Tenant`, `Product`, `User`) w bounded contexts `Identity` i `Catalog`
- Migracje zaaplikowane: `Version20260427070435` (Tenant+Product), `Version20260427095515` (Users)
- Fixtures: 2 tenanty × 1 admin user × 3 produkty. Admin: `admin@demo.localhost`/`admin@acme.localhost` hasło `changeme`
- Multi-tenancy plumbing: TenantContext + listener + SQL filter + RequestTenantSubscriber + auth-aware `CurrentTenantProvider`
- Auth: LexikJWT v3.2.0, `POST /api/auth/login` zwraca JWT, wszystkie inne `/api/*` wymagają `Authorization: Bearer ...`
- API: `Product` jako `#[ApiResource]` na `/api/products` (CRUD + cursor pagination + Swagger UI na `/api/docs`)
- Admin frontend: Refine v5 + shadcn na `https://pim.localhost` — login + lista + create/edit produktów; sidebar nav + i18n (pl/en)
- Test coverage: TenantAssignmentListenerTest (4 cases) + ProductApiTest (6 cases) + TenantIsolationTest (4 cases) + AuthApiTest (5 cases) + Playwright E2E (9 cases — auth + products CRUD)

Quality gates aktywne:
- **Lokalnie**: pre-commit hook + commit-msg hook (husky) — Biome + PHP-CS-Fixer + Conventional Commits; `pnpm --filter @pim/admin e2e` host-side
- **CI**: GitHub Actions na PR + push do main — PHPStan + PHP-CS-Fixer + PHPUnit; Biome + tsc + Vite build; **Playwright E2E (full Caddy + FrankenPHP + Postgres + admin stack)**; composer + pnpm audit (nightly)

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
13. `APP_DEFAULT_TENANT_CODE` flip w testach — pierwotnie w #12, **ZASTĄPIONE w #4** real-auth (each tenant ma własnego admina, test mintuje JWT). (#12 → #4)
14. Oba klucze RSA `config/jwt/*.pem` gitignored (Lexik recipe default) — devs generują own lokalnie, prod mountuje z vault'a, CI generuje per-run przed phpunit/phpstan jobs. Ticket prosił "commit pubkey" ale industry-standard w MVP-stage to local-only. (#4)
15. Fixture admin password = `changeme` — explicit dev-only, full onboarding flow w epiku 0.2 (#24+). (#4)
16. `/api/docs`, `/api/contexts`, `/api` (entrypoint) PUBLIC w `access_control` — żeby OpenAPI/Hydra tooling działał bez auth. Production może lock'ować przez `enable_swagger_ui: false` env-aware. (#4)
17. Brak refresh tokens / token blacklist'u — Lexik default 1h TTL na token. `gesdinet/jwt-refresh-token-bundle` + RBAC w epiku 0.2. (#4)
18. JWT w `localStorage` w admin'ie zamiast httpOnly cookie — explicit Sprint-0 shortcut, refactor w 0.2.6 (#28). (#5)
19. Admin używa plain `react-router` v7 zamiast `@refinedev/react-router-v6` — Refine headless + RR7 idiomatic, mniejszy bundle, mniej plumbing'u. (#5)
20. Custom Hydra-aware DataProvider zamiast `@refinedev/simple-rest` — AP4 zwraca Hydra Collection (`member`, `totalItems`), simple-rest oczekuje `data`+`total`. ~50-liniowy custom provider jaśniejszy niż wrapper z transformacją. (#5)
21. shadcn primitives copy-paste zamiast CLI — CLI wymaga interaktywnego promptu w container'ze, kopiowanie 6 plików zajmuje 5 min. (#5)
22. Admin bundle warning >500 kB (Refine + react-query + zod + radix razem) — code splitting `React.lazy` per route w fazie 1 gdy pojawią się 5+ resource pages. (#5)
23. Brak Playwright E2E w #5 — to scope ticketu #10 (0.0.10), explicit setup ticket. Manual smoke pokrywa wszystkie ścieżki. (#5)

## Aktywne blokery
- **Wybór hostingu / providera** — decyzja na pierwszy pilot (rekomendowane: OVH, Hetzner, mikr.us). Może być odłożone do MVP-Final.
- **Decyzja operacyjna:** wybór trybu wykonania Sprintu 0 — rekomendowane 1-2 tygodnie urlopu/skupienia (sekcja 7 planu).

> **Blokery historyczne (po rewizji zakresu 2026-04-27 nie blokują MVP):**
> - ~~Konto Shopify Partners~~ — Shopify całość (epik 0.9 + Sprint 0 #8) przeniesione do **Faza 1**.
> - ~~Anthropic API key~~ — agent layer (epik 0.7 + Sprint 0 #6/#7) przeniesiony do **Faza 2**.

## REWIZJA ZAKRESU MVP (2026-04-27, post-#5)
**Decyzja operatora:** "agentic management = dodatek; baza i UX frontu są priorytetem". W praktyce:
- **Cały epik 0.7** (Agent layer Beta-Min + Beta-Full, #63-#71 + #108-#112) → **Faza 2**.
- **Epiki 0.8 (BaseLinker, #72-#78) + 0.9 (Shopify, #79-#89)** → **Faza 1** (razem z Magento + IdoSell przesuniętymi z Fazy 1 do Fazy 2).
- **Sprint 0 #6 (Agent), #7 (Cmd+K), #8 (Shopify stub)** → odpowiednio Faza 2 / Faza 2 / Faza 1.
- **Layout #54** — Cmd+K placeholder usunięty z scope.
- **Provenance #61** — wariant `agent` (purple) odłożony do Fazy 2.
- **Hooks pod Fazę 2 zostają w MVP** (4-6h): `pending_changes` table jako pusta migracja, `provenance` enum z zarezerwowanym `agent`, lifecycle events Doctrine.
- Szczegóły w `Project Plan/02-plan-projektu-pim.md` sekcja 3 (rewizja na początku) + sekcje 4 i 5.

## Nowa kolejność wykonania (po Sprincie 0)
1. **Pozostałe Sprint 0:** #16 (audit + findings) → #10 (Playwright E2E) → #13/#14/#15 paralelnie → #9 (demo + gate decision).
2. **MVP-Alpha epiki 0.1, 0.2, 0.3** — fundament (Infrastructure, Identity, Catalog domain).
3. **(decyzja) Epik 0.3a — Categories / taxonomy** (kandydat — operator: "jeszcze nie wiem dokładnie jak").
4. **Epik 0.4 + 0.5** — API extensions + Meilisearch.
5. **Epik 0.6** — Admin UI core CRUD (atrybuty + dynamiczny formularz produktu).
6. **Epik 0.10 + 0.11** — API Configurator + hardening.
7. **Demo pilot → gate decision.**
8. **Faza 1:** BaseLinker + Shopify (+ RLS, monitoring, hardening track B).
9. **Faza 2:** Agent layer + Magento + IdoSell + SaaS aktywacja.

## Następny krok
| # | Ticket | Komentarz |
|---|---|---|
| #14 (0.0.14) | Profilowanie Blackfire/Tideways | Niezależny. p95 < 200 ms na 1000 produktach. |
| #15 (0.0.15) | pgBackRest + WAL stub | Niezależny. Backup co 1h + restore test przed końcem Sprintu. |
| #9 (0.0.9) | Manualny E2E + screencast (gate decision) | Po wszystkich pozostałych Sprint 0 ticketach. Verdict GREEN/RED — predykcja **GREEN**. |

## Postęp po fazach (po rewizji zakresu)
- [ ] Sprint 0 (gate decision) — **10/13 ticketów done** — issues #1-#5, #9-#16 (#6, #7, #8 przeniesione do Faza 1/2)
- [ ] MVP-Alpha (epiki 0.1–0.6, fundament + admin UI) — 0/46 — issues #17-#62
- [ ] MVP-Final (epiki 0.10–0.11, API Configurator + hardening) — 0/18 — issues #90-#107
- [ ] **Faza 1** — Integracje BaseLinker + Shopify + hardening track B — 19 issues (epiki 0.8 + 0.9 + Sprint 0 #8)
- [ ] **Faza 2** — Agent layer + Magento + IdoSell — 16 issues (epiki 0.7 Beta-Min + Beta-Full + Sprint 0 #6/#7)

## Postęp Sprint 0 ticketów
- [x] **#1 / 0.0.1** — Setup monorepo Turborepo + docker-compose + Caddy single-origin (PR #113 merged 2026-04-26)
- [x] **#2 / 0.0.2** — Encja Product + tenant_id + Doctrine TenantFilter (PR #115 merged 2026-04-27)
- [x] **#3 / 0.0.3** — ApiResource Product → /api/products (PR #116 merged 2026-04-27)
- [x] **#4 / 0.0.4** — Authentication minimalny + JWT (PR #118 merged 2026-04-27)
- [x] **#5 / 0.0.5** — Admin Refine + shadcn (PR #119 + hotfix #120 merged 2026-04-27)
- [→] ~~#6 / 0.0.6~~ — Agent endpoint → **przeniesione do Faza 2** (rewizja 2026-04-27)
- [→] ~~#7 / 0.0.7~~ — Cmd+K placeholder → **przeniesione do Faza 2** (rewizja 2026-04-27)
- [→] ~~#8 / 0.0.8~~ — Shopify GraphQL stub → **przeniesione do Faza 1** (rewizja 2026-04-27)
- [ ] #9 / 0.0.9 — Manualny E2E Sprintu 0 + screencast
- [x] **#10 / 0.0.10** — Playwright E2E od dnia 1 (PR #122 merged 2026-04-27)
- [x] **#11 / 0.0.11** — PHPStan max + PHP-CS-Fixer + Biome + husky + CI (PR #114 merged 2026-04-27)
- [x] **#12 / 0.0.12** — Smoke izolacji multi-tenant (PR #117 merged 2026-04-27)
- [x] **#13 / 0.0.13** — Benchmark FrankenPHP worker memory (PR pending — 14 MiB peak na 50 000 prod env z clear, follow-up #123 dla custom PHPStan rule)
- [ ] #14 / 0.0.14 — Profilowanie Blackfire/Tideways
- [ ] #15 / 0.0.15 — pgBackRest + WAL stub
- [x] **#16 / 0.0.16** — Audit CLAUDE.md + 06-sprint-0-findings.md (PR #121 merged 2026-04-27)

## Postęp epików (poza Sprintem 0 — zerowy)
**MVP (po rewizji zakresu):**
- [ ] 0.1 Infrastructure i fundamenty — #17-#23
- [ ] 0.2 Identity & Access — #24-#30
- [ ] 0.3 Domain model — Catalog — #31-#40
- [ ] 0.4 API Platform — exposing entities — #41-#48
- [ ] 0.5 Search — Meilisearch — #49-#53
- [ ] 0.6 Admin UI — core CRUD — #54-#62 (#54 + #61 zrewidowane)
- [ ] 0.10 API Configurator — #90-#95
- [ ] 0.11 Hardening, a11y, analytics, backup, BYOK — #96-#107

**Faza 1 — Integracje (po MVP gate decision):**
- [ ] 0.8 Integracja BaseLinker — #72-#78 (przeniesione z MVP-Final)
- [ ] 0.9 Integracja Shopify — #79-#89 (przeniesione z MVP-Final)
- [ ] +Sprint 0 #8 (Shopify GraphQL stub) — przeniesione

**Faza 2 — Agent layer + dodatkowe konektory:**
- [ ] 0.7 Agent layer — schema-add — #63-#71 (Beta-Min, przeniesione z MVP) + #108-#112 (Beta-Full, przeniesione z MVP)
- [ ] +Sprint 0 #6 (Agent endpoint), #7 (Cmd+K) — przeniesione
- [ ] Magento + IdoSell + Allegro + WooCommerce konektory (przesunięte z Fazy 1)

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
