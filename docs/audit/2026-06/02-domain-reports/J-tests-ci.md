# Domena J — Testy i CI (auditability)

Audyt adwersarski PIM, 2026-06-16. Postawa: adwersarz. Każdy finding ma dowód (plik:linia / output). Analiza WYŁĄCZNIE statyczna (czytanie + grep); testów nie uruchamiano (guardrail: ryzyko wymazania dev DB).

## Metodyka — co sprawdzono i jak

Materiał wejściowy (przeczytane w całości):
- `.github/workflows/quality-php.yml` (8 jobów), `quality-frontend.yml` (5 jobów), `audit.yml`, `security-secrets.yml`, `branch-cleanup.yml`.
- `apps/api/phpunit.dist.xml`, `biome.json`.
- `docs/audit/2026-06/raw/phpstan-config.txt`, `phpstan.txt`, `deptrac-config.txt`, `deptrac.txt`, `db-rls-enabled.txt`.

Inwentaryzacja testów (grep):
- PHP: 287 plików testów (`tests/Unit` 133, `tests/Api` 121, `tests/Integration` 33, `tests/Architecture` 1, `tests/Support` 2), **1500 metod `#[Test]`** (`rg -c "#\[Test\]"`). Uwaga: testy używają atrybutu `#[Test]`, nie prefiksu `testXxx` (stąd `rg "function test"` daje mylące 47).
- Admin FE: 100 plików `e2e/*.spec.ts`, 12 plików Vitest unit (`*.test.ts(x)`).

Czego NIE dało się zrobić (statyczna analiza): nie zmierzono realnego pokrycia (% / coverage), nie potwierdzono że testy faktycznie przechodzą ani że asercje realnie się wykonują — jedynie czytanie kodu testów. Nie uruchomiono PHPUnit ani Playwright.

---

## FINDINGS

### J-01 [HIGH] 57 ze 100 E2E spec files wyłączonych w CI przez `test.fixme(!!process.env.CI)` — gate „bez E2E ticket nie jest done" jest de facto wydrążony

