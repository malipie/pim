# Current Status

## Sub-faza: MVP-ALPHA — epik 0.2 IN PROGRESS. #24 + #25 MERGED indywidualnie. **#26 + #27 + #28 squash-merged razem do main jako PR #136 / `4aae6d9`** (rozwiązanie stacked-PR limbo — operator wybrał opcję 1: jeden combined squash zamiast retargetowania). Pozostały tickety: **#29** (Refine authProvider + httpOnly cookie + auto-refresh) i **#30** (tenant_id wszędzie + RLS policies). Następny: #29.

## Ostatnie 3 akcje
1. **#26 + #27 + #28 squash-merged do main jako PR #136 (`4aae6d9`)** (2026-04-28). Trzy tickety w jednym squash commicie po wykryciu stacked-PR limbo: PR #134 (#27) i PR #135 (#26) zostały zmergowane stacked-PR-style (base=intermediate branch zamiast main), GitHub pokazywał MERGED ale main ich nie miał. Naprawa: odbicie nowego brancha `feat/0.2.5-auth-endpoints` od main, cherry-pick #27 + #26 z `feat/0.2.3-voters` (z `-X theirs` dla agent docs konfliktów), implementacja #28 na top, jeden squash-merge do main. Operator zaakceptował utratę granularności w main history w zamian za czysty branch state. **Combined PR #136 closed:** #26, #27, #28 (GH issues closed razem).
2. **#28 (0.2.5) Auth endpoints — RefreshToken + rotation + theft detection + /me + real logout** (2026-04-28, branch `feat/0.2.5-auth-endpoints`). **Custom RefreshToken entity** (`src/Identity/Domain/Entity/RefreshToken.php`) — `tenantId/userId/familyId UUID + token_hash SHA-256(64) UNIQUE + issuedAt/expiresAt/usedAt/revokedAt`. Bez Doctrine relacji (denormalised UUID columns) bo refresh path jest hot — single-row lookup po hash, brak JOIN. **`family_id`** = wszystkie tokeny z jednego loginu w jednej rodzinie; reuse already-used token revoke'uje całą rodzinę jednym UPDATE (`RefreshTokenRepository::revokeFamily()` przez DQL). **`RefreshTokenService`** (`Application/`) — `issueForUser` / `rotate` (throws `RefreshTokenException` z reason `missing|invalid|expired|revoked|reused`) / `revoke` (idempotent, no-throw). Raw token = 32 bajty random_bytes → base64url (~43 chars). **`AuthCookieFactory`** — single source of truth dla `Set-Cookie`: HttpOnly + SameSite=Strict + Secure (override do false `when@test` bo BrowserKit drops Secure cookies on plain HTTP) + Path=`/api/auth` (cookie nigdy nie wysyłana na `/api/products` itp). **`LoginSuccessHandler`** (NIE Symfony decorator, lecz constructor-inject) wraps Lexik `AuthenticationSuccessHandlerInterface` → call inner → `User::recordLogin()` + flush → `issueForUser` → attach cookie. Wired w `security.yaml` `success_handler: App\Identity\Presentation\LoginSuccessHandler`, NIE direct lexik. **Endpointy:** `POST /api/auth/refresh` (anonymous w access_control bo caller ma expired access) + `GET /api/auth/me` (`{id,email,roles,tenant:{id,code,name},last_login_at}`) + `POST /api/auth/logout` rewritten (revoke + clear cookie, 204 idempotent). **Migration `Version20260428171723`** — `refresh_tokens` table, FK tenant_id/user_id ON DELETE CASCADE. **Tests:** 11 nowych (RefreshTokenApiTest 9 + MeEndpointTest 2): login-issues-cookie, login-records-last-login, refresh-rotates, **reused-token-revokes-family** (DB asserts wszystkie tokeny w family revoked), expired-401, missing-cookie-401, unknown-cookie-401, logout-revokes-and-clears, logout-without-cookie-idempotent, me-returns-current-user, me-without-token-401. **Quality gates:** PHPStan max + cs-fixer + PHPUnit 62/62 + Playwright 9/9 + manual smoke (login → me → refresh + reuse old → 401 reused + family revoked w DB → logout). **Świadome odejścia:** (a) **brak `gesdinet/jwt-refresh-token-bundle`** — bundle nie ma theft detection ani family invalidation ani httpOnly cookies, custom code = 200 linii w jednym kontekście; (b) `RefreshToken` denormalised (UUID kolumny zamiast `ManyToOne`) — refresh path hot, lookup tylko po hash, FKs at schema level enforce'ują integrity; (c) `LoginSuccessHandler` constructor-injection wraps Lexik handler zamiast Symfony service decoration — cleaner contract, immune do Lexik internal class changes; (d) refresh response zwraca `{token}` w body **i** `reason` w error responses (RFC 7807 `+reason` extension field) bo client często chce branchować bez parsowania detail; (e) cookie `Path=/api/auth` zamiast `/` — refresh cookie nigdy nie leakuje do `/api/products` requests, redukuje attack surface; (f) **NIE rozszerzono `AuthApiTest`** — istniejące `loginWithValidCredentialsReturnsJwt` i `logoutWithValidTokenReturns204` przeszły bez zmian, dodanie cookie do response jest backwards-compatible. **Repo gotcha:** PR #134 (#27) i PR #135 (#26) zostały na GitHubie merged stacked-PR-style — base=intermediate branch, nie main. Lokalne `feat/0.2.3-voters` ma squash commits (ef63abb #25, 503a080 #27, 64cfe7c #26), main ma tylko #25 (`dc4917c`). Branch `feat/0.2.5-auth-endpoints` jest nadbudowany na `feat/0.2.3-voters`. Operator musi zdecydować jak rozwiązać stack przed merge do main.
2. **#26 (0.2.3) Voters dla Product (proof-of-concept) + AbstractRbacVoter** (2026-04-28, branch `feat/0.2.3-voters`, stack na #27). **Plan B (zwalidowany przez operatora):** zaimplementuj infrastructure (`AbstractRbacVoter` + tenant ownership check) + zastosuj na istniejącym `Product` jako proof-of-concept; voters dla object_type/attribute/channel dochodzą w 0.3/0.6 razem z encjami. **`AbstractRbacVoter`** w `src/Identity/Infrastructure/Security/` — abstract klasa generic-typed `<string, object|string>`: lookup permission z M2M user→roles→permissions, plus tenant ownership check (`extractTenant()` przez `method_exists('getTenant')` — Product używa `?Tenant`, niezgodny z `TenantAware::getTenant(): Tenant`). Class-level subjects (FQCN string z Post/GetCollection) skip tenant check — Doctrine TenantFilter scopuje subsequent reads. **`ProductVoter`** w `src/Catalog/Infrastructure/Security/` — resource='object' (post-ADR-009 alignment), mapuje READ/CREATE/UPDATE/DELETE → read/write/write/delete. **API Platform Product[ApiResource]** — security strings na każdej operacji + dodano `Delete` operation (Sprint-0 nie miał). **Tests:** 14 nowych w `ProductVoterTest` (12-case decision matrix: 4 role × 3 actions z subset of cross-tenant cases) + classLevelCreate + anonymousTokenAlwaysDenied; rozszerzony `AuthApiTest::viewerRoleCannotDeleteProduct` (viewer → GET 200 + DELETE 403). **Repair existing tests:** AuthApiTest/TenantIsolationTest/ProductApiTest setup'y używały `roles: ['ROLE_ADMIN']` w JSON — voter ich teraz nie autoryzuje, więc dorzucony seed `RbacSeeder` + `addRole(super_admin)`. **Quality gates:** PHPStan max + cs-fixer + PHPUnit 51/51 + Playwright 9/9 + manual smoke (admin GET=200, admin DELETE=204 na realnym IRI). **Świadome odejścia:** (a) **stuby voterów dla nieistniejących encji nie pisane** — pattern udowodniony przez ProductVoter, voters w 0.3/0.6 to 5-liniowy boilerplate; (b) `extractTenant()` przez `method_exists` zamiast wymuszania `TenantAware` interface — Product Sprint-0 ma `?Tenant`, weakening TenantAware łamałoby User non-null contract.
2. **#27 (0.2.4) RBAC seeder + getRoles() merge** (2026-04-28, branch `feat/0.2.4-rbac-seeder`). **Source of truth:** `src/Identity/Domain/Rbac/RbacMatrix.php` — 13 resources × 4 actions = 52 permissions, 4 globalne role (super_admin/catalog_manager/integration_manager/viewer). **`RbacSeeder` service** — idempotentny, bezpieczny przy re-run (sprawdza permission/role po code, dodaje/usuwa permissions z M2M jeśli się różnią od matrix). **CLI command `pim:rbac:seed`** wraporzuje seeder z report'em (created/updated counts). **AppFixtures** woła seeder przed persistencją userów + admin fixture dostaje `super_admin` przez M2M (`addRole($superAdmin)`) zamiast legacy `['ROLE_ADMIN']` w JSON. **`User::getRoles()` cleanup:** mergguje JSON legacy + `'ROLE_'.strtoupper($role->getCode())` z M2M + ROLE_USER floor, deduplicated. JSON column zostaje (legacy fallback dla testów Sprint-0 które tworzą Userów ręcznie z `roles: ['ROLE_ADMIN']`). **`docs/rbac.md`** — matrix dokumentacja + dev workflow. **Quality gates:** PHPStan max + cs-fixer + PHPUnit 34/34 (3 nowe w `RbacSeederTest`: matrix shape, idempotency, getRoles merge) + Playwright 9/9. **Manual smoke:** admin demo login → JWT payload `roles: ["ROLE_SUPER_ADMIN", "ROLE_USER"]`, DB pokazuje 1 role × 52 permissions per admin. **Świadome odejścia:** (a) `final readonly class` na `RbacSeeder` nie zadziała bo mutuje counter pola — `final class` z `private readonly $em/$permissions/$roles` w konstruktorze; (b) JSON column nie jest droppowany migracją — legacy fallback dla pre-existing testów; pełen drop w post-MVP cleanup. **Repo workflow gotcha:** stack #27 na #25 wymagał najpierw rebase #25 na main (po merge #24), bo #25 branch był stworzony z main przed #24 merge. Force-push z `--force-with-lease`.
2. **#25 (0.2.2) Symfony Security z JWT — done** (2026-04-28, branch `feat/0.2.2-security-jwt-form-login`). **Świadomy redesign body ticketu vs Sprint-0:** drop FormLogin authenticator i CSRF protection — admin to React SPA + Refine, backend nie servuje server-rendered HTML. JsonLogin endpoint z #4 zostaje, dochodzi: (a) **explicit Argon2id hasher** w `security.yaml` (memory_cost 65536, time_cost 4 — OWASP 2024 baseline; `when@test` używa argon2id z libsodium-min memory_cost=64, time_cost=3, daje `$argon2id$` PHC string), (b) **`AuthenticationFailureListener`** w `src/Identity/Presentation/` mapujący LexikJWT default response (`{code, message}`) na RFC 7807 Problem Details (`application/problem+json` + `{type, title, status, detail}`), (c) **`LogoutController`** placeholder na `POST /api/auth/logout` zwracający 204. Quality gates: PHPStan max + cs-fixer + PHPUnit 26/26 + Playwright 9/9. **GH bodies updated:** #25 (drop FormLogin/CSRF), #26 (FamilyVoter → ObjectTypeVoter post-ADR-009), #28 (przejmuje `/api/auth/me` + refresh rotation + theft detection + httpOnly cookie).
2. **#24 (0.2.1) RBAC schema baseline** (2026-04-28, PR #132 MERGED). Pierwszy ticket epiku 0.2 — Role/Permission encje + M2M user_roles/role_permissions, User.status + last_login_at + assignedRoles M2M, Tenant.domain + plan.
3. **Epik 0.1 ZAMKNIĘTY — Infrastructure i fundamenty** (2026-04-28). 7/7 ticketów (#17-#23) closed. **Audit recon ujawnił że 4 z 7 było faktycznie zrobione w Sprincie 0** (#18 docker-compose pełna forma, #21 GitHub Actions CI 3 workflows, #22 husky + lint-staged + commitlint, #23 baseline migrations) — zamknięte komentarzami audytu z linkami do Sprint-0 PR-ów. **Nowa praca w jednym PR (`feat/epic-0.1-foundations`):** (a) **#17:** CONTRIBUTING.md (Conventional Commits, branch naming, DoD, hook expectations) + LICENSE (UNLICENSED proprietary) + README refresh (stack components table, Backup&Restore section, Sprint 0 status); (b) **#19:** scaffolding 5 brakujących bounded contexts (`Channel`, `Asset`, `Integration`, `Agent`, `ApiConfigurator`) z 4-warstwowym DDD layout (`Domain/`, `Application/`, `Infrastructure/`, `Presentation/`) + `README.md` per kontekst tłumaczący zakres + który epik dorobi implementację; Catalog + Identity (już istniejące ze Sprintu 0) doequalizowane do 4-warstwowego layoutu; (c) **#20:** 5 placeholder pages w admin (`/attributes`, `/object-types`, `/categories`, `/assets`, `/channels`) używających `<ComingSoon resource epic issue />` komponentu (link do GH issue + i18n PL/EN), sidebar rozszerzony z 6 navigation entries + "Wkrótce/Soon" badge dla niezimplementowanych; (d) **#23:** `pim:db:reset` CLI command w `apps/api/src/Maintenance/DatabaseResetCommand.php` — drop+create+migrate (+optional fixtures) w jednym shocie z guards na APP_ENV=prod (`--force-prod` required). **Per-context migrations dirs odrzucone jako over-engineering** (single Postgres, Symfony default OK).
2. **Ticket #15 (0.0.15) zamknięty** (PR #130 squash-merged 2026-04-28 jako `868b87c`). pgBackRest + WAL stub w docker-compose, restore test passing. **Topologia:** custom `pim-database:local` image (postgres:16-alpine + pgbackrest 2.57 + dcron) — `archive_command='pgbackrest --stanza=pim archive-push %p'` pcha WAL ciągle do MinIO bucket `pim-backups`, hourly cron (`/etc/crontabs/postgres`) odpala `pgbackrest backup`, `pim-init-backup.sh` w tle robi stanza-create + initial full backup gdy postgres jest ready. **TLS gotcha:** pgBackRest 2.57 hard-coduje HTTPS dla S3 (defaultowy `repo-storage-port=443`, brak HTTP-mode), MinIO chodzi po HTTP — dodany sidecar `minio-tls` (Caddy 2-alpine, `tls internal` + `header_up Host {host}` żeby AWS SigV4 HMAC się zgadzał) jako TLS terminator między `database` a `minio:9000`. **Świadome odejścia:** (a) `archive-async=n` zamiast `=y` — async worker trzyma lock na `/tmp/pgbackrest/pim-archive-1.lock` i blokuje stanza-create/backup; sync archive_command jest fine dla write rate dev/MVP single-pilot; (b) cron in-container przez busybox dcron + custom entrypoint `start-pim.sh` (zamiast docker-socket sidecar lub k8s CronJob — zostawione do 0.11.11); (c) pojedynczy obraz postgres+pgbackrest zamiast prawdziwego pgBackRest server-mode TLS — kanoniczny pgBackRest deployment wymaga albo same-host (nasz przypadek) albo SSH/TLS server, mid-pattern z shared-volume sidecar nie istnieje natywnie. **Test scenario:** `scripts/test-pgbackrest-restore.sh` — login → insert 3 markery → `pg_switch_wal()` + `pgbackrest --type=incr backup` → `DELETE FROM products WHERE sku LIKE 'restore-test-*'` → `scripts/pim-backup-restore.sh --type latest --no-confirm` → re-auth + count. **Wynik:** baseline 1005 → post-insert 1008 → post-delete 1005 → **post-restore 1008** ✅ (markery wróciły z backupu). Initial full backup: 37.9 MB DB → 5 MB compressed in repo, 8.9 s. Incremental: 488 KB → 62 KB, ~2 s. Komponenty: `docker/postgres/{Dockerfile,pgbackrest.conf,start-pim.sh,pim-init-backup.sh,pim-cron.sh}`, `docker/caddy/Caddyfile.minio`, `scripts/{pim-backup-restore.sh,test-pgbackrest-restore.sh}`, `docs/runbook/restore.md`, `pnpm backup:{run,info,restore,test}`.
2. **Korekty post-audyt ADR-009** (2026-04-28). Self-audit pracy z 2026-04-27 ujawnił 12 znalezisk; naprawione 9 (F-001..F-004, F-006..F-009, F-010). **F-001 krytyczny:** DDL `channels` w §5.2 architektury referował nieistniejącą tabelę `categories(id)` (po ADR-009 zmigrowana do `objects`) — naprawione na `category_tree_root_object_id REFERENCES objects(id)` z walidacją `kind='category'` przez listener. **F-002:** §8.2 + §8.4 architektury usunięto „rodziny" (sąsiednie sekcje rozjeżdżały się słownictwem z §8.3 zaktualizowanym wcześniej). **F-003:** plan §3.1 / §3.2 / ticket 0.2.3 + 0.7.3 + Faza 2 #65 — usunięto relikty „Family". **F-004:** estymaty Fazy 0 przeliczone — single source of truth to sumy epików §3.3 + milestone tabela §3.4. Faza 0 pełna **170-235h** / okrojona **156-216h** (poprzednio błędnie 188-260h / 174-241h). **F-006/F-007/F-008:** issues #36 (Channel + ChannelObjectTypeMapping), #65 (Tool definitions), #41 (ApiResource) — title + Cel + Zakres przepisane (wcześniej tylko Aktualizacje announce'owały rename). **F-009:** CLAUDE.md commit example zaktualizowany. **F-005:** renumeracja epiku 0.3 — plan §3.3 skonsolidowany (0.3.3 fixtures + 0.3.5 ltree zlepione w jedno 0.3.3, bo fixtures dla `category` MUSI mieć ltree), GH #33 `[0.3.5]` → `[0.3.3]` (body rozszerzone o fixtures dla wszystkich trzech built-in kindów), GH #128 `[0.3.12]` → `[0.3.11]`. Epik 0.3 ma teraz 11 ticketów spójnych z numeracją GH. **Pominięte do follow-up:** F-011/F-012 (kosmetyczne — internal spec checklist self-inconsistency).
3. **Korekty post-audyt ADR-009** (2026-04-28, zmergowane w PR #130). Self-audit pracy z 2026-04-27 ujawnił 12 znalezisk; naprawione 9 (F-001..F-004, F-006..F-009, F-010). **F-001 krytyczny:** DDL `channels` w §5.2 architektury referował nieistniejącą tabelę `categories(id)` (po ADR-009 zmigrowana do `objects`) — naprawione na `category_tree_root_object_id REFERENCES objects(id)` z walidacją `kind='category'` przez listener. **F-004:** estymaty Fazy 0 przeliczone — Faza 0 pełna **170-235h** / okrojona **156-216h**. **F-005:** renumeracja epiku 0.3 (0.3.3 fixtures + 0.3.5 ltree consolidated → 0.3.3, GH #33 `[0.3.5]` → `[0.3.3]`, GH #128 `[0.3.12]` → `[0.3.11]`). Pominięte do follow-up: F-011/F-012 (kosmetyczne).
> **Wcześniej:** ADR-009 (Generic ObjectType) + audit 91 ticketów GH (PR #127 + #129 squash-merged 2026-04-27). Generic `ObjectType` z predefiniowanymi Product/Category/Asset (`is_built_in=true`) + custom kindy (`Customer`/`Supplier`/`PriceList`) odblokowane w Fazie 2/3 feature flagiem. Pojęcie „Family" deprecated. Sugar paths `/api/products|categories|assets` zachowane. Plus #14 (perf k6: 10 VUs p95=105 ms) i #13 (FrankenPHP memory: 50k=14 MiB FLAT z clear).

## Bieżący stan
Sprint 0 = **13/13 ZAMKNIĘTE** (gate GREEN 2026-04-28). Milestone GH #1 closed.

**MVP-Alpha — epik 0.1 ZAMKNIĘTY 7/7. Epik 0.2 — 5/7 ticketów ZAMKNIĘTYCH (#24 #25 #26 #27 #28).** Pozostałe tickety w epiku: #29 (Refine authProvider + httpOnly cookie + auto-refresh), #30 (tenant_id wszędzie + RLS).

**Następny krok:** #29 (Refine authProvider + httpOnly cookie consumption + 401 silent refresh).

Stack on-disk: docker compose ready (`pnpm stack:up`); custom `pim-database:local` image z pgbackrest+dcron + `minio-tls` Caddy sidecar zostają z #15. Backup repo MinIO `pim-backups` z full + incr backup'ami siedzącymi w nim po teście #15 — przy następnej sesji są dostępne lub mogą być wyzerowane przez `pnpm stack:reset` (drop volumes).

Domain model (Sprint-0 stan; po MVP-Alpha epik 0.3 model przejdzie na ObjectType — ADR-009):
- 3 entities (`Tenant`, `Product`, `User`) w bounded contexts `Identity` i `Catalog`
- **Target shape MVP-Alpha (po ADR-009):** `Tenant`, `User`, `ObjectType` (predefined product/category/asset jako built-in fixtures), `ObjectTypeAttribute`, `Attribute`, `Object` (poly per `kind`), `ObjectValue`, `Channel`, `Asset` (dedykowana tabela storage + Object kind='asset' dla user-defined metadata)
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

### Generalizacja ObjectType (2026-04-27, ADR-009)
**Decyzja operatora:** generalizujemy model katalogu — `Product`, `Category`, `Asset` to **instancje generic `ObjectType`** z `is_built_in=true`, nie hard-coded encje. Custom kindy (`Customer`, `Supplier`, `PriceList`) supported od dnia 1 ale wyłączone feature flagiem do Fazy 2/3. Powód: B2B pilot zarządza nie tylko produktami; eksport PIMCore (`Zrodla/PIMCore/masowy_eksport_konfiguracji.json`) pokazuje klasę `Kategoria` z user-defined SEO + image, których obecny model PIM nie obsługuje. **Koszt:** epik 0.3 16-20h → 36-50h (+16-25h, finansowane ze zwolnionego budżetu epiku 0.7). Pełen ADR w `01-architektura-pim.md` §13. Pojęcie „Family" deprecated. Sugar paths `/api/products`, `/api/categories`, `/api/assets` zachowane (DX integratorów). Mitigacja over-engineeringu: ryzyko **R-29** + feature flag + benchmark `attributes_indexed` na 10k×200×3 kindach w MVP-Alpha.

## Nowa kolejność wykonania (po Sprincie 0)
1. **~~Sprint 0~~ ZAMKNIĘTY 13/13 (2026-04-28).**
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
| #24 (0.2.1) | Pierwszy ticket epiku 0.2 (Identity & Access) | RBAC roles/permissions, scheb/2fa-bundle, refresh tokens, password reset flow. Operator decyduje kolejność. |
| #25-#30 (0.2.2-0.2.7) | Pozostałe tickety epiku 0.2 | Patrz `Project Plan/02-plan-projektu-pim.md` §3.3 epik 0.2. |

## Postęp po fazach (po rewizji zakresu)
- [x] **Sprint 0 (gate decision) — 13/13 ✅ GREEN (2026-04-28)** — issues #1-#5, #9-#16 closed (#6, #7, #8 przeniesione do Faza 1/2)
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
- [x] **#9 / 0.0.9** — Manualny E2E Sprintu 0 + screencast (zamknięte 2026-04-28, verdict **GREEN** — auth + tenant isolation + product CRUD smoke ok dla obu tenantów)
- [x] **#10 / 0.0.10** — Playwright E2E od dnia 1 (PR #122 merged 2026-04-27)
- [x] **#11 / 0.0.11** — PHPStan max + PHP-CS-Fixer + Biome + husky + CI (PR #114 merged 2026-04-27)
- [x] **#12 / 0.0.12** — Smoke izolacji multi-tenant (PR #117 merged 2026-04-27)
- [x] **#13 / 0.0.13** — Benchmark FrankenPHP worker memory (PR pending — 14 MiB peak na 50 000 prod env z clear, follow-up #123 dla custom PHPStan rule)
- [x] **#14 / 0.0.14** — Profilowanie perf (PR pending — k6 + EXPLAIN ANALYZE; 10 VUs p95=105ms, single-user p95=18.7ms)
- [x] **#15 / 0.0.15** — pgBackRest + WAL stub (PR #130 squash-merged 2026-04-28 jako `868b87c` — custom postgres image z pgbackrest+cron, MinIO repo via Caddy TLS terminator, restore test 1005→1008 markery odzyskane)
- [x] **#16 / 0.0.16** — Audit CLAUDE.md + 06-sprint-0-findings.md (PR #121 merged 2026-04-27)

## Postęp epików (poza Sprintem 0 — zerowy)
**MVP (po rewizji zakresu + ADR-009):**
- [x] **0.1 Infrastructure i fundamenty — 7/7 ✅ (2026-04-28)** — #17-#23 closed
- [ ] 0.2 Identity & Access — #24-#30
- [ ] 0.3 Domain model — Catalog (po ADR-009: 36-50h, +16-25h vs poprzednio) — #31-#40 + nowy 0.3.11 / GH #128 (Hooks pod kind='custom')
- [ ] 0.4 API Platform — exposing entities (sugar paths /api/products|categories|assets) — #41-#48
- [ ] 0.5 Search — Meilisearch (per-kind indeksy) — #49-#53
- [ ] 0.6 Admin UI — core CRUD — #54-#62 (#54 + #61 zrewidowane; **#57 Resource Families → Resource ObjectTypes** po ADR-009)
- [ ] 0.10 API Configurator (filter per object_type_id) — #90-#95
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
