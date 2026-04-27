# Lessons Learned

> Plik startowy zasiany twardymi wytycznymi z `Project Plan/01-architektura-pim.md`. Po każdej korekcie operatora lub odkrytym wzorcu (sukces ALBO porażka) — dopisz wpis. Czytaj przed każdą sesją.

## Patterns to Follow

### Memory management (FrankenPHP worker mode)

- **`AbstractBatchHandler` jako baza dla każdego Symfony Messenger handlera batch.** Po `flush()` w pętli — `$entityManager->clear()`. Bez tego worker w worker-mode w 50k SKU import zje cały RAM i zabije proces na OOM (ryzyko R-25, "Krytyczny" wpływ).
  - Why: Doctrine Identity Map akumuluje obiekty między requestami. `clear()` to single-line różnica między działającym sync 50k SKU a OOM.
  - How to apply: każdy nowy Messenger handler → albo dziedziczy z `AbstractBatchHandler`, albo PR review pyta "gdzie clear()".

- **Bulk import/export używa Doctrine `iterate()`** zamiast `findAll()`. `clear()` co N=200 rekordów. Plus `doctrine.dbal.logging: false` w prod — logger akumuluje query history w pamięci workera.

- **PHPStan custom rule blokuje `flush()` w pętli bez `clear()`.** CI gate, nie ludzkie review. Jeśli rule false-positive'uje — popraw rule, nie obejdź.

- **Prometheus alert `frankenphp_worker_memory_bytes > 256MB`** — wykrywa wycieki w runtime, nie czeka na OOM.

### Sieć / dev environment

- **Single-origin przez Caddy w FrankenPHP — TYLKO TAK.** `pim.localhost/api/*` → Symfony, `/.well-known/mercure` → Mercure, `/*` → `vite:5173`. Nigdy `localhost:5173` + `localhost:8000` osobno.
  - Why: dwa origins → CORS → Claude Code spędza godziny na konfigurowaniu `nelmio_cors`, naprawianiu Vite origin, znowu fail. Sekcja 3.10a architektury — świadomy wybór dla pętli pracy non-coder + LLM.
  - How to apply: jeśli widzisz error CORS — sprawdź Caddyfile, dodaj `handle_path /api/*` lub `reverse_proxy vite:5173`. Nie dodawaj `nelmio_cors`. Nie zmieniaj `--origin` w Vite.

- **HMR Vite działa przez WebSocket upgrade w Caddy.** Jedna linia w Caddyfile — Vite musi startować z `--host 0.0.0.0`.

- **Topologia dev = topologia prod.** Caddy ma tylko inną domenę (`pim.example.com` vs `pim.localhost`). Brak dryfu konfiguracji.

### Throttling integracji zewnętrznych

- **Shopify: TYLKO Exponential Backoff w MVP, nie Leaky Bucket.** Wyślij request → na 429/`THROTTLED` czytaj `Retry-After` (fallback `2^retry_count`s, max 60s) → `sleep` → retry. Max 5 prób → DLQ.
  - Why: Leaky Bucket z `extensions.cost.throttleStatus.currentlyAvailable` × shared state w Redis to klasa problemów na której LLM się zacina (race conditions, off-by-one). Backoff jest 5-liniowy, deterministyczny, samoreparujący się. Sekcja 7.3 architektury — świadoma redukcja złożoności, koszt sub-optymalności rate limitu = ~15-30 min więcej w nightly sync.
  - How to apply: `Integration\Shopify\GraphQLClient` ma metodę `sendWithBackoff()`. Wszystko z Shopify przez nią. `currentlyAvailable` zapisujemy do `sync_job_logs` **pasywnie**, nie sterujemy.

- **Punkt powrotu do Leaky Bucket (faza 1):** gdy `currentlyAvailable < 100` w >20% requestów (mierzone z sync_job_logs), albo full sync 50k SKU > 60 min, albo klient enterprise żąda <30 min full sync. Dopiero wtedy migracja na Bulk Operations API + Leaky Bucket.

### Multi-tenancy

- **`tenant_id UUID NOT NULL` w każdej tabeli domenowej od dnia 1.** Listener `TenantAssignmentListener` ustawia automatycznie na save. Filter `TenantFilter` dokleja `WHERE tenant_id = :current_tenant` do każdego query.
  - Why: koszt overheadu w MVP <1% perf, koszt dodania post-factum 40-60h + migracje danych. Asymetria zysków uzasadnia (ADR-003).
  - How to apply: każda nowa migracja dodająca tabelę domenową → `tenant_id UUID NOT NULL REFERENCES tenants(id)` + index na `(tenant_id, ...)`. Bez wyjątków.

