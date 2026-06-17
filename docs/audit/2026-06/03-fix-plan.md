# Plan napraw falami — audyt połówkowy PIM (2026-06-16)

> Priorytetyzacja findings z `01-findings.md` w fale gotowości. Każda pozycja = proponowany ticket (tytuł + zakres 2-3 zdania), gotowy do założenia issue.
> Reguła: **żadne demo z realnymi danymi przed Wave 0; żaden pilot przed Wave 1; żaden publiczny SaaS przed Wave 2.**

Estymaty zbiorcze (orientacyjne, na bazie pól findings):
- Wave 0: ~5-8 dni roboczych
- Wave 1: ~4-6 tygodni
- Wave 2: ~3-5 tygodni
- Wave 3: bieżąca higiena

---

## WAVE 0 — STOP-SHIP: przed JAKIMKOLWIEK demo z realnymi danymi

Każdy z tych findings to aktywny cross-tenant leak, account-takeover-surface lub sekret w VCS. Bez nich nie wolno pokazać systemu na realnych danych dwóch podmiotów.

### W0-1 — Mercure: wyłączyć anonymous, eventy private, scope topiku per tenant (AUD-001)
Usunąć dyrektywę `anonymous` z huba; publikować `new Update(..., private: true)` dla wszystkich eventów domenowych; wprowadzić prefiks tenanta w topikach i mintować subscriber-JWT/cookie ograniczony do topików tenanta usera. Dodać test cross-tenant Mercure (nasłuch tenanta A nie odbiera eventu tenanta B).

### W0-2 — Meili: whitelist kluczy filtra przeciw `filterableAttributes` (AUD-004)
Odrzucać 400 dla kluczy spoza `filterableAttributes` (jak dla `$facets`); mapować klucz→znana nazwa pola zamiast `sprintf` z surowym kluczem; wymusić `tenantId` jako osobny, niemieszalny scope. Test: `filter[parentId IS NULL OR tenantId]=<B>` → 400 / 0 wyników.

### W0-3 — Rozdzielić rolę platformową od per-tenant super_admin (AUD-003)
Wprowadzić `platform_operator` (tenant_id NULL, NIE dla Ownerów) z permission `platform.tenants.manage`; `/api/admin/tenants/*` gate na tę permission; `super_admin` per-tenant (tenant_id NOT NULL) dla Ownera = pełne uprawnienia w obrębie SWOJEGO tenanta. Poprawić fixtures/seeder. Test: Owner A → `GET/POST /api/admin/tenants/{B}` → 403.

### W0-4 — Asset preview: signed URL zamiast PUBLIC_ACCESS + disable tenant (AUD-006)
Zastąpić `/api/assets/{id}/preview` krótko-żyjącym signed URL (HMAC+TTL) mintowanym przez authenticated catalog API; do czasu wdrożenia — sprawdzać tenant assetu w handlerze gdy request niesie principal. Usunąć `mc anonymous set download` z MinIO (AUD-028).

### W0-5 — Usunąć `token_dev_only` z odpowiedzi HTTP (AUD-007)
Zwracać `token_dev_only` wyłącznie gdy `kernel.environment !== 'prod'` (lub całkowicie usunąć — token tylko przez Mailer). Dotyczy `PasswordResetController`, `InvitationController`, `InvitationActionsController`. Dodać test że prod-mode nie zwraca pola.

### W0-6 — Egzekwować attribute-level permissions na ścieżce danych (AUD-008)
Wpiąć `FieldRestrictionFilter` w normalizer odpowiedzi CatalogObject (read) + bramkę `canEdit` w `CatalogObjectProcessor` (PATCH) + filtr policy w eksporcie przed wypisaniem kolumn. Test: user z `view` nie zmodyfikuje/wyeksportuje atrybutu bez `edit`.

