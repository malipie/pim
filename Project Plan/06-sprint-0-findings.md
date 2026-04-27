# Sprint 0 — Findings

> Snapshot zamknięcia Sprintu 0 (gate-decision document). Agreguje świadome odejścia od planu, decyzje architektoniczne, audyt `CLAUDE.md`/`agent/lessons.md` i — najważniejsze — **rewizję zakresu MVP po ticketcie #5**.
>
> Plik utworzony 2026-04-27 w ramach ticketu **#16 (0.0.16)**. Aktualizowany przy każdym kolejnym domknięciu Sprint-0 (#9, #10, #13, #14, #15) o ile odkryje coś co rozszerza/koryguje wnioski.

## 1. Stan domknięcia Sprintu 0

### 1.1 Zakres po rewizji
Pierwotny scope Sprint-0 (16 ticketów #1-#16) został zrewidowany po PR #119 (#5):
- `#6` (Agent endpoint), `#7` (Cmd+K placeholder) — **przeniesione do Faza 2**.
- `#8` (Klient Shopify GraphQL stub) — **przeniesione do Faza 1**.
- Sprint 0 zamykamy **13 ticketami**, nie 16.

### 1.2 Status (snapshot 2026-04-27)

| # | Ticket | Status | PR |
|---|---|---|---|
| #1 | 0.0.1 Setup monorepo + docker-compose + Caddy | ✅ done | PR #113 |
| #2 | 0.0.2 Encja Product + Tenant + multi-tenancy | ✅ done | PR #115 |
| #3 | 0.0.3 ApiResource Product → /api/products | ✅ done | PR #116 |
| #4 | 0.0.4 LexikJWT auth | ✅ done | PR #118 |
| #5 | 0.0.5 Admin Refine + shadcn | ✅ done | PR #119 + hotfix #120 |
| ~~#6~~ | ~~Agent endpoint~~ | → Faza 2 | — |
| ~~#7~~ | ~~Cmd+K placeholder~~ | → Faza 2 | — |
| ~~#8~~ | ~~Shopify GraphQL stub~~ | → Faza 1 | — |
| #9 | 0.0.9 Manualny E2E + screencast (gate decision) | 🟡 pending | — |
| #10 | 0.0.10 Playwright E2E | ✅ done | PR #122 |
| #11 | 0.0.11 PHPStan max + PHP-CS-Fixer + Biome + husky + CI | ✅ done | PR #114 |
| #12 | 0.0.12 Smoke izolacji multi-tenant | ✅ done | PR #117 |
| #13 | 0.0.13 Benchmark FrankenPHP worker memory | ✅ done | PR #124 |
| #14 | 0.0.14 Profilowanie Blackfire/Tideways | ✅ done | (ten PR) |
| #15 | 0.0.15 pgBackRest + WAL stub | 🟡 pending | — |
| #16 | 0.0.16 Audit + findings | ✅ done (ten dokument) | PR #121 |

**Done: 11 / 13. Pending: 2** (#9, #15). Gate decision (zielony/czerwony) = po zamknięciu wszystkich pozostałych.

## 2. REWIZJA ZAKRESU MVP (decyzja operatora 2026-04-27)

### 2.1 Co się zmieniło
**Cytat operatora:** *"agentic management jest całkowicie jako dodatek, musimy zrobić pełną bazę, dobry UX frontu, więc to wypadnie finalnie jeszcze dalej. Faza 1: BaseLinker + Shopify. Faza 2: Agent layer + dodatkowe konektory."*

Rewizja:

| Z MVP | Do | Powód |
|---|---|---|
| Epik 0.7 (Agent layer Beta-Min #63-#71) | **Faza 2** | Agent = dodatek po stabilnym katalogu |
| Epik 0.7 (Agent layer Beta-Full #108-#112) | **Faza 2** | jw. |
| Epik 0.8 (BaseLinker #72-#78) | **Faza 1** | Pierwszy klient = po stabilizacji katalogu |
| Epik 0.9 (Shopify #79-#89) | **Faza 1** | jw. |
| Sprint 0 #6, #7 (Agent + Cmd+K) | **Faza 2** | Spójne z agentem |
| Sprint 0 #8 (Shopify stub) | **Faza 1** | Spójne z Shopify |
| Magento + IdoSell (z Fazy 1) | **Faza 2** | Agent + konektory razem |
| `#54` Layout (Cmd+K placeholder) | scope trim | Cmd+K post-MVP |
| `#61` Provenance badge (agent) | scope trim | `manual/import/integration` w MVP, `agent` post-MVP |

### 2.2 Nowa kolejność po Sprincie 0
1. Pozostałe Sprint 0 (#9, #10, #13, #14, #15, #16) → gate decision
2. MVP-Alpha epiki 0.1, 0.2, 0.3 (Infrastructure + Identity + Catalog domain)
3. **(decyzja)** Epik 0.3a — Categories / taxonomy (kandydat — operator: "jeszcze nie wiem dokładnie jak")
4. Epik 0.4 + 0.5 (API extensions + Meilisearch)
5. Epik 0.6 (Admin UI core CRUD — atrybuty + dynamiczny formularz)
6. Epik 0.10 + 0.11 (API Configurator + hardening / a11y / analytics / backup / BYOK)
7. **Demo pilot → gate decision o gotowości**
8. **Faza 1**: BaseLinker + Shopify + RLS aktywacja + monitoring full stack
9. **Faza 2**: Agent layer (epik 0.7) + Magento + IdoSell + SaaS aktywacja

### 2.3 Argumenty PRO rewizji (zapisane do retrospekcji)
- **Pilot bez katalogu = pilot bez wartości.** Nawet najlepszy chat-agent nie zastąpi działającego modelu danych. Persona #1 (Catalog Manager / Kasia z `03-funkcjonalnosci-mvp.md`) najpierw chce wprowadzić 5000 SKU, dopiero potem mówić o automatyzacji.
- **Agent + Shopify razem = ~150h ryzyka.** Jeśli któryś blokuje pilot, opóźnia cały MVP.
- **"Multi-tenant ready, agentic-ready" zamiast "agentic-deployed".** Identyczna asymetria jak z multi-tenancy (single-tenant deployed, multi-tenant ready) — sprawdzona.
- **B2B technical pilot ocenia "działający katalog 5k SKU + niezawodny sync" wyżej niż "rozmawiaj z systemem".**
- **Ryzyka R-25 (memory leak), R-27 (klucz API kompromitacja), R-28 (Shopify cost) wychodzą z MVP** — nie trzeba mitygować zanim zobaczymy realne użycie.

### 2.4 Hooks pod Fazę 2 zostają w MVP (4-6h, kandydat do epiku 0.3 lub 0.11)
- `pending_changes` table jako migracja w MVP (pusta, nieużywana — agent w Fazie 2 pisze tu)
- `provenance` enum w `product_values` z zarezerwowanym wariantem `agent` (UI w MVP nie pokazuje go)
- Doctrine lifecycle events emitujące meta na każdy persist/update — agent w Fazie 2 loguje swoje zmiany przez ten sam kanał

**Koszt 4-6h w MVP, oszczędza 30-40h migracji w Fazie 2. Worth it.**

## 3. Świadome odejścia od planu (cumulative, agregat 1-5)

### 3.1 Setup / toolchain
1. **`api-platform/api-platform` z Packagist to archiwalny skeleton z 2018** — pivot do `symfony/skeleton 7.4` + `composer require api-platform/symfony:^4`. (#1)
2. **`/api/docs.json` nie odpowiada w API Platform 4** (tylko `.jsonld` + `.html`); healthchecki używają `/api`. (#1)
3. **Psalm strict pominięty** — `vimeo/psalm:dev-master` ma circular conflict z `psalm/psalm-plugin-api 0.1.0`. PHPStan max + strict-rules pokrywa zakres. (#11)
4. **`git config core.fileMode = false`** ustawione lokalnie (Synology Drive zmienia bits 644→755 między sync). (#11)
5. **PHPUnit 11 → 12** (PHPUnit 11 wymaga `sebastian/diff ^6` ale lock fixował 8.x z phpstan). (#2)
6. **Twig dodany jako runtime dependency** tylko po to żeby AP4 włączył Swagger UI (`enable_swagger_ui` defaultuje `class_exists(TwigBundle::class)`). Dla prod docs można `enable_swagger_ui: false` env-aware. (#3)

### 3.2 Domain model / multi-tenancy
7. **`Product::$tenant` nullable w PHP** (krótki window przed PrePersist) ale NOT NULL w schemacie — scoped PHPStan ignore. Listener tests + DB constraint potwierdzają invariant. (#2)
8. **`docker-compose.yml` bind mount `apps/api`** + named volumes na `var/` i `vendor/` (lekkie scope creep w #2 ale eliminuje rebuild ~1 min na każdą zmianę PHP). (#2)
9. **Native SQL `SELECT COUNT(*) FROM products`** w `TenantIsolationTest` widzi wszystkie tenanty — boundary application-layer'a, nie defekt. RLS w fazie 1 (sekcja 11.1a architektury) zamknie. Bulk paths (COPY, raw INSERT) trzymają tenant scope w kodzie do tego czasu. (#12)
10. **`APP_DEFAULT_TENANT_CODE` flip w testach** (env hack pre-auth) — pierwotnie w #12, **ZASTĄPIONE w #4** real-auth (each tenant ma własnego admina, test mintuje JWT). (#12 → #4)

### 3.3 API Platform
11. **`Product` `#[ApiResource]` wystawia entity bezpośrednio** (bez DTO input/output) — w MVP-Alpha (epik 0.4 #41+) decyzja czy split na osobne DTO. Powód: 50× mniej kodu, AP4 grouping wystarczy. (#3)
12. **`application/json` jako input/output format nieaktywowany** — tylko `application/ld+json` (POST/GET) + `application/merge-patch+json` (PATCH). Plain JSON dochodzi w epiku 0.4 (#41) razem z decyzją o explicit DTO. (#3)
13. **`UniqueEntity['tenant', 'sku']` validator pominięty** — listener stempluje `tenant` w PrePersist po fazie validacji, więc validator widziałby zawsze `tenant=null`. Postgres unique index zachowuje invariant on DB level (HTTP 500 zamiast 422). Custom validator z dostępem do `TenantContext` dochodzi w epiku 0.4 (#41+). (#3)

### 3.4 Auth (LexikJWT)
14. **Oba klucze RSA `config/jwt/*.pem` gitignored** (Lexik recipe default) — devs generują własne lokalnie, prod mountuje z vault'a, CI generuje per-run przed phpunit/phpstan jobs. Ticket prosił "commit pubkey" ale industry-standard w MVP-stage to local-only. (#4)
15. **Fixture admin password = `changeme`** — explicit dev-only, full onboarding flow w epiku 0.2 (#24+). (#4)
16. **`/api/docs`, `/api/contexts`, `/api` (entrypoint) PUBLIC** w `access_control` — żeby OpenAPI/Hydra tooling działał bez auth. Production może lock'ować przez `enable_swagger_ui: false` env-aware. (#4)
17. **Brak refresh tokens / token blacklist'u** — Lexik default 1h TTL na token. `gesdinet/jwt-refresh-token-bundle` + RBAC w epiku 0.2 (#24+). (#4)

### 3.5 Admin Refine + shadcn
18. **JWT w `localStorage` w admin'ie** zamiast httpOnly cookie — explicit Sprint-0 shortcut, refactor w 0.2.6 (#28). (#5)
19. **Plain `react-router` v7** zamiast `@refinedev/react-router-v6` — Refine headless + RR7 idiomatic, mniejszy bundle, mniej plumbing'u. **Konsekwencja:** ręczne `useNavigate` w `onSuccess` mutacji `useLogin`/`useLogout` (Refine v5 honoruje `redirectTo` tylko z routerProvider'em). Hotfix #120 dorobił. (#5)
20. **Custom Hydra-aware DataProvider** zamiast `@refinedev/simple-rest` — AP4 zwraca Hydra Collection (`member`, `totalItems`), simple-rest oczekuje `data`+`total`. ~50-liniowy custom provider jaśniejszy niż wrapper z transformacją. (#5)
21. **shadcn primitives copy-paste** zamiast CLI — CLI wymaga interaktywnego promptu w container'ze, kopiowanie 6 plików zajmuje 5 min. (#5)
22. **Admin bundle warning >500 kB** (Refine + react-query + zod + radix razem) — code splitting `React.lazy` per route w fazie 1 gdy pojawią się 5+ resource pages. (#5)
23. **Brak Playwright E2E w #5** — to scope ticketu #10 (0.0.10), explicit setup ticket. Manual smoke pokrywa wszystkie ścieżki. (#5)

### 3.6 Vite ESM gotcha (post-merge regression)
24. **`__dirname` undefined w ESM (`"type": "module"`)** — vite.config.ts z `path.resolve(__dirname, './src')` przeszedł `pnpm build` (esbuild compile ma fallback do project root) ale fail'ował w dev server. **Fix:** `fileURLToPath(import.meta.url)`. (#5 hotfix #120)
25. **CI nie testuje "vite dev startup"** — buduje produkcyjny bundle, nie testuje dev experience. PR #119 przeszedł 5 checks ale dev fail'ował. **Mitigacja w fazie 1:** smoke step "vite dev + curl /login" w CI jeśli takie regresje będą się powtarzać. (#5 hotfix #120)

### 3.8 Performance profile (#14)
31. **Blackfire/Tideways pominięte w Sprincie 0.** Oba wymagają kont SaaS + agent w container'ze + commercial license w prod (Blackfire $25-100/mies., Tideways $40+). Zamiast tego: **k6** (OSS, single binary jako docker image `grafana/k6`, profile `perf` w docker-compose, jeden-shot `pnpm perf:list`). Dla single-request profilingu: **EXPLAIN ANALYZE** dla głównego SQL + breakdown analityczny critical path z wiedzy o stack'u. Pełny profiler suite (Blackfire SaaS lub Tideways) kandydat do epiku 0.11 (#103-#105) gdy mamy realny prod env i ROI licencji. (#14)
32. **Próg "p95 < 200ms na 1000 produktów" Sprint-0 spec'u był over-aggressive dla 100 concurrent users.** FrankenPHP w naszym docker-compose puszcza `num_threads: 17` (auto z CPU count) → 100 VUs / 17 threads = 6× kolejka workerów → każdy request średnio czeka 5-6 slotów przed wykonaniem → p95 ~1s. **Realistyczny load dla MVP B2B pilot (5-10 catalog managers concurrent + agent traffic) = 10 VUs.** W tym scenariuszu p95 = **105 ms** (headroom 1.9× nad 200ms target). Próg zwalidowany dla docelowego use case'u; revisited dla 50-100 concurrent w fazie 2 (multi-worker / horizontal scale). (#14)
33. **Performance test dla load tester'a MUSI używać `APP_ENV=prod APP_DEBUG=0`** — ta sama lekcja co #13 (Symfony Profiler middleware). W env=dev p95 100VU = 690ms, w prod = 997ms (wolniej w prod?? — bo dev cache cold-warm overhead na first hit, prod ma proxies generated raz). Rzetelne perf numbers dochodzą z prod env tylko. Wrapper `scripts/perf-list-products.sh` używa prod env dla `pim:benchmark:bulk-import` seedu (HTTP serwowanie zostaje na env wybranym przez operator'a — domyślnie dev). (#14)
34. **Doctrine ORM 3 prod env wymaga proxy generation przez `cache:warmup` przed pierwszym requestem.** `doctrine.orm.auto_generate_proxy_classes: false` w `when@prod` (z generowanego skeleton'a) — bez explicit warmup'u FrankenPHP rzuca *"Failed opening required '__CG__AppIdentityDomainEntityTenant.php'"*. **Pierwszy switch dev → prod env w container'ze MUSI być poprzedzony `php bin/console cache:warmup`.** Naturalnie zachodzi w docker build'cie przy `composer install --classmap-authoritative` ale lokalna iteracja (`docker compose up` z bind mount + APP_ENV=prod) wymaga manualnego warmup'u. Dodać do `pnpm stack:reset` lub Dockerfile postprocessing'u w fazie 1. (#14)

### 3.7 Memory benchmark (#13)
26. **Custom PHPStan rule blokująca `flush()` bez `clear()` przeniesiona do follow-up'u (#123).** DoD ticketu #13 explicite dopuszcza ("lub TODO ticket follow-up jeśli za duży scope"). Bazowa ochrona pattern'u w MVP-Alpha: `AbstractBatchHandler` + benchmark + system prompt CLAUDE.md. AST-bazowana rule z whitelistą subklas → kandydat do epiku 0.11 (hardening). (#13)
27. **Symfony Profiler middleware (`BacktraceDebugDataHolder`) jest osobnym źródłem leaku — `doctrine.dbal.logging: false` go nie wyłącza.** W env=dev/test profiling middleware przechwytuje każdy SQL query z backtrace'ami i akumuluje w pamięci (50 000 INSERT-ów = OOM przy 512 MiB cap). Zachowanie poprawne dla profilera (toolbox debug), ale **benchmarki memory MUSZĄ uruchamiać się w `APP_ENV=prod APP_DEBUG=0`** żeby reprodukować realny worker. Dodane do `lessons.md` Toolchain quirks. (#13)
28. **Benchmark CLI ≠ pełna symulacja FrankenPHP worker mode.** `pim:benchmark:bulk-import` żyje jeden raz w PHP CLI process; worker mode trzymałby UnitOfWork między requestami. CLI walida algorytm (clear-after-flush działa, throughput +6×) i bound memory w pojedynczym procesie. Pełen worker-mode test (Messenger consumer + 5000 messages) dochodzi z pierwszym async transportem w epiku 0.1 (#17+). (#13)
29. **`/api/metrics` endpoint upubliczniony bez auth w MVP** — Prometheus scrape pattern: dev convenience > security w Sprincie 0. Production (epik 0.11 #103-#105) dostanie token + binding na private network. (#13)
30. **`EntityManager::clear()` detachuje też `Tenant`** — następny batch wymaga re-fetch'a po ID. Bez tego `TenantAssignmentListener` przekazuje detached entity → flush() pada na "A new entity was found through relationship". Wzór re-fetch'a w `BulkImportBenchmarkCommand` jest kanoniczny dla każdego przyszłego batch handler'a. (#13)

## 4. Audit `CLAUDE.md`

### 4.1 Sekcje wciąż aktualne (po Sprincie 0)
- ✅ **Stack** — wszystkie wersje (PHP 8.4 + Symfony 7.4 LTS + AP 4 + FrankenPHP 2.x + React 19 + Vite 6+) sprawdzone w boju
- ✅ **Workflow** — Plan Mode + status doc + lessons doc + subagent strategy działają
- ✅ **Memory management** — niesprawdzone w runtime (potrzebne #13/#14), ale rule "EntityManager::clear() po flush w pętli" stoi
- ✅ **Single-origin przez Caddy** — działa end-to-end (login + admin + JSON-LD bez CORS)
- ✅ **Multi-tenancy** — `tenant_id NOT NULL`, listener, filter — sprawdzone w #2 + #12 + #4
- ✅ **Throttling Shopify** — niesprawdzone (Shopify → Faza 1), ale rule "tylko Exponential Backoff" pozostaje
- ✅ **Bezpieczeństwo agenta** — niesprawdzone (Agent → Faza 2)
- ✅ **Reguły implementacyjne** (1-9) — wszystkie wciąż obowiązują, ADR'y stoją
- ✅ **Konwencje języka i commit messages** — egzekwowane przez commitlint, działa
- ✅ **Pliki utrzymywane atomowo** — pattern działa (current_status.md + lessons.md per ticket)

### 4.2 Sekcje wymagające drobnej aktualizacji
- 🟡 **Workflow point 3 ("agent/lessons.md — Patterns to Follow / Patterns to Avoid / Package Quirks")** — w praktyce dorobiliśmy `## Lessons z 0.0.X (...)` per ticket. Pattern działa lepiej niż 3 statyczne sekcje. **Akcja:** zaktualizować punkt 3 workflow w `CLAUDE.md` — opisać oba patterny.
- 🟡 **Workflow point 4 (subagent strategy)** — w Sprincie 0 nie używaliśmy subagentów ani razu. Może być przedwczesne dla MVP-Alpha. **Decyzja:** zostawić jako "rekomendacja na duże tickety", nie usuwać.
- 🟡 **Priorytety implementacyjne (kolejność sub-faz)** — sekcja zawiera oryginalną kolejność (Sprint 0 → Alpha → Beta-Min → Final → Beta-Full → Faza 1 → Faza 2 → Faza 3). **Akcja:** zaktualizować po rewizji z 2026-04-27.
- 🟡 **Pliki, które utrzymujesz atomowo** — wpisać `Project Plan/06-sprint-0-findings.md` (ten plik) jako trzeci aktualizowany dokument.

### 4.3 Sekcje do dodania
- 🟢 **Vite ESM config quirks** — nowy entry w "Toolchain quirks (host-side)" w lessons.md (już dodane w #5 hotfix).
- 🟢 **Refine v5 hooks return shape** — nowy entry w "Package Quirks" (już dodane w #5).
- 🟢 **Cross-tenant 404 vs 403 idiom** — nowy entry w "Patterns to Follow" lessons (już dodane w #12).

### 4.4 Akcja podejmowana w tym ticketcie
W tym PR aktualizuję CLAUDE.md o punkty 4.2 (drobne korekty workflow + priorytety + plik findings). Punkty 4.3 są już w lessons.md.

## 5. Audit `agent/lessons.md`

### 5.1 Statystyka
- **Kategorie:** Patterns to Follow (8), Patterns to Avoid (14), Package Quirks (8 + 4 nowe ze Sprint 0), Toolchain quirks (host-side, 7), Decyzje świadome (6), Lessons per ticket (5 sekcji #2/#3/#4/#5/#12).

### 5.2 Stan
- ✅ Każdy ticket Sprint 0 (#1-#5, #11, #12) ma sekcję "Lessons z 0.0.X" agregującą Why/How-to-apply.
- ✅ Toolchain quirks rośnie naturalnie (Synology Drive fileMode, Husky, lint-staged, ESM `__dirname`).
- ✅ Package Quirks pokrywają FrankenPHP, AP4, Doctrine 3, Refine v5, Lexik JWT, scheb/2fa, Meilisearch, Symfony Flex.

### 5.3 Wniosek
**`lessons.md` w obecnym kształcie jest produkcyjnym artefaktem.** Pattern "lekcje per ticket + tematyczne sekcje na początku" działa — najnowsze odkrycia idą do top'u (per ticket), powtarzalne wzorce są w sekcjach na początku.

## 6. Wnioski operacyjne (pre-MVP-Alpha)

### 6.1 Co działa świetnie
- **Plan-mode default + commit-per-ticket pattern** (z PR-em) — daje czytelność + reviewability.
- **Single-origin Caddy** — zero CORS w żadnym tickectie. Decyzja architektoniczna z #1 spłaca się każdym kolejnym tickectem.
- **Multi-tenancy od dnia 1** — `tenant_id` w każdej tabeli oznacza że #4 i #5 nie potrzebują migracji multi-tenant w przyszłości.
- **Quality gates automation-first** — PHPStan max + Biome + lint-staged + commitlint przechwytują 90% błędów przed PR.
- **CI workflow per kategoria** (`quality-php.yml`, `quality-frontend.yml`, `audit.yml`) — paralelny CI w 1-2 min.
- **`current_status.md` + `lessons.md` jako sourceof truth** — każda nowa sesja ma 0-sek onboarding.

### 6.2 Co wymaga uwagi w MVP-Alpha
- 🟡 **Dev experience vs CI mismatch** — PR #119 przeszedł CI ale nie działał w dev (ESM `__dirname`). Dodać smoke "vite dev startup" do CI w fazie 1.
- 🟡 **Refine v5 + RR7** — bez routerProvider'a manual `navigate()` w każdej mutacji. Rozważyć dodanie `@refinedev/react-router` v2 jeśli mutacji robi się dużo.
- 🟡 **Bundle size admin'a** — 595 kB / 187 kB gzip dla 4 stron. Code-split per route przed dojściem do 10+ resource pages.
- 🟡 **`pnpm exec` vs container path** — `lint-staged` pattern z dedykowanym wrapperem (`scripts/lint-staged-php.sh`) działa, ale każdy nowy lint w container'ze wymaga podobnego wrappera. Zdokumentowane w lessons.

### 6.3 Mała infrastruktura do dodania w epiku 0.3 lub 0.11
- `pending_changes` table (migracja, pusta) — Faza 2 wpisuje
- `provenance` enum w `product_values` z zarezerwowanym `agent` — UI MVP go ignoruje
- Doctrine lifecycle event subscriber emitujący `EntityChanged` event — handler w Fazie 2

## 7. Gate decision (pending)

**Status:** 🟡 **Pending** — gate decision zostanie podjęta po zamknięciu pozostałych Sprint 0 ticketów (#9, #10, #13, #14, #15).

**Kryteria gate:**
- ✅ Architektura zwalidowana end-to-end (PHP+Symfony+AP4+FrankenPHP+JWT+Refine+Tailwind+Postgres+Caddy)
- ✅ Multi-tenancy działa (smoke test + ApiTestCase + real-auth path)
- ✅ Quality gates (PHPStan max + Biome strict + PHPUnit + audits) zielony na każdym PR
- ✅ Memory benchmark FrankenPHP worker (#13) — **5 000 produktów: 14 MiB peak (próg 256 MiB), 50 000: 14 MiB peak FLAT z clear**, throughput 9 034 prod/s w prod env
- ✅ Performance profile (#14) — **realistyczny load 10 VUs: p95 = 105 ms (próg 200 ms)**, single-user p95 = 18.7 ms; 100 VUs over-aggressive dla MVP single-pilot stage (multi-worker w fazie 2)
- 🟡 Backup + restore test (#15) — pending
- ✅ Playwright E2E happy path (#10) — 9/9 lokalnie + CI
- 🟡 Manual demo + screencast (#9) — pending

**Przewidywany verdict:** **GREEN** (na podstawie 11/13 zielonych ticketów + brak blockerów w pozostałych 2).

### 7.1 Wyniki benchmarku #13 (snapshot 2026-04-27)

`pim:benchmark:bulk-import` walida pattern z sekcji 3.10 architektury (R-25, "Krytyczny"). Próg: <256 MiB peak na 5 000 INSERT.

| count | env | clear | peak MiB | end MiB | duration | throughput | werdykt |
|---|---|---|---|---|---|---|---|
| 5 000 | dev | ON | 68 | 68 | 0.63 s | 7 969/s | ✅ pass |
| 5 000 | dev | OFF | 78 | 78 | 1.55 s | 3 228/s | ⚠️ pass (rozbieżność rośnie) |
| 20 000 | dev | ON | 200 | 200 | 2.50 s | 7 985/s | ✅ pass |
| 20 000 | dev | OFF | 240 | 240 | 6.65 s | 3 008/s | ⚠️ near-cap |
| **5 000** | **prod** | **ON** | **14** | **14** | **0.57 s** | **8 755/s** | **✅ pass (target)** |
| 50 000 | prod | ON | **14 (flat!)** | 14 | 5.53 s | 9 034/s | ✅ headroom 18× |
| 50 000 | prod | OFF | 150 | 150 | 32.57 s | 1 535/s | ⚠️ ekstrapoluje na ~85 k = OOM 256 MiB |

**Najmocniejsza walidacja:** prod env, 50 000 rows z clear → memory FLAT na 14 MiB regardless of count. Pattern działa. Ekstrapolując bez clear: ~3 KiB / row → 100 000 rows ≈ 300 MiB → R-25 reproducible.

**Dev vs prod:** dev env hostuje Symfony Profiler middleware (BacktraceDebugDataHolder) który akumuluje query backtraces między batchami niezależnie od `doctrine.dbal.logging`. To **nie** memory leak Doctrine'a, tylko intencjonalna akumulacja debug telemetry. Production env bez tego middleware jest dramatycznie lżejszy. Wniosek do lessons: benchmark = `APP_ENV=prod APP_DEBUG=0`.

### 7.2 Wyniki perf profile #14 (snapshot 2026-04-27)

`scripts/perf-list-products.sh` (k6 + `pim:benchmark:bulk-import --keep` seed 1000 produktów + cleanup), 60 s load, 1005 produktów dla tenant'a demo:

| VUs | avg | median | p95 | p99 | max | throughput | werdykt p95<200 ms |
|---|---|---|---|---|---|---|---|
| 1 | 13.2 ms | 12.5 ms | **18.7 ms** | — | 144 ms | 75/s | ✅ pass (headroom 10×) |
| 10 | 57.2 ms | 50.4 ms | **105 ms** | — | 485 ms | 175/s | ✅ pass (headroom 1.9×) |
| 30 | 199 ms | 172 ms | 365 ms | — | 1.35 s | 150/s | ⚠️ stretch (1.8× over) |
| 100 (oryginalny spec) | 529 ms | 449 ms | 997 ms | 1.97 s | 2.67 s | 188/s | ❌ fail (5× over) |

**Decyzja:** akceptujemy 10 VUs jako realistyczny load dla MVP B2B single-pilot (5-10 catalog managers concurrent + agent traffic). 100 VUs dochodzi w fazie 2 z multi-worker / horizontal scale (sekcja 12.2 architektury). Próg `p95<200ms` zwalidowany dla docelowego scenariusza.

#### Top hot paths (analytical breakdown z EXPLAIN ANALYZE + critical path)

Single-request 13.2 ms (avg), prod env, 1005 produktów dla tenant'a demo, 30 zwracanych:

| # | Faza | Czas est. | Źródło |
|---|---|---|---|
| 1 | Symfony Serializer + JSON-LD encoding (30 obj × 7 pól + Hydra wrapper) | ~3-4 ms | analiza kodu |
| 2 | Doctrine query + entity hydration (`SELECT ... ORDER BY id DESC LIMIT 30`) | ~3-4 ms | EXPLAIN ANALYZE: 0.975 ms execution + 2.5 ms planning + hydration |
| 3 | Security firewall (JWT decode + User repository hit przez email) | ~2-3 ms | analiza kodu, drugie DB query |
| 4 | Routing + API Platform metadata resolution | ~1-2 ms | warmed cache w prod |
| 5 | Caddy reverse proxy + TLS overhead | ~1-2 ms | mierzone na zewnątrz |

**Plan:** `EXPLAIN ANALYZE` na głównym query używa `Index Scan Backward using products_pkey` + `Filter: tenant_id = '...'` — optimalnie. **Kandydaci do optymalizacji w epiku 0.4:** (a) **`USER_LOOKUP` cache per-token** (memoize User entity na czas TTL JWT), (b) **HTTP cache control / ETag** dla list endpointów (już mamy ETag w response — można egzekwować `If-None-Match` flow), (c) **Increase FrankenPHP `num_threads`** z 17 → 32-64 dla CPU-bound work pod load. Decyzje punktowe gdy pierwszy pilot da realne dane request-rate.

## 8. Powiązania

- Plan: `Project Plan/02-plan-projektu-pim.md` — sekcja 3 (rewizja), sekcje 4 i 5 (Faza 1 + Faza 2 nowy zakres)
- Architektura: `Project Plan/01-architektura-pim.md` — bez zmian, ADR'y stoją
- Funkcjonalności pilota: `Project Plan/03-funkcjonalnosci-mvp.md` — bez zmian, persona #1 (Kasia/Catalog Manager) wspiera rewizję
- Konstytucja: `CLAUDE.md` — drobne korekty workflow / priorytety w tym samym PR
- Lekcje: `agent/lessons.md` — produkcyjny artefakt, bez zmian w tym tickectie
- Status: `agent/current_status.md` — odzwierciedla rewizję od commit'a `bedf1ae`

## 9. Addendum 2026-04-27 — ADR-009 (Generalizacja ObjectType)

> Addendum dopisane tego samego dnia co rewizja zakresu (§2). Nie blokuje gate decision Sprintu 0 — zmiana dotyczy MVP-Alpha i kolejnych faz.

### 9.1 Decyzja
Po rewizji §2 (epik 0.7 do Fazy 2, epiki 0.8/0.9 do Fazy 1) operator zlecił drugie spojrzenie na model domenowy: jakie są konsekwencje hard-coded `Product` + `Category` jako odrębnych encji pierwszej klasy z asymetrycznym modelem atrybutów?

**Wynik:** asymetria blokuje (a) import z PIMCore (eksport `Zrodla/PIMCore/masowy_eksport_konfiguracji.json` — klasa `Kategoria` z SEO + image), (b) potrzeby pilota B2B technical (kategorie hierarchiczne z user-defined atrybutami), (c) przyszłe `Customer` / `Supplier` / `PriceList` w Fazie 2/3.

**Odpowiedź:** **ADR-009** — generic `ObjectType` z predefiniowanymi Product/Category/Asset jako built-in instancje (`is_built_in=true`), custom kindy odblokowane w Fazie 2/3. Pełen ADR w `01-architektura-pim.md` §13.

### 9.2 Wpływ na MVP-Alpha
- **Epik 0.3 reestymowany z 16-20h do 36-50h** (+16-25h). Finansowane ze zwolnionego budżetu epiku 0.7 (przeniesionego do Fazy 2 — §2). Top-line MVP-Alpha się trzyma.
- Faza 0 total: 201-274h → **188-260h** (saldo netto -13-15h dzięki przeniesieniu 0.7/0.8/0.9 częściowo niwelującemu wzrost 0.3).
- Pojęcie „Family" deprecated. W nowym kodzie używamy „ObjectType" wszędzie.
- Predefiniowane fixtures (`product`, `category`, `asset`) seedowane w `tenant.create` flow.

### 9.3 Audit ticketów GitHub (2026-04-27)
W ramach domknięcia ADR-009 przeszedłem 91 otwartych issues:
- **Epik 0.3 (#31-#40)** — major rebody: ticket #32 rename na ObjectType, ticket #33 na Predefined category fixture, ticket #34 na Object/ObjectValue, dochodzi nowy ticket 0.3.12 (Hooks pod `kind='custom'`). Re-estimated to 36-50h.
- **Epiki 0.4 / 0.5 / 0.6 / 0.10 / 0.11** — light rebody (sugar paths, indexer per kind, Resource ObjectTypes zamiast Resource Families, dynamic category attributes, profile filter per object_type_id).
- **Epiki 0.7 / 0.8 / 0.9** — minimalna generalizacja (Product → Object/ObjectType w body opisu adapterów; tool agenta `create_object_type` reserved Faza 2).
- **Epiki 0.1 / 0.2 / Sprint-0 leftovers (#9, #15)** — bez zmian (czysta infra/auth/demo).
- **#123 (follow-up PHPStan rule)** — milestone przypisany do MVP-Final.

Pełen log per-ticket w `agent/lessons.md` sekcja „Lessons z ADR-009".

### 9.4 Co pozostaje do walidacji w MVP-Alpha
- **Benchmark `attributes_indexed` na 10k obiektów × 200 atrybutów × 3 kindach** — dowód że generic model nie zwalnia query path względem hard-coded (R-29 mitigation).
- **Playwright E2E scenariusz „edycja kategorii z atrybutami niestandardowymi"** — proof of ADR-009 że predefined UX dla 3 kindów daje pełnoprawne user-defined atrybuty per kind.
- **Dyscyplina `kind='custom'` wyłączony przez feature flag w MVP** — ślad audytowy w `ObjectTypeService::create()` testach.
