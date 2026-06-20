# Rejestr findings — audyt połówkowy PIM (2026-06-16)

> Adwersarski audyt przed wypuszczeniem jako SaaS. Tryb READ-ONLY. Stack żywy: `https://pim.localhost`, `APP_ENV=dev`.
> Pełne dowody (cytaty kodu, transkrypty, EXPLAIN) — w raportach domenowych `02-domain-reports/<X>-*.md` oraz surowych outputach `raw/`.
> Każdy finding: severity (CRITICAL/HIGH/MEDIUM/LOW) + confidence (confirmed = odtworzony dowodem / probable / needs-review).

## Statystyki

| Severity | Liczba |
|---|---|
| CRITICAL | 5 |
| HIGH | 20 |
| MEDIUM | 38 |
| LOW | 18 |
| **Razem** | **81** |

## Status napraw — Wave 0 (zamknięte 2026-06-17)

Wszystkie 7 ticketów Wave 0 (9 findingów, w tym 4 z 5 CRITICAL) naprawione, zmergowane do `main`, z dowodem **CLOSED MEANS CLOSED** (powtórzony oryginalny probe przed/po) w komentarzu zamknięcia każdego issue. Wzorzec naprawy: failing test odtwarzający podatność → fix → zielony test jako regresja.

| Ticket | Findingi | PR | Issue | Status | Dowód „po" (żywy stack 2026-06-17) |
|---|---|---|---|---|---|
| W0-1 | AUD-001 | #1619 | #1573 | ✅ FIXED | anon Mercure subscribe → **401**, 0 eventów (topiki `tenant/{id}/…` + `private:true`, hub bez `anonymous`) |
| W0-2 | AUD-004 | #1618 | #1574 | ✅ FIXED | injection filter-key → **400** (`CatalogSearchService::assertFilterKeys()` whitelist vs filterableAttributes) |
| W0-3 | AUD-003 | #1621 | #1575 | ✅ FIXED | demo Owner `/api/admin/tenants` → **403**; platform-operator → 200 (`PlatformOperatorGuard` + `platform.*`) |
| W0-4 | AUD-006, AUD-028 | #1622 | #1576 | ✅ FIXED | preview bez sygnatury → **403**; HMAC `UriSigner`+TTL, `disable('tenant')` usunięte, MinIO anon→none |
| W0-5 | AUD-007 | #1617 | #1577 | ✅ FIXED | `token_dev_only` env-gated `%kernel.environment%` — prod usuwa pole (regression test 6/6) |
| W0-6 | AUD-008 | #1620 | #1578 | ✅ FIXED | `AttributeReadRestrictionOverlay` strips restricted attrs w read-path (regression test 4/4) |
| W0-7 | AUD-005, AUD-010 | #1623 | #1579 | ✅ FIXED | `.env.dev` odtrackowany+gitignored, `.env` placeholdery, `scripts/lint-tracked-secrets.sh` guard exit 0 |

Pełne transkrypty przed/po — w komentarzach zamknięcia issues #1573–#1579. Reszta findingów (Wave 1–3) — patrz `03-fix-plan.md`.

## Status napraw — Wave 1 (zamknięte 2026-06-18)

Wszystkie 13 ticketów Wave 1 (5. CRITICAL AUD-002 + 22 findingi HIGH/MEDIUM) naprawione, zmergowane do `main`, zamknięte z dowodem **CLOSED MEANS CLOSED** (probe przed/po w komentarzu). **5/5 CRITICAL audytu domknięte** (4 w Wave 0 + AUD-002 RLS w W1-1). Bonus: testy W1-12 odsłoniły i naprawiły 2 realne bugi HIGH (martwy invite-create + invitation accept check-after-write).

| Ticket | Findingi | PR | Issue | Dowód „po" |
|---|---|---|---|---|
| W1-1 | AUD-002 | #1626 | #1580 | `pim_app` NOSUPERUSER/NOBYPASSRLS + 43 tabele FORCE RLS; cross-tenant odczyt = 0 |
| W1-2 | AUD-027 | #1625 | #1581 | polityki `refresh_tokens` → `app.current_tenant`; 0 polityk na legacy GUC |
| W1-3 | AUD-026 | #1627 | #1582 | `FILTER_NAME='tenant'` + ResetInterface; break-glass faktycznie wyłącza filtr |
| W1-4 | AUD-009, 046, 047 | #1628 | #1583 | prod compose `${VAR:?}` fail-loud; Mailpit dev-only; MEILI prod; Secrets Vault |
| W1-5 | AUD-017, 021 | #1630 | #1584 | dcron rejestruje crontab; nowy backup powstał; restore-test PASS |
| W1-6 | AUD-018 | #1637 | #1585 | versioning Enabled; pgBackRest repo osobny bucket (anti-SPOF); mc mirror |
| W1-7 | AUD-019, 020 | #1636 | #1586 | offboarding hard-delete 42 tabel + MinIO cascade; RODO art.17; demo nietknięte |
| W1-8 | AUD-011, 012 | #1633 | #1587 | `FlushWithoutClearInLoopRule` (8/8) + worker mem-limit 512M |
| W1-9 | AUD-013, 014 | #1631 | #1588 | GIN + GiST odtworzone; filtr atrybutowy Bitmap Index Scan (~37×) |
| W1-10 | AUD-015, 016 | #1632 | #1589 | keyset-paging all-scopes + batch-prefetch; All+variants 150k <256 MiB |
| W1-11 | AUD-022, 023 | #1639 | #1590 | auth.spec 8/8 (token-not-in-localStorage); 32 E2E odblokowane, 24 → #1638 |
| W1-12 | AUD-024 | #1634 | #1591 | testy eskalacji ról + token lifecycle (28/62) + 2 bugfixy HIGH |
| W1-13 | AUD-025, 060 | #1635 | #1592 | onboarding creds/seed/audit-schema; shared-types `https .jsonopenapi` |

Dodatkowo: **CVE-2026-54133** (jmespath.php <2.9.1) bump #1629 (composer-audit blocker). Otwarte follow-upy: **#1638** (24 E2E specy do triażu UI-drift/behavior/env). Reszta findingów → Wave 2/3 (`03-fix-plan.md`).

## Status napraw — Wave 2 (zamknięte 2026-06-19)

Wszystkie 14 ticketów Wave 2 (W2-1..W2-14, 24 findingi HIGH/MEDIUM/LOW przed publicznym SaaS) naprawione, zmergowane do `main`, zamknięte z dowodem **CLOSED MEANS CLOSED** (probe/test przed/po w komentarzu). Wzorzec: security → failing test odtwarzający podatność → fix → zielona regresja; config/perf → before/after.

| Ticket | Findingi | PR | Issue | Dowód „po" |
|---|---|---|---|---|
| W2-1 | AUD-032 | #1641 | #1593 | `ValueWriteCore` ALLOWED_KEYS + `additionalProperties:false` dla 17 typów JSONB |
| W2-2 | AUD-033 | #1645 | #1594 | HTMLPurifier allow-list + `URI.AllowedSchemes={http,https,mailto}` na wysiwyg |
| W2-3 | AUD-031 | #1642 | #1595 | `StandardConformingStringsMiddleware` SET+SHOW fail-loud per connection |
| W2-4 | AUD-039 | #1646 | #1596 | `pim:catalog:detect-attributes-drift` (+`--reconcile`, RLS-aware GUC) |
| W2-5 | AUD-040 | #1647 | #1597 | rollback w `em->wrapInTransaction()`, Meili queued post-commit |
| W2-6 | AUD-041 | #1648 | #1598 | 6 lossy migracji `down()` → `throwIrreversibleMigrationException()` |
| W2-7 | AUD-042 | #1649 | #1599 | `Rfc7807ExceptionListener` — custom-API → problem+json, strip class/trace |
| W2-8 | AUD-043, 054 | #1650 | #1600 | `CustomRouteOpenApiFactory`: OpenAPI 31→218 ścieżek; ADR-0020 (hybryda) |
| W2-9 | AUD-045, 044 | #1651 | #1601 | `RequestBodySizeLimitListener` 413 (10MB); Dockerfile prod-stage |
| W2-10 | AUD-034, 035 | #1652 | #1602 | `ImageDownloadDeadLetterListener` finalizuje stuck-session; `processed_messages` self-heal |
| W2-11 | AUD-050, 051, 052 | #1653 | #1603 | `exports:cleanup` retencja + `MaintenanceSchedule` + `data_export` audit |
| W2-12 | AUD-030, 036, 049 | #1655 | #1604 | rate-limit reset/invite 429; worker `pgrep` healthcheck; admin `ErrorBoundary` |
| W2-13 | AUD-048, 073 | #1656 | #1605 | qs 6.15.2 + dompurify 3.4.11; +guzzle/undici repo-wide audit-gate unblock |
| W2-14 | AUD-061, 063 | #1657 | #1606 | `quality-php.yml` paths→paths-ignore (gates zawsze poza docs); benchmark group meta-asercja |

**Świadome odejścia / deferrale Wave 2:**
- **AUD-052** (dh_auditor product-value diff dla `CatalogObject`/`ObjectValue`) — deferowany z W2-11 do dedykowanego ticketu **#1654** (wymaga write-throughput benchmarku bulk-import 50k SKU przed włączeniem — ryzyko rozsadzenia `audit_logs`). W2-11 dostarczyło dedykowany `data_export` event (eksport PII zostawia ślad). „Jawna decyzja scope" dopuszczona treścią ticketu.
- **AUD-054** (API Platform vs custom) — rozstrzygnięty przez **ADR-0020** (świadoma hybryda: AP dla zasobów, custom `#[Route]` dla operacji proceduralnych/CQRS), nie retrofit 117 kontrolerów.
- **AUD-062** (Playwright `retries:2` maskuje flaky) — deferowany do **AUD-022** (root cause = współdzielony storageState; naprawiany razem).