### W0-7 — Usunąć sekrety z VCS + rotacja (AUD-005, AUD-010)
`git rm --cached apps/api/.env apps/api/.env.dev`, usunąć allowlist `!apps/api/.env` z `.gitignore`, dodać `.env.dev` do ignore; **rotować WSZYSTKIE klucze** (APP_BYOK_KEY_V1, JWT passphrase, MERCURE_JWT_SECRET, APP_SECRET, DB/MinIO); commituj tylko `.env.example` z placeholderami. (Historia gita pozostaje — rotacja obowiązkowa.)

---

## WAVE 1 — przed pierwszym PILOTEM (realny klient, realne dane)

Druga linia obrony izolacji, gotowość operacyjna (backup/DR/offboarding), stabilność procesów na wolumenie, pokrycie testami krytycznych ścieżek.

### W1-1 — Osobna rola DB aplikacyjna + FORCE RLS na wszystkich tabelach domenowych (AUD-002)
Utworzyć `pim_app` (NOSUPERUSER, NOBYPASSRLS, nie-owner) z GRANT-ami, ustawić `DATABASE_URL` na nią; `ENABLE`+`FORCE ROW LEVEL SECURITY` na wszystkich ~46 tenantowanych tabelach (nie tylko 7); owner/migracje pod `pim_owner`. Test izolacji pod non-superuserem (cross-read = 0).

### W1-2 — Ujednolicić GUC `app.current_tenant` we wszystkich politykach RLS (AUD-027)
Poprawić polityki `refresh_tokens` z `pim.current_tenant_id` na `app.current_tenant`; zweryfikować pgBouncer/transaction-mode założenia `RlsContextListener`; sync `docs/multi-tenancy.md`. WARUNEK WSTĘPNY W1-1 (inaczej FORCE RLS zepsuje refresh login).

### W1-3 — Naprawić filter-name drift break-glass (AUD-026)
Ujednolicić `SuperAdminContext::FILTER_NAME='tenant'`; test że `useCrossTenantMode` faktycznie wyłącza filtr; `SuperAdminContext implements ResetInterface`.

### W1-4 — Prod secrets: usunąć fallback-defaulty, wdrożyć vault (AUD-009, AUD-047)
Usunąć `${VAR:-default}` dla sekretów w `docker-compose.prod.yml` (fail-loud na braku); dodać `.env.prod`/runbook deployu; wdrożyć Symfony Secrets Vault lub external secrets manager. Profilować Mailpit dev-only + `MEILI_ENV=production` w prod (AUD-046).

### W1-5 — Naprawić backup: cron, obraz, restore-test (AUD-017, AUD-021)
`docker compose build database`, zweryfikować crontab w runtime (full/diff/restore-test), potwierdzić pierwszy automatyczny backup, włączyć cotygodniowy automated restore-test. Cel RPO/RTO z runbooka.

### W1-6 — Backup MinIO + wersjonowanie + separacja repo (AUD-018)
Osobny storage/region dla repo pgBackRest (nie ten sam MinIO co assety); wersjonowanie bucketów; `mc mirror`/replikacja assetów. Test odtworzenia assetów + bazy z zera.

### W1-7 — Offboarding tenanta: uporządkowany hard-delete + kaskada MinIO (AUD-019, AUD-020)
Zaimplementować uporządkowane usuwanie zależności (kod lub celowe FK CASCADE) by `pim:tenants:purge-deleted` faktycznie usuwał tenanta z danymi; kaskada usunięcia obiektów MinIO (prefix delete); test offboardingu; spełnić RODO art. 17.

### W1-8 — Worker memory: PHPStan rule flush-bez-clear + resource limits (AUD-011, AUD-012)
Zaimplementować custom PHPStan rule (CI-gate §3.10) + zarejestrować w `phpstan/services.neon`; dodać `deploy.resources.limits.memory` do workera; alert Prometheus `frankenphp_worker_memory_bytes`.

### W1-9 — Indeksy skali: GIN attributes_indexed + GiST objects.path (AUD-013, AUD-014)
Forward-migracja: `CREATE INDEX … USING GIN (attributes_indexed jsonb_path_ops)` + `CREATE INDEX objects_path_gist_idx ON objects USING GIST (path) WHERE kind='category'`. Benchmark p95 filtra atrybutowego na 50k.