- **RLS aktywujemy DOPIERO przed multi-tenant w fazie 2** (sekcja 11.1a, plan 16-24h). W MVP single-tenant deployment to pierwsza linia obrony, RLS to defence in depth — niepotrzebna gdy 1 tenant.

- **W Sprincie 0 obowiązkowy smoke-test izolacji** (ticket 0.0.12): 2 tenanty, próba cross-read = 0 wyników. To walidacja Doctrine filter, nie RLS.

- **`COPY` (bulk insert/export) ignoruje RLS.** Gdy włączymy RLS w fazie 1 — wyłączać przed `COPY` (jako superuser), włączać po. Albo używać `INSERT ... SELECT`.

### Definicja "Done" — automation-first

- **Bez Playwright E2E test ticket NIE jest done.** Każda widoczna user-facing zmiana dostaje E2E test razem z kodem. Operator (non-coder) nie udaje code review LLM-kodu — automatyzacja jest jedyną realną warstwą walidacji.
  - Why: Gemini point z review — review LLM-generated kodu przez non-codera to fikcja, która uśpi czujność. Jedyne co działa: PHPStan max + ApiTestCase + Playwright + manual smoke 5 min.
  - How to apply: nowy ticket → najpierw szkic Playwright test scenariusza → potem implementacja → potem reszta gate'ów.

