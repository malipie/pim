# Lessons Learned

> Plik startowy zasiany twardymi wytycznymi z `Project Plan/01-architektura-pim.md`. Po każdej korekcie operatora lub odkrytym wzorcu (sukces ALBO porażka) — dopisz wpis. Czytaj przed każdą sesją.

## Patterns to Follow

### Memory management (FrankenPHP worker mode)

- **`AbstractBatchHandler` jako baza dla każdego Symfony Messenger handlera batch.** Po `flush()` w pętli — `$entityManager->clear()`. Bez tego worker w worker-mode w 50k SKU import zje cały RAM i zabije proces na OOM (ryzyko R-25, "Krytyczny" wpływ). **Zwalidowane w #13:** prod env, 50 000 inserts → 14 MiB peak FLAT z clear, 150 MiB rosnąco bez clear. Class: `App\Messaging\AbstractBatchHandler` (`flushAndClear()` + `shouldFlush(int)`).
  - Why: Doctrine Identity Map akumuluje obiekty między requestami. `clear()` to single-line różnica między działającym sync 50k SKU a OOM.
  - How to apply: każdy nowy Messenger handler → albo dziedziczy z `AbstractBatchHandler`, albo PR review pyta "gdzie clear()".

- **Bulk import/export używa Doctrine `Query::toIterable()`** zamiast `findAll()`. `clear()` co N=200 rekordów. Plus `doctrine.dbal.logging: false` w prod — logger akumuluje query history w pamięci workera. (Doctrine ORM 3 zastąpiło stary `iterate()` przez `toIterable()`; API w benchmarku #13 demonstruje wzór.)

- **Po `clear()` zawsze re-fetch'uj `Tenant`** — `clear()` detachuje wszystkie entitki i `TenantAssignmentListener` przekazałby detached referencję do nowego `Product` → flush() pada. Pattern: `$tenantId = $tenant->getId();` przed pętlą, `$tenant = $repo->find($tenantId);` + `$tenantContext->set($tenant);` po każdym clear. Zwalidowane w #13.

- **Benchmarki memory MUSZĄ działać w `APP_ENV=prod APP_DEBUG=0`.** Dev env hostuje Symfony Profiler middleware (`BacktraceDebugDataHolder`) który akumuluje query backtraces niezależnie od `doctrine.dbal.logging: false` flag. W dev env nawet pattern z clear() OOM-uje na 50 000 INSERT pod 512 MiB cap. Production env bez profilera = 14 MiB peak FLAT. (#13)

- **PHPStan custom rule blokuje `flush()` w pętli bez `clear()`.** CI gate, nie ludzkie review. Jeśli rule false-positive'uje — popraw rule, nie obejdź. **Status MVP:** odłożone do follow-up #123 (kandydat do epiku 0.11). Bazowa ochrona w MVP-Alpha: `AbstractBatchHandler` + benchmark + system prompt CLAUDE.md.

- **Prometheus alert `frankenphp_worker_memory_bytes > 256MB`** — wykrywa wycieki w runtime, nie czeka na OOM. **Endpoint w MVP:** `GET /api/metrics` (text/plain Prometheus 0.0.4) wystawia `frankenphp_worker_memory_bytes`, `frankenphp_worker_peak_memory_bytes`, `frankenphp_worker_pid`. Unauthenticated w MVP (dev convenience); production hardening (token + private network) w epiku 0.11 #103-#105.

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
- **`archive-async=y` + interaktywne pgbackrest commands** w jednym container'ze → lock contention na `/tmp/pgbackrest/pim-archive-N.lock`. Sync archive_command (`archive-async=n`) jest fine dla MVP write rate. Async wraca w 0.11.11 z dedicated cron stanza-create cycle.
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
- **pgBackRest 2.57 nie supportuje plain HTTP dla S3 repos.** `repo-storage-port` defaultuje na 443, brak opcji wymuszenia HTTP. `repo1-storage-verify-tls=n` wyłącza tylko cert verify, nie sam TLS. Workaround: TLS terminator (Caddy `tls internal`) między pgBackRest a HTTP-only S3 endpoint'em (np. MinIO w dev). Production używa MinIO native TLS lub real S3 z prawdziwymi certami. (Odkryte w 0.0.15.)
- **AWS SigV4 binds Host header w podpisie request'u.** Każdy reverse proxy między klientem S3 a endpoint'em MUSI propagować original Host header (`header_up Host {host}` w Caddy, `proxy_set_header Host $host` w nginx). Default Caddy reverse_proxy rewrituje Host na upstream → MinIO odpowiada `SignatureDoesNotMatch` HTTP 403. Bezpieczne tylko z `repo1-s3-uri-style=path`. (Odkryte w 0.0.15.)
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

## Lessons z 0.0.3 (ApiResource Product + ApiTestCase)

- **Per-operation `denormalizationContext` to clean way to make a field immutable po POST.** `Patch` operation z grupą `product:patch` nie zawierającą `sku` powoduje że PATCH z `sku` w body jest cicho zignorowany (no setter, group out of scope). Czystsze niż `setSku()` który by sie wywołał ale rzucił. UI/dokumentacja ma się odbijać tylko od grup. (#3)
  - Why: PIM convention — SKU to identyfikator businesowy, nie zmienia się po creation. Domain-level invariant kodyfikowany w warstwie API.
  - How to apply: każde pole które po PATCH ma być immutable (np. `tenant`, `createdAt`, kandydat: `family`) trzymaj poza `*:patch` grupą. Dodatkowy setter NIE-tworzy.

- **Cursor pagination w API Platform 4 wymaga 3 elementów razem:** `paginationType: 'cursor'` w operation + `paginationViaCursor: [['field' => ..., 'direction' => ...]]` + `OrderFilter` + `RangeFilter` na tym samym polu. Bez `RangeFilter` `id[lt]=...` nie działa. Bez `OrderFilter` rekordy nie są stabilnie zwracane. (#3)
  - Why: docs API Platform mówią o tym tylko mimochodem; bez wszystkich trzech filter dostajesz `Collection` bez `view.next/previous` i klient nie wie jak iterować.
  - How to apply: każdy resource z `paginationType: 'cursor'` MUSI mieć `#[ApiFilter(OrderFilter::class, properties: ['id' => 'DESC'])]` + `#[ApiFilter(RangeFilter::class, properties: ['id'])]`. Tworzymy custom PHPStan rule w fazie 1 jeśli będzie dryf.

- **API Platform 4 wymaga `application/ld+json` Content-Type domyślnie** — plain `application/json` daje 415 Unsupported Media Type. PATCH wymaga `application/merge-patch+json` (RFC 7396). BrowserKit Client `'json' => $payload` shortcut ustawia `Content-Type: application/json` co fail'uje. W ApiTestCase używaj `'headers' => ['content-type' => 'application/ld+json']` + `'body' => json_encode(...)`. (#3)
  - Why: AP4 default `formats: ['jsonld' => ['mime_types' => ['application/ld+json']]]`, plain JSON nie jest w `formats`. Można dodać `application/json` do `formats` w `api_platform.yaml` ale to expanduje API surface — decyzja na epik 0.4.

- **Dla testów PostgreSQL z dbname_suffix `_test`, Foundry's `ResetDatabase` rebuilds schema z entity metadata przez `SchemaTool`, NIE przez migrations.** Działa pod warunkiem że entity attrybuty (Doctrine) odpowiadają migracjom 1:1. Jeśli kiedyś migracja będzie zawierała custom DDL (np. Postgres RLS w fazie 1) trzeba switch'ować Foundry config na `ResetDatabaseMode::MIGRATE`. (#3)
  - Why: `ResetDatabaseMode::SCHEMA` jest 5-10× szybsze niż MIGRATE; dla MVP to default.

- **`failOnDeprecation="true"` + AP 4.1 deprecation `alwaysBootKernel`** — `ApiTestCase` w 4.1 oczekuje że klasa testowa zadeklaruje explicite `protected static ?bool $alwaysBootKernel = true;` (lub false) zanim AP 5.0 zmieni domyślne zachowanie. Bez tej deklaracji każdy test fail'uje z deprecation. Wzór do każdego nowego ApiTestCase. (#3)

- **`docker compose exec -T -e APP_ENV=test api ...`** — runtime override APP_ENV jest potrzebny dla testów PHPUnit w container'ze, bo container ma `APP_ENV=dev` z docker-compose env, a phpunit.dist.xml `<server name="APP_ENV" value="test" force="true">` ustawia tylko `$_SERVER` które Dotenv nadpisuje aktualnym env. (#3)

- **Twig bundle install jest jedynym sposobem żeby Swagger UI renderował się w AP 4.** `enable_swagger_ui` defaultuje na `class_exists(TwigBundle::class)` — bez Twig dostajesz `404 Swagger UI is disabled`. Twig waży ~1 MB; OK trade-off za auto-renderowane docs dev/staging. Dla prod opcjonalnie `enable_swagger_ui: false`. (#3)

- **Mutable kontekst (`TenantContext`) musi być explicite ustawiony dla bezpiecznego seed'u w testach** — w `setUp` po `setKernelClass`/`getContainer` wywołać `tenantContext->set($tenant)` przed `$em->persist($product)`. Listener pulluje z mutable holder, nie z security tokenu. Test wymaga seedowania bez auth, więc env-fallback nie wystarczy (subscriber tylko na HTTP request). (#3)

- **API Platform 4 OpenAPI request body example** — `new Post(openapi: new \ApiPlatform\OpenApi\Model\Operation(requestBody: new \ApiPlatform\OpenApi\Model\RequestBody(content: new ArrayObject([...]))))` — dosyć wielo-warstwowo, ale działa. Dla MVP wystarczy 1-2 example'y na resource. Dokumentacja AP4 jest minimalna w tym obszarze; wzór sourceujemy z `vendor/api-platform/openapi/Model/RequestBody.php`. (#3)

## Lessons z 0.0.12 (multi-tenant isolation smoke test)

- **Cross-tenant access zwraca 404, NIGDY 403.** `TenantFilter` ukrywa istnienie rekordu w innym tenancie; 403 byłoby side-channel leak'iem ("widzę że istnieje, ale nie wolno mi"). Idiom egzekwowany w testach (`fetchingTenantBProductAsTenantAReturns404`, `patchingTenantBProductAsTenantAReturns404`). (#12)
  - Why: każde 403 dla cross-tenant = oracle który leak'uje SKU/ID z innego tenanta. Standard branżowy (Shopify, Stripe).
  - How to apply: `Patch`/`Put`/`Delete` operacje też muszą zwracać 404 (nie 403/422) gdy filter nie znajduje rekordu. To naturalne behavior `ReadProvider` w AP4 — nie trzeba custom code'u, ale weryfikuj w każdym nowym ApiTestCase.

- **Native SQL bypassa Doctrine `TenantFilter` z designu** — `TenantFilter` to application-layer boundary, NIE security boundary. Bulk operations (raw INSERT/SELECT przez DBAL `Connection`, COPY) widzą wszystkie tenanty. RLS w fazie 1 (sekcja 11.1a architektury) zamknie. Bulk paths trzymają tenant scope w kodzie do tego czasu. (#12)
  - How to apply: każdy nowy serwis który używa `Connection->executeQuery()` zamiast EM/QueryBuilder MUSI explicite dodać `WHERE tenant_id = :tenant`. Custom PHPStan rule kandydat na fazę 1.

- **`Product::assignTenant()` BEZPOŚREDNIO w setUp testowym to OK pattern dla seedowania bez `TenantContext`.** Listener `TenantAssignmentListener` no-opuje gdy entity ma już tenant przypisany (`null !== $entity->getTenant()`). Daje czyste seed'owanie wielo-tenantowych fixtures bez dance'u przez kontekst. (#12)
  - Why: TenantContext + listener jest dobry dla request-time persist'ów (auth-driven), ale dla seed'u wielu tenantów po kolei jest niewygodny. Direct `assignTenant()` jest jawny i nie zależy od container state.
  - How to apply: zarezerwowane do `@internal` use case'ów — w produkcyjnym kodzie zawsze przez listener. W testach setup-only.

- **Pre-auth tenant flip w testach: `$_ENV` + `$_SERVER` + `putenv` + `static::ensureKernelShutdown()`** — wszystkie trzy mechanizmy ustawiają env, bo Symfony `EnvVarProcessor` może odczytać przez którykolwiek (`$_SERVER` ma priorytet ale `getenv()` jest fallbackiem dla niektórych ścieżek). `ensureKernelShutdown()` po seedzie kasuje cache w booted kernelu — następny `createClient()` build'uje świeży kontener z nową wartością parametru `app.default_tenant_code`. (#12 — **zastąpione w #4 przez JWT-mintowanie per user**)
  - Why: `%env(...)%` placeholders są resolvowane przy każdym booting'u kontenera, ale single kernel instance cache'uje wartość. Bez shutdown'u test #2 widziałby wartość z test #1.
  - How to apply: po #4 wzorzec to `JWTTokenManagerInterface->create($user)` + `Authorization: Bearer ...` — environment-agnostic, single boot kernela, wielokrotnie szybsze.

## Lessons z 0.0.4 (LexikJWT auth + multi-tenant principal)

- **Mint JWT w teście via `JWTTokenManagerInterface->create($user)` + `Authorization: Bearer ...` zamiast HTTP login flow.** Nie potrzebujesz `/api/auth/login` request'u w każdym ApiTestCase — bezpośrednio z DI containera, single kernel boot, deterministycznie. Login flow i tak weryfikujesz jednym dedykowanym `AuthApiTest`. (#4)
  - Why: HTTP login dodaje 1 request per test (~50-100ms), a JWT manager jest zwykłym serwisem. ApiTestCase z 6 testami → 600ms oszczędności.
  - How to apply: każdy nowy ApiTestCase z auth → helper `authenticatedClient()` który mintuje token raz i ustawia default header'y na `Client::setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$token]])`.

- **`User` z `TenantAware` "darmowo" zwalnia `CurrentTenantProvider`'a od env-fallback'u dla autentykowanych requestów.** `CurrentTenantProvider->getCurrent()` ma trójkę: `$user instanceof TenantAware` → user's tenant; else env code; else null. Po wprowadzeniu auth (#4) prawie zawsze trafia w pierwszy branch — env-fallback to teraz tylko CLI commands i fixtures. (#4)
  - Why: Ten kawałek kodu pisaliśmy w #2 dla "future auth"; w #4 sprawdziło się bez modyfikacji.
  - How to apply: Każdy nowy "principal" (np. service user dla integracji w epiku 0.8/0.9) musi implementować `TenantAware` żeby filtr działał automatycznie.

- **`#[ORM\Column(type: 'string')]` dla password hash** (bez `length`) — Bcrypt/Argon hash może być 60-100+ znaków zależnie od algorytmu i parametrów; default `varchar(255)` Symfony to bezpieczny zapas. NIE ograniczaj `length: 60` jak w niektórych poradnikach — Argon2id może być >100. (#4)

- **`access_control` rule order MA znaczenie — pierwszy match wins.** `^/api/auth/login` (PUBLIC) PRZED `^/api` (ROLE_USER); `^/api$` z anchor'em `$` żeby entrypoint był public ale `/api/products` nie. Inaczej dostajesz 401 na `/api/auth/login` (firewall pyta o token zanim zauthenticate). (#4)
  - How to apply: zawsze testuj 401 na public route i 401 na protected route bez tokena — daje natychmiastowy feedback czy access_control jest dobrze ustawiony.

- **Lexik `json_login` + `username_path: email`** — domyślnie Symfony oczekuje `username` w body, ale UX'owo używamy `email`. `username_path` przekierowuje. Nie zapomnij — bez tego frontend wysyłający `{"email": ...}` dostaje 401 bez sensownego błędu. (#4)

- **CI musi generować JWT keys przed cache:clear i przed phpunit.** Lexik bundle przy boot'cie sprawdza obecność plików `JWT_SECRET_KEY` i `JWT_PUBLIC_KEY` (lazy: tylko przy pierwszym `create()`/`parse()` call). Cache compiler nie odpala lazy services, więc cache:clear technically would pass — ale phpstan-symfony wciąga container i może dotknąć services. Bezpieczniej generować zawsze. Wzór: `openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:ci -pkeyopt rsa_keygen_bits:4096` + `openssl pkey ... -pubout`. (#4)

- **Klucze RSA: oba gitignored, devs/CI/prod różne źródła.** Lexik recipe domyślnie gitignoruje `config/jwt/*.pem`. Production: vault-mounted. CI: per-run generation. Devs: local generation z własnym passphrase. To industry-standard dla MVP-stage; commit'owanie pubkey'a (jak prosił ticket) miałoby sens tylko gdy chcesz że CI może verify'ować tokeny wygenerowane lokalnie — niepotrzebne w obecnym setup'ie. (#4)

## Lessons z 0.0.5 (admin Refine v5 + shadcn + ESM gotchas)

- **`__dirname` jest undefined w ESM (`"type": "module"`).** `vite.config.ts` z `path.resolve(__dirname, './src')` przejdzie `pnpm build` (esbuild compile ma fallback do project root) ale fail'uje w dev server — `Failed to resolve import "@/..."`. **Fix:** `import { fileURLToPath } from 'node:url'` + `path.dirname(fileURLToPath(import.meta.url))`. To kanoniczny ESM pattern. (#5)
  - Why: bundler (Vite build) i dev server (Vite serve) używają różnych pathów do resolve aliasu — build przeżywa, dev nie.
  - How to apply: każdy ESM config (vite, vitest, tsup, rollup) z resolve aliases używa `import.meta.url` jako bazy.

- **Refine v5 + plain react-router (bez `@refinedev/react-router-v6` adaptera) wymaga ręcznego `useNavigate` w `onSuccess`/`onError` mutacji.** `authProvider.login()` zwraca `{ success: true, redirectTo: '/products' }`, ale Refine v5 honoruje `redirectTo` **tylko gdy zarejestrowany jest `routerProvider`**. Bez niego mutacja sukcesu fire-uje, token się zapisuje, ale ekran zostaje na `/login`. User widzi "silent button" — nic się nie dzieje. (#5)
  - Why: Refine headless decoupling oznacza że router integration jest opt-in. Tradeoff: less coupling, więcej manual wiring per use case.
  - How to apply: każdy `useLogin`/`useLogout`/`useRegister` w stack'u z plain react-router → `mutate(values, { onSuccess: () => navigate(target) })`. Można też dodać `@refinedev/react-router` (v2 dla RR7) jeśli mutacji jest wiele.

- **Refine v5 hooki return shape różni się między query a mutation.** `useList`/`useOne` → `{ query, result }` (query to QueryObserver, result to flat data). `useCreate`/`useUpdate` → `{ mutation, mutate, mutateAsync }` (mutation to MutationObserver z `isPending`). **ALE** `useLogin`/`useLogout`/`useGetIdentity`/`useIsAuthenticated` → bezpośrednio `UseMutationResult` / `UseQueryResult` (TanStack native, bez wrapping'u). Sprawdzaj typy przed pierwszym użyciem nowego hooka. (#5)
  - How to apply: dla data hooks `const { result, query } = useList(...)`; dla mutation hooks `const { mutate, mutation } = useCreate(...)` i `mutation.isPending`; dla auth hooks `const { mutate, isPending } = useLogin()` (TanStack native).

- **TanStack Query v5 zmienił `isLoading` na `isPending` dla mutacji.** Mutation lifecycle: `idle | pending | success | error`. Property `isPending` zastąpiło `isLoading`. Queries dalej mają `isLoading`. (#5)

- **TS 6.0 deprecated `baseUrl` w tsconfig.** Path mapping (`paths`) działa bez `baseUrl` — wystarczy klucz w `paths` z relatywną ścieżką (`"@/*": ["./src/*"]`). Bez `baseUrl` nie ma deprecated warning'u. Vite resolve działa niezależnie przez vite.config.ts alias. (#5)

- **Pagination param w Refine v5 to `currentPage`, nie `current`.** Migracja z v3/v4 → v5 zmienia nazwy. DataProvider implementacja czyta `pagination?.currentPage`. (#5)

- **`erasableSyntaxOnly: true` w tsconfig blokuje constructor property promotion.** `constructor(public readonly status: number)` daje `TS1294: This syntax is not allowed`. Musisz przepisać na: declare property + assign w body. To preferencja Vite/TS team — zachęca do "type-only" syntax który łatwiej erase'uje. (#5)

- **shadcn primitives copy-paste zamiast CLI dla container-based dev.** CLI `@shadcn/cli` wymaga interaktywnego promptu — nieprzyjemne w `docker compose exec`. Manual install z [ui.shadcn.com](https://ui.shadcn.com) (Button, Input, Label, Card, Table, Textarea — 6 plików ~200 linii each) zajmuje 5 min i daje pełną kontrolę. Tailwind v4 theme tokens (oklch + dark variant) idą w `index.css`. (#5)

- **JWT decoding po stronie frontendu dla `getIdentity` jest OK dla MVP.** Lexik token zawiera `username` i `roles` w payload — `atob(token.split('.')[1])` plus parse. Nie weryfikujemy podpisu po stronie frontu (klient nigdy nie powinien temu ufać), ale dla wyświetlenia "Hello, admin@..." to wystarczy. Refine `getIdentity` mockuje to bez round-tripu do API. (#5)
  - How to apply: prawdziwa walidacja zachodzi i tak na backendzie przy każdym request'cie. Frontend dostaje informacje "do wyświetlenia" za darmo.

- **Manual smoke przed merge nie zastępuje "uruchom dev server na clean stash" po merge.** PR #119 przeszedł 5 CI checks (Biome, TS noEmit, Vite build, audit) — ale dev server (Vite serve) z czystego stanu fail'ował na ESM `__dirname`. CI buduje produkcyjny bundle, nie testuje dev experience. Add'uj smoke step "vite dev startup" do CI w fazie 1 jeśli takie regresje będą się zdarzać. (#5)
  - Why: build vs dev mają różne code paths w Vite/esbuild — build optymalizuje, dev parsuje na żywo.
  - How to apply: po każdym merge do main odpal lokalnie `pnpm dev` z czystego cache (`docker compose restart admin`) i sprawdź `https://pim.localhost`. Albo dodaj to do `Definition of Done` ticketów frontendowych.

## Lessons z epiku 0.1 (Infrastructure i fundamenty — recon + audit)

- **Audit-first dla "infra/foundation" epików — zamykaj retroaktywnie te ticketów które Sprint-0 już zrealizował**, nie pisz od zera. 4 z 7 ticketów epiku 0.1 (#18 docker-compose, #21 GitHub Actions, #22 husky/lint-staged/commitlint, #23 baseline migrations) były **faktycznie zrobione w Sprincie 0** w ramach #1/#11/#13/#15. Audit recon = `gh issue view` + `find` + `ls` + diff vs scope checklist → zamknięcie z komentarzem audytowym linkującym do Sprint-0 PR-ów. Pattern oszczędza 8-12h "implementacji" rzeczy które już dział. (epik 0.1)
  - Why: epiki "fundament" naturalnie wykonują się fragmentarycznie podczas Sprint-0 vertical-slice'u (pierwszy ticket potrzebuje bundle layout, pierwszy CI dotyka GitHub Actions, etc.). Plan projektu rozpisał je formalnie ale realnie pojawiły się ad-hoc — co jest OK (lessons #2 walidują pattern).
  - How to apply: zaczynając każdy nowy epik 0.X w MVP-Alpha — najpierw recon (audit) wszystkich ticketów: `gh issue view` + sprawdź state plików/kodu vs scope checklist. Tylko prawdziwie missing scope dostaje implementację. Audit-close idzie z komentarzem `## Audit close (YYYY-MM-DD)` opisującym które Sprint-0/poprzednie PR-y pokryły scope.

- **`<ComingSoon resource epic issue />` placeholder pattern dla niezimplementowanych admin resources** — zamiast 5 nearly-identical pages, jeden komponent który accept'uje props (resource name, epic, GitHub issue number) + fallback i18n key per resource. Każdy placeholder route renderuje deterministyczny "not yet" page z linkiem do tracking issue zamiast 404. Sidebar entries oznaczone "Wkrótce/Soon" badge'iem. Operator wie gdzie kliknąć, użytkownicy widzą roadmap. (#20)
  - Why: 5 oddzielnych stub pages → 5 plików do utrzymania, 5 razy dłuższy `App.tsx`, ryzyko że padną out-of-sync gdy zmieni się design. Single component + props → DRY + spójność.
  - How to apply: każdy "to-be-implemented" admin resource w epikach 0.X dostaje route + ComingSoon placeholder + sidebar entry z `comingSoon: true` flagą. Gdy epik dorabia resource — placeholder zostaje wymieniony na real Refine list/create/edit, sidebar flag droppuje.

- **Per-context migrations dirs to over-engineering w MVP single-Postgres setup.** Plan projektu sugerował `migrations/Catalog/`, `migrations/Identity/`, etc. — ale Symfony default (single `migrations/` dir) działa per database, nie per bounded context. Single Postgres z RLS w Faza 1+ zostaje single-DB; nie ma sensu rozbijać migrations na sub-dirs które nie odpowiadają deployment'owej granicy. (#23 audit)
  - Why: bounded contexts w DDD są **logiczne** (oddzielenie kodu), nie **fizyczne** (oddzielenie schematów DB). PIM ma jeden Postgres cluster z tabelami Catalog (`objects`, `object_values`...) + Identity (`users`, `tenants`) + Channel (`channels`) — ale wszystkie żyją w jednej bazie z FKs między contextami. Migrations operują na bazie, nie na bounded context.
  - How to apply: zostawiamy Symfony default `apps/api/migrations/` z timestampowanymi migracjami. Per-context split DOPIERO gdy wprowadzimy schema-level isolation (multi-database architecture w Fazie 3+ jeśli kiedykolwiek).

- **`pim:db:reset` jako wrapper nad Symfony Console drop+create+migrate(+fixtures)** — operator workflow w Sprincie 0 wymagał 3 osobnych `bin/console` calls plus `docker compose stop api` żeby FrankenPHP zwolnił connection. Wrapper command łączy SQL side w jedno wywołanie z confirmation prompt, env guard (`force-prod` required dla prod), opcjonalnym `--with-fixtures`. (#23)
  - Why: każda multi-step ops procedura w MVP musi mieć single-command entry point — operator (non-coder) nie pamięta sekwencji 3-4 commands z konkretnymi flagami. Risk: zapomnij `--allow-no-migration` → pierwsza migration fail; zapomnij `--no-interaction` → CI hang.
  - How to apply: każda ops procedura która ma >2 kroki dostaje wrapper (bash script lub Symfony command). Patterns: `pim-backup-restore.sh` (host-side), `pim:db:reset` (Symfony command). Następne kandydaty: `pim:tenant:create`, `pim:fixtures:reset --tenant=X`.

## Lessons z 0.0.15 (pgBackRest + WAL stub + MinIO TLS terminator)

- **pgBackRest 2.57 hard-coduje HTTPS dla S3 repos.** `--repo-storage-port` defaultuje na 443 i nie ma opcji "use HTTP". `--repo1-storage-verify-tls=n` wyłącza tylko weryfikację certu, nie samą warstwę TLS. MinIO w dev chodzi po plain HTTP — bez wstawienia TLS terminatora między pgBackRest a MinIO dostajesz `[ServiceError] TLS error [1:167772427] wrong version number` (TLS handshake na port który odpowiada HTTP-em). **Wzór:** mały Caddy sidecar `minio-tls` (`tls internal` + reverse_proxy do `http://minio:9000`) jako jedyny TLS terminator dla pgBackRest → MinIO traffic. (#15)
  - Why: pgBackRest jest opinionated o tym że produkcyjne S3 to zawsze HTTPS — autorzy nie widzą value w plain-HTTP path nawet dla dev. Minimalna inwazja w MinIO config (zachowuje console na HTTP), izolowana zmiana.
  - How to apply: dodaj service `minio-tls` (`caddy:2-alpine` + `Caddyfile.minio` z `local_certs` + `minio-tls:443 { tls internal; reverse_proxy http://minio:9000 { header_up Host {host} } }`). pgBackRest config wskazuje `repo1-s3-endpoint=minio-tls`. Production setup (0.11.11) używa MinIO native TLS lub real S3.

- **AWS SigV4 zawiera Host header w podpisie — Caddy reverse_proxy MUSI zachować oryginalny Host upstream'owi.** Default Caddy reverse_proxy rewrituje Host na `upstream_hostport` (np. `minio:9000`), ale klient (pgBackRest) podpisał request używając Host'a `minio-tls`. MinIO weryfikuje sygnaturę po drugiej stronie i widzi `Host: minio:9000` w request'cie ale podpisaną wartość `minio-tls` — `<Code>SignatureDoesNotMatch</Code>` HTTP 403. **Fix:** `header_up Host {host}` w `reverse_proxy` block. Bezpieczne tylko z `repo1-s3-uri-style=path` (path-style URLs nie używają Host'a do bucket dispatch). (#15)
  - Why: AWS Signature Version 4 wbudowuje Host w canonical request → HMAC. Każdy proxy między klientem a S3 endpoint'em musi przepuszczać Host nietknięty albo klient musi podpisywać dla docelowego upstream'a.
  - How to apply: każdy reverse_proxy / load balancer przed S3-compatible storage MUSI mieć `header_up Host {host}` (Caddy) lub equivalent (`proxy_set_header Host $host` w nginx, `--preserve-host` w innych). Jeśli kiedyś przejdziemy na virtual-host bucket addressing (`repo1-s3-uri-style=host`), trzeba też ogarnąć subdomain bucket'u — wtedy MinIO musi widzieć `<bucket>.<host>`.

- **`archive-async=y` + ad-hoc `pgbackrest stanza-create`/`backup` = lock contention.** W async mode pgBackRest spawnuje long-running spool worker (process holding `/tmp/pgbackrest/pim-archive-1.lock`) który ciągle obsługuje WAL push z lokalnego spool'a. Każda inna komenda (stanza-create, ręczny backup) failuje na: `[050]: unable to acquire lock on file '/tmp/pgbackrest/pim-archive-1.lock': Resource temporarily unavailable. HINT: is another pgBackRest process running?`. Dla Sprint-0 stuba `archive-async=n` jest poprawne (sync archive_command odpala pgbackrest archive-push i kończy się od razu — brak persistent worker'a). Production (0.11.11) wraca na async + dedicated stanza-create cycle przed backup'em. (#15)
  - Why: async optymalizuje throughput WAL archiving pod heavy write load (postgres nie czeka na MinIO upload). Dla dev stuba write rate jest pomijalny — sync mode upraszcza model bez kosztu.
  - How to apply: każdy long-running pgbackrest mode (async, server) trzymający lock blokuje commands w tym samym container'ze. Jeśli musimy mieć async, stanza-create idzie raz przed cron startem; backup przez kolejkę/scheduler awareness.

- **pgBackRest deployment w Dockerze ma TYLKO 2 kanoniczne topologie.** Nie ma "shared volume sidecar" middle-ground: (1) **single-host** — postgres + pgbackrest w jednym obrazie/container'ze, archive_command + backup commands lokalnie; LUB (2) **server-mode TLS** — pgbackrest w drugim container'ze jako TLS server, postgres → SSH/TLS link. Próba "sidecar z shared `postgres_data` volume" nie działa bo (a) named volume mount przykrywa chown'y z Dockerfile'a → permission issues UID 70, (b) pgbackrest do `backup` potrzebuje libpq connection do pg + read access do data dir równocześnie — `pg1-host` ustawione = pgbackrest oczekuje SSH/TLS remote, NIE TCP libpq. Single-host pattern był wybrany dla Sprint-0 (busybox dcron + custom entrypoint chains do upstream `docker-entrypoint.sh postgres`). (#15)
  - How to apply: production (0.11.11) prawdopodobnie zostanie na single-host single-container — k8s DaemonSet z postgres+pgbackrest sidecar OR systemd timers. Server-mode TLS dochodzi gdy backup repo musi być fizycznie izolowany od PG host'a (off-site DR).

- **Restore = orchiestrowany na hoście, NIE jako Symfony command.** Issue #15 prosił o `pim:backup:restore` Symfony command, ale restore musi: (a) zatrzymać `api` (FrankenPHP trzyma persistent connections które blokują postgres shutdown), (b) zatrzymać `database`, (c) wytrzeć `$PGDATA`, (d) odpalić `pgbackrest restore` jako postgres user, (e) wystartować z powrotem. To są host-level orchestration steps — Symfony command runuje wewnątrz `api` container'a i nie może zatrzymać samego siebie. **Wzór:** bash skrypt `scripts/pim-backup-restore.sh` jak `scripts/perf-list-products.sh` — invokowany z hosta, używa `docker compose run --rm --no-deps --entrypoint /bin/sh database` żeby wykonać wipe+restore w one-shot container'ze (reuse env + volumes z compose service). (#15)

- **Custom postgres image + named volume `postgres_data` na `/var/lib/postgresql/data` zachowuje compatibility z fresh `postgres:16-alpine`.** Switch obrazu z `postgres:16-alpine` na `pim-database:local` (postgres:16-alpine + pgbackrest + dcron) **bez wipe volume'u** działa: postgres uruchamia się z istniejącym data dir, applikuje nowe `command: -c archive_mode=on -c archive_command=...` przy starcie, archive_command zaczyna pchać WAL gdy stanza-create się zakończy. Same alpine base + UID 70 postgres user = bez konfliktów ownership. (#15)

- **Recreate database container z `up -d --force-recreate database` propaguje przez depends_on tree.** compose checkuje `service_completed_successfully` minio-init z PRZESZŁOŚCI (12h temu exit 0) — to cache'owane state w docker. Dla świeżego CI każdy `down -v` + `up` wymusi re-run minio-init. Pattern działa w obu scenariuszach. (#15)

## Lessons z 0.0.16 (audit + scope revision)

- **Rewizja zakresu MVP w trakcie Sprintu 0 jest NORMALNĄ częścią procesu, nie awarią.** Plan zakładał agentic-first deployment; po pierwszym frontend slice (#5) operator zobaczył że pilot ocenia "działający katalog" wyżej niż "rozmawiaj z systemem". Cofnięcie agenta + integracji do Faz 1/2 to **5 minut decyzji + 30 minut reorganizacji ticketów** (35 issues, 2 nowe milestone'y). (#16)
  - Why: oryginalny plan był aspiracyjny; pierwszy ticket frontendowy sprowadza wymagania na ziemię.
  - How to apply: po każdym milestone (np. zakończenie sub-fazy) zapytaj operatora "czy plan zakresu wciąż pasuje?" przed wejściem w następną. Lepsze 30 min reorganizacji teraz niż 30h przepisywania w Fazie 1.

- **Living document vs frozen-in-time** — `06-sprint-0-findings.md` jest "living" (sekcje 1.2 i 7 update'owane przy każdym kolejnym Sprint-0 closure), `01-architektura-pim.md` jest frozen-in-time (ADR'y się tylko dorabiają). Każdy doc w `Project Plan/` deklaruje swój tryb na początku — dev session widzi czy szuka aktualnego stanu czy historycznego. (#16)

- **Gate decision = predykcja po 7-8/13 ticketach, finalna po 13/13.** Sprint 0 verdict GREEN można przewidzieć z dużą pewnością gdy 60%+ ticketów zielone i pozostałe nie mają blockerów. **Predykcja w `findings` doc daje operatorowi czas na rozważenie czy gate-decision ma sens** zanim CI/E2E ciągi rozstrzygną. (#16)

- **Reorganizacja milestone'ów na GitHub'ie via `gh api` + bash loop.** Tworzenie milestone'a: `gh api repos/owner/repo/milestones -f title=...`. Przeniesienie issue: `gh issue edit N --milestone "..."`. Zamykanie milestone'u: `gh api -X PATCH repos/owner/repo/milestones/N -f state=closed`. Pętla bash z grep-em po numerach ticketów = ~2 min na 30 ticketów. Skrypt nie idzie do repo (one-shot), idzie do lessons jako wzór. (#16)

- **Komentarz na przeniesionym issue tłumaczy "dlaczego" — nie tylko "gdzie".** Każdy z 3 przeniesionych Sprint-0 ticketów (#6, #7, #8) i 35 ticketów epików dostał komentarz z linkiem do `Project Plan/02-plan-projektu-pim.md` i wyjaśnieniem decyzji. Future-self wracający do issue widzi context, nie tylko "moved to milestone X". (#16)

## Lessons z 0.0.14 (perf profile + k6 + EXPLAIN ANALYZE)

- **k6 zamiast Blackfire/Tideways w MVP.** OSS, single binary jako `grafana/k6` docker image, `profile: ["perf"]` w docker-compose (nie startuje z `pnpm stack:up`), one-shot `pnpm perf:list`. Blackfire/Tideways wymagają konta SaaS + agent w container'ze + commercial license w prod — overhead setup'u >ROI dla pilot stage. Pełny profiler suite kandydat do epiku 0.11 (#103-#105). (#14)
  - How to apply: każdy nowy load test → `tools/perf/<scenario>.js` + wrapper script w `scripts/perf-<scenario>.sh` (login → seed → k6 → cleanup).

- **`network_mode: "service:caddy"` dla k6** — k6 reuse'uje stos sieciowy Caddy edge'a, więc trafia na to samo `https://pim.localhost` co browser/curl z hosta i akceptuje ten sam self-signed cert (z `insecureSkipTLSVerify: true` w options). Brak osobnego DNS aliasing'u, brak osobnej trasy. (#14)

- **Próg `p95 < 200ms` jest zależny od (concurrent_users / php_threads).** FrankenPHP `num_threads: 17` (auto z CPU count) → 100 VUs = 6× kolejka per thread → p95 ~1s. Dla MVP B2B single-pilot stage (5-10 catalog managers + agent) realistyczny load = 10 VUs gdzie p95 = 105 ms (headroom 1.9×). 100 VUs to enterprise scale, dochodzimy z multi-worker / horizontal scale w fazie 2 (sekcja 12.2 architektury). (#14)
  - How to apply: każdy load test report MUSI deklarować VUs + thread count + interpretację dla docelowego use case'u. Sam `p95<200ms@100VUs` bez kontekstu nie jest meaningful.

- **Performance numbers MUSZĄ pochodzić z `APP_ENV=prod APP_DEBUG=0`.** Ta sama lekcja co #13. W env=dev profiler middleware bije latencję 5-10× (każdy request loguje DataCollector, serializuje, persistuje na disk). `pnpm perf:list` używa env=prod dla seedu (CLI) ale operator MUSI pamiętać też o restarcie HTTP api w prod env: `docker compose stop api && APP_ENV=prod docker compose up -d api && docker compose exec api php bin/console cache:warmup`. (#14)

- **Doctrine ORM 3 prod env wymaga proxy generation przed pierwszym requestem.** `auto_generate_proxy_classes: false` w `when@prod` — bez `php bin/console cache:warmup` FrankenPHP rzuca *"Failed opening required '__CG__App...EntityProxy.php'"* na pierwszym persist/find. Naturalnie zachodzi w docker build'cie (`composer install --classmap-authoritative`) ale lokalna iteracja z bind mount + `APP_ENV=prod` wymaga manualnego warmup. (#14)
  - How to apply: każdy switch dev → prod env w lokalnym container'ze: `docker compose exec -T -e APP_ENV=prod -e APP_DEBUG=0 api php bin/console cache:warmup`. Dodać do dokumentacji `pnpm stack:reset --prod` w fazie 1.

- **EXPLAIN ANALYZE jako main profiling tool dla Sprint 0.** Single SQL query na głównym list endpoincie zwraca strukturę: cost, actual time, buffers shared, planning time, execution time. `Index Scan Backward using products_pkey` + `Filter: tenant_id = ...` = optymalny plan dla `ORDER BY id DESC + LIMIT`. Planning time (2.5 ms) bije execution time (1 ms) na małej skali — query plan caching w fazie 1 to potencjalna optymalizacja. (#14)

- **Hot path breakdown dla GET /api/products?page=1 (single user, prod env, 13 ms total):** (1) Symfony Serializer + JSON-LD encoding ~3-4ms, (2) Doctrine query + hydration ~3-4ms, (3) Security firewall (JWT decode + User repository) ~2-3ms, (4) Routing + API Platform metadata ~1-2ms, (5) Caddy proxy + TLS ~1-2ms. **Brak jednego dominującego bottleneck'a — distributed cost.** Optymalizacja punktowa (cache User per-JWT, ETag/304, +threads) gdy first pilot pokaże request rate >>10/s. (#14)

## Lessons z 0.0.13 (FrankenPHP memory benchmark + AbstractBatchHandler)

- **`paginationViaCursor` w API Platform 4 deklaruje KIERUNEK KURSORA, nie domyślne ORDER BY.** Bez explicit `?order[id]=desc` od klienta lub `order: ['id' => 'DESC']` na operacji, Postgres zwraca wiersze w fizycznej kolejności (insert order). Nowo utworzony produkt może wylądować poza pierwszą stroną i operator widzi "po zapisie nie ma na liście". Każdy `paginationType: 'cursor'` resource MUSI mieć dopowiadający `order:` na GetCollection, nie tylko `paginationViaCursor`. (#13 post-merge fix)
  - Why: `paginationViaCursor` instruuje API Platform jak budować linki next/prev (jaki filter range applikować na cursor query param), ale ORDER BY musi przyjść z innej deklaracji. Łatwo przeoczyć — wygląda jak duplikacja konfiguracji.
  - How to apply: `new GetCollection(paginationType: 'cursor', paginationViaCursor: [['field' => 'id', 'direction' => 'DESC']], order: ['id' => 'DESC'], ...)`. Field i direction muszą być spójne między oboma.

- **Fixtures admin email pattern: `admin@<tenant_code>.localhost` dla wszystkich tenantów.** Pierwotnie demo miało `admin@pim.localhost` (legacy z czasu gdy był tylko jeden tenant), acme `admin@acme.localhost`. Operator naturalnie próbuje `admin@demo.localhost` dla demo i nie da się zalogować — silent UX regression. Pattern `admin@<code>.localhost` jest jedyny spójny. (#13 post-merge fix)

- **Cleanup po crashu benchmarku jest manualny — `--keep` ON-by-default po OOM.** Gdy benchmark padnie na OOM (n.p. dev-env profiler middleware leak), skrypt nie dochodzi do `DELETE FROM products WHERE sku LIKE 'bench-%'`. Zostawia śmieci. **Zawsze sprawdzaj `SELECT COUNT(*) FROM products` po failed benchmark run i wyczyść ręcznie.** Fix: nie uruchamiaj benchmarków w `APP_ENV=dev` (R-25-debug leak) + `psql -c "DELETE ..."` po nieudanych runach. (#13 post-merge fix)



- **Pattern `EntityManager::clear()` po `flush()` w pętli daje memory FLAT regardless of row count w prod env.** Benchmark `pim:benchmark:bulk-import` w `APP_ENV=prod APP_DEBUG=0`: 5 000 → 14 MiB peak, 50 000 → 14 MiB peak (identyczne!). Bez clear: 50 000 → 150 MiB i CPU 6× wolniej. **Pattern jest egzekwowalny:** R-25 ("Krytyczny" wpływ) zwalidowany. (#13)
  - Why: Doctrine UnitOfWork akumuluje IdentityMap między flush'ami; clear() detachuje wszystko, kolejny batch zaczyna od pustego heap'u. CPU savings (6×) wynikają z tego że flush() iteruje cały UnitOfWork — bez clear() rośnie liniowo z każdym batchem.
  - How to apply: każdy nowy bulk path (Messenger handler, CLI command, sync worker) MUSI iść przez `App\Messaging\AbstractBatchHandler::flushAndClear()` lub kanoniczny inline pattern (`flush()` → `clear()` → re-fetch tenant). Custom PHPStan rule (#123) dodajemy w fazie 1.

- **Symfony Profiler middleware (`BacktraceDebugDataHolder`) jest osobnym źródłem leaku — `doctrine.dbal.logging: false` go nie wyłącza.** W env=dev/test profiler middleware przechwytuje każdy SQL query z backtrace'em i akumuluje w pamięci (50 000 INSERT-ów = OOM przy 512 MiB cap, **mimo poprawnego clear pattern'u**). Zachowanie poprawne dla profilera, ale benchmarki/workery memory MUSZĄ działać w `APP_ENV=prod APP_DEBUG=0`. (#13)
  - Why: profiling middleware jest osobną warstwą od `dbal.logging` flagi — kontrolowany przez `kernel.debug` parameter. Symfony Profiler trzyma query timeline w pamięci do końca request'a, ale w worker mode "request" trwa godziny.
  - How to apply: każdy long-running CLI / Messenger consumer w docker-compose.yml = `APP_ENV=prod` lub `APP_DEBUG=0`. Dev env to debug toolbox, nie production simulation.

- **`EntityManager::clear()` detachuje WSZYSTKIE entitki, włącznie z `Tenant`** — następny batch musi re-fetch'ować tenanta po ID. Bez tego `TenantAssignmentListener` przekazuje detached `Tenant` do nowego `Product` → flush() pada na *"A new entity was found through the relationship..."*. Wzór z `BulkImportBenchmarkCommand` jest kanoniczny. (#13)
  - Why: Doctrine ORM 3 nie ma `merge()`; jedyna ścieżka odzyskania managed instance to `find()` po ID. TenantContext trzyma referencję do detached Tenant po clear() — listener musi widzieć managed instance.
  - How to apply: każdy batch handler który czyta tenant z `TenantContext` po `clear()` MUSI: zachować `$tenantId = $tenant->getId();` przed pętlą + `$tenant = $repo->find($tenantId);` + `$tenantContext->set($tenant);` po każdym `clear()`.

- **Benchmark CLI ≠ pełna symulacja FrankenPHP worker mode.** CLI command spawn-uje fresh PHP process (allocator state reset między runami); worker mode trzyma proces między requestami (allocator state persists, leak compounds across messages). CLI walida algorytm (clear-after-flush działa, throughput +6×) i bound memory w jednym procesie. Pełen worker-mode test (Messenger consumer + 5 000 messages) dochodzi z pierwszym async transportem w epiku 0.1 (#17+). (#13)
  - How to apply: gdy ktoś dodaje `messenger: async` transport (Redis/Doctrine) i pierwszy long-running handler — re-uruchom benchmark w trybie message-consumer (osobne sub-issue do #17+).

- **`/api/metrics` Prometheus endpoint w MVP jest unauthenticated.** Wystawia `frankenphp_worker_memory_bytes` gauge dla worker procesu który obsłużył scrape. Sprint 0 = dev convenience > security. Production hardening (token + private network binding) dochodzi w epiku 0.11 #103-#105. Format: standardowy `text/plain; version=0.0.4`. (#13)

- **`number_format()` na intach + readonly w abstract class + PHPStan max** — `(int) $input->getOption(...)` powoduje `cast.useless` w PHPStan max bo Symfony PHPDoc deklaruje return jako `mixed|null`. Workaround: `/** @var string $x */ $x = $input->getOption(...);` przed użyciem. Druga gotcha: `\assert($x instanceof Foo)` po `Query::toIterable()` w Doctrine 3 z phpstan-doctrine — generic narrows to `iterable<int, Foo>`, więc assert flagged jako `function.alreadyNarrowedType`. Po prostu pomiń assert. (#13)

## Lessons z 0.0.10 (Playwright E2E + docker-compose CI)

- **`docker compose up --wait` + healthcheck queryjący domain DB = chicken-and-egg.** Healthcheck api hituje `/api`, który przez `RequestTenantSubscriber` queryje tabelę `tenants`. Bez migracji → 500 → unhealthy → `--wait` timeout. Migracje wymagają activnego api containera. **Wzór:** dwustopniowy startup: `up -d --wait db redis` → `up -d api` (no wait) → poll `php -v` aż exec działa → `migrate + fixtures` → `up -d --wait reszta`. (#10)
  - Why: pełen stack zależy od schemy DB; healthcheck domyślnie chce być deterministycznym sygnałem "container ready" — z DB-driven endpointem trzeba wstrzyknąć migracje pomiędzy.
  - How to apply: każdy nowy container/healthcheck który dotyka domain DB musi być w "phase 2" startup pipeline'u. Init-only containery (np. minio-init) idą OBOK głównego waita.

- **`docker compose --wait` traktuje `restart: no` one-shot exit (kod 0) jako wait failure.** `minio-init` robi `mc mb pim-assets` i wychodzi cleanly. `--wait` widzi non-running container → exit 1. **Fix:** explicit service list `up -d --wait db redis api admin caddy mercure` zamiast wszystko. (#10)
  - Why: `docker compose --wait` waits for services to be running OR healthy — exited (success or fail) nie jest stanem "running".
  - How to apply: alternatywa to `service_completed_successfully` w depends_on, ale list-explicit jest prościej i jaśniej w CI.

- **Caddy single-origin healthcheck MUSI używać HTTPS — Caddy listening only na :443.** Docker-compose Caddy healthcheck pierwotnie miał `wget http://localhost/api`. Caddy z auto-HTTPS i auto-redirect=disabled nie listening na :80 — wget connection refused. Lokalnie `compose ps` pokazywał `(unhealthy)` ale nikt nie zauważył bez `--wait`. **Fix:** `wget --no-check-certificate https://localhost/api`. (#10)
  - Why: single-origin Caddyfile binds tylko HTTPS w naszej topologii. HTTP→HTTPS redirect wyłączony.
  - How to apply: każdy container behind Caddy musi healthcheck'ować HTTPS endpoint, nie HTTP. Custom CA cert akceptowany przez `--no-check-certificate` w wget / `-k` w curl.

- **Playwright w Alpine container = no go.** `node:22-alpine` (admin) nie ma `apt-get`, Playwright nie zainstaluje deps Chromium. **Strategia:** dev = host-side install (`pnpm playwright install`), CI = official `mcr.microsoft.com/playwright` LUB `ubuntu-latest` + `playwright install --with-deps`. (#10)
  - Why: Playwright bundle Chromium z linux deps jako Debian/Ubuntu packages.
  - How to apply: jeśli dev container kiedyś migruje na Debian, można nano przenieść Playwright do container. Do tego czasu: instrukcja w README + `pnpm --filter @pim/admin e2e` z hosta.

- **Random timestamp+random SKU dla testów na non-reset DB.** Sprint 0 nie ma DB reset między test runami (dev DB), więc testy mutacyjne (POST products) muszą używać unikalnych SKU per run. `${prefix}-${Date.now().toString(36)}-${random3digit}`. CI ma fresh DB więc kolizja niemożliwa, ale test musi działać też lokalnie. (#10)

- **Playwright `getByRole('cell', { name: ... })` strict mode** — gdy substring matchuje wiele cells, fail z "strict mode violation". Użyj `exact: true` lub bardziej specyficznego selektora. Najczęstszy case: cell SKU + cell name zawierający SKU jako substring. (#10)

- **CI buduje produkcyjny bundle — nie testuje dev experience.** Wzór z #5 (ESM `__dirname`) potwierdzony znowu: `vite build` przeszedł, `vite dev` fail'ował. E2E job z `pnpm dev` przez Caddy = pierwszy CI step który faktycznie testuje dev stack. **Akcja:** każdy frontend ticket który dotyka Vite config / dev server MUSI być testowany przez pełen E2E w CI, nie tylko build. (#10)

- **Trzy fixy w CI debugowaniu = three commits, nie squash do jednego.** Pierwotna implementacja PR #122 → CI fail → fix migracji → CI fail → fix --wait list → CI fail → fix Caddy HTTPS healthcheck → CI green. Każdy commit ma czytelny `fix(ci)/fix(infra)` message + link `Refs #10`. Po squash-merge git history ma jeden czysty commit, ale podczas debug'u widać kolejność rozumowania. (#10)
  - How to apply: debugger CI commits to NORMA, nie smell. Po-mortem w `chore(agent)` na main agreguje wnioski.

## Lessons z ADR-009 (Generalizacja ObjectType — 2026-04-27)

> Praca planowo-dokumentacyjna na poziomie modelu domenowego. Bez zmiany kodu (epik 0.3 nie był jeszcze rozpoczęty — ADR-009 zmienia plan przed pierwszą migracją Catalog). PR #1 (`docs/adr-009-objecttype`) wprowadza ADR + audit planu; PR #2 (`chore/adr-009-issue-reshape`) reshape'uje 30+ otwartych GitHub Issues i ten log.

### Decyzja
**Generic `ObjectType` z predefiniowanymi Product/Category/Asset siedzącymi jako built-in instancje (`is_built_in=true`) + custom kindy (`Customer`, `Supplier`, `PriceList`) odblokowane w Fazie 2/3.** Pełen ADR w `Project Plan/01-architektura-pim.md` §13.

### Alternatywy odrzucone
- **(a) Hard-coded `Product` + `Category` z asymetrycznym modelem (status quo).** Asymetria blokuje import z PIMCore (eksport `Zrodla/PIMCore/masowy_eksport_konfiguracji.json` pokazuje klasę `Kategoria` z user-defined SEO + image — nie ma na to miejsca w obecnym `Category` z 3 polami). Blokuje przyszłe `Customer`/`Supplier` bez 8-12h migracji DDL per byt.
- **(b) Pełna generalizacja jak PIMCore Class Definition** (admin/agent definiuje wszystkie typy w runtime, brak twardych encji). UX dla MVP się rozjeżdża — admin musi sam zdefiniować „produkt" przed pierwszym użyciem. Blokuje optymalizację per kind (ltree dla category, storage dla asset).
- **(c) Generic `ObjectType` z predefined fixed UX** — wybrana opcja. Kompromis: rdzeń elastyczny (atrybuty + EAV-z-JSONB parametryzowane o `object_type_id`), UX zoptymalizowany pod 3 predefined kindy w admin UI, sugar paths w API.

### Co się sprawdziło w retrospekcji
- **Rdzeń ADR-006 (hybrid attribute model) jest wystarczająco elastyczny** — generalizacja parametryzuje go o `object_type_id` zamiast wymyślać 4 mechanizmy jak PIMCore. To dowód że decyzja architektoniczna 2-letniego horyzontu (ADR-006) potrafi pociągnąć rozszerzenie zakresu (ADR-009) bez przepisywania.
- **Asymetria „multi-tenant ready, single-tenant deployed" (ADR-003) reaplikuje się do ObjectType** — tak samo „custom kindy ready, predefined deployed" — sprawdzony pattern.
- **Saldo budżetu MVP** netto -31 do -39h vs poprzedni 201-274h (rewizja 2026-04-27 zwolniła 51-69h przez przeniesienie epików 0.7/0.8/0.9 do Faz 1/2, ADR-009 dodał 20-30h w epiku 0.3). Wynik: Faza 0 **170-235h pełny / 156-216h okrojony**. Top-line MVP-Alpha mieści się w okrojonym MVP. Single source of truth: sumy epików §3.3 + milestone tabela §3.4 planu.

### Co pozostaje do walidacji w MVP-Alpha
- **Benchmark `attributes_indexed`** — query po atrybut-value na 10k obiektach × 200 atrybutów × 3 kindach < 50ms. Proof że generic model nie zwalnia query path (R-29 mitigation). Jeśli benchmark fail — wracamy do partial indexes per kind.
- **Playwright E2E „edycja kategorii z atrybutami niestandardowymi (SEO, image)"** — proof że predefined UX dla 3 kindów daje pełnoprawne user-defined atrybuty per kind.
- **Dyscyplina `kind='custom'` wyłączony** — feature flag `enable_custom_object_types` egzekwowany w `ObjectTypeService::create()` i tool `create_object_type` agenta. PHPUnit testy + Playwright testy enforce'ują.
- **Audit log per kind** — DoctrineAuditBundle musi pokrywać wszystkie kindy, nie tylko hard-coded `Product`. Test w 0.11.4 + 0.11.5 (#99 + #100).

### Audit GitHub Issues — log per ticket (2026-04-27)

**Epik 0.3 — major rebody:**
- **#31 [0.3.1] Attribute + AttributeGroup + AttributeOption** — light append: atrybuty wiązane z `ObjectType` przez junction `object_type_attributes`; jeden atrybut może być reused przez wiele typów. Sama encja Attribute pozostaje generic, scope ticketu bez zmian.
- **#32 [0.3.2] Family + FamilyAttribute** → **rewrite na ObjectType + ObjectTypeAttribute**. Rename w title, body przepisany od zera. Service blokuje deletion `is_built_in=true`, feature flag `enable_custom_object_types` na `ObjectTypeService::create()`.
- **#33 [0.3.3] Category z ltree** → **rewrite na Predefined ObjectType `category` + ltree validator dla kind='category'**. Listener `CategoryPathValidator` parametryzowany przez `kind`. Sugar API `/api/categories`.
- **#34 [0.3.4] Product (rozszerzona) + ProductValue + attributes_indexed** → **rewrite na Object (poly per kind) + ObjectValue + attributes_indexed**. Dodatkowo migracja danych ze Sprintu 0 (`products` → `objects` z `kind='product'`). Generated columns parametryzowane per kind.
- **#35 [0.3.5] Association** — light append: działa generycznie na `Object` (`object_associations` zastępuje `product_associations`).
- **#36 [0.3.6] Channel + Locale + Currency + ChannelAttributeMapping** — light append: rename `ChannelAttributeMapping` → `ChannelObjectTypeMapping` (poly per kind).
- **#37 [0.3.7] Asset + AssetVariant** — light append: Asset jako predefined `ObjectType kind='asset'` + dedykowana tabela `assets` z FK `object_id` na powiązany Object (storage details w assets, user-defined metadata w object_values).
- **#38 [0.3.8] Doctrine listenery** — light append: `AttributesIndexedSyncListener` parametryzowany per `object_type_id`, `CompletenessRecalculator` czyta reguły z `ObjectType.completeness_rules`.
- **#39 [0.3.9] Symfony Validator constraints** — light append: parametryzacja per ObjectType w `AttributeValidationCompiler`.
- **#40 [0.3.10] Migracje + seeders** — light append: rozszerzenie data testowych (5 kategorii z user-defined atrybutami SEO/image, 10 assetów w 1 tenancie).
- **#128 [0.3.11] Hooks pod kind='custom' na poziomie ApiResource** — **NEW** ticket dodany (renumbered z [0.3.12] do [0.3.11] w korekcie 2026-04-28). Factory `ObjectTypeAwareApiResource`, serializer context per kind, Voter `CustomObjectTypeVoter` enforce'ujący feature flag.

**Epik 0.4 — light update wszystkich (#41-#48):**
- #41 (ApiResource) — sugar paths `/products`, `/categories`, `/assets` przez extraProperties; jeden controller pod spodem.
- #45 (data transformers) — rename ProductDenormalizer → ObjectDenormalizer, parametryzowany per `object_type_id`.
- #42, #43, #44, #46, #47, #48 — jednolinijkowy „post ADR-009: respect `object_type_id` in filters/serializers/data transformers/Mercure events".

**Epik 0.5 — light update wszystkich (#49-#53):**
- Indexer Meilisearch parametryzuje się o `object_type_id`, jeden indeks per kind (`products`, `categories`).
- Reindex CLI: `pim:search:reindex --kind=product|category|all`.

**Epik 0.6 — UPDATE:**
- #54 (Layout) — Cmd+K placeholder usunięty (rewizja 2026-04-27); sidebar pokazuje fixed sekcje pierwszej klasy.
- #55 (Resource Products) — bez zmiany scope (form parametryzowany o `object_type_id` już planowany).
- #56 (Resource Attributes) — dochodzi filtr `applies_to_object_type`.
- **#57 (Resource Families) → rename na Resource ObjectTypes** + UI predefined locked + sekcja Custom disabled „Faza 2".
- #58 (Categories tree) — dochodzi dynamic attribute editor for `kind='category'` (proof of ADR-009).
- #59 (Channels) — `ChannelObjectTypeMapping` (poly per kind).
- #60 (Assets) — UI obsługuje storage details + user-defined attributes razem.
- #61 (Provenance) — działa na `object_values` zamiast `product_values`; wariant `agent` zarezerwowany Faza 2.
- #62 (i18n) — bez zmiany scope.

**Epik 0.10 — light update wszystkich (#90-#95):**
- #90 (ApiProfile + ApiKey) — pole `object_types` JSONB w ApiProfile.
- #91-#95 — UI multiselect ObjectType, filter response per `object_type_id`, OpenAPI export sugar paths.

**Epik 0.11 — light update kluczowych:**
- #99 (Audit log) — DoctrineAuditBundle obejmuje wszystkie obiekty `Object` + dedykowany audit dla `ObjectType` i `Attribute`.
- #100 (Playwright E2E) — dochodzi scenariusz „edycja kategorii z atrybutami niestandardowymi" + „próba `kind='custom'` blocked feature flagiem". Sync to BaseLinker/Shopify w Fazie 1, agent w Fazie 2.

**Faza 1 — Integracje (light):**
- #74 (BaseLinker adapter) — pobiera dane z `Object kind='product'`; mapping per `ObjectType`.
- #81 (Shopify adapter) — pobiera dane z `Object kind='product'`; Collections z `Object kind='category'`; metafields per ObjectType.

**Faza 2 — Agent (light):**
- #6 (Sprint-0 agent endpoint) — `assign_attribute_to_object_type` zastępuje `assign_attribute_to_family`; `create_object_type` reserved Faza 2.
- #63 (Bundle Agent) — AgentRun loguje tool calls per `kind` w `tool_calls` JSONB.
- **#65 (Tool definitions) — KEY UPDATE:** lista toolów po ADR-009. `search_object_types` (nowy), `assign_attribute_to_object_type` (rename), `create_object_type` (nowy, reserved feature flagiem), `create_category` (sugar tool).
- #66 (Tool execution) — Voter `CustomObjectTypeVoter` enforce'uje feature flag.
- #67 (Pending changes) — `target_kind` w rekord.
- #71 (Audit logging) — `target_kind` indeksowane.

**Follow-up:**
- **#123 (Custom PHPStan rule blocking flush in loop without clear)** — milestone przypisany do **MVP-Final** (był NONE). Po ADR-009 rule operuje na `object_values` flush patterns, nie tylko `product_values`.

**Sprint-0 leftovers (#9, #15) i Epiki 0.1 (#17-#23) / 0.2 (#24-#30)** — bez zmian (czysta infra/auth/demo, neutralne wobec ADR-009).

### Statystyka audytu
- 30 ticketów edytowanych (epik 0.3: 10 + nowy 0.3.11; epik 0.4: 8 light; epik 0.5: 5 light; epik 0.6: 9 update + 1 rename; epik 0.10: 6 light; epik 0.11: 2 light; Faza 1: 2 light; Faza 2: 7 light).
- 1 nowy ticket utworzony (#128 — 0.3.11).
- 1 ticket dostał milestone (#123 → MVP-Final).
- 0 ticketów zamkniętych jako duplikaty/obsolete.

### Korekty post-audyt (2026-04-28)
Self-audit ujawnił 12 znalezisk; korekty wprowadzone w drugiej iteracji:
- **F-001 (krytyczne):** §5.2 architektury — `channels.category_tree_root_id REFERENCES categories(id)` → `category_tree_root_object_id REFERENCES objects(id)` (target enforce'owany przez `ChannelCategoryRootValidator`, bo Postgres FK nie wspiera predykatu na kolumnie target).
- **F-002:** §8.2 + §8.4 architektury — usunięto „rodziny", przykład Approval flow przepisany na `assign_attribute_to_object_type`.
- **F-003:** §3.1 (Cele) + §3.2 (Sprint 0 OOS) + ticket 0.2.3 + ticket 0.7.3 + Faza 2 #65 w planie — usunięto relikty „Family"/„rodziny".
- **F-004:** estymaty zsynchronizowane z sumami epików §3.3 + milestone tabelą §3.4. Faza 0 pełna **170-235h** (poprzednio błędnie 188-260h). Source of truth: §3.3 i §3.4 planu, sekcja 7 i streszczenie z nich się derive'ują.
- **F-006/F-007/F-008:** issues #36, #65, #41 — title + Cel + Zakres przepisane (wcześniej tylko Aktualizacje announce'owały rename, aktywne checkboxy zostawały stare).
- **F-009:** CLAUDE.md commit example — przepisany z `Product+Family+ProductValue` na `ObjectType+ObjectTypeAttribute+is_built_in`.
- **F-010:** lesson log #36 (rename ChannelAttributeMapping) teraz odpowiada faktycznemu stanowi issue body.

**F-005 (renumeracja epiku 0.3) — wykonana 2026-04-28:**
- Plan §3.3 zaktualizowany: 0.3.3 (Predefined fixtures) i 0.3.5 (custom logika `kind='category'` ltree) zlepione w jeden ticket 0.3.3 (fixtures są zlepione z ltree dla category — nie ma sensu rozdzielać). Epik 0.3 ma teraz 11 ticketów (było pre-rewrite 10).
- GH issue #33: `[0.3.5]` → `[0.3.3]`, body rozszerzone o fixtures dla wszystkich trzech built-in kindów (product/category/asset).
- GH issue #128: `[0.3.12]` → `[0.3.11]` (zlikwidowana luka po konsolidacji 0.3.3+0.3.5).
- Reszta GH issues zachowuje swoje numery: #35 [0.3.5], #36 [0.3.6], #37 [0.3.7], #38 [0.3.8], #39 [0.3.9], #40 [0.3.10] — pasują do zaktualizowanej numeracji planu.

## Lessons z 0.2.2 / #25 (Symfony Security + JWT — React SPA flavour)

- **FormLogin authenticator nie ma odbiorcy w naszej architekturze** — admin to React SPA + Refine, backend Symfony nie renderuje HTML. Body ticketu #25 wymagał FormLogin (relikt z czasów przed-SPA decision); świadomie pominięte. Why: dead code Symfony który nikt nie woła + dodatkowy attack surface. CSRF protection idzie w pakiecie z FormLogin (session cookie) — też pominięte. JsonLogin stateless + Bearer JWT nie potrzebują CSRF.
  - How to apply: jak następny ticket zażąda komponentów Symfony Security pod server-rendered admin (`scheb/2fa-bundle` UI, password reset form, OAuth login button) — NIE dodawaj FormLogin firewall'a, dodawaj odpowiednik po stronie React + REST endpoint backend.

- **Argon2id explicit w `security.yaml`, nie `auto`.** OWASP 2024 baseline: memory_cost ≥ 19 MiB (= 19456 KiB), time_cost ≥ 2, threads = 1. Pinujemy `memory_cost: 65536` (64 MiB), `time_cost: 4`, threads default. **`when@test`** ma niższy `memory_cost: 64` (KiB), `time_cost: 3` — to **floor libsodium**, niżej (memory_cost: 8, time_cost: 1) crashuje runtime'em `$opsLimit must be 3 or greater` z `SodiumPasswordHasher`. Why: `auto` w Symfony 7.4 wybiera za nas — fine kiedy działa, problemy kiedy nie (operator nie zauważy że hasło jest nagle bcrypt). Plus assert `$argon2id$` prefix daje pewność że ustawienie wzięło się.

- **LexikJWT failure response nie jest RFC 7807** — domyślnie zwraca `{code, message}` z `Content-Type: application/json`. Reszta API zwraca `application/problem+json` z API Platform. Mapowanie przez `AuthenticationFailureListener` (Lexik dispatchuje `Events::AUTHENTICATION_FAILURE` PRZED zwróceniem response — listener może `setResponse()` na event'cie). Why: spójny error format dla integratorów — jeden parser dla wszystkich błędów. How to apply: jak dodajesz nowy authenticator albo handler, sprawdź czy zwraca `application/problem+json` zanim zamerguj.

- **Worker mode FrankenPHP wymaga `composer / cache:clear + restart` po zmianach w `config/packages/*.yaml` lub event listenerach.** Symptom: `composer test` green, manual `curl` pokazuje stare zachowanie. Lekarstwo: `docker compose exec api php bin/console cache:clear && docker compose restart api`. Why: worker preloaduje DI container, listener subscriptions cachują się w boot-time. PHPUnit dostaje świeżego kernel'a, manual smoke uderza w długo żyjący proces.
  - How to apply: po każdym ticketcie z security.yaml lub event listener changes — zrób manual smoke PO restart api, nie tylko PHPUnit.

- **Logout w MVP to placeholder 204** — JWT jest stateless, bez refresh tokenów + blacklist'y nie da się invalidować access tokena. Endpoint istnieje by SPA miała gdzie wpiąć button. Pełen logout (revoke refresh + clear httpOnly cookie + cookie chain) w #28+#29. Why: nie udajemy że logout działa — komentarz w controllerze + body ticketu #25 jasno mówi że to placeholder. Klient client-side dropuje access token aż server-side invalidation dochodzi w #28.

## Lessons z 0.2.4 / #27 (RBAC seeder + getRoles() merge)

- **Seeder seeduje matrix, nie aktualnie istniejące encje.** `RbacMatrix::RESOURCES` zawiera m.in. `object`, `channel`, `attribute_group` — encje które dochodzą w epikach 0.3/0.6. Seeder tworzy permission rows niezależnie od istnienia tabel. Why: voters (#26) i API surface'y muszą mieć permissions do referowania, nawet gdy backing entity nie istnieje. Source of truth = matrix; entity layer nadrabia. How to apply: dodanie nowego resource = edytuj `RESOURCES` list + udokumentuj w `docs/rbac.md`, voter na to czeka.

- **`final readonly class` nie działa gdy klasa mutuje stan w runtime.** PHP 8.4: `readonly class` czyni wszystkie pola immutable, nawet z domyślną wartością (`private int $x = 0;` → fatal error "Readonly property cannot have default value"). Pattern dla seederów / builderów: `final class X` z `public function __construct(private readonly ...)` w konstruktorze. Why: immutable per-instance state vs counter pola które resetują się per-call.

- **`User::getRoles()` jako merge point JSON legacy + M2M.** Legacy `['ROLE_ADMIN']` w JSON (Sprint-0 fixture) + `ROLE_'.strtoupper($role->getCode())` z M2M + `ROLE_USER` floor → `array_values(array_unique($roles))`. Why: jeden ticket = jedna zmiana — drop JSON column to osobny ticket post-MVP. Do tego czasu fixture'y i ad-hoc testy mogą dalej tworzyć `new User(... ['ROLE_X'])` i działa.

- **Idempotency seedera = unique indexes z #24 są twoją siatką bezpieczeństwa.** `permissions(resource, action)` UNIQUE + `roles(tenant_id, code)` UNIQUE. Buggy seeder duplikujący row = SQL error przy flush, nie cicho duplikaty. Test: re-run `seed()` → `isNoOp() == true`.

- **Stack PR-ów w epikach: rebase poprzedni branch na main przed stack'iem.** #27 stack'owany na #25. #25 branch był stworzony z main PRZED merge'em #24 → #25 nie miało Role/Permission encji. Lekarstwo: `git checkout main && git pull && git checkout #25-branch && git rebase main && git push --force-with-lease`. Why: stack `#27` na pre-#24 stanie #25 = brakuje schema. Symptom: `ls src/Identity/Domain/Entity/` pokazuje tylko Tenant.php + User.php. **Pattern:** zawsze rebase parent branch na świeże main przed odbiciem child branchu.

## Lessons z 0.2.3 / #26 (Voters — ObjectVoter via ProductVoter proof)

- **`AbstractRbacVoter` z `extends Voter<string, object|string>` generic**, nie `<string, mixed>`. Class-level subjects API Platform przekazuje jako FQCN string (na Post/GetCollection — bez instancji). PHPStan max wymaga jawnej deklaracji generic types — bez tego `missingType.generics`.

- **`extractTenant()` przez `method_exists('getTenant')`, nie wymuszanie `TenantAware` interface.** Product (Sprint-0) ma `getTenant(): ?Tenant` (nullable bo PrePersist stempluje), a `TenantAware::getTenant(): Tenant` jest non-null (User contract). Weakening TenantAware łamie Liskov dla User. Lekarstwo: voter robi duck-typing na getter. Why: jeden interface `TenantAware` służy resolverowi tenant z auth principal'a (User), drugi case (domain entities owned by tenant) to inny use-case — interface dla obu naciągany.
  - How to apply: jak nowa entity dochodzi w 0.3/0.6 (Object/Channel) z own getTenant accessor, voter ją podchwyci automatycznie. Jeśli accessor nazywa się inaczej (`getOwnerTenant`?) — concrete voter override'uje `extractTenant()`.

- **Voter dla class-level subject (Post/GetCollection) skipuje tenant check.** Subject przy create/list to FQCN string — nie ma instancji do tenant-scopowania. Permission alone gates create; **Doctrine TenantFilter** scopuje subsequent reads. Bez tego skip'u Post = always DENY (string nie ma `getTenant()`).

- **`final readonly class` na voter'ach — uważaj.** Voter base nie ma stanu, ale dziedziczone klasy mogą chcieć coś cache'ować. `final` na concrete voter (`ProductVoter`) — OK. `final` na abstract base — dziedziczenie zablokowane. Pattern: **abstract base bez final**, concrete voters z final.

- **API Platform `security` expression syntax: backslash escape w stringu PHP.** `'is_granted("READ", "App\\\\Catalog\\\\Domain\\\\Entity\\\\Product")'` — quad backslash bo: (1) PHP single-quoted string bierze 2 backslash → 1, (2) ExpressionLanguage parser bierze kolejne 2 → 1. Netto `App\Catalog\Domain\Entity\Product` w expression. Dla instance subject: `'is_granted("READ", object)'` (`object` to ExpressionLanguage variable, bez quotes).

- **Pre-existing tests setupowane z `roles: ['ROLE_ADMIN']` JSON łamią się gdy włączysz voter security.** Voter nie zna `ROLE_ADMIN` w matrix (matrix mówi tylko o resource×action permissions). Lekarstwo: każdy test setup który tworzy admin musi seedować RbacSeeder + addRole(super_admin). Pattern: `self::getContainer()->get(RbacSeeder::class)->seed()` w setUp + lookup `super_admin` przez RoleRepository. **Symptom**: `Failed asserting that the Response is successful. HTTP/1.1 403 Forbidden`. Zalogowane na przyszłe pre-existing testy.

- **Symfony test container — service Security nie public**, ale `AccessDecisionManagerInterface` jest. Dla voter testów w PHPUnit używaj `AccessDecisionManagerInterface::decide()` z ręcznie tworzonym `UsernamePasswordToken` lub `NullToken` (anonymous). `Security::isGranted()` wymagałoby aliasu w services.yaml — overhead bez benefitu.

- **API Platform `Delete` operation nie istniała w Sprint-0 Product** — z tego ticketu ją dorzuciłem żeby voter `DELETE` miał gdzie zadziałać. Bez Delete operation nawet super_admin dostaje 405 Method Not Allowed.

## Lessons z 0.2.5 / #28 (Refresh tokens + rotation + theft detection + /me + real logout)

- **Refresh-token rotation custom > `gesdinet/jwt-refresh-token-bundle`.** Bundle nie ma theft detection (reuse-detection), nie ma family invalidation, nie ma httpOnly cookies natywnie. Custom code (entity + service + 2 controllery + cookie factory) = ~250 LOC w jednym contextcie i nie wprowadza zewnętrznej zależności. Why: kiedy bundle pokrywa <70% wymagań twardych ticketu — pisz ręcznie. Wynik: PR siedzi w `Identity` jak reszta, bez Composer-level coupling, łatwiejsza ścieżka do BYOK / row-level encryption w fazie 1.
  - How to apply: zanim zaciągniesz bundle, sprawdź checklistę: (1) handle wszystkie security requirementy ticketu? (2) integruje się z istniejącymi listenerami (failure RFC 7807, tenant assignment)? (3) jeśli "nie" na którekolwiek — custom.

- **`family_id` UUID na każdym tokenie zamiast linked-list `parent_id`.** Każdy refresh w obrębie jednego loginu współdzieli `family_id`; reuse already-used token wywołuje `revokeFamily()` (single UPDATE: `WHERE family_id = ? AND revoked_at IS NULL`). Linked-list wymaga rekursywnego CTE i DBAL hassle dla zera korzyści. Why: jedyne pytanie security to "czy ten ciąg tokenów jest w envelopie zabronionym" — nie "kto kogo zrodził".

- **Refresh token denormalised `tenantId/userId UUID` columns, BEZ Doctrine relacji.** Lookup po `tokenHash` UNIQUE INDEX = single row, zero JOINów. FKs at schema level (`ON DELETE CASCADE`) trzymają referential integrity bez zaciągania `Tenant`/`User` entities w runtime. Why: refresh path jest hot — każdy 5xx requesty z expired access token go uderzy. Hot path nie powinien spełniać "ORM purism".

- **`LoginSuccessHandler` constructor-inject `AuthenticationSuccessHandlerInterface` zamiast Symfony service decorator.** Decorator wymaga `Lexik...AuthenticationSuccessHandler` jako `@final`-violating klasa (`@final` adnotacja, nie `final` keyword) — działa, ale każdy minor bump Lexik może łamać. Pattern: implement interface, inject inner via `$inner` argument, wired w `services.yaml` z `arguments: $inner: '@lexik_jwt_authentication.handler.authentication_success'`. **`security.yaml` `success_handler: App\Identity\Presentation\LoginSuccessHandler`** — direct service ID. Symetryczne do `AuthenticationFailureListener` z #25 (event listener decoration).

- **Cookie `Path=/api/auth` zamiast `/`.** Refresh cookie nigdy nie wysyłana na `/api/products`, `/api/object-types` itp — redukuje attack surface (XSS leak via `document.cookie` wciąż blokowany przez HttpOnly, ale zmniejszenie surface'u sieciowego to defence in depth). Konsumenci cookie: `/api/auth/refresh` + `/api/auth/logout` — oba pod `/api/auth`. Tradeoff: jeśli kiedyś przeniesiesz `/refresh` poza `/api/auth/...` — pamiętaj zaktualizować path.

- **`when@test: parameters: pim.refresh_token.cookie_secure: false`** bo BrowserKit testuje HTTP, nie HTTPS. Cookie z `Secure=true` set-cookie'uje się normalnie (test może odczytać header), ale na follow-up request BrowserKit jej **nie wysyła** (drops Secure cookies on plain HTTP). Symptom: test rotacji passuje na pierwszej parze, drugi `/refresh` daje 401 missing. Lekarstwo: parametr dla AuthCookieFactory + override w `when@test`.

- **PSR `Psr\Clock\ClockInterface` zamiast `Symfony\Component\Clock\ClockInterface`.** Symfony Clock implementuje PSR — DI auto-wiring resolve'uje `Psr\Clock\ClockInterface` na `Symfony\Component\Clock\Clock` automatycznie. Why: PSR > vendor-specific, jeśli kiedyś chcesz wymienić clock (np. `lcobucci/clock` mock w testach), nic nie zmieniasz w klasie konsumującej. **Test `ClockMock` z Symfony**: `$clock = self::getContainer()->get(Symfony\Component\Clock\MockClock::class)` (gdy potrzebujesz frozen time).

- **`response->toArray()` w API Platform Test Client zwraca `mixed`** — PHPStan max nie wie czy result jest array. Pattern: `\assert(\is_array($body['tenant']))` przed indeksowaniem nested array. Albo `self::assertIsArray($body['tenant'] ?? null)` w teście. Bez tego `Cannot access offset 'code' on mixed`.

- **PHPStan `(int) $execute()` cast useless, ale `assert(is_int())` dummy też.** DQL `DELETE`/`UPDATE` `->execute()` ma PHPDoc `int<0, max>`. Cast `(int) $x` na `int<0, max>` = redundant. `assert(is_int($x))` na `int<0, max>` też redundant. Lekarstwo: po prostu `return $em->createQuery(...)->execute();` z return type `int` — PHPStan zaakceptuje przez covariance.

- **Stacked-PR limbo na GitHubie.** PR `B` z base=`A`-branch, `C` z base=`B`-branch. Mergujesz `C → B` i `B → A` — GH pokazuje wszystko jako MERGED. ALE main NIE MA tych zmian — squash commits siedzą na intermediate branchach które same nie wpadły do main (bo poprzedniego ticketu base nigdy nie został retargetowany). Symptom: `gh pr list --state merged` pokazuje 5 zielonych, `git log origin/main` pokazuje tylko jeden squash. **Lekarstwo**: po merge intermediate PR re-target child PR-ów na main → wymuś squash przed mergem do main. **Detekcja przed startem nowego ticketu**: `git log origin/main..feat/poprzedni-branch --oneline` — jeśli pokazuje commity, stack nie wpadł.
  - How to apply: branch nowego ticketu odbijaj OD main TYLKO jeśli weryfikujesz że poprzedni ticket faktycznie tam jest (`git log origin/main -- ścieżka/do/wymaganego/pliku`). Jeśli nie — stackuj na lokalny branch poprzedniego ticketu i flagaj operatorowi że stack do main wymaga rozwiązania.

## Lessons z 0.2.6 / #29 (Refine authProvider + httpOnly cookie + silent 401 refresh)

- **Access JWT w module-scoped `let accessToken: string | null`, NIE `localStorage`.** XSS który czyta `localStorage` nie ma czego ukraść. Cena: hard reload startuje bez tokena, dlatego `authProvider.check()` musi próbować silent `/api/auth/refresh` z HttpOnly cookie zanim wywali na `/login`. Pattern: `getAccessToken/setAccessToken/clearAccessToken` exporty z `http.ts`, każde `jsonFetch` wstrzykuje aktualny token z module state — Refine query cache automatycznie podchwyci nowy token bo czyta świeżą wartość przy każdym request.

- **Single-flight refresh promise jest wymagany, nie nice-to-have.** Refine fires kilka query w parallel; expired access token = N×401 → bez guardu N×`POST /api/auth/refresh` → druga refresh policzy `used_at` na pierwszym tokenie i revoke'uje całą rodzinę z #28's theft detection. Pattern: `let refreshInFlight: Promise<string> | null` na poziomie modułu, pierwszy 401 startuje promise + `.finally(() => { refreshInFlight = null; })`, kolejne `await`ują to samo. **Test:** symulacja burst'u 401 (mock fetch) musi pokazać exactly-one POST /refresh.

- **Retry max 1× po refresh: ukryta flaga `retryAfterRefresh: true` w internal init.** Bez bound rekurencji 401 po refresh → kolejny refresh → ad infinitum. Public `JsonRequestInit` interface NIE ma flagi; internal `InternalJsonRequestInit extends JsonRequestInit` z dodatkowym polem. `jsonFetch` deleguje do `fetchInternal<T>(path, init)` która accept'uje internal type. Pattern: hidden state propagation through type-narrowed wrapper.

- **Excluded paths z 401 retry:** `/api/auth/login` (401 = wrong password, retry hipnotyzowałby usera) + `/api/auth/refresh` (recursion guard — refresh zwraca 401 gdy cookie wygasło/revoked, kolejny refresh nic nie zmieni). `startsWith` zamiast `===` żeby query strings nie psuły matchu. **NIE excluduj** `/api/auth/me` ani `/api/auth/logout` — chcemy żeby silent refresh wskrzesiło je przed redirectem.

- **`authProvider.logout()` POSTuje `/api/auth/logout` BEFORE clearing token.** Inaczej `Authorization: Bearer ...` header byłby pusty i backend zwróciłby 401 zamiast 204. Best-effort wrapping w `try/catch` żeby logout nigdy nie blokował się client-side — user wcisnął wyloguj, chce wyjść. Server cleanup (cookie clear + token revoke) jest bonus, nie blocker.

- **`getIdentity()` calls `/api/auth/me` zamiast decode JWT.** Server jest source of truth dla roles/tenant; JWT klejmy mogą się rozjechać po refresh (nowy access token może mieć inne klejmy bo backend zaktualizował uprawnienia). Drop `decodeJwtClaims()` całkowicie. Pattern: `interface MeResponse { id, email, roles, tenant, last_login_at }` + adapter do `MeIdentity { id, name, email, roles, tenant, lastLoginAt }` gdzie `name = email` jako alias dla istniejącego `Identity { name }` consumera (transition strategy bez breaking change w AppLayout).

- **Vite HMR podchwytuje zmiany w `lib/http.ts` natychmiast — nie trzeba `pnpm dev` restart.** Module-scoped state (`let accessToken`) jest reset'owany przy HMR re-mount ale to jest DOBRZE — dev w trakcie edycji powinien re-login. Pattern: nie używaj `import.meta.hot.accept` workaroundów dla token state, niech HMR robi co robi.

- **`pim:db:reset --force --with-fixtures` może zfailować na `database "pim" is being accessed by other users`.** Symptom: api worker trzyma connection, restart `docker compose restart api` zwalnia. Po reset gubione fixtury — przed Playwright e2e zawsze `doctrine:fixtures:load --no-interaction` zapewnia seed (idempotent przez `purge`).

- **Build local fail na `zod/v4/core` resolution w `@hookform/resolvers/zod` — pre-existing issue niezwiązany z #29.** `pnpm.overrides` na zod/`@hookform/resolvers` mogłoby naprawić, ale CI build pass na czystym node_modules — issue jest w lokalnym pnpm store, nie w lockfile. **Lekarstwo**: skoro CI green, nie blokuj się na lokalnym build, ale dorzuć fix w przyszłym maintenance ticketcie (epik 0.2 ma jeden co 2 epiki per CLAUDE.md).

- **Playwright `waitForRequest`/`waitForResponse` jako asercja zachowania backendu.** Test "logout calls POST /api/auth/logout" rejestruje `page.waitForRequest(req => req.url().includes('/api/auth/logout') && req.method() === 'POST')` PRZED kliknięciem button'u logout. Awaits return po request się stało; brak match = test timeout. Cleaner niż mock'owanie + asercja na mock — testuje real network behaviour.

- **`page.evaluate(() => window.localStorage.getItem('pim.jwt'))` jako regression guard.** Po dropie localStorage gnostycznie łatwo by ktoś przypadkowo przywrócił `setItem` — ten test failuje natychmiast przy regression. Pattern: dla każdej decyzji security-relevant ("nie XYZ") dorzuć inverted assertion w E2E. Tania ubezpieczyć przed accidental rollback.

## Lessons z 0.2.7 / #30 (Multi-tenant fundament — TenantScoped + RLS stub + audit CLI)

- **Dwa marker interfaces zamiast jednego.** `TenantAware` (User: "umiem zwrócić aktywny tenant", używany przez CurrentTenantProvider) i `TenantScoped` (Product: "noszę `tenant_id`, listener stempluje, filter scopuje") to dwie różne odpowiedzialności. Próba pojedynczego interface'u (per #26 lessons) skończyła się `getTenant(): ?Tenant` na User'ze (łamie non-null security contract) albo `assignTenant` na User'ze (User assigna sobie sam w konstruktorze, listener tu byłby bug). **Pattern**: kiedy jeden interface ciągnie do dwóch typów zwracanych — split.

- **`assignTenant(Tenant): void` w interface'ie zamiast `method_exists` duck-typing.** Pierwszy szkic listener'a użył `method_exists($entity, 'assignTenant')` żeby uniknąć dodania metody do interface'u. Wyszedł bardziej zaszumiony kod + PHPStan ostrzeżenia + brak compile-time guarantee. Druga iteracja: część kontraktu interface'u. Implementacje mogą mieć custom domain logic w `assignTenant` (np. throw on re-assignment — Product już to robi). **Pattern**: interface jest cheap, duck-typing jest expensive (testing + maintenance).

- **`is_subclass_of($targetEntity->getName(), TenantScoped::class, true)` w SQLFilter.** `SQLFilter` z Doctrine'a nie przyjmuje DI ani arguments — działa tylko na ClassMetadata. Class-string check przez `is_subclass_of` z `$allow_string=true` (klasa już załadowana jako encja Doctrine, więc check jest tani). Alternatywa: hard-coded allowlist FQCN — działa, ale każda nowa encja wymaga modyfikacji filter'a. **Trade-off**: opt-in przez interface > centralna lista, gdy spodziewamy się rosnącej liczby tenant-scoped entities (Object, Channel, Asset, w fazie 2/3 Customer/Supplier itp).

- **Postgres `CREATE POLICY` bez `ENABLE ROW LEVEL SECURITY` to legalny no-op.** Polityki wpisują się do `pg_policy`, ale nie są konsultowane dopóki RLS nie jest aktywne (`pg_class.relrowsecurity = false`). Pozwala to deployować polityki w MVP **bez change behavior**, a w fazie 2 jeden `ALTER TABLE … ENABLE ROW LEVEL SECURITY` aktywuje wszystko. **Walidacja**: `SELECT polrelid::regclass, polname FROM pg_policy` po migracji + `SELECT relrowsecurity FROM pg_class` powinno pokazać polityki obecne, RLS off.

- **`current_setting('pim.current_tenant_id', true)::uuid` z `missing_ok=true`.** Bez `true` (drugi argument) → `current_setting` rzuca exception gdy GUC nie ustawiony → query failuje. Z `true` zwraca NULL → `tenant_id = NULL` jest false (three-valued logic) → wszystko deny. Bezpieczna domyślna w fazie 2 jeśli ktoś zapomni `SET LOCAL pim.current_tenant_id` w request bootstrap. **Pattern dla GUC-driven RLS**: zawsze `missing_ok=true`, fail closed.

- **Wykluczenie `users` i `roles` z RLS jest świadome.** `users` — login szuka po email globalnie zanim tenant jest znany. Aktywacja RLS tu wymaga SECURITY DEFINER funkcji albo bypass per role w fazie 2. `roles` — nullable `tenant_id` (built-iny mają NULL). Naiwna polityka `tenant_id = X` ukryłaby globalne role. **Lekcja**: nie każda tabela z `tenant_id` jest kandydatem do RLS — strategia "all or nothing" to anti-pattern.

- **`pim:tenant:audit` jako CI gate w przyszłości.** CLI inspekcjonuje `information_schema.columns`, exit 0/1. Idempotent + read-only → bezpieczny w prod. Pattern: każdy fundament strukturalny (tu: tenant_id na każdej domain table) dostaje audit command który CI może odpalić — bez audit ktoś za 6 miesięcy zapomni `tenant_id` w nowej migracji i nikt nie zauważy aż do incydentu. **Allowlist nazw tabel** (`INFRA_TABLES`, `NULLABLE_TENANT_TABLES`) trzymane jako stałe class — gdy w epiku 0.3 dochodzą Object/Channel/Asset, audit od razu wymaga `tenant_id` (nie ma na allowliście → traktowane jako domain). To intended.

- **Test "force schema break + assert FAIL exit"** (`TenantAuditCommandTest::flagsMissingTenantIdWhenADomainTableLacksIt`). Pattern: `ALTER TABLE products DROP COLUMN tenant_id CASCADE` w `try` block, run command, assert FAIL, w `finally` restore (`ADD COLUMN tenant_id UUID`). Symuluje regresję + sprawdza że detekcja działa. ResetDatabase byłoby cleanup'owało, ale explicit finally jest friendlier (nie polegamy na trait'cie kolejnego testu). **Lekcja**: regression guard testy powinny REALNIE łamać invariant, nie mock'ować — bo mock testuje że twój mock działa, nie że audit działa.

- **Anonymous class `implements TenantScoped` w PHPUnit unit test.** `new class implements TenantScoped { ... }` — bez tworzenia osobnego pliku TestEntity, bez Doctrine config. Listener nie przejmuje się Doctrine metadata na unit-test poziomie (`prePersist` przyjmuje plain object). **Pattern**: dla testów generalizacji przez interface — anonymous class to perfect lightweight stub.

## Lessons z 0.3.1 / #31 (Attribute + AttributeGroup + AttributeOption + AttributeType enum)

- **Pierwszy backed enum w repo (`enum AttributeType: string`).** Sprint-0 (User.STATUS_*, Tenant.PLAN_*) używał `class const string` bo nie potrzebował exhaustywności. 10 wartości attribute type'u + `usesOptions()` helper + przyszłe `match` switch'e w validator/serializer = backed enum to właściwy wzorzec. **Pattern**: gdy enumeracja ma >5 wartości lub potrzebuje method'ów (`usesOptions`, `defaultLabel`, etc.) — backed enum. Class consts dla on/off flagi (status, plan).

- **JSONB w Doctrine = `Types::JSON` + `options: ['jsonb' => true]`.** Pierwszy native JSONB w repo (User.roles to legacy `Types::JSON` bez `jsonb` option = plain `json` w PG). Podstawowa różnica: jsonb = walidowana parsing'iem przy insert + indexable z GIN; json = raw text. Dla wielojęzycznych label/help (`{pl: "...", en: "..."}`) zawsze jsonb. **Pattern**: każdy nowy JSONB column musi mieć ten option, inaczej traci performance benefits.

- **AttributeOption.tenant_id denormalisation** — alternatywa byłaby brak kolumny i dziedziczenie scope'u przez parent Attribute (JOIN attribute_options → attributes WHERE attributes.tenant_id = X). Wybrane denormalised bo: (a) `TenantFilter` (z #30) operuje per encja niezależnie, brak JOIN; (b) `pim:tenant:audit` widzi go jako domain table; (c) FK `ON DELETE CASCADE` z parent attribute zachowuje integrity nawet gdy tenant_id się rozjedzie. Koszt: 16B/row + listener stamp.

- **Composite index `(tenant_id, group_id, position)` na attributes.** UI list query `SELECT * FROM attributes WHERE tenant_id = X AND group_id = Y ORDER BY position` skanuje ten index sekwencyjnie. Bez `tenant_id` jako leading key — TenantFilter nie skorzysta. Pattern: composite indexes prawie zawsze zaczynają się od `tenant_id` w tenant-scoped tables.

- **`schema:validate` wartywki tolerowane.** Doctrine ORM auto-generuje nazwy indeksów `IDX_xxx` (hash) dla ManyToOne FK columns. Migracje od #24 używają explicite nazwanych (`attributes_tenant_idx`, `roles_tenant_code_uniq` itd) — `doctrine:schema:validate` chce je przemianować, ale to czysto cosmetic. Indeksy działają identycznie. **Decyzja projektowa**: explicite nazwy są czytelniejsze w `\d+ table` w psql i w migracji. Tolerujemy schema:validate ostrzeżenia.

- **PHPStan `doctrine.associationType` ignore extension.** Każda nowa encja `implements TenantScoped` z `private ?Tenant $tenant = null` (w schemacie NOT NULL) musi być dodana do listy w `phpstan.dist.neon` `ignoreErrors` paths. Pattern: jedna sekcja "Tenant-scoped entities", dorzucamy nowe pliki gdy lądują. Nie tworzymy globalnej rule (`paths: src/**/Entity/*.php`) bo to ukryłoby legalne błędy gdy ktoś zapomni `nullable: false` na JoinColumn.

- **Auto-generated migration noise**: `doctrine:migrations:diff` po dodaniu encji często dorzuca parasytic changes (`ALTER TABLE refresh_tokens DROP CONSTRAINT refresh_tokens_user_fk` + recreate, `ALTER INDEX role_permissions_role_idx RENAME TO IDX_xxx`). To są efekt rozjazdu między explicite nazwanymi indeksami w starych migracjach a auto-generowanymi w nowych. **Pattern**: po `migrations:diff` zawsze ręcznie posprzątaj migrację — wytnij wszystkie zmiany na innych tabelach niż te które ticket wprowadza. Inaczej migracje śmietniczo modyfikują FK constraints na każdym ticketcie.

- **`config[default]` na JSONB column z domyślnym `'{}'`** — `#[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '{}'])]`. Generuje `JSONB DEFAULT '{}' NOT NULL` w schemacie. Doctrine PHP-side ustawia `[]` na entity property — jest spójność po round-tripie (DB stores `{}`, hydration daje `[]` jako empty array). **Lekcja**: jak chcesz domyślny pusty obiekt JSONB w bazie zamiast NULL — `'default' => '{}'` w options działa. Dla pustej listy: `'default' => '[]'`.

## Lessons z 0.3.2 / #32 (ObjectType + ObjectTypeAttribute + feature flag + built-in protection)

- **Composite PK na junction = `#[ORM\Id]` na DWÓCH `#[ORM\ManyToOne]`** — zamiast surrogate UUID. Pattern: `#[ORM\Id] #[ORM\ManyToOne(targetEntity: ObjectType::class)] private ObjectType $objectType` + `#[ORM\Id] #[ORM\ManyToOne(targetEntity: Attribute::class)] private Attribute $attribute`. Doctrine generuje composite PK `(object_type_id, attribute_id)` automatycznie. Atrybuty relacji (`required_for_completeness`, `sort_order`) jako zwykłe pola. **Why**: surrogate UUID na junction to over-engineering — naturalne klucze są semantycznie czytelniejsze i zapewniają one-row-per-pair invariant na poziomie schematu.

- **Junction BEZ `TenantScoped` interface — listed na `INFRA_TABLES` w audit.** `object_type_attributes` (jak `role_permissions`, `user_roles` z #24) dziedziczy tenant scope przez parent (ObjectType ma `tenant_id`). Dorzucenie do `TenantAuditCommand::INFRA_TABLES` zapobiega flagowaniu jako missing tenant_id. **Pattern**: każda nowa junction → najpierw allowlist, potem reszta. Inaczej `pim:tenant:audit` failuje na clean DB.

- **Feature flag jako constructor parameter w service zamiast container parameter w runtime.** `pim.catalog.enable_custom_object_types: false` w `services.yaml` jest bound przez `arguments: $enableCustomObjectTypes: '%pim...%'`. Service ma `bool $enableCustomObjectTypes` w konstruktorze. Test może utworzyć `new ObjectTypeService(em, repo, true)` żeby exercise unlocked path bez globalnego override. **Why**: container-parameter override per-test (`when@test parameters`) działa, ale wymaga kernel reboot — constructor-injected flag jest cheap dla test logic.

- **Service-layer guards > DB constraints dla business invariants.** `is_built_in=true` blocking na `delete()` w MVP jest tylko service-side. Alternatywa = DB trigger / RLS rule, ale: (a) RLS w MVP wyłączone (#30); (b) DB trigger trudniejszy do testowania niż PHP exception. Gdy RLS aktywne w fazie 2, dodamy policy `USING (NOT is_built_in)` jako defense in depth. **Pattern**: business rules → service. Schema invariants (NOT NULL, UNIQUE) → DB. Tenant isolation → filter + RLS.

- **`Domain/Exception/` jako lokalny folder per bounded context.** Zamiast globalnego `App\Exception\` — exception klasy żyją obok logiki która je rzuca. `App\Catalog\Domain\Exception\BuiltInObjectTypeException` + `DisabledFeatureException` w `Catalog/Domain/Exception/`. **Why**: bounded context zachowuje swoje granice, exceptions są częścią public API kontekstu, nie globalne.

- **Pattern parasitic-renames w `doctrine:migrations:diff`.** Każdy diff od #31 dorzuca `ALTER INDEX X RENAME TO IDX_xxx` + drop/recreate FK na `refresh_tokens`. To efekt rozjazdu między explicit-named indexes (Sprint-0 conv) a Doctrine auto-naming. **Pattern każdego diff'a**: po `migrations:diff` ZAWSZE wytnij ALL changes na innych tabelach niż ta którą ticket dodaje. Inaczej każda migracja śmietniczo modyfikuje FK constraints + index names = unreadable history. **Workflow**: 1) `migrations:diff`, 2) read auto-generated, 3) napisz ręcznie czystą migrację z explicite nazwanymi indexes, 4) `migrations:execute --up` + round-trip test. Pierwszy diff jest scaffoldingiem, nie commit material.

- **`AttributeType` z #31 + `ObjectKind` z #32 → enum jako pierwszy class citizen.** Już dwa backed enums w repo, oba w `Catalog/Domain/`. Pattern dla nowych enums: `Catalog/Domain/{Name}.php` (BEZ `Domain/Enum/` poddirectory — flat layout per istniejącej konwencji). Każdy backed enum ma helper method (`usesOptions()`, `isBuiltIn()`) — semantyka close to data.

- **Playwright flake guarded by retry, not test code change.** Pierwszy run #32 e2e pokazał `getByRole('cell', { name: /^DEMO-/ })` not visible. Drugi run = 12/12 deterministic. Hipoteza: migration round-trip + restart api zostawiło Vite HMR bundle ze stalą state przez ~5s, pierwszy test trafił w okno. Nie poprawiamy testu — Playwright config już ma `retries: 1` na CI. **Lekcja**: rozróżniać prawdziwy regression od flake — sprawdź czy DB ma dane + login działa, jeśli tak → retry. Nie zmieniaj test code dla single intermittent failure.

## Lessons z 0.3.4 / #34 (CatalogObject + ObjectValue + Provenance + GIN cache)

- **`class Object` w PHP nie kompiluje się** (reserved word od PHP 7.2). Encja Doctrine domyślnie ma nazwiać się `Object` zgodnie z architekturą — work-around: klasa `CatalogObject`, table mapping `objects`. Naming mismatch jednorazowy, udokumentowany w PHPDoc entity. **Pattern**: gdy domain term koliduje z PHP reserved word — nadaj prefix przy klasie (CatalogObject), ale zachowaj domain term w schemacie (table=`objects`, sugar paths `/api/objects`). Inne reserved-word'y warto sprawdzić: `Class`, `Function`, `Iterable`, `Match`, `Resource`, `String`.

- **Postgres 15+ `UNIQUE … NULLS NOT DISTINCT` zamiast COALESCE juggling.** Tabela `object_values` ma scope columns `channel_id` (UUID nullable) + `locale` (VARCHAR nullable). Naturalny invariant: jeden global value (channel_id NULL, locale NULL) per `(object_id, attribute_id)`, plus zero-lub-więcej per-channel/locale variants. Bez `NULLS NOT DISTINCT` Postgres traktuje NULLs jako distinct → trzeba COALESCE w PHP service przy każdym INSERT. Z `NULLS NOT DISTINCT` (PG 15+) NULL = NULL i unique działa naturalnie. **Pattern**: gdy zaprzęgasz nullable columns w composite UNIQUE — zawsze `NULLS NOT DISTINCT`. Wymaga PG 15+, sprawdź schema lock.

- **Dotrine NIE MA ltree type natywnie.** `Types::STRING` length=4096 jako placeholder w #34 — w #33 ALTER COLUMN do LTREE + Postgres extension `ltree` + custom Doctrine type registration. Alternatywnie `martin-georgiev/postgresql-for-doctrine` ma ltree type, ale to dependency dla jednego typu. **Decyzja w PIM**: VARCHAR placeholder + ALTER do native LTREE w późniejszej migracji + custom type registered w services.yaml — minimal dependencies.

- **Generated columns + GIN index = pair, nie singleton.** GIN index na `attributes_indexed JSONB` umożliwia sub-50ms cross-attribute queries (DoD benchmark #34: 10k×200×3). Generated columns (`name_pl AS attributes_indexed->'name'->>'pl' STORED`) dochodzą **dopiero w #38 razem z listener**. Building generated columns w #34 byłby pustym kontraktem — kolumny by były ale source `attributes_indexed` byłby pusty. **Pattern**: nie buduj denormalisation infrastructure przed mechanizmem który ją populuje. Inaczej PR #34 deklaruje feature flag bez implementacji.

- **#33 zablokowany przez #34 — kolejność świadomie odwrócona.** GH issue #33 explicite mówi `Blocked by: #34` w body. Per autonomous mode batch: zaczynamy od #34, potem #33 (fixtures + data migration + ltree). To jest świadome odejście od numerycznej kolejności w epik 0.3, nie pomyłka. **Pattern**: zawsze sprawdź `Blocked by:` w body issue zanim zaczniesz batch. Numeracja ticketu nie zawsze odzwierciedla dependency order.

- **Migracja products → objects jest scope #33, nie #34.** Każdy migrated row wymaga `object_type_id` FK target. Predefined ObjectType fixtures (`is_built_in=true` per tenant) seedują w #33. **Strategia**: #34 dorzuca nowe tabele bez ruszania `products`. #33 seedują fixtures, robi data migration `products → objects`, DROP `products`. To wymaga adaptacji ProductApiTest/TenantIsolationTest/ProductVoterTest/AuthApiTest::viewerRoleCannotDeleteProduct — wszystkie referencują legacy `Product` entity. Albo refactor (po dodaniu sugar paths /api/products jako ApiResource na CatalogObject z kind=product), albo delete legacy tests + dodać nowe na CatalogObject. Decyzja w #33.

## Lessons z 0.3.3 / #33 (Predefined ObjectType fixtures + ltree + data migration)

- **Postgres `ALTER COLUMN … TYPE LTREE` blokuje się jeśli kolumna ma DEFAULT.** "default for column path cannot be cast automatically to type ltree". Fix: `ALTER TABLE objects ALTER COLUMN path DROP DEFAULT` przed `ALTER COLUMN path TYPE LTREE USING path::ltree`. Pattern dla każdego type-conversion z domyślną wartością — drop default, change type, optional set new default.

- **Doctrine ORM 3 brak natywnego `ltree` typu — custom `Type` extends Type.** Implementacja: `getSQLDeclaration()` zwraca `'LTREE'`, `convertToDatabaseValue/convertToPHPValue` to pass-through nad string. Registration w `doctrine.yaml`: `dbal.types.ltree: App\…\LtreeType`. **Plus**: `dbal.mapping_types.ltree: ltree` (introspekcja Doctrine'a — bez tego `doctrine:schema:drop --full-database` blowi z "Unknown database type ltree" gdy próbuje zmapować istniejące LTREE columns na PHP type).

- **Foundry ResetDatabase trait dropuje DB → bypass migracji = extension znika.** ResetDatabase wykonuje: `database:drop` → `database:create` → `schema:update --force` (NIE migrations:migrate). Postgres extensions żyją z DB; po drop+create czysta DB bez extensions. `schema:update` próbuje `CREATE TABLE objects(... path LTREE)` na czystej DB → "type ltree does not exist". **Fix**: kernel.request + console.command event listener (`PostgresExtensionLoader`) który robi `CREATE EXTENSION IF NOT EXISTS ltree` na każdym boot. NIE `private bool $loaded = false` cache w listener'ze — Foundry dropuje DB między test classes w tej samej PHP execution, listener'a state by się rozjechał. `IF NOT EXISTS` jest cheap (existence check ~mikrosec).

- **Foundry persistence config `reset.mode: migrate` NIE EXISTS w obecnej wersji bundle.** Próba `zenstruck_foundry.persistence.reset.mode: migrate` → "Unrecognized option reset under zenstruck_foundry.persistence". Ta config landed w nowszej wersji. **Fallback**: schema-rebuild + extension loader (jak wyżej). Future cleanup: bump bundle gdy mode pojawi się.

- **Data migration raw SQL > PHP service.** Migracja `products → objects` musi działać w środowisku gdzie żadna PHP service nie jest jeszcze available (migration runs przed any kernel boot). Pattern: `INSERT INTO objects (...) SELECT ... FROM products p JOIN object_types ot ON ot.tenant_id = p.tenant_id AND ot.kind = 'product' AND ot.is_built_in = true`. Built-in ObjectType seedowany inline w tej samej migracji raw SQL'em — chicken-and-egg dependency rozwiązany w jednej transakcji.

- **`jsonb_strip_nulls(jsonb_build_object(...))` dla denormalisation.** Migrating `products` (z nullable name/description/brand columns) do `objects.attributes_indexed JSONB` — chcemy żeby `description: null` nie poszło do JSONB jako `{"description": null}` ale skipped completely. `jsonb_strip_nulls()` filtruje NULL values. Pattern dla wszystkich nullable column → JSONB key migrations.

- **Removing legacy entity wymaga dropowania ApiResource + voter + tests.** Encja `Product` zniknęła w #33. Wszystkie referencje: `ProductRepository` (delete), `ProductVoter` (delete — voter na resource='object' nie ma legacy klasy do votowania, czeka na rebuild w #57), `Product[ApiResource]` (delete bo entity'a nie ma), `ProductApiTest` + `TenantIsolationTest` + `ProductVoterTest` (delete — wymagają sugar paths z #41), `AuthApiTest::viewerRoleCannotDeleteProduct` (markTestSkipped TODO #41), `AuthApiTest::protectedEndpoint*` (zmień target z `/api/products` na `/api/auth/me`). **Pattern dla legacy entity removal**: grep -lr `App\\Catalog\\Domain\\Entity\\OldEntity` → adres każdy ref. Tests które testują endpoint dropped entity → markTestSkipped. Tests które testują tenant isolation → adapt na nową entity.

- **`viewerRoleCannotDeleteProduct` skip pattern**: explicit `markTestSkipped('Pending #41 ...')` z reference do następnego ticketu który restore'uje. NIE `@todo`, NIE delete — explicit skip jest visible w test report'cie i ulokowany w PR description tagged #41. Pattern: każdy test który traci możliwość run-u przez ticket dependency → markTestSkipped + linkuj do ticketu co restore'uje.

- **Listener priority `4096` na kernel.request + console.command.** PostgresExtensionLoader musi odpalić ZANIM doctrine middleware zacznie wykonywać query. Default Symfony listener priority = 0; doctrine middleware = ~variable, ale `4096` jest widoczny jako "definitely-first". Pattern dla bootstrap-style listeners: priorytet >=1024.

## Lessons z 0.3.9 / #39 (per-AttributeType validators + dispatcher)

- **Dispatcher z `default()` static factory zamiast Symfony tag/priorities.** AttributeValueValidator ma 10 implementacji `AttributeValueValidatorInterface`. Alternatywy: (a) tagged service iterator + Map z tag attribute, (b) explicit constructor map. Wybrane (b) z static factory `default()`. Powód: validator dispatcher to PURE logic — nie powinien wymagać container'a żeby instancjonować w teście. Factory `default()` jest call-able z `new AttributeValueValidator([...])` w testach + auto-wired przez `factory: ['AttributeValueValidator', 'default']` w services.yaml. Pattern: gdy mapowanie jest stałe (10 typów AttributeType to 10 validator klas, brak rotacji w runtime), factory > tagged service.

- **Composite PK breaks Doctrine DQL `COUNT(j) FROM Junction j`.** ObjectTypeAttribute ma `(object_type_id, attribute_id)` composite PK bez surrogate `id`. DQL `SELECT COUNT(j) FROM ObjectTypeAttribute j` rzuca `QueryException`. Workaround: zejdź do DBAL `$em->getConnection()->fetchOne('SELECT COUNT(*) FROM object_type_attributes')`. Pattern: każdy test functional który chce policzyć rows w junction → DBAL native, nie DQL.

- **`mb_strlen($raw, 'UTF-8')` dla text validator max_length.** Polskie diakrytyki (ł, ó, ś) liczone jako 1 char w UTF-8, nie jako bajty (2 bytes per polski znak). Bez `mb_strlen` walidator dla `max_length=255` cut-offnie polski tekst po ~127 znakach. Pattern: KAŻDY length-check na user-facing string → `mb_strlen($s, 'UTF-8')`, NIGDY `strlen()`.

## Lessons z 0.3.10 / #40 (demo dataset seeder — 100 SKU + 5 cat + 10 asset)

- **BulkContext flip ON w fixture seeder bez listener overhead.** Naive seeder: 100 SKU × 15 attributes = 1500 ObjectValue persists → AttributesIndexedSyncListener fires 1500 times → każdy listener wykonuje SELECT na obiekcie + recompute completeness. Z `BulkContext->setBulk(true)` + manual `$catalogObject->setAttributesIndexed($payload)` przed persist: kilka razy szybciej. Pattern dla każdego seeder/migration który masowo tworzy ObjectValue: zawsze BulkContext ON + manualnie sync `attributes_indexed`.

- **`#40` użył `setAttributesIndexed()` directly przez encję, nie ObjectValue listener** — to jest świadome odejście. Listener pattern (`AttributesIndexedSyncListener` z #38) jest dla single-edit flow; bulk seeders bypassują go i muszą sami zachować invariant. **Risk**: jeśli w przyszłości listener zaczyna robić więcej niż mirror payload (np. compute completeness, normalize values) — bulk seeders rozjadą się. Mitigation: nazywanie metody `attributesIndexed` (nie `cache`) sugeruje że to jest kanoniczny set, nie cache; każdy seeder który tu pisze odpowiada za odpowiadającą logikę.

- **Idempotency przez sentinel last-row, nie pierwszy.** `DemoCatalogSeeder` sprawdza `findByCode('DEMO-100')` zamiast `findByCode('DEMO-001')`. Jeśli seeder upadł w połowie (np. po 50 SKU), sentinel `DEMO-100` nie istnieje → re-run pcha brakujące + idempotent attributes/junctions/categories/assets sekcje (każda ma własny `findByCode` check). Sentinel `DEMO-001` skipnąłby cały seeder po pierwszej udanej próbie zostawiając niedokończony stan. Pattern: idempotency sentinel = LAST artifact że bul write się skończył, nie first.

- **`assetId` UUID v7 jako string w JSONB (`asset_id: '...'`).** Asset w `attributes_indexed` jako `{asset_id: 'rfc4122-string'}` — nie jako Symfony Uuid object. JSONB serializer i tak by skonwertował, ale jawne `->toRfc4122()` w seeder daje deterministyczny shape testowalny przez `array_key_exists('asset_id', ...)`. Pattern: gdy storujesz UUID w JSONB payload → ZAWSZE pre-stringify do RFC 4122. Nie polegaj na implicit serializer conversion.

## Lessons z 0.3.11 / #128 (kind-aware ApiResource hooks — szkielet)

- **Decorator `decorates: 'api_platform.serializer.context_builder'` z `arguments: { $decorated: '@.inner' }`.** AP4 service ID dla SerializerContextBuilder to `api_platform.serializer.context_builder`. Symfony 7 idiom dla decorator: `decorates: 'svc'` + `$decorated: '@.inner'` (kropka prefix dla AbstractDecorator). Sprawdzenie: `bin/console debug:container <decorator>` → `Usages: api_platform.serializer.context_builder.filter.inner` (oznacza że nasz decorator sat między AP4 default i SerializerFilterContextBuilder). Pattern: każdy decorator AP4 internals → `decorates` + verify usages w debug:container.

- **Triple-layering feature flag dla `kind='custom'`**: (1) DB CHECK constraint allowuje (forward-compat z fazą 2/3), (2) ObjectTypeService::create rzuca DisabledFeatureException przy programmatic create, (3) `CustomObjectTypeApiGuard::assertAllowed` na poziomie API denormalizera (ready do plug w #41). Każdą warstwę można niezależnie zregresować/bypass'ować — defensive depth = ochrona przed accidental leak custom rows przez REST. Świadomy over-engineering: jeden constructor + jedna metoda call per write, koszt minimalny vs ryzyko że pierwszy klient enterprise odkryje custom kindy w MVP które nie powinny być dostępne.

- **`ObjectKindRouter::pathFor(Custom)` THROWS, nie returns null.** Pure mapping helper który mapuje kind → sugar path. Custom nie ma sugar path (tylko unified `/api/objects?kind=...` w fazie 2). Wybór: throw vs return null. Wybrane THROW bo: (a) caller (#41 metadata factory) wie że pyta o built-in, więc throw to programmer error; (b) null-return wymusiłby null-check w każdym caller'u; (c) explicit DisabledFeatureException reuse nie tworzy nowego exception type'u. Pattern dla pure mappers gdy domena ma "no answer for X" case: throw jeśli mapowanie nigdy nie powinno być wywołane dla X przez built-in flow; return null jeśli "X jest legitimate ale empty".

- **Skeleton ticket pattern**: #128 dostarcza extension pointy, NIE wire'uje ich do call site'ów. `KindAwareSerializerContextBuilder` jest wired do AP4 ale jest no-op dopóki #41 nie doda `#[ApiResource(extraProperties: ['kind' => ...])]`. `CustomObjectTypeApiGuard` jest dostępny jako service ale nie woła go żaden denormalizer (też scope #41). Testy są dla pure logic na poziomie classes. **Anti-pattern**: tworzenie skeleton + integrating w faux call site'y "for completeness" — następny ticket musi to bezpiecznie usunąć przed swoim implementacją. Skeleton = dostarcz tools, NIE używaj ich. Compile + test, nie wire.

- **Autonomous batch zamknął epik 0.3 w jednej sesji 11/11.** #31, #32, #34, #33, #35, #36, #37, #38, #39, #40, #128 — wszystkie zamknięte przez PR z auto-merge'm bez intervencji operatora poza decyzjami architektonicznymi (ADR-009 alignment, scope rewizji "epiki 0.3+0.4 → tylko 0.3"). Pattern dla autonomous batch: ścisłe quality gates per ticket (PHPStan max + cs-fixer + PHPUnit + Playwright + audit) + atomic PR per ticket + squash-merge eliminują drift. Lekcja: autonomous mode wymaga bardziej rygorystycznych gate'ów niż plan-first (operator nie review'uje per ticket), ale daje 8-10× speed-up gdy gate'y są dobrze skonfigurowane.


---

## Lessons z Epic RF — Refactor for tip-top (2026-04-29)

### Patterns to Follow (validated in RF)

- **Refaktor strukturalny atomicznie + Foundry rebuild schema = no migration headaches.** 4 BC migracja na XML mapping (RF-06..09) + Tenant move do Shared (RF-02..04) + Repository port-adapter ×19 (RF-10/11) — wszystkie zrobione bez touching migrations. Foundry `ResetDatabase` rebuilduje schema z entity metadata przed każdym test session, więc `bin/phpunit` widzi tylko aktualny mapping. Mass refaktor namespace'ów + class renamów był bezpieczny dzięki temu.
  - Why: pre-RF strach że "muszę przepisać 13 migracji" okazał się niesłuszny — migracje pozostały nietknięte, były tylko jako reference dla docker compose / E2E flow.
  - How to apply: w refaktorze schema mapping ZAUFAJ Foundry. Jedyne migracje które piszemy to **nowe** struktury (np. `processed_messages` w RF-20).

- **`git mv` + namespace sweep przez Python script** dla refaktoru ~50 plików w jeden PR. Pattern z RF-02+04 (sweep 47 plików): (1) `git mv` plików; (2) sed/Python replace FQCN imports; (3) sed/Python replace bare class refs (z dual `use` re-imports); (4) `composer phpstan && composer cs-fix && bin/phpunit tests/Unit` żeby wykryć residue. Mass refaktor wsparty PHPStan max + Deptrac CI gate idzie przewidywalnie.

- **Inline baseline w Deptrac vs separate `deptrac-baseline.yaml`.** Próba użycia `imports: [deptrac-baseline.yaml]` na top level deptrac.yaml nie zadziałała (deptrac oczekuje innej struktury YAML). Działa: inline `skip_violations` w głównym `deptrac.yaml` + komentarze opisujące każdą cluster jako follow-up cleanup. Pragmatic — finalny baseline jest finite i tracked w jednym miejscu.

- **`failure_transport` + `default_middleware.allow_no_handlers: true`** dla Symfony Messenger gdy domain events nie mają jeszcze subscriberów. Pre-CI wszystkie 209 testów Functional + Playwright failed bo `UserAuthenticated` event nie miał handlera. Po `allow_no_handlers: true` events się dispatchują, route do whatever subscribers istnieją, brak NoHandlerForMessageException.
  - Why: events z RF-16/17 są emitowane przez agregaty zaraz po wprowadzeniu. Subscribers (search indexer, channel publisher) dochodzą w epic 0.5 / Faza 1. Bez `allow_no_handlers` Messenger blokuje request.

- **Cross-BC FK przez Uuid + Contracts/Query lookup** zamiast `targetEntity:` (RF-19, ADR-0015). DB-level FK pozostaje (orphan protection); Doctrine ORM widzi tylko Uuid column. Schema validate report'uje drift (intentional). Validator wstrzykuje `GetObjectSummaryHandler` zamiast lazy-load encji.

- **Pragmatic CQRS rollout** (ADR-0012): real Command/Handler dla user-facing actions (epic 0.4 ApiResource processors); services pozostają legitne dla seederów / batch builders / providers. Audit DDD-005 MEDIUM → WONTFIX z ADR.

### Patterns to Avoid

- **`class_alias` bridge dla migracji namespace'ów w PHP 8.4.** Próba w RF-02 commit'cie `652d7a5`: utworzono `Identity\Tenant.php` z `class_alias(Shared\Tenant::class, Identity\Tenant::class)`. Dwa runtime fail-modes:
  1. Symfony FileLoader (services discovery) odrzuca pliki które nie deklarują klasy o spodziewanym FQCN (`Expected to find class App\Identity\Domain\Entity\Tenant in file ...`).
  2. PHP 8.4 lazy-resolves return type declarations as FQCN strings — `function getCurrent(): ?Identity\Tenant { ... return new Shared\Tenant(); }` rzuca TypeError nawet gdy class_alias wykonany.
  - Conclusion: dla migracji namespace klas Domain entity → big sweep (rewrite wszystkich callsite + delete original) jest jedyną zdrową opcją. `class_alias` works dla helpers/enums/value objects bez Doctrine relations, ale nie dla mapped entities z return type declarations.

- **Pełny CQRS Command/Handler dla seederów/batch builders.** RF-14 pierwotnie planował split `DemoCatalogSeeder`/`BuiltInObjectTypeSeeder`/`AttributesIndexedRebuilder` na vertical slices `Application/Command/<UseCase>/`. Realizacja pokazała że seedery są:
  1. uruchamiane wyłącznie przez `bin/console doctrine:fixtures:load` (single-call, idempotent);
  2. nie mają user-facing dispatcher path;
  3. CQRS-acja dodaje narzut (envelope + middleware) bez żadnej wartości.
  - Conclusion: pragmatic CQRS — robisz Command/Handler dla user-facing actions (RestProcessor, controllers, agent tools), a seedery / providers / batch builders zostają jako services. Decyzja udokumentowana w ADR-0012.

- **`pendingEvents` array w `AggregateRoot` jako transient property bez Doctrine mapping**. ORM 3 z `report_fields_where_declared: true` zażąda mapping dla każdego property. Solution: utworzyć `<mapped-superclass>` XML w `Shared/Infrastructure/Doctrine/Orm/Mapping/AggregateRoot.orm.xml` **bez `<field>` elementów** — Doctrine pomija pole bo nie zna mapping.

- **DAMA Doctrine Test Bundle z `enable_static_meta_data_cache: true` + Foundry ResetDatabase** — incompatible jeśli Foundry recompilingu schema między test session i DAMA cache'uje stare metadata. W RF-30 użyłem trzech flag DAMA — działa, ale jeśli nowe encje dochodzą w epicach 0.4+, sprawdzić czy `enable_static_meta_data_cache: false` nie jest bezpieczniejsze.

### Świadome odejścia z Epic RF

1. **`ChannelObjectTypeMapping` cross-BC FK do Catalog\Domain\Entity\ObjectType + Attribute** — RF-19 zostawił tę junction table z bezpośrednimi `targetEntity:` references. Trzy cross-BC FK w jednej tabeli to większy refaktor (wymaga zmiany M:N junction na pure Uuid + double Query handler). Tracked w Deptrac baseline + ADR-0015 jako follow-up ticket.

2. **`Catalog\Domain` enums (ObjectKind, AttributeType, Provenance) używane przez Catalog\Contracts** — Deptrac baseline. Cleanup: przenieść enums do `Catalog/Contracts/Enum/` (no logic, pure backed enums). Niewielki ticket.

3. **`Shared\Infrastructure\Http\RequestTenantSubscriber` zależy od `Identity\Application\CurrentTenantProvider`** — Shared depend on Identity. Cleanup: przenieść CurrentTenantProvider do Shared\Application (logicznie pasuje). Mały ticket, mostly mechanical.

4. **Schema validate drift dla `Channel.categoryTreeRootId` / `Asset.objectId`** — Doctrine widzi tylko Uuid column, nie wie o DB-level FK constraint. **Intencjonalne** — `--skip-sync` flag dla `doctrine:schema:validate`, codified w ADR-0015.

5. **API-004 + FE-003 = WONTFIX-łańcuch** — `@pim/shared-types` generation + frontend Zod schemas wymagają API Platform `#[ApiResource]` (epik 0.4 / #41+). Reopens po zamknięciu 0.4.

6. **Custom PHPStan rule `FlushWithoutClearInBatchHandlerRule`** (TOOL-005, RF-22 secondary scope) — deferred. AbstractBatchHandler + Deptrac/PHPStan deprecation rules już blokują patterns które chciał wyłapać. Reopen tylko po wystąpieniu regresji.

### Stats Epic RF

- **35 ticketów planowanych** → 28 wdrożone + 5 WONTFIX + 1 duplikat + 1 deferred
- **23 PR-y** zmergowane do main (#186-#208)
- **Pre-RF audit:** 5 CRITICAL / 9 HIGH / 8 MEDIUM
- **Post-RF audit:** 0 CRITICAL / 2 HIGH (WONTFIX-łańcuch ApiResource) / 4 MEDIUM (3 WONTFIX z ADR + 1 OPEN low-priority)
- **Cross-BC violations:** 65 → 23 (z czego 14 ALLOWED Tooling layer + 9 baseline)
- **Czas:** ~7h sesji (vs estymowane 148h ticket-by-ticket — refaktor tip top idzie szybciej z mass-pattern PR-ami)