CLAUDE.md („Definicja Done"): *„Playwright E2E dla każdej widocznej zmiany. Bez E2E ticket NIE jest done."* Tymczasem w CI ponad połowa speców UI jest celowo pomijana.

Dowód (`apps/admin/e2e/`):
```
TOTAL e2e spec files: 100
specs z test.fixme(...CI...) / CI_BLOCKED / E2E_BLOCKED: 57
```
- `e2e/products-locale-switch.spec.ts:25` → `test.fixme(!!process.env.CI, CI_BLOCKED)` z komentarzem „shared auth-rate-limiter storageState gap".
- `e2e/settings-channels-crud.spec.ts:32` → `test.fixme(true, E2E_BLOCKED_BY_RATE_LIMITER)` — „lands as the 9th test in the shared [...] rate-limiter".
- `e2e/975-relation-picker-candidates.spec.ts:21` → `test.fixme(true, '#1366 — relation picker E2E pre-existing CI failure, follow-up')`.
- `e2e/imports.spec.ts:62,101` → `test.fixme(... 'Pending #799: file input on imports wizard not reached')`.
- `e2e/auth.spec.ts` — happy-path logowania jest fixme: `:15` `test.fixme(true, BLOCKED_BY_41)` dla „user can log in and lands on the products list" oraz „seeded credentials match the fixtures" (`:53`), „logout clears the session" (`:42`).

Przyczyna systemowa: współdzielony auth rate-limiter (5/15min) + jeden `storageState` — od ~9. testu UI w serii CI logowania przekraczają limit i specy padają, więc zamiast naprawić limiter w test-env, masowo oznaczono je `fixme`. To NIE jest pojedynczy flaky — to wyłączenie >50% pokrycia regresji UI z bramki, która oficjalnie jest „obowiązkowa".

Skala wyłączonych obejmuje krytyczne flowy: products list/edit/create (`products.spec.ts`, `view-07-products-edit-create.spec.ts`, `products-excel-edit.spec.ts`), kanały (`settings-channels-crud`, `chc-03/08/09`), atrybuty (`1177`, `1211`, `attributes-delete`), kategorie, locale switching.

Rekomendacja: naprawić root cause (per-test świeży `storageState` + reset puli rate-limitera per spec; krok CI „Reset auth rate limiter cache before E2E" w `quality-frontend.yml:229` resetuje pulę tylko RAZ na cały run, nie per-spec). Zinwentaryzować i odblokować 57 speców; dopóki są fixme, twierdzenie „E2E obowiązkowe" jest nieprawdziwe.

---

### J-02 [HIGH] Deptrac „0 violations" maskuje 286 realnych przecieków warstw (Import/Export → Catalog\Domain internals) baseline'owanych w `skip_violations`

`raw/deptrac.txt`:
```
Violations           0
Skipped violations   286
Uncovered            5099
Allowed              1273
```
„0 violations" jest zielone WYŁĄCZNIE dlatego, że 286 zależności jest jawnie wpisanych do `skip_violations` w `deptrac.yaml` (raw/deptrac-config.txt:240–466). Charakter skipów to NIE szum — to realne naruszenia architektury: 56 klas-kluczy, np. `App\Export\Application\Builder\ExportBuilder` sięga do `Catalog\Domain\Entity\CatalogObject/ObjectValue/Attribute` + repozytoriów (config:280–288); `App\Import\Application\Handler\ImportRunHandler` do `Catalog\Application\BatchValueWriter`, `BulkContext`, `CatalogObject`, `ObjectCategory` (config:430–445). Udokumentowane jako tech-debt (#1466 „shared writer core"), ale dopóki są w baseline, bramka deptrac NIE chroni warstwy Import/Export ↔ Catalog.

Dodatkowo `Uncovered=5099` — ogromna część `src/` nie jest objęta żadnym kolektorem warstwy (16 warstw obejmuje tylko Domain/Application/Infrastructure/Presentation/Contracts wybranych BC). Fitness function pokrywa wąski wycinek kodu.

Rekomendacja: traktować deptrac jako warunkowo zielony; egzekwować burndown #1466; rozważyć kolektor „catch-all" dla `Uncovered`, by nowe moduły domyślnie podlegały regule, a nie były niewidoczne.

---

### J-03 [HIGH] Brak testów dla flowów password-reset / invitation-accept / magic-link mimo że istnieją w kodzie (powtórka lekcji #657/#658)

Flowy istnieją w `src/`:
- `src/Identity/Application/PasswordResetService.php`, `src/Identity/Presentation/Controller/PasswordResetController.php`, `src/Identity/Domain/Entity/PasswordResetToken.php`.
- `src/Identity/Application/InvitationService.php`, `src/Identity/Presentation/Controller/InvitationController.php`, `src/Identity/Domain/Entity/Invitation.php`.
- `src/Identity/Application/MagicLinkTokenHasher.php`.

Dowód braku testów:
```
rg -l "PasswordResetService|InvitationService|MagicLinkTokenHasher|PasswordResetController|
       InvitationController|PasswordResetToken|Invitation" apps/api/tests/
=> (exit 1, ZERO trafień)
```
Brak jakiegokolwiek testu (unit/api/integration) dla: token expiry, token reuse (jednorazowość magic-linka), invalid token, accept-invitation happy path, password-reset end-to-end. To dokładnie scenariusze, które lekcja `feedback_closed_means_closed.md` / RBAC Phase 2 re-audit (#657/#658) wskazała jako „closed ≠ end-to-end testable". Brute-force/rate-limit, refresh-rotation, token-reuse-family-revoke i MFA SĄ pokryte (patrz sekcja „Co jest DOBRZE pokryte") — reset/invite/magic-link wypadły z testów całkowicie.

Rekomendacja: dodać Api/* + Unit testy dla reset/invitation/magic-link obejmujące expiry, single-use, invalid-token-401, oraz że token NIE jest zwracany w response (dev-token leak).

---

### J-04 [MEDIUM] Brak testu attribute-level RBAC bypass przez eksport — ExportBuilder nie filtruje atrybutów wg uprawnień i nic tego nie weryfikuje

3-state attribute permissions to filar RBAC (PRD §3.5). Eksport jest oczywistym wektorem obejścia: rola bez `view` na atrybut nadal może go wyeksportować, jeśli builder nie respektuje grantów.

Dowód:
```
rg -i "permission|grantedAttribute|attributePermission|isGranted" src/Export/Application/Builder/ExportBuilder.php
=> ZERO trafień  (builder nie aplikuje attribute-level RBAC)
rg -i "attribute.?permission|field.?level|hidden.?attribute|exclude.*attribute" tests/Api/Export tests/Unit/Export
=> ZERO trafień  (brak testu)
```
Nie ma ani implementacji filtra, ani testu który by wykrył wyciek atrybutów zabronionych dla roli przez `/api/export`. (Domena B oceni samą lukę funkcjonalną; dla domeny J: brak testu wykrywającego.)

Rekomendacja: dodać test „rola bez view na atrybut X — eksport nie zawiera kolumny X" (CSV i XLSX, sync i async).

---

### J-05 [MEDIUM] Izolacja tenantów na poziomie HTTP pokryta tylko dla READ products; brak cross-tenant WRITE/UPDATE/DELETE na core (objects/object_values/channels/assets) i brak negatywnego testu RLS

Istniejące pokrycie izolacji:
- `tests/Integration/Import/ImportTenantIsolationTest.php` — solidne, ale TYLKO encje Import (sessions/profiles/logs/undo) + backups + relation cross-tenant (testuje TenantFilter na poziomie repo, `cross-read = 0`).
- `tests/Integration/Import/ImportWorkerRlsGucTest.php` — ustawienie/reset GUC `app.current_tenant` w workerze.
- `apps/admin/e2e/multi-tenant-isolation.spec.ts` (RUNS w CI) — cross-tenant READ na `/api/products` (demo vs acme, SKU rozłączne).
- `tests/Api/Catalog/SmartFilterPresetsApiTest.php:316–336` — drugi tenant + JWT, izolacja preset (READ).
- `tests/Api/Import/StartImportApiTest.php:1402` `backupFromAnotherTenantIs404`, `UserDeactivationControllerTest` (target z innego tenanta → 404).

Luki (brak testu):
1. **Cross-tenant WRITE/UPDATE/DELETE** na core: brak testu, że tenant A nie może PATCH/DELETE `CatalogObject`/`ObjectValue`/`Channel`/`Asset` należącego do tenanta B (404/403). Pokryty jest tylko READ products i punktowo backup/user.
2. **Asset IDOR**: `tests/Api/Catalog/AssetsApiTest.php` ma tylko `getCollectionReturnsEmptyListForFreshTenant` (pusta lista dla świeżego tenanta) — brak cross-tenant GET/DELETE konkretnego asset ID, brak testu authz na asset preview/download.
3. **Negatywny test RLS na poziomie DB**: nie ma testu sprawdzającego, że gdy Doctrine TenantFilter jest wyłączony, RLS i tak blokuje surowy SQL — co istotne, bo `raw/db-rls-enabled.txt` pokazuje, że RLS jest włączony TYLKO na 7 tabelach (`api_tokens`, `audit_logs`, `import_logs`, `import_staged_files`, `import_undo_log`, `invitations`, `user_tenant_memberships`); core (`objects`, `object_values`, `attributes`, `channels`, `assets`) ma `relrowsecurity=f` → izolacja core polega WYŁĄCZNIE na Doctrine TenantFilter, więc każdy native query / okno po `clear()` jest poza ochroną i poza testem.
4. **Mercure leak**: `tests/Api/Catalog/MercureBroadcastApiTest.php` asertuje topics (`https://pim.localhost/objects`, `.../objects/{id}`) ale to topiki GLOBALNE, nie tenant-scoped — brak testu, że tenant B nie odbiera update'ów tenanta A przez SSE.

Rekomendacja: dodać Api/* cross-tenant write-deny dla objects/values/channels/assets; test asset-preview authz; integration test „native SQL bez filtra → RLS blokuje" (przynajmniej dla tabel z RLS); test tenant-scope topiku Mercure.

---

### J-06 [MEDIUM] `import-benchmark` (memory gate 5k/50k, 256 MiB) wykluczony z domyślnego runu — łatwo o ciche zgubienie w CI

`phpunit.dist.xml:50–54` wyklucza grupę `import-benchmark` z suite `all`. W `quality-php.yml:265–276` jest osobny krok `php bin/phpunit --group import-benchmark`. To działa, ale gate pamięciowy (kluczowy dla FrankenPHP worker mode wg CLAUDE.md §Memory management) wisi na jednym ręcznie dopisanym kroku CI — usunięcie/zmiana nazwy grupy nie wywoła żadnego błędu (test po prostu nie pobiegnie i nikt nie zauważy). Brak meta-testu pilnującego, że grupa istnieje i nie jest pusta.

Rekomendacja: dodać asercję CI „liczba testów w grupie import-benchmark > 0" lub przenieść do dedykowanego required-job z własną nazwą w branch protection.

---

### J-07 [LOW] `reportUnmatched: false` na 2 grupach `ignoreErrors` osłabia gwarancję „martwych ignorów" dokładnie tam, gdzie dotykają `src/`

`raw/phpstan-config.txt`: baseline (`phpstan-baseline.neon`) jest PUSTY (`ignoreErrors: []`) — brak maskowania przez baseline, dobrze. `reportUnmatchedIgnoredErrors: true` globalnie. Ale dwie grupy dotykające produkcyjnego `src/` mają `reportUnmatched: false`:
- `doctrine.associationType` na ~35 plikach encji TenantScoped (config:89–131),
- `property.deprecated` na `src/Shared/Domain/Tenant.php` (config:142–145).

Uzasadnienie (cache miss lokalnego Dockera) jest udokumentowane, ale skutek: jeśli któryś z tych ignorów stanie się martwy (błąd zniknął), CI tego nie wykryje dla tych dwóch grup — czyli ignore może „przeżyć" usunięcie problemu i potencjalnie zamaskować przyszły, podobny błąd na tych plikach.

Rekomendacja: po stabilizacji cache (CI jest kanoniczny) przywrócić `reportUnmatched: true` dla obu grup.

---

### J-08 [LOW] Pojedyncze testy z `assertTrue(true)` — pattern „no-throw", nie atrapy, ale słabo weryfikują brak side-effectu

Próbka (NIE klasyfikuję jako fałszywe — testują negatywną ścieżkę „listener nie rzuca dla input out-of-scope"):
- `tests/Unit/Identity/Infrastructure/Http/EndpointGuardListenerTest.php:61,74,87,103,158,171,210` — 7× `assertTrue(true)` po wywołaniu listenera bez rzucenia (ścieżki throw są jednak osobno pokryte przez `expectException` w tym samym pliku, np. linie 40–47).
- `tests/Unit/Catalog/CategoryPathValidatorTest.php:39,116` — `nullPathPassesForAnyKind` (null path nie waliduje).
- `tests/Unit/Channel/ChannelCategoryRootValidatorTest.php:112` — `nonChannelEntityIsIgnored`.
- `tests/Unit/Identity/TenantAssignmentListenerTest.php:84` — `itIgnoresEntitiesThatAreNotTenantScoped`.

Ocena: to dozwolony PHPUnit pattern (potwierdzony w `phpstan-config.txt:74–77` jako świadoma decyzja), nie testy-atrapy asertujące nieprawdę. Słabość: weryfikują tylko „brak wyjątku", nie weryfikują jawnie braku side-effectu (np. że encja out-of-scope NIE dostała tenant_id) — można by użyć `assertSame(null, $unrelated->...)`. Niskie ryzyko, odnotowane dla kompletności.

Pozostałe `markTestSkipped` to środowiskowe (Meilisearch/AES-NI/intl/symlink niedostępne) — uzasadnione (`AuthApiTest.php:184` skip czeka na #41 sugar paths; `SearchEndpointsApiTest.php:44`, `QuickSearchApiTest.php:32` „covered by Playwright stack"). Brak `markTestIncomplete`/`@group skip` w PHP.

---

## Co jest DOBRZE pokryte (kontekst adwersarski — gdzie NIE ma luki)

- **CI gates są twarde** (`rg "continue-on-error|allow-failure|\|\| true"` po `.github/workflows/`): jedyny `|| true` to `docker-compose.log` dump on-failure (`quality-frontend.yml:254`) — nieszkodliwy. PHPStan (level max), Deptrac (`--no-cache`), Biome strict, PHP-CS-Fixer (dry-run), Semgrep (ERROR+WARNING blokują), `lint-raw-sql.sh` (`exit 1` przy braku `tenant-safe:`), composer/pnpm audit, OpenAPI drift (diff → `exit 1`) — wszystkie failują twardo.
- **PHPStan baseline pusty** — zero ukrytego masking przez baseline.
- **Auth**: brute-force (`AuthLoginRateLimitTest` — 6. próba → 429+Retry-After, sukces też zjada budżet), refresh rotation + **token-reuse → family revoke** (`RefreshTokenApiTest:125`), expired-token-401 (:162), MFA challenge/backup-code/wrong-code (`MfaLoginApiTest`, `TwoFactorEnrolmentApiTest`), Argon2id hash fixtury.
- **Formula/CSV injection na EKSPORT**: `CsvStreamWriterTest` + `XlsxStreamWriterTest` (IMP2-2.8 #1484) — leading TAB dla `=`/`+`/`-`/`@`, „a=b" nie ruszane.
- **Zip-bomb**: `XlsxArchiveGuardTest` — max entries, max uncompressed, ratio bomb, non-zip reject; inspekcja central directory bez dekompresji.
- **RBAC self-escalation**: `UserVoterTest` — `change_roles` na sobie = DENIED (privilege escalation przez single PATCH zablokowane), nieadmin → 403 (UserCreate/RolesList/UsersList/UserDeactivation), `WorkflowStatePolicyTest` viewer-bez-edit.
- **Cross-tenant 404**: backup (`StartImportApiTest:1402`), user deactivation target.

---

## NIEZBADANE

- **Realne pokrycie / coverage %** — nie mierzono (analiza statyczna; PHPUnit nie uruchamiany). 1500 metod `#[Test]` to liczba deklaracji, nie wykonań — nie potwierdzono że wszystkie przechodzą ani że asercje się wykonują.
- **Czy 57 fixme E2E faktycznie padłyby gdyby je włączyć** — nie uruchomiono Playwright; przyjęto deklarowany powód (rate-limiter/storageState/#41/#799/#1366) z komentarzy.
- **Zawartość pozostałych ~120 plików Api/* i 33 Integration** — przejrzano nazwy metod i kluczowe pliki tenant/auth/import/export; nie czytano każdego testu liniowo.
- **Czy Semgrep `cortex-rbac.yml` realnie łapie wzorce** — przeczytano config CI (severity ERROR+WARNING blokują, INFO odfiltrowane bo false-positive na FK chains), nie zweryfikowano reguł w `.semgrep/`.
- **Frontend Vitest (12 plików)** — policzono liczbę, nie audytowano treści asercji.
- **branch protection na GitHub** — workflowy deklarują „branch protection requires this", ale faktyczna konfiguracja required-checks na repo nie była sprawdzana (poza zakresem read-only repo).
- **Magic-link/reset/invitation negatywne ścieżki w runtime** — potwierdzono brak testów statycznie; nie zweryfikowano zachowania endpointów na żywym stacku (guardrail: bez live-stack).