- **Stack testowy = TYLKO 2 narzędzia: PHPUnit + Playwright.** Nie używaj Pest (drugi runner = niepotrzebny config), nie używaj Behat (`ApiTestCase` z API Platform pokrywa 100% przypadków integracyjnych z lepszym lock-inem do framework'u). Sekcja 2.2 planu — świadomy minimalizm.

### Bezpieczeństwo agenta

- **Twarde limity z sekcji 8.5 architektury są nienegocjowalne.** 50 tool calls/h/user, 10/agent_run, 100k tokens/run, 500k/dzień/user, $20/dzień/tenant, $300/mies./tenant. Po 100% — agent wyłączony do północy UTC.

- **Org-level monthly cap w Anthropic Console = $1000 dla MVP-prod** — niezależny od logiki aplikacyjnej hardstop. Klucze osobne per environment (dev/staging/prod), rotacja co 90 dni.

- **BYOK dla enterprise** (ticket 0.11.12). Klient enterprise podaje własny Anthropic key, szyfrowany AES-256-GCM. Mitiguje R-27 (kompromitacja klucza platformy → faktura $1000-10000).

- **Anomaly detection:** wzrost tool calls/h o >5× względem 7-dniowej średniej → flag dla security review. Sygnał wycieku klucza lub abuse.

### Domain modeling

- **Hybrid model atrybutów: `attributes` + `product_values (value JSONB)` + denormalizowany `products.attributes_indexed JSONB` z GIN.** Dla single-edit synchroniczny listener, dla bulk path async worker `attributes-indexed-rebuild` z `EntityManager::clear()` co 1000.
  - Why: czysty EAV jest okropny dla performance cross-attribute queries. Czysty JSONB traci scope/locale info. Hybrid daje czytelność + perf (ADR-006).
  - How to apply: bulk handler **wyłącza** synchroniczny listener przez `BulkContext::isBulk()` — synchroniczny listener × 50k SKU = killer. Po batchu publikujemy `ProductValuesChanged(productIds: [...])` na kolejkę.

- **`provenance` pole w `product_values` obowiązkowe:** `manual | import | agent | integration` + meta JSONB. UI pokazuje provenance badges. Bez tego nie wiemy kto/co zmieniło wartość.

- **Generowane kolumny dla najczęściej używanych atrybutów** (Postgres `GENERATED ALWAYS AS` z JSONB) — np. `name_pl`, `sku`. Pozwalają na BTree index, szybsze niż GIN dla equality queries.

### Strings i konfiguracja

- **Wszystkie user-facing stringi w admin przez `t()` (react-i18next).** Żadnych literałów polskich/angielskich w komponentach React. Wszystkie label/help atrybutów jako JSONB `{"pl": ..., "en": ...}` w bazie.

- **URL-e zewnętrznych API w `AppConstants` / `services.yaml`.** Żadnych literałów `https://api.shopify.com/...` w handlerach. Klucze API z env vars / Vault, nigdy w kodzie.

- **OpenAPI generuje TS types przez build step** (`openapi-typescript` z `/api/docs.json` → `packages/shared-types/`). Frontend nie pisze ręcznie typów request/response — eliminuje dryf backend↔frontend.

## Patterns to Avoid

- **`flush()` w pętli bez `clear()`** w worker-mode → OOM gwarantowany.
- **`Color(0xFF...)` / hardkodowany hex w komponentach React** → utrudnia theming i dark mode (jeśli dodamy w fazie 3). Wszystko przez Tailwind tokens / shadcn variants.
- **`Navigator.push` / własne routery z państwem nawigacji** → łamią deep linking i refresh. React Router 7 wszędzie.
- **`localhost:5173` osobno + `api.localhost:8000`** → CORS hell. Single-origin przez Caddy.
- **Leaky Bucket dla Shopify w MVP** → zacinanie LLM. Backoff wystarczy.
- **Mock w testach integracji uderzających w bazę** → testy mijają, prod-migracja faila. Real Postgres przez testcontainers / docker-compose test.
- **`Bulk Operations API` Shopify w MVP** → +6-8h implementacji + 3-4× trudniejszy debug. Faza 1 jak benchmarks pokażą.
- **Pest / Behat** → drugie narzędzie testowe = niepotrzebny config, kontekst, CI step. PHPUnit + Playwright wystarczy.
- **`Material UI` zamiast shadcn** → custom UX patterny dla agenta walczą z framework'iem. shadcn = lokalny ownership komponentów.
- **Custom REST kontrolery** dla rzeczy, które API Platform potrafi → 5-10× więcej kodu i utrzymania niż dodanie `#[ApiResource]`.
- **`StateNotifier` / `StateProvider`** (przykład z innego projektu) → tu nieaplikowalne, używamy React `useState` + Refine hooks + Zustand jeśli potrzeba global state.
- **Hive / inne lokalne persystencje na frontend** → admin jest online-only, nie potrzebujemy offline cache w MVP.
- **Foldery zaczynające się od kropki** (`.agent/`, `.cache/`) w katalogach synchronizowanych przez Synology Drive / iCloud → mogą być cicho filtrowane przez sync provider. Używaj nazw bez kropki (`agent/`).
- **Estymaty godzinowe w GitHub Issues / labelach / treści ticketów** → nie mają sensu w pracy operator + LLM. Pomijaj `est: S/M/L/XL`, pomijaj liczby godzin w body issue. Plan i architektura zachowują estymaty jako orientacja kosztu fazy, ale na poziomie pojedynczego ticketu są szumem. (Decyzja operatora 2026-04-26 przy rozpisywaniu MVP backloga.)

## Package Quirks

- **FrankenPHP 2.x worker API ≠ 1.x** — od dnia 1 piszemy zgodnie z 2.x, test w Sprint 0 (sekcja 3.10 architektury).
- **API Platform 4** — konwencje filtrów, paginacji, serializacji przez grupy trzeba znać. Trochę "magic" — debug wymaga znajomości framework'u (ADR-008).
- **Refine 5+ z React 19** — sprawdź release notes przy major bump (build_runner-equivalent dla TS to nie ma, ale OpenAPI types regeneracja).
- **Shopify Metafields** — limit 200/produkt, 10MB/value, namespace+key max 64 znaki. Adapter waliduje przed wysłaniem (ticket 0.9.3).
- **Shopify variant cap 100/produkt.** Dla SKU z >100 wariantami split na osobne produkty z wskazaniem na siebie.
- **Mercure hub i MinIO server na AGPL v3** → osobne demony, nie linkowane do kodu app → bezpieczne dla white-label. Nie używaj jako library.
- **Doctrine 3.x + Symfony 7.4** — drobne breaking changes względem 2.x w lifecycle events. Sprawdź `EventSubscriberInterface` patterns przy każdej migracji listener'a.
- **`scheb/2fa-bundle`** — wymaga wpięcia w security firewall **przed** głównym authenticator'em, kolejność w `security.yaml` ma znaczenie.
- **Meilisearch** — facetable attributes muszą być zadeklarowane explicitly w settings indeksu, inaczej facets zwracają empty bez błędu (cicha pułapka). Healthcheck w docker-compose: użyj `curl http://localhost:7700/health`, nie `wget` (image v1.13 ma wgeta ale nie łączy się przez `localhost`, prawdopodobnie IPv6 dual-stack mismatch).
- **`api-platform/api-platform` na Packagist to archiwalny skeleton z 2018** (Symfony 3.4, Behat, nelmio/cors-bundle). Dla nowych projektów użyj `composer create-project symfony/skeleton apps/api 7.4.*` + `composer require api-platform/symfony:^4 api-platform/doctrine-orm:^4`. (Odkryte w 0.0.1.)
- **API Platform 4 nie obsługuje formatu `json` na `/api/docs`** — dostępne są `.jsonld` (Hydra), `.html` (Swagger UI). Dla healthchecków używaj `/api` (entrypoint, zawsze 200 z JSON-LD). (Odkryte w 0.0.1.)
- **Symfony Flex `composer require` z mieszanymi constraintami `^7.4` + recipes** — czasem wpisuje `^8.0` w composer.json gdy najnowszy stable tag to 8.x, ale lock fixuje 7.4.x → conflict przy następnym `composer remove`. Bezpieczniejszy bootstrap: ręcznie spisany `composer.json` z `7.4.*` na wszystkich `symfony/*`, potem `composer install`. (Odkryte w 0.0.1.)

## Toolchain quirks (host-side)

- **pnpm via `npm install -g pnpm@latest`**, nie corepack — Homebrew-installed Node 25 nie ma corepack jako shim. Corepack jest w `node_modules/.bin/corepack` ale nie w PATH globally bez `corepack enable`. Najprostsze: `npm install -g pnpm@latest`.
- **`pim.localhost` rozwiązuje się natywnie na macOS** (RFC 6761 + mDNSResponder dla `*.localhost`) — `/etc/hosts` jest niepotrzebny. Inne systemy mogą wymagać manualnego wpisu `127.0.0.1 pim.localhost`. (Odkryte w 0.0.1.)
- **Docker Desktop / OrbStack daemon musi być uruchomiony przed bootstrap'em** — `composer create-project` przez Docker, `docker compose build`, `docker compose up` wszystkie wymagają running daemon. Operator pamięta o uruchomieniu Docker'a przed sesją.
- **`git config core.fileMode = false`** musi być ustawione lokalnie. Synology Drive sync zmienia file mode bits 644→755 na niektórych plikach (docs, configs) między sync — bez tego każdy commit miałby fałszywe mode changes na CLAUDE.md, Project Plan/*.md, .github/ISSUE_TEMPLATE/*. Hooki + skrypty wymagające +x rejestruj przez `git update-index --chmod=+x <plik>` (zachowuje exec bit w git index niezależnie od fileMode setting). (Odkryte w 0.0.11.)
- **Husky pre-commit hooks i `pnpm exec`** — narzędzia wymagane przez pre-commit muszą być w **root** `node_modules` (nie tylko w workspace). Przykład: Biome zainstalowany tylko w `apps/admin` powoduje fail `pnpm exec biome` z root contextu. Dodaj do root devDeps. (Odkryte w 0.0.11.)
- **lint-staged + Docker exec** — lint-staged przekazuje **host paths** jako argumenty, ale `docker compose exec api` widzi container paths (`/app/...`). Skrypt wrapper musi ignorować argumenty i polegać na config-bundled Finder (np. PHP-CS-Fixer ma `Finder::in([...])` w `.php-cs-fixer.dist.php`). Wzór: `scripts/lint-staged-php.sh` w repo. (Odkryte w 0.0.11.)
- **vimeo/psalm:dev-master ma circular conflict z psalm/psalm-plugin-api 0.1.0** — plugin requires `vimeo/psalm <7`, ale dev-master to 7.x. W MVP używamy PHPStan max + strict-rules zamiast Psalm — pokrycie równoważne dla typowych use cases. Jeśli Psalm potrzebny w fazie 1, pinować do `^5.x` stable. (Odkryte w 0.0.11.)
- **PHP-CS-Fixer rule `@PHP84Migration:risky`** nie istnieje (tylko `@PHP84Migration` non-risky). Dla risky PHP 8.4 features używaj `@PHP82Migration:risky` lub `@PHP83Migration:risky` (najnowszy risky preset). (Odkryte w 0.0.11.)
- **PHPStan max + cast `mixed → string`** wymaga assertion (`assert(is_string($x))`) lub guard (`if (!is_string($x)) throw ...`). Sam `(string) $mixed` failuje na `cast.string` rule. Symfony bootstrap (`public/index.php`) typowo dotknięty. (Odkryte w 0.0.11.)
- **API Platform 4 docs endpoint** — `/api/docs.json` zwraca 404 (nie supported format). Dostępne: `.jsonld` (Hydra), `.html` (Swagger UI). Healthchecki używaj `/api` (entrypoint, zawsze 200 z JSON-LD). (Odkryte w 0.0.1, ponownie zweryfikowane w 0.0.11.)

## Decyzje świadome (do nieprzepisywania bez przyczyny)

- **PHP/Symfony zamiast Node/TS-fullstack** → branżowa zgodność PIM (Akeneo, PIMcore, Ergonode), Doctrine = najmocniejszy ORM dla DDD (ADR-001).
- **Refine + shadcn + osobny frontend zamiast EasyAdmin/Twig** → agentic-first UX (Cmd+K, streaming, schema diff) niemożliwy w server-rendered (ADR-005). Akceptujemy 2 języki + 2 apps = monorepo Turborepo, OpenAPI-generated TS types.
- **Meilisearch zamiast Elasticsearch** → 10× prostszy operacyjnie, MIT, wystarczy do 200k SKU. ES dochodzi w fazie 2 jeśli analytics tego wymagają (ADR-004).
- **PostgreSQL JSONB+ltree zamiast czystego EAV lub czystego JSONB** → hybrid, czytelność + perf z denormalizacją (ADR-006).
- **Multi-tenant ready, single-tenant deployed** → koszt 2-3h vs 40-60h post-factum (ADR-003).
- **Agent wbudowany w MVP, mikroserwis w fazie 2** → priorytet prostoty deploymentu (ADR-007).

## Lessons z 0.0.2 (multi-tenancy + dev workflow)

- **PHPUnit 11 vs `sebastian/diff` 8** — PHPUnit 11.x wymaga `sebastian/diff ^6` ale phpstan ekosystem fixuje 8.x w lock'u. Dla nowych projektów używaj **PHPUnit 12** od razu. (#2)
- **Doctrine ORM 3 + property nullability vs schema NOT NULL** — gdy property assignuje listener (PrePersist), PHP-side property musi być nullable (`?Type`) ale kolumna może być NOT NULL. PHPStan-doctrine wykrywa jako `doctrine.associationType` mismatch — dodaj scoped `ignoreErrors`. Listener tests + DB constraint zapewniają faktyczny invariant. (#2)
- **`#[AsAlias]` na konkretnej klasie bez interfejsu** — Symfony 7.x kontener wymaga że `#[AsAlias]` jest na klasie z interface. Dla services tylko concrete (np. `TenantFilterConfigurator`) pomijaj attribute — autowire/autoconfigure działa przez `App\: '../src/'` resource match. (#2)
- **Doctrine SQL filtry inicjalizują się leniwie** — Nie próbuj wczytywać security context w `SQLFilter::addFilterConstraint()`. W tym momencie firewall może jeszcze nie działać (CLI, fixtures, early boot). Wzór: mutable `TenantContext` service + osobna konfiguracja parametrów filtra przez `EntityManager::getFilters()->enable()->setParameter()`. (#2)
- **Mutable `TenantContext` service zamiast direct security access** — Doctrine filtry, fixtures, testy, CLI commands wszystkie potrzebują tenanta ale nie wszystkie mają security token. Context jest pchany do filtra i listener'a explicit, nie pulled z security przy SQL-build time. (#2)
- **`TenantAssignmentListener` rzuca LogicException przy braku contextu** zamiast pozwolić DB odrzucić INSERT z NOT NULL constraint violation. Czytelny komunikat dla operatora zamiast cryptic Postgres error. (#2)
- **Fixtures multi-tenant pattern** — pierwsza pętla persistuje wszystkie tenanty (jednym `flush()`), potem druga pętla per tenant: `tenantContext->set($tenant)` + persist produktów + `flush()`. Bez tego pattern'u listener stempluje wszystkie produkty do pierwszego tenanta. (#2)
- **Bind mount apps/api do container'a + named volumes na `var/` i `vendor/`** — bez tego każda zmiana PHP wymaga `docker compose build api` (~1 min). Z bind mount worker FrankenPHP automatycznie reloaduje. Vendor pozostaje w named volume żeby `composer require` na host nie kolidował z container'em. (#2)
- **Reset bazy danych** wymaga zatrzymania `api` container'a — FrankenPHP worker keeps connection open, blokuje `DROP DATABASE`. Sequence: `docker compose stop api && psql DROP/CREATE && docker compose start api && migrate`. (#2)
- **Postgres user/database name** — czytaj z `.env` (POSTGRES_USER, POSTGRES_DB), nie hardkoduj `app`. Symfony skeleton domyślnie używa `app/app/!ChangeMe!`, my mamy `pim/pim/ChangeMeInDev`. (#2)