### W1-10 — Eksport: keyset-paging na wszystkich ścieżkach + batch-prefetch (AUD-015, AUD-016)
Rozszerzyć constant-memory streaming na scope Selected/Filter/include_variants; batch-prefetch `object_values`/relacji/kategorii per strona keyset. Benchmark memory 50k+variants pod 256 MiB.

### W1-11 — Odblokować E2E + auth happy-path w CI (AUD-022, AUD-023)
Per-spec `storageState` (login raz, reuse) + reset puli rate-limitera w teście → odblokować 57 speców; włączyć auth.spec.ts (login/logout/refresh/token-storage). Bezpieczeństwo, nie feature.

### W1-12 — Testy eskalacji ról + token lifecycle (AUD-024)
Api/Unit: non-admin → 403 na `POST/PATCH/DELETE /api/roles`; PATCH cudzej-tenantowej roli → 404; attribute-permissions PUT nie przyznaje grantów ponad zakres; reset/invitation token single-use/expiry i nie wycieka.

### W1-13 — Naprawić onboarding (AUD-025, AUD-060)
Poprawić creds w `ONBOARDING.md` (`admin@demo.localhost`/`changeme`); udokumentować `pim:db:reset` jako kanoniczny one-shot; dodać `audit:schema:update` do ręcznego flow; naprawić `shared-types generate` (scheme+ścieżka `/api/docs.jsonopenapi`).

---

## WAVE 2 — przed publicznym SaaS

Integralność danych, kontrakt API, RODO retencja, twardnienie injection/walidacji, observability.

### W2-1 — Walidacja JSONB envelope per-type + odrzucanie extra keys (AUD-032)
Rozszerzyć `ValueWriteCore` na wszystkie 17 typów (number→numeric, price→{amount,currency}…) + `additionalProperties:false`. Test adwersarski (śmieć odrzucony).

### W2-2 — Serwerowa sanityzacja HTML wysiwyg (AUD-033)
Allow-list tagów/atrybutów przy zapisie (HTMLPurifier); blokować `javascript:`/`data:` w href. Defense-in-depth niezależny od DOMPurify.

### W2-3 — FilterDSL: parametryzacja PDO lub hardening (AUD-031)
Przejść na bind-params (VIEW-10) lub egzekwować `standard_conforming_strings=on` w bootstrapie + `validate()` w `ExportPreflightController`. Test regresyjny konfiguracji.

### W2-4 — Drift attributes_indexed: detect/report + reconcyliacja (AUD-039)
Komenda detect/report drift + cron reconcyliacji; nie skipować cicho po 3 retry (dead-letter lub flag). Test driftu.

### W2-5 — Rollback importu atomowy (AUD-040)
Owinąć rollback w jedną transakcję lub idempotentny checkpoint rollbacku. Test crash-recovery rollbacku.

### W2-6 — Migracje destrukcyjne: jawna nieodwracalność + pre-dump (AUD-041)
Dla lossy `down()` jawny `throwIrreversibleMigrationException` + wymóg pre-dump w runbooku; CI test migrate→rollback→migrate na świeżej bazie.

### W2-7 — Jednolity RFC 7807 dla custom controllerów (AUD-042)
Exception listener mapujący custom HttpException na RFC 7807; usunąć `class`/`trace` z odpowiedzi prod.

### W2-8 — Decyzja API Platform vs custom + dokumentacja OpenAPI (AUD-043, AUD-054)
ADR uzasadniający stan (lub retrofit krytycznych zasobów na ApiResource); udokumentować custom endpointy w OpenAPI (228 ścieżek, nie 31) przed otwarciem API partnerom.

### W2-9 — Globalny limit rozmiaru JSON body (AUD-045) + Dockerfile prod-stage (AUD-044)
Listener limitujący rozmiar body dla endpointów nie-importowych; multi-stage prod target lub build-arg wymuszający APP_ENV na buildzie prod.

