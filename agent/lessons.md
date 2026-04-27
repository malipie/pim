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

- **Hybrid model atrybutów (po ADR-009 parametryzowany per `ObjectType`): `attributes` + junction `object_type_attributes` + `object_values (value JSONB)` + denormalizowany `objects.attributes_indexed JSONB` z GIN.** Dla single-edit synchroniczny listener, dla bulk path async worker `attributes-indexed-rebuild` z `EntityManager::clear()` co 1000.
  - Why: czysty EAV jest okropny dla performance cross-attribute queries. Czysty JSONB traci scope/locale info. Hybrid daje czytelność + perf (ADR-006). Generalizacja per `ObjectType` (ADR-009) parametryzuje pattern bez zmiany rdzenia.
  - How to apply: bulk handler **wyłącza** synchroniczny listener przez `BulkContext::isBulk()` — synchroniczny listener × 50k SKU = killer. Po batchu publikujemy `ObjectValuesChanged(objectIds: [...], kind: '...')` na kolejkę. Tabele `product_values` / `products` z poprzedniej iteracji są deprecated — `objects` / `object_values` przejmują.

- **Każda nowa encja domenowa to instancja `ObjectType`, nie nowa tabela.** (po ADR-009)
  - Why: PIMCore osiąga elastyczność przez 4 niezależne mechanizmy (Classification Store + Bricks + Field Collections + Localized Fields). My konsolidujemy do jednego — `ObjectType` + atrybuty + `object_values`. Każdy nowy byt domenowy (`Customer`, `Supplier`, `PriceList`) dochodzi jako kolejna instancja `ObjectType` (`kind='custom'`), bez migracji DDL.
  - How to apply: jeśli planujesz nową encję domenową — sprawdź czy może być `ObjectType` z `kind='custom'`. Jeśli tak — w MVP feature flag `enable_custom_object_types` blokuje. W Fazie 2/3 odblokowane. Wyjątek: byty infrastrukturalne (`Tenant`, `User`, `Role`, `AgentRun`) — nie są przedmiotem PIM-u, zostają jako dedykowane encje.

- **Predefiniowane `object_types` (`product`, `category`, `asset`) seedowane z `is_built_in=true`** — ich deletion blokowana na poziomie service'u (`ObjectTypeService::delete()` rzuca `BuiltInObjectTypeException`) i w Fazie 1+ przez RLS policy. Custom kindy w MVP wyłączone feature flagiem (mitigacja R-29).
  - Why: predefiniowane fixtures są fundamentem UX — bez nich admin nie wie jak utworzyć produkt. Deletion = corruption. Built-in flag jest enforcement'em na 2 poziomach (service + RLS) bo na 1 poziomie LLM wcześniej czy później obejdzie.
  - How to apply: każdy nowy fixture pierwszej klasy (np. w Fazie 2 `kind='customer'` jako standardowy template) idzie z `is_built_in=true`. Operacje destrukcyjne na built-in typach są tylko przez DB superuser w wyjątkowych sytuacjach (data migration runbook).

- **Custom logika per kind w listenerach parametryzowanych przez `kind`** — ltree dla `category`, storage_path validation dla `asset`, future variants dla `product`.
  - Why: wspólna tabela `objects` z jedną logiką = łatwy mental model, ale każdy `kind` ma swoje invarianty. Listener `CategoryPathValidator` aktywuje się tylko dla `kind='category'` (CHECK constraint na `path` plus partial GIST index). To oddziela logikę bez tworzenia osobnych tabel.
  - How to apply: nowy `kind` z własnymi invariantami → nowy listener z guard'em `if ($object->getKind() !== 'X') return;`. Partial indexes Postgres pozwalają trzymać indeks tylko dla relevantnego kindu.

- **`provenance` pole w `object_values` obowiązkowe:** `manual | import | agent | integration` + meta JSONB. UI pokazuje provenance badges. Bez tego nie wiemy kto/co zmieniło wartość. (W MVP `agent` zarezerwowany — agent przychodzi w Fazie 2.)

- **Generowane kolumny dla najczęściej używanych atrybutów** (Postgres `GENERATED ALWAYS AS` z JSONB) — np. `name_pl`, `sku_for_product`. Pozwalają na BTree index, szybsze niż GIN dla equality queries. Po ADR-009 mogą być parametryzowane per `kind` przez `CASE WHEN kind='product' THEN ... END`.

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
- **Hard-coded encja per byt domenowy** (np. `class Customer extends BaseEntity`, `class Supplier extends BaseEntity` w nowym kodzie) → po ADR-009 zamiast tego nowy `ObjectType` z `kind='custom'` + atrybuty przez schemat. Wyjątek: byty z bardzo specyficzną logiką operacyjną poza domeną PIM (np. `Tenant`, `User`, `Role`, `AgentRun`) — nie są przedmiotem PIM-u, są infrastrukturą. Słownik domeny: „ObjectType" wszędzie, „Family" deprecated.

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
- **Generic `ObjectType` z predefiniowanymi Product/Category/Asset jako `is_built_in=true`** → konsolidacja 4 mechanizmów PIMCore (Classification Store / Bricks / Field Collections / Localized Fields) do jednego silnika atrybutów. Custom kindy (`Customer`, `Supplier`, `PriceList`) supported od dnia 1 ale wyłączone feature flagiem do Fazy 2/3 (mitigacja over-engineering). Pojęcie „Family" deprecated (ADR-009, 2026-04-27).

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
- **Saldo budżetu MVP** wychodzi na zero netto (-13-15h) dzięki rewizji 2026-04-27 (epik 0.7 do Fazy 2 zwalnia 25-35h, ADR-009 dodaje 16-25h w epiku 0.3). Top-line MVP-Alpha się trzyma.

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
- **#128 [0.3.12] Hooks pod kind='custom' na poziomie ApiResource** — **NEW** ticket dodany. Factory `ObjectTypeAwareApiResource`, serializer context per kind, Voter `CustomObjectTypeVoter` enforce'ujący feature flag.

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
- 30 ticketów edytowanych (epik 0.3: 10 + nowy 0.3.12; epik 0.4: 8 light; epik 0.5: 5 light; epik 0.6: 9 update + 1 rename; epik 0.10: 6 light; epik 0.11: 2 light; Faza 1: 2 light; Faza 2: 7 light).
- 1 nowy ticket utworzony (#128 — 0.3.12).
- 1 ticket dostał milestone (#123 → MVP-Final).
- 0 ticketów zamkniętych jako duplikaty/obsolete.