**Środowiskowy unblock w trakcie Wave 2** (świeże CVE 2026-06-19, blokowały repo-wide audit-gate; naprawione w #1656): guzzle 7.10.4→7.12.1 + psr7 2.11.0→2.12.1 (CVE-2026-55767/55568/55766); undici dev-only ×2 (GHSA-vxpw-j846-p89q, GHSA-hm92-r4w5-c3mj via jsdom) → `ignoreGhsas` (ref #1644).

Pełne transkrypty przed/po — w komentarzach zamknięcia issues #1593–#1606. Reszta findingów (Wave 3 — bieżąca higiena/LOW) — patrz `03-fix-plan.md`.

## Status napraw — Wave 3 (zamknięte 2026-06-20)

Wszystkie 10 ticketów Wave 3 (#1607–#1616, higiena/dług techniczny) naprawione, zmergowane do `main`, zamknięte z dowodem **CLOSED MEANS CLOSED**. Cross-cutting refaktory (Deptrac 286-burndown, FE jsonFetch migracja, monolit-split) dostarczone wzorcem **minimum-viable + enforcement + proof-slice + tracked-deferral** (mandat marathon dla cross-context): reguła/lint zatrzymuje przyrost długu, proof-slice dowodzi kierunku, bulk → tracking z uzasadnieniem.

| Ticket | Findingi | PR | Issue | Dowód „po" |
|---|---|---|---|---|
| W3-1 | AUD-053, 079, 078 | #1669 | #1607 | Deptrac Uncovered 5283→0 (`Vendor` collector), Backup gated, Search zawężony, DROP sierot audit, `CurrentUserProvider` seam (5 kontrolerów, baseline 288→284); bulk-286→#1668 |
| W3-2 | AUD-055, 056, 057 | #1671 | #1608 | ADR-0021 + jsonFetch-lint (baseline 61), `AbstractBulkHandler` (−627 linii), product-detail 1499→492 + max-lines-lint (21); FE-bulk→#1670 |
| W3-3 | AUD-059, 081 | #1666 | #1609 | `docs/development/adding-a-field-or-endpoint.md` (realny Channel slice), apps/admin/README rewrite, root onboarding (db-init/exec-T/.env/shared-types) |
| W3-4 | AUD-058 | #1665 | #1610 | CompletenessMetrics ogólny ring live (useQuery), `DashboardMockBanner` (live vs demo jawnie); deferrale udokumentowane |
| W3-5.1 | AUD-029, 037, 038 | #1661 | #1611 | PermissionResolver legacy-scope fix (super_admin 117 perms nietknięty), CompletenessFilter→indexed column (+bonus `global`-key), grid column-virtualization |
| W3-5.2 | AUD-064, 065, 066 | #1660 | #1612 | tenant-fallback deny w prod (gated, dev nietknięty), RBAC reconciliation meta-test (ZERO zmian grantów), magic-byte guard |
| W3-5.3 | AUD-067, 068, 069 | #1659 | #1613 | x-powered-by strip (Caddy+php.ini), prod TRUSTED_HOSTS fail-loud, RelationImportStep buffer cap |
| W3-5.4 | AUD-070, 071, 072 | #1663 | #1614 | Meili degraded → 503 problem+json, product-detail chunk 797→67KB, ObjectType delete FK→409 |
| W3-5.5 | AUD-074, 076 | #1664 | #1615 | CSP prod `script-src 'self'` (env-conditional), PermissionRoute podpięty (5/5 test); AUD-075 (i18n parity)→#1667 |
| W3-5.6 | AUD-077, 080 | #1662 | #1616 | break-glass runbook (z realnego kodu), phpstan `reportUnmatched` + 4 testy hygiene |

**Świadome odejścia / deferrale Wave 3 (tracking, NIE wyciszanie):**
- **AUD-075** (i18n pl/en parity) → **#1667**: wypełnienie en.json flipuje dwujęzyczne selektory E2E (mieszane pl-only/en-only ~30 speców) — wymaga E2E-locale determinizmu (razem z #1638).
- **AUD-053 bulk** (Import/Export→Catalog/User ~284 skipy) → **#1668**: wielokontrolerowy refaktor (część zamkniętego #1466); kierunek udowodniony seam-em (5 kontrolerów).
- **AUD-055/057 bulk** (~136 jsonFetch + 16 monolitów) → **#1670**: enforcement (ADR+2 CI-guardy nie-rosnące) + proof-slice'y dostarczone; migracja przyrostowa.
- **AUD-062** (Playwright retries:2) → **AUD-022** (root cause storageState).

## Fix-plan KOMPLETNY (Wave 0–3, 2026-06-20)

**44 tickety** naprawione + zmergowane + zamknięte z dowodem CLOSED-MEANS-CLOSED (Wave 0: 7 · Wave 1: 13 · Wave 2: 14 · Wave 3: 10). **5/5 CRITICAL domknięte** (AUD-001/004/005/007 Wave 0 + AUD-002 RLS Wave 1). Pierwotne blokery NO-GO (poniżej) zaadresowane: AUD-001 Mercure (W0), AUD-004 Meili filter-key (W0), AUD-007 token_dev_only (W0), AUD-002 RLS defence-in-depth (W1-1 FORCE RLS), AUD-003 platform-vs-tenant (RBAC). Otwarte follow-upy (tracked, nie blokery): #1638 (E2E triage), #1644 (undici/jsdom), #1654 (dh_auditor), #1667/#1668/#1670 (Wave 3 deferrale). **Decyzja GO/re-audit** należy do Phase 7 pentest + `00-executive-summary.md` — poniższy werdykt to pierwotny stan audytu (zachowany historycznie).

**Werdykt: NO-GO dla SaaS** (patrz `00-executive-summary.md`). Uwaga precyzyjna po przebiegu empirycznym: **rdzeń izolacji danych domenowych (Doctrine TenantFilter przez REST) DZIAŁA** — matryca 2-tenant obaliła podejrzenie wycieku (patrz „Pozytywy empiryczne" niżej). NO-GO wynika z wektorów **POZA filtrem wierszowym**: AUD-001 Mercure (confirmed), AUD-004 Meili filter-key (confirmed), AUD-007 token_dev_only (confirmed account takeover), AUD-002 zerowy defence-in-depth (RLS martwy), AUD-003 model uprawnień platform-vs-tenant.

**Rewizja po przebiegu empirycznym (2026-06-16, matryca 2-tenant demo↔acme):**
- **AUD-003 (globalny super_admin): CRITICAL → HIGH.** Matryca dowiodła, że TenantFilter izoluje dane domenowe nawet dla super-admina (cross-read po ID = 404 w obie strony); `/api/admin/tenants` to celowy operator-panel zwracający wyłącznie metadane (gated `requireSuperAdmin`, audytowany). To NIE wyciek danych — pozostaje realnym ryzykiem modelu uprawnień (brak rozróżnienia platform-vs-tenant; fixtures nadają globalny super_admin Ownerom).
- **AUD-007 (token_dev_only): HIGH → CRITICAL.** Potwierdzony empirycznie account takeover (64-znakowy token w body bezwarunkowo, brak guardu env). Klasa auth-bypass.
- **AUD-006 (asset preview):** wektor potwierdzony (PUBLIC_ACCESS + disable tenant + handler dociera do rekordu cross-tenant), ale wyciek bajtów NIEodtworzony (brak blobów w dev storage) → confidence wycieku = probable; severity HIGH utrzymane (luka architektoniczna).

**Kluczowe rozstrzygnięcia adwersarskie:**
- Konflikt B-03 vs H-06 (MFA brute-force): rozstrzygnięty z kodu — `MfaLoginChallengeStore.php:30,101` ma `MAX_ATTEMPTS=5` + discard challenge → **MFA-brute ODRZUCONY**; AUD-030 ograniczony do password-reset/invitation.
- F-1/F-2 (brak indeksów): agent F sklasyfikował CRITICAL; wg skali audytu (CRITICAL = cross-tenant/auth/RCE/data-loss/secret) to **HIGH** (degradacja wydajności na realnym wolumenie).
- K1/K3 (backup): agent K sklasyfikował CRITICAL; na dev to **HIGH** (mechanizm backupu nie działa = SaaS-blocker), na PROD byłoby CRITICAL.
- A-03 = B-05 (asset preview), B-02 = H-01 (token_dev_only), E-06 = F-4 (eksport OOM), L-01 = J-04 (deptrac skip) — zdeduplikowane.

## Pozytywy empiryczne (zweryfikowane na żywym stacku, nie deklaratywne)

- **Izolacja danych domenowych przez TenantFilter — SOLIDNA.** Matryca demo↔acme (`probes/matrix-2tenant.txt`): kolekcje pokazują wyłącznie własne dane (demo objects=6747 vs acme=3; attributes 37 vs 9; assets 10 vs 0); cross-read po ID = **404 w obie strony** dla products/objects/attributes/channels/assets/import-sessions; nagłówek `X-Tenant-Id` ignorowany (tenant z JWT, nie sterowalny). Działa **nawet dla super-admina** (najtwardszy przypadek). `TenantFilterConfigurator` włącza filtr per request bez wyjątku dla super-admina.
- **Operator-panel `/api/admin/tenants` poprawnie zaprojektowany:** `requireSuperAdmin()` (non-super → 403), tylko metadane (code/name/plan/active_users), audyt `cross_tenant_access=true`.
- Pozytywy strukturalne z domen (potwierdzone): PHPStan level max baseline pusty (0 błędów), composer audit 0 CVE, eksport formula-injection neutralizowany (IMP2-2.8 fix), SSRF `NoPrivateNetworkHttpClient`, 0 deserializacji, MFA brute-cap, JWT w pamięci (nie localStorage), refresh httponly cookie, nagłówki edge dev (CSP/HSTS/XFO), optimistic locking + unikalność SKU w DB, WAL archiving żyje, break-glass audytowany.

---

## CRITICAL

### AUD-001 — Mercure SSE: anonimowa subskrypcja + eventy bez `private` + topiki bez scope tenanta = cross-tenant leak w czasie rzeczywistym
- **Severity:** CRITICAL · **Confidence:** confirmed · **Domena:** A (multi-tenant)
- **Lokalizacja:** `docker-compose.yml:372-374` (`anonymous`); `apps/api/src/Catalog/Application/Subscriber/MercurePublisher.php:126-127` (topiki bez tenanta); `Symfony\Component\Mercure\Update` default `private=false`; endpoint `/.well-known/mercure`.
- **Dowód:** hub z dyrektywą `anonymous`; `grep "new Update(...true)"` = 0 trafień (każdy event publiczny); topiki `https://pim.localhost/objects` bez prefiksu tenanta. PROBE: `curl -sk "https://pim.localhost/.well-known/mercure?topic=…objects"` → HTTP/2 200, `content-type: text/event-stream`, kanał otwarty bez tokena.
- **Scenariusz:** atakujący bez konta subskrybuje `?topic=…/objects` i odbiera w czasie rzeczywistym objectId/kind/code/zmienione atrybuty WSZYSTKICH tenantów (broadcast topic). Analogicznie import/export progress, `identity/tenant/{id}`.
- **POTWIERDZENIE EMPIRYCZNE (matryca):** anonimowy subskrybent (bez tokena, bez tożsamości tenanta) odebrał 2 realne eventy tenanta demo — `object.enabled_changed` z `objectId: 019ecbf1-…` (obiekt RPT-1). Dowód: `probes/mercure-stream.txt`, `probes/mercure-leak.txt`.
- **Rekomendacja:** usuń `anonymous` (wymuś subscriber-JWT); `new Update(..., private: true)` dla wszystkich eventów; prefiks tenanta w topikach + endpoint mintujący `mercureAuthorization` cookie ograniczony do topików tenanta usera. (Domknąć: czy Export/Import/PermissionInvalidation publishery też używają topików bez tenant_id.)
- **Estymata:** M (8-16h). Pełny dowód: `02-domain-reports/A-multitenant.md` (A-01).

### AUD-002 — Connection user = superuser + BYPASSRLS + owner wszystkich tabel; RLS martwy w runtime, izolacja wyłącznie app-layer
- **Severity:** CRITICAL · **Confidence:** confirmed · **Domena:** A
- **Lokalizacja:** `apps/api/.env:9` (`POSTGRES_USER=pim`); `raw/db-owner-roles.txt:15` (`pim|t|t|t`); `raw/db-rls-enabled.txt` (FORCE=`f` wszędzie).
- **Dowód:** `psql "SELECT current_user"` → `pim`; `pim` ma `rolsuper=t`, `rolbypassrls=t`, jest ownerem 86/86 tabel; 0 tabel z FORCE RLS; tylko 7/~46 tenantowanych tabel ma ENABLE. Trzy niezależne drogi bypassu RLS. Łamie `01-architektura-pim.md:867` („app user nigdy nie ma BYPASSRLS") + R-09.
- **Scenariusz:** jakikolwiek bug w `TenantFilter` (native SQL, zapomniany `TenantScoped`, `disable('tenant')` bez re-enable) = natychmiastowy cross-tenant odczyt/zapis na objects/object_values/attributes/channels/assets — bez drugiej linii obrony. „Live proof izolacji" z IMP2-2.4/2.5 testował TenantFilter, nie RLS (martwy na superuserze).
- **Rekomendacja:** osobna rola `pim_app` (NOSUPERUSER, NOBYPASSRLS, nie-owner) z GRANT-ami; `ENABLE` + `FORCE ROW LEVEL SECURITY` na wszystkich tabelach domenowych; owner/migracje pod `pim_owner`. Warunek wstępny multi-tenant go-live.
- **Estymata:** L (16-24h+). Pełny dowód: `A-multitenant.md` (A-02).

### AUD-003 — Brak rozróżnienia roli platformowej od per-tenant `super_admin` — fixtures nadają globalny super_admin Ownerom (recon metadanych + ryzyko cross-tenant write)
- **Severity:** HIGH (rewizja po empiryku: z CRITICAL — TenantFilter izoluje dane domenowe; problem to model uprawnień, nie data-leak) · **Confidence:** confirmed
- **REWIZJA EMPIRYCZNA:** matryca dowiodła, że to NIE wyciek danych domenowych — `/api/admin/tenants` zwraca tylko metadane (gated `requireSuperAdmin`, audytowany), a cross-read po ID = 404. Pozostaje realnym ryzykiem: brak rozróżnienia platform-vs-tenant + fixtures/seed nadają globalny `super_admin` Ownerowi każdego tenanta → Owner klienta widzi metadane konkurencji + (probable, write nietestowany per guardrail) może suspend/delete cudzy tenant.
- **Domena:** B (RBAC) · **Lokalizacja:** `RbacSeeder.php:103`, `AppFixtures.php:185`; `SuperAdminTenantsController`/`SuperAdminTenantWriteController` (gate tylko `#[RequiresPermission(user, admin)]`); `SuperAdminContext::runCrossTenant` wyłącza TenantFilter.
- **Dowód:** login `admin@demo.localhost` → `GET /api/admin/tenants` → HTTP 200, widoczne 2 tenanty (acme+demo); `GET /api/admin/tenants/{acme_id}` → HTTP 200 (detal cudzego tenanta). DB: `super_admin` globalna (tenant_id NULL), przypisana adminowi KAŻDEGO tenanta; `grep platform_admin|isPlatform` = 0 (brak rozróżnienia platform-vs-tenant).
- **Scenariusz:** Owner tenanta A wywołuje `DELETE/POST /api/admin/tenants/{B}/suspend` → kasuje/zawiesza konkurencyjnego klienta SaaS, albo `GET` → recon listy klientów. Pełne złamanie izolacji najemców na płaszczyźnie admin.
- **Rekomendacja:** odrębna rola `platform_operator` (tenant_id NULL, NIE dla Ownerów) z `platform.tenants.manage`; `super_admin` per-tenant dla Ownera; `/api/admin/*` gate na `platform.tenants.manage`.
- **Estymata:** M-L (12-20h). Pełny dowód: `B-rbac.md` (B-01).

### AUD-004 — Meilisearch filter injection przez niewalidowany klucz filtra → cross-tenant read
- **Severity:** CRITICAL · **Confidence:** confirmed · **Domena:** C (injection)
- **Lokalizacja:** `apps/api/src/Search/Application/CatalogSearchService.php:81-93` (reachable z `SearchController::run`, `BulkSelectionController`).
- **Dowód:** `$key` z `?filter[<KEY>]` wstawiany do wyrażenia Meili przez `sprintf('%s = "%s"', $key, addslashes($value))` bez whitelistu klucza (tylko `$facets` whitelistowane). PROBE na żywym Meili: klucz `parentId IS NULL OR tenantId` z wartością tenanta-ofiary → wyrażenie `tenantId="0000…" AND kind="product" AND parentId IS NULL OR tenantId="019ed034…"` → `estimatedTotalHits=1` (docs cudzego tenanta) vs `0` w czystym scope. Operator OR w kluczu znosi AND-scope tenanta.
- **Scenariusz:** user tenanta A: `GET /api/search/products?filter[parentId IS NULL OR tenantId]=<id-B>` → odczyt produktów tenanta B (SKU, atrybuty, completeness). Enumeracja danych konkurencji.
- **Rekomendacja:** whitelist kluczy filtra wobec `filterableAttributes` (jak dla `$facets`), 400 spoza listy; mapować klucz→znana nazwa pola; `texnantId` jako osobny niemieszany scope.
- **Estymata:** M (4-8h). Pełny dowód: `C-injection.md` (C-1).

### AUD-005 — Realne sekrety w trackowanym `apps/api/.env` (BYOK master key, JWT passphrase, Mercure JWT) + jawny allowlist w `.gitignore`
- **Severity:** CRITICAL · **Confidence:** confirmed (z zastrzeżeniem: wartości dev — patrz niuans)
- **Domena:** D (sekrety) · **Lokalizacja:** `apps/api/.env:78` (`APP_BYOK_KEY_V1`), `:84` (`JWT_PASSPHRASE`), `:112` (`MERCURE_JWT_SECRET`); `.gitignore:52` (`!apps/api/.env`).
- **Dowód:** `git ls-files apps/api/.env` → tracked; `.gitignore` jawnie force-trackuje. `APP_BYOK_KEY_V1` = master key AES-256-GCM szyfrujący klucze BYOK klientów (ADR-0017) → kompromitacja = odszyfrowanie kluczy klientów z `tenant_agent_configs`. Wszystko w historii gita od `8927c4cb`/`a2be99cc`. Gitleaks historia: 4 trafienia (`.env`, `.env.dev`, fixture).
- **Niuans:** `APP_ENV=dev`, część wartości to placeholdery (`!ChangeThisMercure…`), ale BYOK/JWT mają niezerową entropię i muszą być traktowane jako skompromitowane. Plik sam mówi „DO NOT DEFINE PRODUCTION SECRETS". Severity CRITICAL utrzymane bo: (a) sekret w VCS = rotacja obowiązkowa, (b) prod fallback (AUD-009) może użyć tych samych klas wartości.
- **Rekomendacja:** usuń `.env`/`.env.dev` z trackingu (`git rm --cached`), usuń allowlist `!apps/api/.env`, rotuj WSZYSTKIE klucze, przenieś do Symfony Secrets Vault / env injection na prod, commituj tylko `.env.example` z placeholderami.
- **Estymata:** M (4-8h + rotacja). Pełny dowód: `D-secrets-config.md` (D-01).

---

## HIGH

### AUD-006 — `GET /api/assets/{id}/preview`: PUBLIC_ACCESS + jawny `disable('tenant')` serwuje bajty dowolnego tenanta po UUID
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** A/B (dedup A-03 + B-05)
- **Lokalizacja:** `security.yaml:105` (PUBLIC_ACCESS), `PreviewAssetController.php:58-62` (`$filters->disable('tenant')`), `AssetUploader.php:187` (UUID w `attributes_indexed.previewUrl`).
- **Dowód:** PROBE: `curl` anonimowy `/api/assets/{uuid}/preview` → HTTP 404 (nie 401) = endpoint bez auth. Komentarz autora przyznaje „tenant isolation is by-id rather than by-context here". UUIDv7 zawiera timestamp (częściowo przewidywalny), wycieka w eksportach/logach/response.
- **Scenariusz:** atakujący zna UUID assetu tenanta B (z eksportu/response innego usera) → pobiera bajty bez logowania i bycia w tenancie B.
- **POTWIERDZENIE EMPIRYCZNE (matryca):** wektor confirmed (anonim/cross-tenant request dociera do rekordu mimo `disable('tenant')`, zwraca 404 nie 401), ale wyciek BAJTÓW nieodtworzony — wszystkie 10 assetów demo to osierocone rekordy bez fizycznych blobów (404 „Variant blob missing" także dla właściciela). Gdyby blob istniał, anonim by go pobrał. Confidence wycieku bajtów = probable. Dowód: `probes/asset-preview.txt`.
- **Rekomendacja:** krótko-żyjące signed URL (HMAC+TTL) mintowane przez authenticated API; do tego czasu sprawdzaj tenant w handlerze gdy request niesie principal.
- **Estymata:** M (8-12h). Dowód: `A-multitenant.md` (A-03), `B-rbac.md` (B-05).

### AUD-007 — `token_dev_only`: plaintext token resetu hasła i zaproszenia zwracany w response HTTP bez guardu środowiska (account takeover na deploy)
- **Severity:** CRITICAL (rewizja po empiryku: z HIGH — potwierdzony account takeover, klasa auth-bypass) · **Confidence:** confirmed (empirycznie: 64-znakowy token w body bezwarunkowo) · **Domena:** B/H (dedup B-02 + H-01)
- **Lokalizacja:** `PasswordResetController.php:53`, `InvitationController.php:78`, `InvitationActionsController.php:148`.
- **Dowód:** PROBE live: `POST /api/auth/password-reset/request {email}` → HTTP 200 `{"status":"sent","token_dev_only":"<256-bit token>"}`. `grep kernel.debug|when@prod|APP_ENV` w kontrolerach = 0 (brak guardu). Endpoint PUBLIC_ACCESS. Token funkcjonalny (`consume()` go akceptuje).
- **Scenariusz:** na prod (jeśli kod trafi bez naprawy + brak realnego mailera) atakujący znając email ofiary → reset → `confirm` → account takeover cross-tenant. Dokładnie lekcja #657/#658.
- **Rekomendacja:** zwracać `token_dev_only` tylko gdy `kernel.environment !== 'prod'` lub nigdy (token wyłącznie przez Mailer). Dopóki niezałatane — endpointy NIE gotowe do prod.
- **Estymata:** S (2-4h). Dowód: `B-rbac.md` (B-02), `H-api-contract.md` (H-01).

### AUD-008 — Attribute-level (3-state) permissions NIE są egzekwowane na ścieżce danych (read / PATCH / export)
- **Severity:** HIGH · **Confidence:** confirmed (z kodu) · **Domena:** B
- **Lokalizacja:** `FieldRestrictionFilter.php` (jedyny konsument = `RestrictedField.php`); `CatalogObjectProcessor.php` (PATCH, brak `canEdit`); serializer CatalogObject (GET pełne `attributes`); `Export/` (grep `AttributePermission|PermissionResolver` = 0).
- **Dowód:** docstring `FieldRestrictionFilter`: „wiring per endpoint … is the Phase 6 retrofit's responsibility". `canViewAttribute/canEditAttribute` używane tylko w schemacie formularza (`GetObjectTypeListSchemaHandler.php:64`), nie w danych. Export nie ma konsumenta policy.
- **Scenariusz:** user z attribute-permission `view`/`restricted` na wrażliwym atrybucie (cena zakupu): (a) odczyta przez `GET /api/products/{id}`, (b) zmodyfikuje przez PATCH mimo braku `edit`, (c) wyeksportuje do CSV. PRD §3.5 spełnione tylko w UI, nie w API.
- **Rekomendacja:** wpiąć `FieldRestrictionFilter` w normalizer odpowiedzi (read) + bramkę edit w `CatalogObjectProcessor` + filtr policy w eksporcie przed kolumnami.
- **Estymata:** L (16-24h). Dowód: `B-rbac.md` (B-04), `J-tests-ci.md`.

### AUD-009 — Prod overlay startuje z fallback-defaultami sekretów; nic nie wymusza zmiany
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** D
- **Lokalizacja:** `docker-compose.prod.yml:102,108,115,118` + base `docker-compose.yml:132,315-316`.
- **Dowód:** wzorzec `${VAR:-default}`: `APP_SECRET:-ChangeMeBeforeDeploy`, `POSTGRES_PASSWORD:-!ChangeMe!`, `MERCURE_JWT_SECRET:-!ChangeMercure…`, `MEILI_MASTER_KEY:-masterKeyPleaseChangeMe`, `MINIO_ROOT_*:-minioadmin`. `docker compose -f … prod up` z pustym `.env` startuje produkcję na słabych kluczach BEZ aborcji (kontrast: `alertmanager` celowo fails loudly).
- **Scenariusz:** deploy bez kompletu env-ów → prod na znanych publicznie defaultach (forge JWT/Mercure, dostęp DB/MinIO).
- **Rekomendacja:** usuń fallbacki dla sekretów (fail-loud na braku), `.env.prod`/runbook deployu, realne użycie Secrets Vault.
- **Estymata:** M (4-8h). Dowód: `D-secrets-config.md` (D-02).

### AUD-010 — `apps/api/.env.dev` trackowany z realnym `APP_SECRET`, luka w `.gitignore`
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** D
- **Lokalizacja:** `apps/api/.env.dev:3`; `.gitignore` (pattern `.env` nie łapie `.env.dev`, brak świadomego allowlistu).
- **Dowód:** `git show HEAD:apps/api/.env.dev` istnieje; `git check-ignore` brak dopasowania → plik wszedł „przez przypadek"; zawiera `APP_SECRET=<hex>` (commit `a2be99cc`).
- **Rekomendacja:** `git rm --cached apps/api/.env.dev`, dodać do `.gitignore`, rotować `APP_SECRET`.
- **Estymata:** S (1-2h). Dowód: `D-secrets-config.md` (D-03).

### AUD-011 — Brak custom PHPStan rule „flush-bez-clear" — NIENEGOCJOWALNA wytyczna §3.10 nieegzekwowana
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** E (FrankenPHP)
- **Lokalizacja:** `apps/api/src/PHPStan/Rules/` (tylko `RequiresPermissionAnnotationRule`, `HardcodedRoleCheckRule`); `phpstan-config.txt:14-15` (komentarz: „third rule … tracked as follow-up").
- **Dowód:** reguła nie istnieje; gwarancja jest manualna (dyscyplina dziedziczenia `AbstractBatchHandler`). CLAUDE.md §3.10 deklaruje ją jako CI-gate.
- **Scenariusz:** pierwszy handler nowego kontrybutora z `flush()` w pętli bez `clear()` przejdzie CI zielono i zOOM-uje workera pod 50k SKU (R-25).
- **Rekomendacja:** zaimplementować regułę + dodać do `phpstan/services.neon`.
- **Estymata:** M (6-10h). Dowód: `E-frankenphp-memory.md` (E-01).

### AUD-012 — Worker bez `deploy.resources.limits` — nieograniczony pamięciowo na poziomie kontenera
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** E
- **Lokalizacja:** `docker-compose.yml:180-206` (worker bez `*resource_limits_api`); komentarz HARD-02 (linie 17-29) sam ostrzega.
- **Dowód:** worker ma tylko `<<: *default_restart`; `--memory-limit=256M` to soft-recycle MIĘDZY wiadomościami — pojedyncza leakująca wiadomość urośnie ponad limit i ubije host zanim Messenger zrecykluje.
- **Rekomendacja:** dodać `deploy.resources.limits.memory` (np. 512M) do workera; alert Prometheus `frankenphp_worker_memory_bytes`.
- **Estymata:** S (1-2h + prod overlay). Dowód: `E-frankenphp-memory.md` (E-02).

### AUD-013 — Brak indeksu GIN na `objects.attributes_indexed` (regres migracji) — filtr atrybutowy nie skaluje
- **Severity:** HIGH (agent F: CRITICAL; przeklasyfikowane jako perf) · **Confidence:** confirmed · **Domena:** F
- **Lokalizacja:** `Version20260430092112.php:167` (`up()` DROP bez odtworzenia), `:223` (`down()` odtwarza jako btree zamiast GIN).
- **Dowód:** `grep attributes_indexed raw/db-indexes.txt` = 0. EXPLAIN: `attributes_indexed @> …` leci jako `Filter`, nie `Index Cond`. Kod zakłada GIN (`AttributeFilter.php:18-22`, `JsonbContainsFunction.php:19`).
- **Scenariusz:** każdy `?attribute[brand]=…` skanuje wszystkie wiersze tenanta (do 50k, sufit 200k) — rdzeń wyróżnika (hybrid atrybutów) nie skaluje.
- **Rekomendacja:** `CREATE INDEX … USING GIN (attributes_indexed jsonb_path_ops)`. Benchmark p95 po zaseedowaniu 50k.
- **Estymata:** S (1-2h migracja + weryfikacja). Dowód: `F-performance-static.md` (F-1).

### AUD-014 — Brak indeksu GiST na `objects.path` (ltree drzewa kategorii)
- **Severity:** HIGH (perf) · **Confidence:** confirmed · **Domena:** F
- **Lokalizacja:** `Version20260430092112.php:166,168` (DROP), brak odtworzenia w forward-path (kontrast: `channel_category_nodes` ma GiST).
- **Dowód:** `pg_indexes` dla `objects` path = pusty. Zapytania ltree (`path <@ :ancestor`) → seq scan po całej `objects`.
- **Rekomendacja:** `CREATE INDEX objects_path_gist_idx ON objects USING GIST (path) WHERE kind='category'`.
- **Estymata:** S (1-2h). Dowód: `F-performance-static.md` (F-2).

### AUD-015 — Eksport non-streaming (scope Selected/Filter lub All+variants) materializuje cały graf do pamięci — OOM workera
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** E/F (dedup E-06 + F-4)
- **Lokalizacja:** `SyncExportRunner.php:104` (`canStream()` true tylko `scope=All && !includesVariants()`), `:116,130,149` (`resolveTargets`/`findByObjectType`/`applyVariantFanout`).
- **Dowód:** każda ścieżka poza All-masters ładuje `list<CatalogObject>` + dzieci + `$result`/mapy naraz. Docblock „stays under 50 MB" prawdziwy tylko dla streamowalnej ścieżki. Brak limitu kontenera (AUD-012) potęguje.
- **Scenariusz:** eksport 50k SKU z `include_variants=ON` (typowy przy sync do kanału) hydratuje pełny graf → OOM (próg 256 MiB, R-25).
- **Rekomendacja:** keyset-paging dla wszystkich ścieżek eksportu (nie tylko All-masters); batch-prefetch.
- **Estymata:** L (16-24h). Dowód: `E-frankenphp-memory.md` (E-06), `F-performance-static.md` (F-4).

### AUD-016 — Eksport: N+1 na `object_values` (oraz relacjach/kategoriach) per obiekt
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** F
- **Lokalizacja:** `ExportBuilder.php:166` (`findByObject` per obiekt), `:207` (relacje per obiekt/kolumna), `:249` (kategorie).
- **Dowód:** 50k SKU × (1 SELECT values + relacje + kategorie) = 100k-150k roundtripów na jeden eksport. Cel PRD §11.2 (<30s) nieosiągalny dla tej ścieżki.
- **Rekomendacja:** batch-prefetch `findByObjectIds(page)` zamiast `findByObject(object)` dla strony keyset.
- **Estymata:** M (8-16h). Dowód: `F-performance-static.md` (F-3).

### AUD-017 — Cron backupu martwy od ~49 dni — najnowszy backup bazowy z 2026-04-28
- **Severity:** HIGH (agent K: CRITICAL; na PROD = CRITICAL) · **Confidence:** confirmed · **Domena:** K
- **Lokalizacja:** runtime crontab `postgres`; `docker/postgres/Dockerfile:49-53` vs runtime drift.
- **Dowód:** `pgbackrest --stanza=pim info` → jedyny full `20260428-070020F`. Brak `cron.log`, `/var/spool/cron/cronstamps/` pusty (dcron nigdy nie wykonał crontaba postgres). Stack uruchamiany 06-13/14/16.
- **Scenariusz:** RPO bazowe = 49 dni; PITR przez WAL żywy (K2 pozytyw), ale replay 49 dni WAL na starej bazie = godziny + ryzyko (restore-test wyłączony — AUD-021).
- **Rekomendacja:** naprawić crontab/obraz (rebuild database image), zweryfikować pierwszy automatyczny backup, włączyć restore-test.
- **Estymata:** M (4-8h). Dowód: `K-backup-dr.md` (K1).

### AUD-018 — MinIO bez backupu i wersjonowania; repo backupu bazy w tym samym MinIO co assety (SPOF)
- **Severity:** HIGH (agent K: CRITICAL) · **Confidence:** confirmed · **Domena:** K
- **Lokalizacja:** `mc version info` (oba buckety un-versioned); brak `mc mirror`/replikacji.
- **Dowód:** `pim-assets`/`pim-backups` un-versioned; pgBackRest repo w tym samym MinIO instance. Utrata wolumenu `minio_data` = jednoczesna utrata assetów + backupów bazy.
- **Rekomendacja:** osobny storage/region dla repo backupu, wersjonowanie bucketów, `mc mirror`/replikacja assetów.
- **Estymata:** M-L (na prod). Dowód: `K-backup-dr.md` (K3).

### AUD-019 — Offboarding tenanta (hard-delete) niewykonalny przy obecnych FK (24× ON DELETE RESTRICT) — RODO art. 17 niespełnione
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** K
- **Lokalizacja:** `PurgeDeletedTenantsCommand.php:138-143` (sam `remove($tenant)`); FK na `tenants` (raw `db-fk-ondelete.txt`): 24 RESTRICT.
- **Dowód:** PHPDoc twierdzi „CASCADE takes care", ale 24 FK RESTRICT (objects, users, assets, attributes, channels, import_*, export_*) → `DELETE FROM tenants` rzuci FK-violation dla każdego realnego tenanta. Brak testu (`rg PurgeDeletedTenants tests` = 0).
- **Rekomendacja:** zaimplementować uporządkowany hard-delete (kaskada w kodzie lub FK CASCADE celowo) + test offboardingu; spełnić RODO.
- **Estymata:** L (16-24h). Dowód: `K-backup-dr.md` (K4).

### AUD-020 — Brak kaskady do MinIO przy offboardingu — assety/eksporty tenanta (PII) zostają w storage bezterminowo
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** K
- **Lokalizacja:** brak kodu kasującego obiekty MinIO przy delete tenanta (grep w Asset/Export/Import = 0); `PurgeDeletedTenantsCommand` dotyka tylko bazy.
- **Dowód:** izolacja storage tylko przez prefiks `<tenant-uuid>/`; po offboardingu binarki (zdjęcia=PII, eksporty z danymi) zostają. RODO art. 17 niespełnione dla obiektów.
- **Rekomendacja:** kaskada usunięcia obiektów MinIO (prefix delete) spięta z offboardingiem.
- **Estymata:** M (8-12h, łączone z AUD-019). Dowód: `K-backup-dr.md` (K5).

### AUD-021 — Obraz `pim-database` zbudowany 7 tygodni temu; runtime crontab to stara wersja, restore-test wyłączony
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** K
- **Lokalizacja:** `docker image inspect pim-database:local` (Created 2026-04-28); runtime crontab vs `Dockerfile:49-53`.
- **Dowód:** runtime ma 1 wpis `pim-cron.sh` bez argumentu (→ `TYPE=incr`), brakuje cotygodniowego `pim-restore-test.sh` (wczesne wykrycie korupcji backupu). Drift kod→runtime ukrywa że ulepszenia nie działają.
- **Rekomendacja:** `docker compose build database`, weryfikacja crontaba w runtime, CI-check zgodności.
- **Estymata:** S (2-4h). Dowód: `K-backup-dr.md` (K2).

### AUD-022 — 57/100 plików E2E wyłączonych w CI (`test.fixme(process.env.CI)`)
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** J
- **Lokalizacja:** `apps/admin/e2e/` (products/channels/attributes/categories/assets); root cause = współdzielony rate-limiter 5/15min + brak `storageState`.
- **Dowód:** ~66 testów `test.fixme` (52 `CI` + 14 `true`). CLAUDE.md: „bez E2E ticket nie jest done" — ponad połowa krytycznych flow UI nie biegnie regresyjnie.
- **Rekomendacja:** per-spec storageState (login raz, reuse) + reset puli limitera w teście → odblokować 57 speców.
- **Estymata:** M (8-16h). Dowód: `J-tests-ci.md` (J-01).

### AUD-023 — Auth happy-path (login/logout/refresh, token-not-in-localStorage) NIE testowany w CI (6/8 `test.fixme(true)`)
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** J
- **Lokalizacja:** `apps/admin/e2e/auth.spec.ts` (`BLOCKED_BY_41`).
- **Dowód:** 6/8 testów `test.fixme(true)` — pomijane zawsze: login→dashboard, logout, silent refresh, „seeded credentials match fixtures", access-token-not-in-localStorage. Krytyczny flow auth + XSS-surface tokenu bez siatki regresji.
- **Rekomendacja:** odblokować po #41 lub niezależnie; to bezpieczeństwo, nie feature.
- **Estymata:** M (część AUD-022). Dowód: `J-tests-ci.md` (J-02).

### AUD-024 — Brak testów API dla custom role builder + password-reset/invitation/magic-link (powierzchnie eskalacji i auth)
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** J
- **Lokalizacja:** `RoleWriteController`, `RoleAttributePermissionsController`, `PasswordResetService`, `InvitationService`, `MagicLinkTokenHasher` — `rg` po `tests/` = 0 dla write/expiry/single-use.
- **Dowód:** jedyny test ról = `RolesListControllerTest` (GET listy). Brak testu że non-admin dostaje 403 na `POST/PATCH/DELETE /api/roles`, że PATCH cudzej roli → 404, że token reset jest single-use/expiry i nie wycieka (lekcja #657/#658).
- **Rekomendacja:** Api/Unit testy eskalacji ról + token lifecycle.
- **Estymata:** M (8-16h). Dowód: `J-tests-ci.md` (J-03).

### AUD-025 — Onboarding kłamie: złe dane logowania + pominięty `audit:schema:update` → blokada „Day 1" + 500 na audytowanych encjach
- **Severity:** HIGH · **Confidence:** confirmed · **Domena:** M (dedup M1-01 + M1-02)
- **Lokalizacja:** `ONBOARDING.md:18` (`admin@demo.local`/`demo` — błędne), `:14-15` (brak `audit:schema:update`); poprawne: `AppFixtures.php:58,173` (`admin@demo.localhost`/`changeme`), `DatabaseResetCommand.php:73-98`.
- **Dowód:** PROBE: `admin@demo.localhost`/`changeme` → HTTP 200 + JWT; `admin@demo.local`/`demo` → fail. ONBOARDING uczy 2-krokowego seedu bez `audit:schema:update --force` → INSERT do audytowanej encji rzuca „relation *_audit does not exist" → 500.
- **Rekomendacja:** poprawić creds, udokumentować `pim:db:reset` jako kanoniczny one-shot, dodać `audit:schema:update` do ręcznego flow.
- **Estymata:** S (1-2h). Dowód: `M-dx-metrics.md`, `M-onboarding.md` (M1-01/02).

---

## MEDIUM

### AUD-026 — Drift nazwy filtra `tenant` vs `tenant_filter` → break-glass nigdy nie wyłącza TenantFilter (fałszywy invariant)
- HIGH-impact-latentny ale MEDIUM (dziś maskowane). **Confidence:** confirmed · **Domena:** A
- **Lokalizacja:** `doctrine.yaml:129` (`tenant`) vs `SuperAdminContext.php:44` (`FILTER_NAME='tenant_filter'`), `:91-103`.
- **Dowód:** `isEnabled('tenant_filter')` zawsze false → `disable()` pomijane → filtr `tenant` pozostaje aktywny w cross-tenant callbacku. Maskowane bo break-glass operuje głównie na encjach nie-TenantScoped/raw SQL.
- **Rekomendacja:** ujednolicić `FILTER_NAME='tenant'`; test że `useCrossTenantMode` faktycznie wyłącza filtr; `SuperAdminContext implements ResetInterface`.
- **Estymata:** S (2-4h). Dowód: `A-multitenant.md` (A-04).

### AUD-027 — GUC name drift: listenery ustawiają `app.current_tenant`, polityki `refresh_tokens` czytają `pim.current_tenant_id`
- **Confidence:** confirmed · **Domena:** A
- **Lokalizacja:** `RlsContextListener.php:58,67`, `TenantRlsGucMiddleware.php:62,74` (ustawiają `app.current_tenant`); `raw/db-rls-policies.txt:13-16` (`refresh_tokens` czyta `pim.current_tenant_id`).
- **Dowód:** kod nigdy nie ustawia `pim.current_tenant_id`. Po włączeniu FORCE RLS (AUD-002 fix) `refresh_tokens` deny-all → refresh login zepsuty; 5/18 polityk martwych.
- **Rekomendacja:** ujednolicić wszystkie polityki na `app.current_tenant`; sync `docs/multi-tenancy.md`.
- **Estymata:** S (2-4h). Dowód: `A-multitenant.md` (A-05).

### AUD-028 — MinIO bucket `pim-assets` ustawiony na anonimowy download
- **Confidence:** confirmed · **Domena:** A · **Lokalizacja:** `docker-compose.yml:359` (`mc anonymous set download local/pim-assets`).
- **Dowód:** cały bucket pobieralny bez credentiali; mitygacja: MinIO tylko `expose` (nie published) w obecnym compose. Bomba zegarowa przy bezpośrednim wystawieniu.
- **Rekomendacja:** usuń `mc anonymous set download`, presigned URL (spójne z AUD-006).
- **Estymata:** S (2-4h). Dowód: `A-multitenant.md` (A-06).

### AUD-029 — `PermissionResolver`: legacy `user_roles` w UNION wnosi pusty scope → neutralizuje per-locale/channel restriction
- **Confidence:** confirmed · **Domena:** B · **Lokalizacja:** `PermissionResolver.php:113-125,164-173`.
- **Dowód:** SQL łączy `user_role_assignments` (ze scope) UNION `user_roles` (literalny `'[]'`); `mergeScope` traktuje pusty jako most-permissive. Fixtures DEMO używają `user_roles` → admin@demo ma pusty scope locale/channel.
- **Rekomendacja:** przyspieszyć konsolidację `#644`; do tego czasu nie polegać na per-locale/channel scope jako granicy.
- **Estymata:** M (8-12h). Dowód: `B-rbac.md` (B-06).

### AUD-030 — Brak rate-limitu na `password-reset/request` i `invitations/*/accept` (spam/enumeration; MFA-brute ODRZUCONY)
- **Confidence:** confirmed · **Domena:** B/H (B-03 zredukowany + H-07)
- **Lokalizacja:** brak limitera (tylko `auth_login` 5/15min i `auth_refresh` mają konsumentów).
- **Dowód:** PROBE i kod: brak `->consume()` na tych endpointach. **Rozstrzygnięcie konfliktu:** 2FA-brute z B-03 ODRZUCONY — `MfaLoginChallengeStore.php:30,101` ma `MAX_ATTEMPTS=5` + discard challenge (H-06 ma rację). Token reset 256-bit + always-200 anti-enum → impact ograniczony do spamu/timing.
- **Rekomendacja:** limiter per-IP+per-email na reset, per-IP na invitation accept.
- **Estymata:** S (2-4h). Dowód: `B-rbac.md` (B-03), `H-api-contract.md` (H-06/H-07).

### AUD-031 — FilterDSL → SQL przez konkatenację literałów (bezpieczne TYLKO przy `standard_conforming_strings=on`); `ExportPreflightController` pomija `validate()`
- **Confidence:** confirmed · **Domena:** C · **Lokalizacja:** `FilterDslResolver.php:637,656,670,627`; `ExportPreflightController.php:213-219`.
- **Dowód:** kompilator buduje parameter-free SQL (komentarz przyznaje „VIEW-10 will switch to PDO-bound"). EXPLAIN potwierdza: escaping `''` trzyma przy obecnej konfiguracji (`x' OR '1'='1` → pojedynczy literał). ALE `ExportPreflightController::countFilter` bierze DSL z payloadu bez `validate()`.
- **Scenariusz:** dziś NIE wykonalny SQLi; dług — zależność od ustawienia serwera + każda nowa gałąź kompilatora bez bind-params ryzykuje dziurę.
- **Rekomendacja:** PDO bind params (VIEW-10) lub egzekwować `standard_conforming_strings=on` w bootstrapie + `validate()` w ExportPreflight.
- **Estymata:** L (8-16h pełna parametryzacja) / S (1-2h hardening). Dowód: `C-injection.md` (C-2).

### AUD-032 — `ValueWriteCore` nie egzekwuje kontraktu envelope dla 12/17 typów — dowolny śmieć w `object_values.value`
- **Confidence:** confirmed · **Domena:** C · **Lokalizacja:** `ValueWriteCore.php:34-65` (`VALUE_VALIDATED_TYPES` = tylko 5), `ObjectValue.php:74-90`.
- **Dowód:** test `normalise` w kontenerze: `number="abc OR 1=1"`, `price.amount="lol"`, dodatkowe klucze (`__proto__`, `evil`), `<script>` — wszystko przechodzi verbatim. Kontrakt `docs/api/jsonb-schemas.md` nieegzekwowany przy zapisie.
- **Scenariusz:** klient API zapisuje non-canonical value → niezaudytowane readery (Meili `DocumentFlattener`, integracje Shopify/BaseLinker, completeness) mogą się wykrzaczyć/propagować śmieci do kanałów; wektor stored-XSS (AUD-033).
- **Rekomendacja:** walidacja per-type dla wszystkich typów + `additionalProperties:false` (odrzucanie extra keys).
- **Estymata:** M (4-8h). Dowód: `C-injection.md` (C-3).

### AUD-033 — Brak serwerowej sanityzacji HTML wysiwyg — XSS broniony WYŁĄCZNIE przez DOMPurify na froncie
- **Confidence:** confirmed · **Domena:** C · **Lokalizacja:** `WysiwygValidator.php:26-52` (tylko `is_string`+`max_length`); render `wysiwyg-editor.tsx:125` (DOMPurify — dziś safe).
- **Dowód:** zapisany HTML to surowy string (`<img onerror>` ląduje w bazie verbatim — AUD-032). Obrona „depend on every reader to sanitize".
- **Scenariusz:** dowolny przyszły/niezaudytowany konsument renderujący wartość bez DOMPurify (storefront, raport, email, eksport HTML) → stored-XSS się odpala.
- **Rekomendacja:** serwerowa sanityzacja HTML przy zapisie (allow-list tagów, np. HTMLPurifier); minimum blokować `javascript:`/`data:` w href.
- **Estymata:** M (4-6h). Dowód: `C-injection.md` (C-4).

### AUD-034 — `ImageDownloadMessage` bez dead-letter listenera → sesja importu może utknąć non-terminal na zawsze
- **Confidence:** confirmed · **Domena:** E · **Lokalizacja:** `ImportRunDeadLetterListener.php:44` (obsługuje tylko `ImportRunMessage`); `ImageDownloadHandler.php:195-207` (decrement na końcu `__invoke`).
- **Dowód:** finalizacja wymaga `pending_image_batches===0`; jeśli `__invoke` rzuci przed atomic decrement → 5 retry → dead-letter → licznik nigdy nie zdekrementowany → sesja stuck w `running`. Dowód dead-letteringu: `messenger:failed:show` Id 5 `ImageDownloadMessage`.
- **Rekomendacja:** `WorkerMessageFailedEvent` listener dla `ImageDownloadMessage` dekrementujący licznik + finalizujący sesję.
- **Estymata:** M (4-8h). Dowód: `E-frankenphp-memory.md` (E-03).

### AUD-035 — `processed_messages` zależy od migracji, worker `auto_setup=1` jej nie tworzy — race przy świeżym deployu
- **Confidence:** confirmed · **Domena:** E · **Lokalizacja:** `IdempotencyMiddleware.php:70`; `docker-compose.yml:196` (`auto_setup=1`).
- **Dowód:** `auto_setup` tworzy tylko `messenger_messages`, nie `processed_messages`. Dowód okna (2026-06-14): async wiadomości rzucały `42P01` → dead-letter. Świeży deploy gdzie worker wstaje przed `migrate` → cała kolejka import się wykrzacza.
- **Rekomendacja:** wymuś kolejność (worker po migracjach) lub utwórz `processed_messages` w setup.
- **Estymata:** S (2-4h). Dowód: `E-frankenphp-memory.md` (E-04).

### AUD-036 — Worker healthcheck permanentnie `unhealthy` (false-positive) — maskuje realną awarię
- **Confidence:** confirmed · **Domena:** E · **Lokalizacja:** `docker-compose.yml:180-206` (worker dziedziczy HTTP healthcheck z obrazu api).
- **Dowód:** `docker inspect` → `unhealthy, FailingStreak:145, curl: (7) Failed to connect port 80`. Worker to CLI `messenger:consume`, nie serwer HTTP. Proces działa (`[OK] Consuming`). Maskuje realny crash + `depends_on: service_healthy` nigdy nie spełniony.
- **Rekomendacja:** dedykowany healthcheck workera (np. `messenger:stats` lub sprawdzenie procesu).
- **Estymata:** S (1-2h). Dowód: `E-frankenphp-memory.md` (E-05).

### AUD-037 — `CompletenessFilter` czyta z JSONB zamiast ze zindeksowanej kolumny `completeness_pct`
- **Confidence:** needs-review (pusta baza — wymaga 50k) · **Domena:** F · **Lokalizacja:** `CompletenessFilter.php:72-73`.
- **Dowód:** generuje `JSONB_GET_NUMERIC(o.completeness,'pct')` zamiast `o.completenessPct`; istnieje pokrywający indeks `objects_tenant_kind_compl_idx`. Funkcyjne wyrażenie na JSONB nie jest sargable.
- **Rekomendacja:** przepisać filtr na kolumnę `completenessPct`; weryfikacja EXPLAIN ANALYZE na 50k.
- **Estymata:** S (1-2h). Dowód: `F-performance-static.md` (F-5).

### AUD-038 — Brak wirtualizacji wierszy/kolumn w gridzie (`ExcelLikeGrid`) — degradacja przy 200×100+ komórek
- **Confidence:** confirmed · **Domena:** F · **Lokalizacja:** `excel-like-grid.tsx:178,190`; brak biblioteki wirtualizacji w `package.json`.
- **Dowód:** pełny render wiersz×kolumna bez windowing. Czynnik łagodzący: server-side paginacja (max 200 wierszy/stronę). Przy 200+ atrybutach × 200 wierszy = ~20k+ komórek DOM.
- **Rekomendacja:** wirtualizacja kolumn (np. tanstack-virtual) dla szerokich ObjectType.
- **Estymata:** M (8-16h). Dowód: `F-performance-static.md` (F-6).

### AUD-039 — Drift `attributes_indexed` ↔ `object_values` bez mechanizmu wykrycia (drift już obecny w danych)
- **Confidence:** confirmed (empirycznie) · **Domena:** G · **Lokalizacja:** `RebuildAttributesIndexedHandler.php:104-110` (silent skip po 3 retry), `AttributesIndexedRebuilder.php:62-82`.
- **Dowód:** PROBE psql: `in_index_not_in_values=13` (13 osieroconych kluczy, 4 obiekty); `ACME-001/002/003` mają cache bez `object_values`. Handler po `MAX_REBUILD_RETRIES=3` cicho skipuje (warning, return) — wiadomość „success", brak `failed`.
- **Scenariusz:** rebuild skipuje obiekt przy współbieżnej edycji → list/Meili/completeness pokazują dane niezgodne z kanonem; eksport wysyła do kanału stare wartości; skala rośnie cicho.
- **Rekomendacja:** komenda detect/report drift + cron reconcyliacji; nie skipować cicho (dead-letter lub flag).
- **Estymata:** M (8-12h). Dowód: `G-data-integrity.md` (G-01).

### AUD-040 — Rollback importu (`ImportRollbackService::rollback`) nieatomowy — okno częściowego stanu przy crashu
- **Confidence:** confirmed · **Domena:** G · **Lokalizacja:** `ImportRollbackService.php:82-139` (5 niezależnych commitów bez `wrapInTransaction`).
- **Dowód:** sekwencja replayUndoLog→flush→DELETE values→DELETE objects→markRolledBack; crash między krokami zostawia sieroty (values usunięte, objects zostają) lub status `completed` mimo wykasowanych danych → ponowny rollback na zużytym undo-logu.
- **Rekomendacja:** owinąć rollback w jedną transakcję lub idempotentny checkpoint rollbacku (jak w imporcie).
- **Estymata:** M (8-12h). Dowód: `G-data-integrity.md` (G-02).

### AUD-041 — Migracje destrukcyjne z danymi mają lossy/strukturalny `down()` — rollback nie przywraca danych
- **Confidence:** confirmed · **Domena:** G · **Lokalizacja:** `Version20260607130000.php:34,42` (channels label gubi `en`), `Version20260605100000` (traci linki channel↔currency), `Version20260607140000` (binding locale), `Version20260606120000` (root kategorii NULL), `Version20260612210000` (irreversible JSONB).
- **Dowód:** `down()` odtwarza schemat ale nie dane; `Version20260612210000` rzuca irreversible licząc na dump z `backups/` (którego istnienia nie weryfikuje; AUD-017 cron stale zwiększa ryzyko).
- **Rekomendacja:** dla lossy migracji jawny `throwIrreversibleMigrationException` + wymóg pre-dump w runbooku; testowy migrate→rollback→migrate na świeżej bazie w CI.
- **Estymata:** M (przegląd + dokumentacja). Dowód: `G-data-integrity.md` (G-03).

### AUD-042 — Dwa niespójne formaty błędów: API Platform RFC 7807 vs Symfony FlattenException (z `class`/`trace` info leak)
- **Confidence:** confirmed · **Domena:** H · **Lokalizacja:** ~157 custom route'ów rzucających `BadRequestHttpException` z gołymi stringami.
- **Dowód:** `POST /api/object_types {}` → HTTP 400 z `type:rfc2616`, `class:Symfony\…\BadRequestHttpException` (info leak nazwy klasy), `trace:[...]` (dev). Bez `Accept` → `text/html` error page. Integrator dostaje dwa kontrakty błędów.
- **Rekomendacja:** jednolity RFC 7807 exception listener dla custom controllerów; usuń `class`/`trace` z odpowiedzi prod.
- **Estymata:** M (8-16h). Dowód: `H-api-contract.md` (H-02).

### AUD-043 — OpenAPI dokumentuje 31 ścieżek; router ma 228 ścieżek `/api/*` (API-first naruszony)
- **Confidence:** confirmed · **Domena:** H/L (H-03 + L-02) · **Lokalizacja:** `raw/openapi.json` (31 paths) vs `raw/routes.txt` (228 `/api/*`).
- **Dowód:** cała powierzchnia custom (auth, MFA, reset, invitation, bulk-edit, export, import, asset, super-admin, RBAC) nieudokumentowana w OpenAPI; 117 plików `#[Route]` vs 2 `#[ApiResource]`. CLAUDE.md pkt 3 („wszystko przez API Platform") faktycznie odwrócony, bez ADR.
- **Rekomendacja:** decyzja świadoma — albo retrofit krytycznych zasobów na ApiResource przed otwarciem API partnerom, albo ADR + aktualizacja CLAUDE.md (uczciwość) + jawne dokumentowanie custom endpointów w OpenAPI.
- **Estymata:** L (retrofit) / S (ADR). Dowód: `H-api-contract.md` (H-03), `L-architecture-debt.md` (L-02).

### AUD-044 — `Dockerfile` hardkoduje `APP_ENV=dev`, brak prod-stage
- **Confidence:** confirmed · **Domena:** H · **Lokalizacja:** `apps/api/Dockerfile:9`.
- **Dowód:** override tylko w `docker-compose.prod.yml`. Build/run obrazu bez prod-overlayu (docker run, k8s, pomyłka) → cała app w dev mode (trace, Swagger, profiler). Mitygacja: php.ini `display_errors=0`.
- **Rekomendacja:** multi-stage prod target lub jawny build-arg wymuszający APP_ENV na buildzie prod.
- **Estymata:** M (4-8h). Dowód: `H-api-contract.md` (H-04).

### AUD-045 — Brak aplikacyjnego limitu rozmiaru JSON body na endpointach nie-importowych (DoS workera)
- **Confidence:** confirmed · **Domena:** H · **Lokalizacja:** chain Caddy 150MB → php.ini `post_max_size=110M` → app (limit tylko import).
- **Dowód:** PROBE: 240KB JSON na `/api/products/bulk-edit` w pełni zdekodowany przed walidacją. ~109MB JSON → OOM/CPU-DoS workera (`memory_limit=256M`).
- **Rekomendacja:** globalny limit rozmiaru body (listener) dla endpointów nie-importowych.
- **Estymata:** S (2-4h). Dowód: `H-api-contract.md` (H-05).

### AUD-046 — Mailpit działa w prod overlay; Meilisearch z `MEILI_ENV=development` w prod
- **Confidence:** confirmed · **Domena:** D · **Lokalizacja:** `docker-compose.yml:397-405` (Mailpit bez profilu), `:298` (`MEILI_ENV: development`).
- **Dowód:** prod overlay profiluje dev-only tylko `admin`; Mailpit (łapie mail reset/invitation z tokenami) i Meili-dev (luźniejszy wymóg klucza) startują w prod.
- **Rekomendacja:** `profiles: ["dev-only"]` dla Mailpit; `MEILI_ENV: production` + wymuszony master key w prod overlay.
- **Estymata:** S (1-2h). Dowód: `D-secrets-config.md` (D-04).

### AUD-047 — Brak Symfony Secrets Vault; sekrety wyłącznie w plikach `.env`/env compose
- **Confidence:** confirmed · **Domena:** D · **Lokalizacja:** `ls apps/api/config/secrets` = brak.
- **Dowód:** CLAUDE.md deklaruje vault, realnie nieużyty. Brak rotacji, audytu dostępu, szyfrowania at-rest sekretów infra.
- **Rekomendacja:** wdrożyć Secrets Vault lub udokumentowany external secrets manager dla prod.
- **Estymata:** M (8-16h). Dowód: `D-secrets-config.md` (D-05).

### AUD-048 — `qs` 6.15.1 (przez `@refinedev/core`) — DoS w `qs.stringify` (CVE GHSA-q8mj-m7cp-5q26)
- **Confidence:** confirmed · **Domena:** I · **Lokalizacja:** transitive `@refinedev/core@5.0.12 → qs@6.15.1`.
- **Dowód:** `raw/pnpm-audit.txt` moderate, patched >=6.15.2; komponenty filtrów budują query z inputu usera.
- **Rekomendacja:** `pnpm.overrides` na `qs@>=6.15.2` lub bump Refine.
- **Estymata:** S (1-2h). Dowód: `I-frontend.md`.

### AUD-049 — Brak Error Boundary w całej aplikacji admin — uncaught render error = biały ekran
- **Confidence:** confirmed · **Domena:** I · **Lokalizacja:** `apps/admin/src` (`grep ErrorBoundary` = 0); `App.tsx:377` (tylko Suspense), `main.tsx` (brak global handler).
- **Dowód:** pojedynczy rzucony błąd w renderze → pusty `#root`. Dokładnie ryzyko z incydentu white-screen 2026-05-13 (komentarz `http.ts:141`), ochrona punktowa (tylko fetch JSON).
- **Rekomendacja:** top-level `ErrorBoundary` wokół `<Routes>` + `unhandledrejection` handler.
- **Estymata:** S (2-4h). Dowód: `I-frontend.md`.

### AUD-050 — Eksporty z „forever retention" + brak enforcement retencji
- **Confidence:** confirmed · **Domena:** K · **Lokalizacja:** `flysystem.yaml:43-44`.
- **Dowód:** Free-tier 7d cleanup + scheduled command nie istnieją; eksporty (pełne dane/PII) gromadzą się bezterminowo. Konflikt z RODO (disaster-recovery.md:234 ostrzega o GDPR breach window).
- **Rekomendacja:** scheduled cleanup + enforcement retencji per tier.
- **Estymata:** M (8-12h). Dowód: `K-backup-dr.md` (K6).

### AUD-051 — Commandy retencji/offboardingu nie są nigdzie schedulowane
- **Confidence:** confirmed · **Domena:** K · **Lokalizacja:** `pim:audit:cleanup`, `pim:tenants:purge-deleted` — `rg AsSchedule|cron` = 0 poza komentarzem.
- **Dowód:** Symfony Scheduler nieużywany; brak cron w `docker/`. `audit_logs` rośnie bez granic (50140 wierszy), soft-delete window nigdy się nie zamyka.
- **Rekomendacja:** Symfony Scheduler / cron dla retencji + offboardingu.
- **Estymata:** S (2-4h). Dowód: `K-backup-dr.md` (K7).

### AUD-052 — Audit generyczny nie zapisuje diffu (old/new null); dane produktowe poza audytem
- **Confidence:** confirmed · **Domena:** K · **Lokalizacja:** `AuditLogListener.php:93-94` (oldValue/newValue null); `dh_auditor.yaml:25-33` (CatalogObject/ObjectValue NIE audytowane).
- **Dowód:** generyczny listener łapie tylko metadane HTTP; zmiana wartości atrybutu produktu nie zostawia śladu kto/co/kiedy. Eksport danych bez dedykowanego audit eventu.
- **Rekomendacja:** audyt diff dla danych produktowych (lub jawna decyzja scope) + dedykowany `data_export` event.
- **Estymata:** M (8-16h). Dowód: `K-backup-dr.md` (K8).

### AUD-053 — Deptrac „0 violations" maskuje 286 realnych przecieków warstw (Import/Export → Catalog internals) + 5099 uncovered
- **Confidence:** confirmed · **Domena:** L/J (L-01 + J-04) · **Lokalizacja:** `raw/deptrac.txt`, `deptrac-config.txt:240-466`.
- **Dowód:** Violations=0, Skipped=286 (56 source-class keys), Uncovered=5099. ExportBuilder/ImportRunHandler/~30 Import controllerów sięgają `Catalog\Domain`/`Identity\Domain\Entity\User`. Gate zielony tylko przez baseline.
- **Rekomendacja:** dokończyć `#1466` (shared writer core); `Identity\Contracts` dla User; catch-all kolektor na `Uncovered`.
- **Estymata:** L (część #1466). Dowód: `L-architecture-debt.md` (L-01), `J-tests-ci.md` (J-04).

### AUD-054 — „Wszystko przez API Platform" faktycznie odwrócone (117 plików custom `#[Route]` vs 2 `#[ApiResource]`) — patrz AUD-043
- **Confidence:** confirmed · **Domena:** L · (powiązane z AUD-043; rejestrowane osobno jako dług architektoniczny/dryf bez ADR).
- **Rekomendacja:** ADR uzasadniający odwrócenie reguły lub plan retrofitu. Dowód: `L-architecture-debt.md` (L-02).

### AUD-055 — Trzy współistniejące wzorce pobierania danych w admin FE (jsonFetch / TanStack / Refine) — ryzyko stale-data
- **Confidence:** confirmed · **Domena:** L · **Lokalizacja:** `apps/admin/src` (jsonFetch 138 / useQuery 55 / Refine 50 plików).
- **Dowód:** dane invalidowane przez `queryClient` nie odświeżają ekranów na `jsonFetch` (lekcja `feedback_useeffect_to_usequery_pattern`). 138 plików = 138 potencjalnych stale-data.
- **Rekomendacja:** ADR „nowy kod = useQuery", lint rule, migracja priorytetowo ekranów reagujących na mutacje.
- **Estymata:** L (migracja). Dowód: `L-architecture-debt.md` (L-03).

### AUD-056 — Duplikacja API 13.27% — 13 Bulk handlerów kopiuj-wklej
- **Confidence:** confirmed · **Domena:** M · **Lokalizacja:** `Catalog/Application/Bulk/*Handler.php` (jscpd: 12 najgorszych klastrów).
- **Dowód:** `raw/jscpd-api` 1142 klony, 12451 zdup. linii; wszystkie top klastry to Bulk handlery (~48-62 zdup. linii/para).
- **Rekomendacja:** wspólny writer core (#1466) — usuwa większość naraz.
- **Estymata:** część #1466. Dowód: `M-dx-metrics.md`.

### AUD-057 — Monolityczne pliki FE bez reguły lint `max-lines` (product-detail-page 1190 linii + 17 plików >500)
- **Confidence:** confirmed · **Domena:** M · **Lokalizacja:** `raw/cloc-byfile-top.txt`; `biome.json` (brak `max-lines`).
- **Dowód:** TOP: product-detail-page.tsx 1190, attributes/show.tsx 1006, universal-list-page.tsx 1001. Nic nie hamuje rozrostu w CI.
- **Rekomendacja:** reguła `max-lines` (warn 500) + rozbicie product-detail na tab-komponenty.
- **Estymata:** M (rozbicie kluczowych). Dowód: `M-dx-metrics.md`.

### AUD-058 — Cały dashboard zasilany `mock-data.ts` (9 widgetów) — pierwsze wrażenie po loginie to strona-atrapa
- **Confidence:** confirmed · **Domena:** M · **Lokalizacja:** `features/.../dashboard` (KpiCards, CompletenessMetrics, BackupWidget, AlertCenter…).
- **Dowód:** 9 komponentów importuje `mock-data.ts` (oznaczone MockBadge — plus, ale łatwe do przeoczenia).
- **Rekomendacja:** podłączyć dashboard do realnego API lub wyraźny baner „dane zastępcze".
- **Estymata:** M-L. Dowód: `M-dx-metrics.md`.

### AUD-059 — Brak udokumentowanego splitu Domain entity ↔ API Platform Resource (XML) ↔ Input DTO ↔ Processor — dev utyka na warstwie API
- **Confidence:** confirmed · **Domena:** M · **Lokalizacja:** `Channel.php` (brak `#[ApiResource]`), `Channel.xml`, `ChannelInput.php`, `ChannelProcessor.php`.
- **Dowód:** wzorzec (encja konstruktor-only + serializacja XML + DTO + Processor) NIE opisany w ONBOARDING/CONTRIBUTING. Dodanie pola = edycja ~9-11 plików w 2 aplikacjach bez referencyjnego przykładu.
- **Rekomendacja:** dev-doc „Jak dodać pole/endpoint" z pełnym vertical-slice.
- **Estymata:** S (dokumentacja). Dowód: `M-onboarding.md` (M1-07/10).

### AUD-060 — Skrypt regeneracji `@pim/shared-types generate` zepsuty (zły scheme `http` + zła ścieżka `/api/docs.json` → 404)
- **Confidence:** confirmed · **Domena:** M · **Lokalizacja:** `packages/shared-types/package.json:13`.
- **Dowód:** PROBE: `http://pim.localhost/api/docs.json` → HTTP 000; `https://…/api/docs.json` → 404; poprawne `/api/docs.jsonopenapi` → 200. Jedyna droga odświeżenia typów po zmianie schematu niedrożna (artefakt `api.d.ts` zacommitowany maskuje to w buildzie).
- **Rekomendacja:** poprawić scheme+ścieżkę na `https://pim.localhost/api/docs.jsonopenapi`.
- **Estymata:** S (15 min). Dowód: `M-onboarding.md` (M1-08).

### AUD-061 — `quality-php.yml` ma `paths:` filter — PHPStan/PHPUnit/Deptrac nie biegną dla zmian poza `apps/api/**`
- **Confidence:** confirmed · **Domena:** J · **Lokalizacja:** `.github/workflows/quality-php.yml:6-21`.
- **Dowód:** PR dotykający tylko `packages/shared-types/**` lub `config/` przejdzie bez backend gates. Zmiana łamiąca backend ścieżką poza filtrem nie odpali testów.
- **Rekomendacja:** rozszerzyć paths lub osobny required job bez filtra dla krytycznych gate'ów.
- **Estymata:** S (1-2h). Dowód: `J-tests-ci.md` (J-05).

### AUD-062 — `retries: 2` w CI maskuje flaky E2E
- **Confidence:** confirmed · **Domena:** J · **Lokalizacja:** `playwright.config.ts:32`.
- **Dowód:** test zielony za 3. razem raportowany jako passed; w połączeniu z AUD-022 (rate-limiter jako powód fixme) ukrywa niestabilność.
- **Rekomendacja:** naprawić root cause (storageState) zamiast maskować retry; rozważyć `retries:1` + raport flaky.
- **Estymata:** część AUD-022. Dowód: `J-tests-ci.md` (J-06).

### AUD-063 — `import-benchmark` (memory gate 256 MiB) wykluczony z domyślnego runu testów
- **Confidence:** confirmed · **Domena:** J · **Lokalizacja:** `phpunit.dist.xml:50-54`, `quality-php.yml:265`.
- **Dowód:** gate pamięciowy wisi na jednym ręcznym kroku CI; zniknięcie grupy nie wywoła błędu.
- **Rekomendacja:** meta-asercja „grupa niepusta" lub osobny required job.
- **Estymata:** S (1-2h). Dowód: `J-tests-ci.md`.

---

## LOW

### AUD-064 — `APP_DEFAULT_TENANT_CODE=demo` commitowany; fallback tenanta dla nieuwierzytelnionych
- **Domena:** A · `apps/api/.env:61`, `CurrentTenantProvider.php:51-53`. Anti-wzorzec dla SaaS (request bez usera → kontekst `demo`). Rekomendacja: pusty default → deny w prod. Dowód: `A-multitenant.md` (A-07).

### AUD-065 — Drift macierzy RBAC: role w DB spoza `RbacMatrix` (admin, approver, tenant_owner, modeler, channel_manager, marketing)
- **Domena:** B · `RbacMatrix.php` (4 role) vs DB (10+). Uprawnienia np. `approver`/`channel_manager` nieaudytowalne z jednego źródła. Rekomendacja: zsynchronizować macierz/docs. Dowód: `B-rbac.md` (B-07).

### AUD-066 — Detekcja formatu importu po rozszerzeniu pliku, nie po magic-byte
- **Domena:** C · `FileParserService.php:47-56`. Zmitygowane (XlsxArchiveGuard, OpenSpout failuje). Rekomendacja: lekki magic-byte check. Dowód: `C-injection.md` (C-5).

### AUD-067 — Wyciek wersji PHP w nagłówku `x-powered-by: PHP/8.4.21` na `/api`
- **Domena:** D · `curl -skI /api`. Edge Caddy nie zdejmuje `X-Powered-By`. Rekomendacja: `header /api* -X-Powered-By` / `expose_php=Off`. Dowód: `D-secrets-config.md` (D-06).

### AUD-068 — Prod overlay nie nadpisuje `TRUSTED_HOSTS` (dziedziczy `pim.localhost`)
- **Domena:** D · `docker-compose.prod.yml`. Pod realną domeną Symfony odrzuci żądania dopóki operator nie nadpisze. Potwierdza niekompletność prod overlay. Dowód: `D-secrets-config.md` (D-07).

### AUD-069 — `RelationImportStep` buforuje wszystkie linki + monotoniczny `$seenTriples` bez capu
- **Domena:** E · `RelationImportStep.php:47-50,173,239`. String DTO (przeżywają clear celowo), ryzyko ograniczone przy gęstych relacjach 50k. Rekomendacja: cap/flush buforów. Dowód: `E-frankenphp-memory.md` (E-07).

### AUD-070 — Degradacja Meili: search zwraca „0 trafień" zamiast sygnału awarii (myli operatora)
- **Domena:** E · `CatalogSearchService.php:134-146`. Brak rozróżnienia degraded vs empty. (Lista produktów idzie po Postgres — plus.) Rekomendacja: sygnalizować degraded-mode. Dowód: `E-frankenphp-memory.md` (E-08).

### AUD-071 — Pojedynczy chunk JS 777KB (`product-detail-page`) > próg ostrzeżenia 700KB
- **Domena:** F · `ls -lS apps/admin/dist`. Lazy-loaded (nie blokuje startu). Rekomendacja: dalszy podział (lazy taby). Dowód: `F-performance-static.md` (F-7).

### AUD-072 — `ObjectTypeService::delete` nie łapie `ForeignKeyConstraintViolationException` → race = 500 zamiast 409
- **Domena:** G · `ObjectTypeService.php:227-240` (kontrast: `DeleteAttributeHandler` robi to poprawnie). Rekomendacja: catch FK → 409. Dowód: `G-data-integrity.md` (G-04).

### AUD-073 — `dompurify` 3.4.8 — Trusted Types policy survives `clearConfig()` (CVE GHSA-vxr8-fq34-vvx9)
- **Domena:** I · `package.json:42`. App nie używa Trusted Types (ekspozycja minimalna), ale to jedyna bariera XSS wysiwyg. Rekomendacja: bump >=3.4.9. Dowód: `I-frontend.md`.

### AUD-074 — CSP osłabione: `script-src 'unsafe-inline' 'unsafe-eval'`
- **Domena:** I · `docker/caddy/Caddyfile:50`. Udokumentowany tradeoff (Vite HMR/Refine). Rekomendacja: ostrzejszy CSP z nonce dla prod builda. Dowód: `I-frontend.md`.

### AUD-075 — i18n: 28 kluczy tylko w pl.json, brak w en.json — mixed-language UI dla EN
- **Domena:** I · `locales/{pl,en}.json`. `fallbackLng:pl` ratuje (nie surowy klucz), ale EN widzi polskie stringi (picker kategorii, taby). Rekomendacja: uzupełnić + CI-check parytetu. Dowód: `I-frontend.md`.

### AUD-076 — `PermissionRoute` zdefiniowany ale nigdy nieużyty — UX leak (martwy guard, klikalne linki do stron bez uprawnień)
- **Domena:** I · `grep "<PermissionRoute"` = 0 użyć. Backend egzekwuje (403), ale user może nawigować do `/admin/break-glass` zanim dostanie odmowę. Rekomendacja: podpiąć lub usunąć. Dowód: `I-frontend.md`.

### AUD-077 — Brak runbooka break-glass mimo odwołania w CLAUDE.md
- **Domena:** K · `docs/operations/break-glass-runbook.md` nie istnieje. Mechanizm break-glass solidny i audytowany, ale recovery zablokowanego Ownera bez procedury zwiększa MTTR. Rekomendacja: spisać runbook. Dowód: `K-backup-dr.md` (K9).

### AUD-078 — Sieroty schematu DB: `object_associations_audit`, `association_types_audit` (encje usunięte ADR-014, tabele audit zostały)
- **Domena:** L · `Version20260524110000.php` DROP bazowych, audit nie sprzątnięte (0 wierszy, brak triggerów). Rekomendacja: migracja sprzątająca. Dowód: `L-architecture-debt.md` (L-04).

### AUD-079 — `Backup` BC poza fitness-gate deptrac (12 plików, 0 reguł); Search/Tooling z jawnie dozwolonym dostępem do Internals
- **Domena:** L · `grep Backup deptrac-config.txt` = 0; Search ma `Catalog_Internals`+`Identity_Internals` jako allowed. Rekomendacja: kolektor Backup, zawężenie Search. Dowód: `L-architecture-debt.md` (L-05).

### AUD-080 — `reportUnmatched: false` na 2 grupach ignoreErrors w `src/` + `assertTrue(true)` w 4 plikach testów
- **Domena:** J · `phpstan-config.txt:89-145`; `AuthLoginRateLimitTest.php:94-97` (`assertSame($x,$x)`), `EndpointGuardListenerTest` (7× assertTrue(true)). Rekomendacja: `reportUnmatched:true` po stabilizacji; `expectNotToPerformAssertions()`. Dowód: `J-tests-ci.md` (J-07/J-08/J-tests-ci).

### AUD-081 — Niespójności onboardingu: README bez `migrate`, `pim:db:reset` nieudokumentowany, `exec` vs `exec -T`, `.env.example` rozjazd, shared-types „build step" mylące
- **Domena:** M · `M-onboarding.md` (M1-03/04/05/06/09). Każda z osobna LOW, łącznie podnoszą friction Day-1. Rekomendacja: ujednolicić dokumentację onboardingu. Dowód: `M-onboarding.md`.

---

> **Aktualizacja po przebiegu empirycznym (matryca 2-tenant):** patrz sekcja na końcu `02-domain-reports/A-multitenant.md` / `probes/` — uzupełniana po zakończeniu probe'a.