### W2-10 — Crash-safety importu: dead-letter ImageDownload + processed_messages setup (AUD-034, AUD-035)
`WorkerMessageFailedEvent` listener dla `ImageDownloadMessage` (dekrement+finalizacja sesji); wymusić kolejność worker-po-migracjach lub utworzyć `processed_messages` w setup.

### W2-11 — RODO retencja: scheduled cleanup + audit diff danych produktowych (AUD-050, AUD-051, AUD-052)
Symfony Scheduler/cron dla `pim:audit:cleanup` + `pim:tenants:purge-deleted` + retencja eksportów per tier; audyt diff dla CatalogObject/ObjectValue lub jawna decyzja scope + dedykowany `data_export` event.

### W2-12 — Rate-limit reset/invitation + worker healthcheck + Error Boundary FE (AUD-030, AUD-036, AUD-049)
Limiter per-IP+per-email na password-reset, per-IP na invitation accept; dedykowany healthcheck workera; top-level ErrorBoundary + `unhandledrejection`.

### W2-13 — Dependency hardening (AUD-048, AUD-073)
`pnpm.overrides` na `qs@>=6.15.2`, bump `dompurify>=3.4.9`; re-run `pnpm audit`.

### W2-14 — CI coverage gaps (AUD-061, AUD-062, AUD-063)
Rozszerzyć `quality-php.yml paths` lub osobny required job bez filtra; naprawić root cause flaky zamiast `retries:2`; meta-asercja na import-benchmark group.

---

## WAVE 3 — higiena / dług techniczny

### W3-1 — Burndown deptrac (AUD-053, AUD-079) + sprzątanie sierot (AUD-078)
Dokończyć `#1466` (shared writer core → spala 286 skip); `Identity\Contracts` dla User; catch-all kolektor na 5099 uncovered; kolektor Backup; migracja DROP `*_associations_audit`.

### W3-2 — Konsolidacja FE: jeden wzorzec fetch + rozbicie monolitów + dedup (AUD-055, AUD-056, AUD-057)
ADR „nowy kod=useQuery" + lint; reguła `max-lines` (warn 500) + rozbicie product-detail-page; wspólny writer core dla Bulk handlerów (część #1466); konsolidacja bliźniaczych dialogów FE.

### W3-3 — Dokumentacja developera (AUD-059, AUD-081)
Dev-doc „Jak dodać pole/endpoint" (pełny vertical-slice Domain→Resource XML→DTO→Processor→UI→test); przepisać `apps/admin/README.md` (usunąć template Vite); ujednolicić komendy onboardingu (`exec -T`, `.env`, shared-types build step).

### W3-4 — Dashboard realne dane lub jasny baner demo (AUD-058)
Podłączyć widgety dashboardu do API lub dodać wyraźny baner „dane zastępcze".

### W3-5 — Drobne (AUD-029, AUD-037, AUD-038, AUD-064 – AUD-077, AUD-080)
PermissionResolver legacy scope (#644); CompletenessFilter→kolumna; wirtualizacja grid; default tenant code; RBAC matrix sync; magic-byte detekcja; x-powered-by/TRUSTED_HOSTS; RelationImportStep cap; Meili degraded sygnał; chunk JS split; ObjectType delete FK→409; CSP nonce prod; i18n parytet; PermissionRoute podpiąć/usunąć; break-glass runbook; reportUnmatched/assertTrue.

---

## Mapowanie finding → wave (skrót)

| Wave | Findings |
|---|---|
| **0** | AUD-001, 003, 004, 005, 006, 007, 008, 010, 028 |
| **1** | AUD-002, 009, 011, 012, 013, 014, 015, 016, 017, 018, 019, 020, 021, 022, 023, 024, 025, 026, 027, 046, 047, 060 |
| **2** | AUD-030, 031, 032, 033, 034, 035, 036, 039, 040, 041, 042, 043, 044, 045, 048, 049, 050, 051, 052, 054, 061, 062, 063, 073 |
| **3** | AUD-029, 037, 038, 053, 055, 056, 057, 058, 059, 064, 065, 066, 067, 068, 069, 070, 071, 072, 074, 075, 076, 077, 078, 079, 080, 081 |
