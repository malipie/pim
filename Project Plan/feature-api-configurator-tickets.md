# Backlog — Uniwersalny Konfigurator API (epik APIC)

> **Status:** backlog do realizacji. Utworzony 2026-06-26.
> **Źródło architektury:** [`feature-api-configurator-uniwersalny-plan.md`](feature-api-configurator-uniwersalny-plan.md) (§6 model domenowy, §8 fazy, §10 brief, §12 mini-ADR).
> **Decyzja architektoniczna:** ADR-0022 (`docs/adr/0022-api-configurator-consumer-producer-boundary.md` + streszczenie w `01-architektura-pim.md` §13).
> **Designy UI:** `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/integracje/api-*.jsx` (8 ekranów + primitives + shell).
> **Epik label:** `epik-API-CONFIG` (+ `epik-0.10`). Prefix ID: `APIC`, format `APIC-P{faza}-{nn}`.
> **Milestone'y:** M0 Foundation · M1 Consumer Foundation · M2 Descriptor+Mapping · M3 Sync Engines · M4 Monitor+Producer · M5 Hardening.

Ten plik to single source of truth backlogu. GitHub Issues są lustrem (skondensowane body + link tutaj). Tracking faktyczny w Issues + milestone'ach.

---

## Mapa GitHub Issues (utworzone 2026-06-26)

48 issues MVP, milestone'y #28–#33 (M0–M5). Issue body linkuje do sekcji tego pliku; poniżej odwrotny indeks ID → numer.

| APIC-ID | Issue | APIC-ID | Issue | APIC-ID | Issue | APIC-ID | Issue |
|---|---|---|---|---|---|---|---|
| APIC-P0-01 | #1756 | APIC-P0-02 | #1757 | APIC-P0-03 | #1758 | APIC-P0-04 | #1759 |
| APIC-P0-05 | #1760 | APIC-P1-01 | #1761 | APIC-P1-02 | #1762 | APIC-P1-03 | #1763 |
| APIC-P1-04 | #1764 | APIC-P1-05 | #1765 | APIC-P1-06 | #1766 | APIC-P1-07 | #1767 |
| APIC-P1-08 | #1768 | APIC-P1-09 | #1769 | APIC-P2-01 | #1770 | APIC-P2-02 | #1771 |
| APIC-P2-03 | #1772 | APIC-P2-04 | #1773 | APIC-P2-05 | #1774 | APIC-P2-06 | #1775 |
| APIC-P2-07 | #1776 | APIC-P2-08 | #1777 | APIC-P2-09 | #1778 | APIC-P3-01 | #1779 |
| APIC-P3-02 | #1780 | APIC-P3-03 | #1781 | APIC-P3-04 | #1782 | APIC-P3-05 | #1783 |
| APIC-P3-06 | #1784 | APIC-P3-07 | #1785 | APIC-P3-08 | #1786 | APIC-P3-09 | #1787 |
| APIC-P3-10 | #1788 | APIC-P3-11 | #1789 | APIC-P3-12 | #1790 | APIC-P4-01 | #1791 |
| APIC-P4-02 | #1792 | APIC-P4-03 | #1793 | APIC-P4-04 | #1794 | APIC-P4-05 | #1795 |
| APIC-P4-06 | #1796 | APIC-P4-07 | #1797 | APIC-P4-08 | #1798 | APIC-P5-01 | #1799 |
| APIC-P5-02 | #1800 | APIC-P5-03 | #1801 | APIC-P5-04 | #1802 | APIC-P5-05 | #1803 |

Hooki §7 (deferred) bez issue do czasu wejścia w scope.

---

## Konwencje

- **Cls**: `BE` (backend) · `FE` (frontend) · `SEC` (security-first) · `DOCS`.
- **[PM]**: ticket wymaga Plan Mode + (gdy dotyczy) aktualizacji ADR — cross-context lub decyzja architektoniczna.
- **[DEF]**: hook §7, świadomie odłożony; w backlogu, bez issue na starcie.
- **Bounded context konsumenta:** `apps/api/src/Integration/Generic/` (Domain/Application/Infrastructure/Presentation/Contracts). Cross-BC tylko przez `*_Contracts` (Deptrac).
- **Bounded context producenta:** `apps/api/src/ApiConfigurator/` (domknięcie istniejącego zalążka).

### Standard DoD (każdy ticket, o ile nie zaznaczono inaczej)

- [ ] Acceptance criteria spełnione.
- [ ] **PHPStan max**: 0 errors (BE).
- [ ] **Deptrac**: 0 violations (cross-BC tylko przez Contracts).
- [ ] **PHP-CS-Fixer**: czysto (BE).
- [ ] **Biome strict** + **tsc --noEmit**: 0 errors (FE).
- [ ] **PHPUnit** ≥80% nowej logiki domenowej; **ApiTestCase** dla każdego endpointu (401 + 403 + 404 + walidacja + happy path).
- [ ] **Playwright E2E**: happy path + ≥1 edge case (FE z widoczną zmianą).
- [ ] **axe-core**: 0 violations serious/critical (FE).
- [ ] **Multi-tenancy**: cross-tenant read = 0 wyników (encje TenantScoped).
- [ ] **composer audit + pnpm audit**: 0 high/critical.
- [ ] **OpenAPI snapshot** `docs/api-spec/v0.json` zaktualizowany (nowe endpointy).
- [ ] Manual smoke 5 min na `pim.localhost`; PR opis nie używa „działa" bez smoke testu.
- [ ] CI green; PR merged do main.

### Reuse (potwierdzone sygnatury)

| Klocek | Ścieżka / namespace | Rola |
|---|---|---|
| `ScheduleDispatcherService` / `CronExpressionParser` | `App\Import\Application\Service` | harmonogram (computeNextRun/runNow; isValid/nextRun/nextRuns/describe) |
| `ImportSchedule(Run)` + `ScheduleRunStatus` | `App\Import\Domain\Entity` / `…\Enum` | wzorzec encji harmonogramu + statusów runów |
| `ValueWriteCore` + `BatchValueWriter::writeMany(CatalogObject, writes[], Provenance)` | `App\Catalog\Application` | inbound upsert (walidacja, completeness, indeks GIN) |
| `ObjectResolver::resolve/resolveMany/decide` | `App\Import\Application\Service` | parowanie rekordu po match key |
| `Provenance` (`Manual\|Import\|Integration`) | `App\Catalog\Domain` | znakowanie pochodzenia; `Integration` już istnieje |
| `EncryptionServiceInterface` / `AesGcmEncryptionService` / `EncryptedSecret` | `App\Shared\Application\Crypto`, `App\Shared\Infrastructure\Crypto` | szyfr odwracalny credentiali konsumenta (AES-256-GCM, rotacja) |
| `SsrfGuard::isAllowed(url)` + `import.ssrf_safe_http_client` (`NoPrivateNetworkHttpClient`) | `App\Import\Application\Service\Media` / `config/services.yaml` | obrona SSRF (per-redirect) |
| `SyncExportRunner` / `ExportBuilder::build` / `ColumnResolver` / `ValueSerializer` | `App\Export\Application\…` | outbound payload (serializacja obiektów wg wyboru kolumn) |
| Messenger transporty sync/async/import/failed + `TenantContextRebindingMiddleware` + `TenantRlsGucMiddleware` | `config/packages/messenger.yaml` | async + tenant GUC `app.current_tenant` dla RLS |

---

# M0 — Foundation

Cross-cutting: ADR, Deptrac, shell UI, primitives, labele/milestone'y. Blokuje całą resztę.

## APIC-P0-01: docs(architecture): add ADR-0022 — consumer/producer boundary & generic connector placement
- **Typ:** `docs` · **Cls:** DOCS · **Milestone:** M0 · **Est:** 3–4h · **Risk:** medium · **[PM]**
- **Blocked by:** — · **Blocks:** P0-02, P1-01
- **Cel:** Utrwalić decyzję o granicy Konsument/Producent i umiejscowieniu generycznego konektora (per-file MADR + streszczenie §13).
- **Scope:** 6 punktów decyzyjnych z §12 planu: (1) podział Konsument/Producent, (2) konektor w `Integration/Generic` (nie `ApiConfigurator`), (3) warstwa transport+mapowanie+harmonogram nad Import/Export, (4) dwa mechanizmy sekretów (hash/szyfr), (5) mapowanie 1:1 MVP + `ValueTransformerInterface` seam, (6) konflikt LWW + provenance anti-loop. Status: Accepted (2026-06-26). Referencje: ADR-0016, ADR-0017, ADR-0019, ADR-0020, §7 architektury. **Uwaga:** numer to ADR-0022 (4-cyfrowa seria MADR; ADR-016 z planu był miscountem — ADR-0016 to format kluczy API/Argon2id). *Wykonane w sesji backlogowej — ten ticket = formalny rekord/review.*
- **AC:**
  - [ ] AC-1: `docs/adr/0022-api-configurator-consumer-producer-boundary.md` w formacie MADR (Status/Kontekst/Decyzja/Konsekwencje/Alternatywy/Links).
  - [ ] AC-2: streszczenie ADR-0022 w `01-architektura-pim.md` §13; 6 punktów decyzyjnych pokrytych.
  - [ ] AC-3: link do backlogu (`feature-api-configurator-tickets.md`) i planu.
- **Files:** `docs/adr/0022-api-configurator-consumer-producer-boundary.md` (new), `Project Plan/01-architektura-pim.md` (modified).
- **DoD:** standard (bez gates kodowych — docs-only); review operatora.

## APIC-P0-02: chore(deptrac): register Integration/Generic context with Contracts layer
- **Typ:** `chore` · **Cls:** BE · **Milestone:** M0 · **Est:** 3–5h · **Risk:** high · **[PM]**
- **Blocked by:** P0-01 · **Blocks:** P1-01, P4-03
- **Cel:** Zarejestrować warstwy `Integration/Generic_Internals` + `Integration/Generic_Contracts` w Deptrac, zanim powstanie pierwsza encja.
- **Scope:** W `apps/api/deptrac.yaml`: collectory dla `src/Integration/Generic/{Domain,Application,Infrastructure,Presentation}` (Internals) i `src/Integration/Generic/Contracts` (Contracts). Ruleset: Internals → Contracts + Shared + Catalog_Contracts + Channel_Contracts + Asset_Contracts + Identity_Contracts + Vendor; Contracts → Shared + Vendor. Pusty `Contracts/.gitkeep` + szkielet katalogów.
- **AC:**
  - [ ] AC-1: `deptrac.yaml` zawiera obie warstwy.
  - [ ] AC-2: `vendor/bin/deptrac` zielony na pustym szkielecie.
  - [ ] AC-3: cross-BC dozwolony tylko przez `Integration_Generic_Contracts`.
- **Files:** `apps/api/deptrac.yaml`, `apps/api/src/Integration/Generic/**/.gitkeep`.
- **Testing:** Layer 1 — `deptrac` w CI.
- **DoD:** standard (Deptrac green).

## APIC-P0-03: chore(labels): create epik-API-CONFIG label and milestones
- **Typ:** `chore` · **Cls:** DOCS · **Milestone:** M0 · **Est:** 1h · **Risk:** low
- **Blocked by:** — · **Blocks:** wszystkie issue
- **Cel:** Utworzyć label `epik-API-CONFIG` + milestone'y M0–M5.
- **Scope:** `gh label create epik-API-CONFIG`; 6 milestone'ów. (Wykonane w sesji backlogowej — ten ticket dokumentuje.)
- **AC:** [ ] label istnieje; [ ] 6 milestone'ów istnieje.
- **DoD:** standard (infra-only).

## APIC-P0-04: feat(ui): scaffold shared "Konfigurator API" shell with consumer/producer split
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M0 · **Est:** 6–8h · **Risk:** low
- **Blocked by:** P0-03 · **Blocks:** P0-05, wszystkie FE
- **Cel:** Zbudować shell obszaru „Konfigurator API" z podziałem Konsument (Połączenia) / Producent (Moje API) + Monitor — odwzorowanie `api-app.jsx`.
- **Scope:** `apps/admin/src/features/api-configurator/` — layout + routing Refine (`/integrations/api-configurator/*`): zakładki consumer/producer/monitor, pusty stan (dane podpinane w kolejnych ticketach). i18n klucze nawigacji. Wpięcie w sidebar (Integracje).
- **AC:**
  - [ ] AC-1: trasa renderuje shell z 3 sekcjami wg `api-app.jsx`.
  - [ ] AC-2: routing nie psuje istniejących `/integrations/*` (exports/imports/api-profiles).
  - [ ] AC-3: i18n PL/EN, brak literałów.
- **Files:** `apps/admin/src/features/api-configurator/**`, `App.tsx` (resources/routes), `i18n` pl/en.
- **Testing:** Playwright smoke nawigacji; axe-core na shellu.
- **DoD:** standard.

## APIC-P0-05: feat(ui): port shared API-configurator primitives library
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M0 · **Est:** 8–12h · **Risk:** low
- **Blocked by:** P0-04 · **Blocks:** wszystkie ekrany FE
- **Cel:** Odwzorować ~16 współdzielonych primitives z `api-primitives.jsx` jako produkcyjne komponenty shadcn.
- **Scope:** `AuthBadge`, `DirectionBadge`, `ConnStatusPill`, `MethodPill`, `RolePill`, `PaginationPill`, `JsonView`, `CoverageBar`, `TypeCompat`, `DirToggle`, `Segmented`, `ApiToggle`, `Field`, `TextInput`/`SelectInput`, `SecurityNote`, `SectionLabel`. Klasy Tailwind pixel-perfect z prototypu; warianty stanów (active/paused/error). Storybook/przykłady opcjonalnie.
- **AC:**
  - [ ] AC-1: każdy primitive renderuje warianty z prototypu (<2% pixel mismatch).
  - [ ] AC-2: a11y — role/aria, focus ring, axe-core 0 serious/critical.
  - [ ] AC-3: i18n-ready (labelki przez `t()` lub propsy).
- **Files:** `apps/admin/src/features/api-configurator/components/primitives/**`.
- **Testing:** axe-core; unit render testy kluczowych primitives.
- **DoD:** standard.

---

# M1 — Consumer Foundation (faza §8.1)

`Connection` + szyfr. credentiale + SSRF-safe `GenericRestClient` + `ConnectionTester` + hub + wizard (kroki 1–2).

## APIC-P1-01: feat(integration): add Connection entity + RLS migration
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M1 · **Est:** 8–10h · **Risk:** high · **[PM]**
- **Blocked by:** P0-02 · **Blocks:** P1-02, P1-03, P1-05, P1-06, P2-01
- **Cel:** Wprowadzić rdzeń konsumenta — encję `Connection` (TenantScoped + RLS).
- **Scope:** Encja `App\Integration\Generic\Domain\Entity\Connection`: `code`, `name`, `baseUrl`, `authType` (enum `none|api_key|bearer|basic|oauth2_token`), `encryptedCredentials` (ciphertext+version, 2 kolumny), `defaultHeaders` (JSONB), `rateLimitHint`, `status` (`active|paused|error|draft`), `lastHealthCheckAt`. Repo interface + Doctrine impl. Migracja z RLS policy (`app.current_tenant` GUC) + indeks (tenant_id, code) unique. Kontrakty w `Generic/Contracts` jeśli cross-BC.
- **AC:**
  - [ ] AC-1: encja + migracja + RLS policy.
  - [ ] AC-2: `tenant_id NOT NULL`, ustawiany przez `TenantAssignmentListener`.
  - [ ] AC-3: unique (tenant_id, code); indeks na status.
  - [ ] AC-4: cross-tenant read = 0.
- **Files:** `src/Integration/Generic/Domain/Entity/Connection.php`, `…/Domain/Enum/AuthType.php`, `…/Domain/Enum/ConnectionStatus.php`, repo + Doctrine, `migrations/VersionYYYYMMDDHHMMSS.php`.
- **Testing:** Layer 1 unit (encja); Layer 2 integration (persist + RLS); Layer 3 cross-tenant.
- **DoD:** standard.

## APIC-P1-02: feat(integration): encrypt Connection credentials via AesGcmEncryptionService
- **Typ:** `feat` · **Cls:** SEC · **Milestone:** M1 · **Est:** 5–7h · **Risk:** high
- **Blocked by:** P1-01 · **Blocks:** P1-06, P5-03
- **Cel:** Szyfrować credentiale połączenia odwracalnie i nigdy nie zwracać ich w odpowiedzi API.
- **Scope:** Wpięcie `EncryptionServiceInterface` → `EncryptedSecret` przy zapisie; deszyfracja tylko w runtime kliencie. Response filter (wzorzec `ApiProfileResponseFilter`) maskuje credentiale (jak `webhookSecret`). Rotacja = regeneracja (lazy `needsRotation`).
- **AC:**
  - [ ] AC-1: credentiale zapisane jako ciphertext+version; brak plaintextu w DB.
  - [ ] AC-2: GET/list nigdy nie zwraca credentiali (test asercja).
  - [ ] AC-3: round-trip encrypt→decrypt poprawny.
- **Files:** state processor/listener zapisu Connection, response filter, testy.
- **Testing:** Layer 1 (encrypt/decrypt), Layer 2 (API nie ujawnia sekretu).
- **DoD:** standard + asercja braku sekretu w odpowiedzi.

## APIC-P1-03: feat(integration): build SSRF-safe GenericRestClient wrapping NoPrivateNetworkHttpClient
- **Typ:** `feat` · **Cls:** SEC · **Milestone:** M1 · **Est:** 8–12h · **Risk:** critical · **[PM]**
- **Blocked by:** P1-01 · **Blocks:** P1-04, P1-05, P1-09, P2-03, P2-04
- **Cel:** Jedyny klient HTTP konsumenta — generyczny, SSRF-safe, z auth injection per `authType`.
- **Scope:** `GenericRestClient` owijający `import.ssrf_safe_http_client` (`NoPrivateNetworkHttpClient`) + `SsrfGuard.isAllowed` re-check per redirect. Injection nagłówków auth: api_key (header+value), bearer, basic, oauth2_token. Timeouty, limit rozmiaru odpowiedzi, respekt `Retry-After`. Deszyfracja credentiali z `EncryptedSecret`. **Bez** własnej logiki private-range (delegacja do wrappera).
- **AC:**
  - [ ] AC-1: każde wywołanie idzie przez NoPrivateNetworkHttpClient; redirecty walidowane.
  - [ ] AC-2: auth header poprawny per typ.
  - [ ] AC-3: blokada private/loopback/link-local/metadata IP (test).
  - [ ] AC-4: brak credentiali w logach.
- **Files:** `src/Integration/Generic/Infrastructure/Http/GenericRestClient.php`, config services, testy.
- **Testing:** Layer 1 (auth injection), Layer 2 (SSRF blokady, redirect-to-private).
- **DoD:** standard + SSRF testy.

## APIC-P1-04: feat(integration): validate connector descriptor (baseUrl scheme + path template)
- **Typ:** `feat` · **Cls:** SEC · **Milestone:** M1 · **Est:** 3–5h · **Risk:** high
- **Blocked by:** P1-03 · **Blocks:** P5-02
- **Cel:** Sanity-check descriptora przy zapisie — tylko http/https, brak `file://`, brak interpolacji do prywatnych zakresów.
- **Scope:** Validator `baseUrl` (scheme allowlist) + `pathTemplate` (brak schematów lokalnych, brak `{var}` rozwijanych do prywatnych IP). Opcjonalny allowlist domen per tenant (hook — minimalnie flaga). Błędy RFC 7807.
- **AC:** [ ] AC-1: non-http(s)/file:// odrzucone; [ ] AC-2: walidacja przy create/update Connection i RemoteEndpoint; [ ] AC-3: błąd 422 z Problem Details.
- **Files:** `src/Integration/Generic/Application/Validation/DescriptorValidator.php`, wpięcie w processory.
- **Testing:** Layer 1 (matryca URL), Layer 2 (422 na złym descriptorze).
- **DoD:** standard.

## APIC-P1-05: feat(integration): add ConnectionTester health/auth check endpoint
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M1 · **Est:** 5–7h · **Risk:** medium
- **Blocked by:** P1-03 · **Blocks:** P1-08
- **Cel:** Endpoint testujący połączenie (health + auth) z podglądem payloadu.
- **Scope:** `POST /api/connections/{id}/test` — próbne żądanie przez `GenericRestClient`, zwraca status HTTP, latency, rozmiar, content-type, próbkę odpowiedzi (bez sekretów); aktualizuje `lastHealthCheckAt` + `status`. Custom `#[Route]` (operacja proceduralna), widoczne w OpenAPI (CustomRouteOpenApiFactory).
- **AC:** [ ] AC-1: zwraca status+latency+sample; [ ] AC-2: 200 OK gdy auth zweryfikowane, błąd RFC 7807 gdy nie; [ ] AC-3: aktualizuje lastHealthCheckAt.
- **Files:** `src/Integration/Generic/Presentation/Controller/ConnectionTestController.php`, OpenAPI snapshot.
- **Testing:** ApiTestCase (200/401/403/404 + happy path z mock external).
- **DoD:** standard.

## APIC-P1-06: feat(integration): expose Connection CRUD API (API Platform)
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M1 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P1-02 · **Blocks:** P1-07, P1-08
- **Cel:** Wystawić zasób `Connection` (list/create/update/pause) przez API Platform; credentiale write-only.
- **Scope:** ApiResource + Input/Patch DTO + State processor; credentiale tylko zapis; filtry (status), cursor pagination jeśli >1000. RBAC voter (kto zarządza integracjami).
- **AC:** [ ] AC-1: CRUD + pause; [ ] AC-2: credentiale nie w odpowiedzi; [ ] AC-3: 401/403/404/422 pokryte; [ ] AC-4: OpenAPI zaktualizowany.
- **Files:** `src/Integration/Generic/Infrastructure/ApiPlatform/**`, voter, OpenAPI snapshot.
- **Testing:** ApiTestCase pełny; RBAC voter test.
- **DoD:** standard.

## APIC-P1-07: feat(ui): build API hub — consumer connection list (api-hub)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M1 · **Est:** 8–12h · **Risk:** low
- **Blocked by:** P1-06, P0-05 · **Blocks:** P3-12, P4-08
- **Cel:** Hub konsumenta — lista połączeń (KPI strip, search/filter, grid kart, NewConnectionCard) wg `api-hub.jsx`.
- **Scope:** KPI strip (connections active/paused/error, syncs 24h sparkline, records in/out, top issues), toolbar search+filtry, `ConnectionCard` (status pill, direction, baseUrl, auth, cron, coverage bar, cursor, last sync), `NewConnectionCard` → wizard. Refine resource `connections`. Empty/loading/error states.
- **AC:** [ ] AC-1: pixel-perfect z `api-hub.jsx` (<2%); [ ] AC-2: filtry/search działają na żywym API; [ ] AC-3: empty/loading/error; [ ] AC-4: i18n PL/EN; [ ] AC-5: axe-core 0 serious.
- **Files:** `features/api-configurator/consumer/hub/**`.
- **Testing:** Playwright (lista + filtr + nawigacja do wizarda); axe-core.
- **DoD:** standard.

## APIC-P1-08: feat(ui): build connection wizard steps 1–2 (URL+auth → test) (api-wizard)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M1 · **Est:** 10–14h · **Risk:** medium
- **Blocked by:** P1-06, P1-05, P0-05 · **Blocks:** P2-06
- **Cel:** Kreator połączenia kroki 1–2 (Połączenie URL+auth, Test) wg `api-wizard.jsx`; kroki 3–4 stub do M2.
- **Scope:** Stepper 4-krokowy; Krok 1 (name→slug code, baseUrl https, auth segmented + pola warunkowe, default headers, rate limit, SecurityNote SSRF/AES); Krok 2 (Test → status/latency/size/content-type + sample JSON). Zapis Connection (draft). Kroki 3–4 placeholder. Walidacja klienta + serwera.
- **AC:** [ ] AC-1: kroki 1–2 pixel-perfect; [ ] AC-2: Test uderza w `/connections/{id}/test` i pokazuje wynik; [ ] AC-3: zapis tworzy Connection; [ ] AC-4: i18n; [ ] AC-5: axe-core.
- **Files:** `features/api-configurator/consumer/wizard/**`.
- **Testing:** Playwright (przejście kroków + test połączenia mock); axe-core.
- **DoD:** standard.

## APIC-P1-09: feat(integration): per-tenant + per-connection rate limiter
- **Typ:** `feat` · **Cls:** SEC · **Milestone:** M1 · **Est:** 6–8h · **Risk:** high
- **Blocked by:** P1-03 · **Blocks:** P5-02
- **Cel:** Ograniczyć ruch wychodzący: per `Connection` (respekt 429 + backoff) i per tenant (anti-abuse).
- **Scope:** Token-bucket per Connection (`rateLimitHint`), respekt `Retry-After`/429 z exponential backoff (wzorzec Shopify §7.3), budżet per tenant. Reuse wzorca `ApiKeyRateLimitListener`. Telemetria do `SyncRunLog` (pasywnie).
- **AC:** [ ] AC-1: 429 → backoff wg Retry-After (fallback 2^n, max 60s); [ ] AC-2: limit per tenant; [ ] AC-3: brak własnego Leaky Bucket (tylko backoff).
- **Files:** `src/Integration/Generic/Infrastructure/Http/RateLimiter*`, wpięcie w `GenericRestClient`.
- **Testing:** Layer 1 (backoff math), Layer 2 (429 retry).
- **DoD:** standard.

---

# M2 — Descriptor + Mapping (fazy §8.2 + §8.3)

`RemoteEndpoint` + `RemoteField` + `SchemaDiscoveryService` + `FieldMapping` + walidacja typów + mapping FE.

## APIC-P2-01: feat(integration): add RemoteEndpoint entity + migration
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P1-01 · **Blocks:** P2-02, P2-03, P2-05
- **Cel:** Descriptor operacji (odpowiednik „stream" Airbyte).
- **Scope:** Encja `RemoteEndpoint`: `connectionId`, `role` (`read_list|read_one|write_create|write_update`), `httpMethod`, `pathTemplate`, `queryParams` (JSONB), `requestBodyTemplate` (JSONB), `pagination` (JSONB: `none|offset|page|cursor|link_header`), `recordSelector` (JSONPath), `responseFormat` (`json`). TenantScoped + RLS + migracja. FK do Connection.
- **AC:** [ ] AC-1: encja + migracja + RLS; [ ] AC-2: FK + cascade; [ ] AC-3: cross-tenant = 0.
- **Files:** `src/Integration/Generic/Domain/Entity/RemoteEndpoint.php`, enumy, repo, migracja.
- **Testing:** Layer 1/2/3.
- **DoD:** standard.

## APIC-P2-02: feat(integration): add RemoteField entity + migration
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **Est:** 4–6h · **Risk:** low
- **Blocked by:** P2-01 · **Blocks:** P2-04, P2-07
- **Cel:** Pole zewnętrznego API (wykryte z próbki lub ręczne).
- **Scope:** Encja `RemoteField`: `endpointId`, `path` (JSONPath), `label`, `dataType`, `sampleValue`. TenantScoped + RLS + migracja.
- **AC:** [ ] AC-1: encja + migracja; [ ] AC-2: FK do endpoint; [ ] AC-3: cross-tenant = 0.
- **Files:** `src/Integration/Generic/Domain/Entity/RemoteField.php`, repo, migracja.
- **Testing:** Layer 1/2/3.
- **DoD:** standard.

## APIC-P2-03: feat(integration): implement pagination strategies (none/offset/page/cursor/link_header)
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **Est:** 8–10h · **Risk:** medium
- **Blocked by:** P2-01, P1-03 · **Blocks:** P2-04, P3-04
- **Cel:** Sterowniki paginacji w `GenericRestClient` napędzane descriptorem endpointu.
- **Scope:** Strategie: none, offset, page, cursor, link_header. Iterator stron z `recordSelector` (JSONPath do listy). Limit stron / guard nieskończonej pętli.
- **AC:** [ ] AC-1: 5 strategii działa na fixtures; [ ] AC-2: recordSelector wyciąga rekordy; [ ] AC-3: guard pętli.
- **Files:** `src/Integration/Generic/Infrastructure/Http/Pagination/**`.
- **Testing:** Layer 1 per strategia (mock multi-page).
- **DoD:** standard.

## APIC-P2-04: feat(integration): SchemaDiscoveryService — fetch sample → flatten → propose RemoteFields
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **Est:** 8–10h · **Risk:** medium
- **Blocked by:** P2-02, P2-03 · **Blocks:** P2-05
- **Cel:** Wyeliminować ręczne wpisywanie schematu — fetch próbki → spłaszczenie JSON → propozycja pól.
- **Scope:** `SchemaDiscoveryService`: woła `read_list`/`read_one`, spłaszcza JSON (JSONPath), wykrywa `dataType` + `sampleValue`, zwraca kandydatów `RemoteField`. User akceptuje/edytuje (FE P2-06).
- **AC:** [ ] AC-1: zwraca listę pól z typami z próbki; [ ] AC-2: zagnieżdżone obiekty/tablice spłaszczone; [ ] AC-3: brak sekretów w logach.
- **Files:** `src/Integration/Generic/Application/Discovery/SchemaDiscoveryService.php`.
- **Testing:** Layer 1 (spłaszczanie + inferencja typu), Layer 2 (fetch mock).
- **DoD:** standard.

## APIC-P2-05: feat(integration): RemoteEndpoint + RemoteField CRUD API
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P2-04 · **Blocks:** P2-06
- **Cel:** Endpointy edycji descriptora + trigger discovery.
- **Scope:** ApiResource dla RemoteEndpoint/RemoteField + `POST /api/connections/{id}/discover` (uruchamia SchemaDiscoveryService). Walidacja descriptora (P1-04). OpenAPI.
- **AC:** [ ] AC-1: CRUD endpointów/pól; [ ] AC-2: discover zwraca kandydatów; [ ] AC-3: 401/403/404/422; [ ] AC-4: OpenAPI.
- **Files:** ApiPlatform resources + discover controller, OpenAPI snapshot.
- **Testing:** ApiTestCase pełny.
- **DoD:** standard.

## APIC-P2-06: feat(ui): wire wizard steps 3–4 (endpoints + schema discovery)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M2 · **Est:** 8–12h · **Risk:** medium
- **Blocked by:** P2-05, P1-08 · **Blocks:** —
- **Cel:** Dokończyć kreator: krok 3 (Endpointy — tabela role/method/path/pagination/selector + add) i krok 4 (Schema — „Pobierz próbkę" → lista wykrytych pól do akceptacji).
- **Scope:** Krok 3 builder endpointów (RolePill/MethodPill/PaginationPill, dodawanie/usuwanie). Krok 4 discovery (sample JSON + checkboxy pól, all/none). Zapis → przejście do mapowania.
- **AC:** [ ] AC-1: kroki 3–4 pixel-perfect; [ ] AC-2: discover na żywym API zwraca pola; [ ] AC-3: akceptacja pól zapisuje RemoteFields; [ ] AC-4: i18n + axe-core.
- **Files:** `features/api-configurator/consumer/wizard/steps/**`.
- **Testing:** Playwright (endpoint add + discovery mock); axe-core.
- **DoD:** standard.

## APIC-P2-07: feat(integration): add FieldMapping entity + versioned migration
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P2-02 · **Blocks:** P2-08, P3-01
- **Cel:** Mapowanie 1:1 `pimTarget ↔ remoteFieldPath` (wersjonowane, reużywalne).
- **Scope:** Encja `FieldMapping`: `bindingId` (lub luźne do czasu SyncBinding), `pimTarget` (attr code / pole systemowe: sku/name/status/category…), `remoteFieldPath`, `direction` (`inbound|outbound|both`), `isMatchKey`. Wersjonowanie (reusability między wiązaniami). TenantScoped + RLS + migracja.
- **AC:** [ ] AC-1: encja + migracja; [ ] AC-2: wersjonowanie; [ ] AC-3: cross-tenant = 0.
- **Files:** `src/Integration/Generic/Domain/Entity/FieldMapping.php`, repo, migracja.
- **Testing:** Layer 1/2/3.
- **DoD:** standard.

## APIC-P2-08: feat(integration): FieldMapping API + type-compatibility validation
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P2-07 · **Blocks:** P2-09, P3-04, P3-06
- **Cel:** Persist mapowań + ostrzeżenie przy niezgodności typów + wymóg ≥1 matchKey dla inbound.
- **Scope:** ApiResource FieldMapping + walidator typów (string/number/bool/date ISO-8601 coercion; mismatch → warning, nie błąd). Enforce ≥1 isMatchKey gdy inbound. OpenAPI.
- **AC:** [ ] AC-1: CRUD mapowań; [ ] AC-2: warning przy mismatch typów; [ ] AC-3: brak matchKey dla inbound = błąd walidacji; [ ] AC-4: OpenAPI.
- **Files:** ApiPlatform resource + validator, OpenAPI snapshot.
- **Testing:** ApiTestCase + walidacja typów.
- **DoD:** standard.

## APIC-P2-09: feat(ui): build 1:1 field mapping screen (api-mapping)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M2 · **Est:** 12–16h · **Risk:** medium
- **Blocked by:** P2-08, P0-05 · **Blocks:** P3-12
- **Cel:** Mapper dwukolumnowy PIM ↔ remote wg `api-mapping.jsx`.
- **Scope:** Wiersze mapowań (lewy PIM panel + DirToggle + key toggle + prawy remote panel), CoverageBar, TypeCompat warnings, inline „Dodaj mapowanie 1:1", pule niezmapowanych (PIM + remote), disabled „transformacja" placeholder (hook). Zapisy + invalidacja cache.
- **AC:** [ ] AC-1: pixel-perfect (<2%); [ ] AC-2: dodaj/usuń/toggle kierunek/key na żywym API; [ ] AC-3: coverage + warningi z BE; [ ] AC-4: i18n + axe-core.
- **Files:** `features/api-configurator/consumer/mapping/**`.
- **Testing:** Playwright (dodaj mapowanie + toggle + key); axe-core.
- **DoD:** standard.

## APIC-P2-10: feat(integration): pluggable value-transform hook interface (identity impl) — [DEF]
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **[DEF]** · **Est:** TBD
- **Blocked by:** P2-08
- **Cel:** Seam `ValueTransformerInterface` (impl identity) pod przyszły silnik transformacji bez zmiany schematu. Hook §7 — bez issue na starcie.

## APIC-P2-11: feat(integration): AI-assisted auto-mapping suggestions — [DEF]
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M2 · **[DEF]** · **Est:** TBD
- **Blocked by:** P2-08
- **Cel:** Auto-suggest mapowań (reuse wzorca `AutoMapper`). Hook §7.

---

# M3 — Sync Engines (fazy §8.4–§8.7)

Inbound cursor delta · outbound push · bidirectional+conflict · scheduler · sync-config FE + detail.

## APIC-P3-01: feat(integration): add SyncBinding entity + migration
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 8–10h · **Risk:** high
- **Blocked by:** P2-07 · **Blocks:** P3-02, P3-03, P3-09
- **Cel:** Sedno — co, dokąd, jak, jak często.
- **Scope:** Encja `SyncBinding`: `connectionId`, `objectTypeId`, `readEndpointId?`, `writeEndpointId?`, `direction` (`inbound|outbound|bidirectional`), `schedule` (cron), `cursor` (JSONB: field/type `updated_at|incremental_id|opaque`/state), `conflictPolicy` (`lww|pim_wins|remote_wins`), `matchKeyMapping`, `enabled`. TenantScoped + RLS + migracja.
- **AC:** [ ] AC-1: encja + migracja + RLS; [ ] AC-2: FK Connection/ObjectType/Endpoint; [ ] AC-3: cross-tenant = 0.
- **Files:** `src/Integration/Generic/Domain/Entity/SyncBinding.php`, enumy, repo, migracja.
- **Testing:** Layer 1/2/3.
- **DoD:** standard.

## APIC-P3-02: feat(integration): add SyncRun + SyncRunLog entities + migration
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P3-01 · **Blocks:** P3-09, P4-01
- **Cel:** Audyt runów (wzorzec `ImportScheduleRun` + `sync_job_logs`).
- **Scope:** `SyncRun` (`bindingId`, `direction`, `startedAt`, `status`, counts created/updated/skipped/failed, `cursorBefore/After`) + `SyncRunLog` (per-rekord: match key, action, fields, status, message). Status enum (reuse `ScheduleRunStatus` jako wzorzec). TenantScoped + RLS + migracja.
- **AC:** [ ] AC-1: obie encje + migracja; [ ] AC-2: liczniki + cursor before/after; [ ] AC-3: per-record log.
- **Files:** `src/Integration/Generic/Domain/Entity/SyncRun.php`, `…/SyncRunLog.php`, repo, migracja.
- **Testing:** Layer 1/2/3.
- **DoD:** standard.

## APIC-P3-03: feat(integration): cursor manager with monotonic crash-safe persistence
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 6–8h · **Risk:** high
- **Blocked by:** P3-01 · **Blocks:** P3-04, P3-06
- **Cel:** Atomowe, monotoniczne przesuwanie kursora (crash-safe, brak re-procesowania).
- **Scope:** `CursorManager`: odczyt/zapis stanu kursora na `SyncBinding`; walidacja monotoniczności przed persist (`updated_at|incremental_id|opaque`); atomowy zapis po batchu.
- **AC:** [ ] AC-1: cursor rośnie monotonicznie (odrzuca cofnięcie); [ ] AC-2: zapis atomowy po batchu; [ ] AC-3: re-run po crashu nie duplikuje.
- **Files:** `src/Integration/Generic/Application/Sync/CursorManager.php`.
- **Testing:** Layer 1 (monotonia, crash sim).
- **DoD:** standard.

## APIC-P3-04: feat(integration): InboundSyncHandler — delegate upsert to ValueWriteCore (provenance=Integration)
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 12–16h · **Risk:** high · **[PM]**
- **Blocked by:** P3-03, P2-08, P2-03 · **Blocks:** P3-05, P3-08
- **Cel:** Inbound pull → upsert delegowany do rdzenia Catalog z `Provenance::Integration`.
- **Scope:** `InboundSyncHandler`: czyta cursor → `read_list` z delta (`since`/`>cursor`) → paginuje (P2-03) → mapuje 1:1 → `ObjectResolver.resolveMany` po matchKey → `BatchValueWriter.writeMany(obj, writes, Provenance::Integration)` → przesuwa cursor atomowo (P3-03) → zapis `SyncRun(Log)`. Batch + `EntityManager::clear()` co N (worker memory).
- **AC:** [ ] AC-1: rekordy zewn. → obiekty PIM (create/update wg matchKey); [ ] AC-2: provenance=Integration na zapisach; [ ] AC-3: cursor przesuwany monotonicznie; [ ] AC-4: SyncRun z licznikami; [ ] AC-5: memory <128MB przy 1000.
- **Files:** `src/Integration/Generic/Application/Sync/InboundSyncHandler.php`, message.
- **Testing:** Layer 1 (mapowanie), Layer 2 (upsert do realnego Postgres), Layer 3 cross-tenant.
- **DoD:** standard + benchmark memory.

## APIC-P3-05: feat(integration): route inbound sync messages with tenant + RLS GUC context
- **Typ:** `feat` · **Cls:** SEC · **Milestone:** M3 · **Est:** 5–7h · **Risk:** critical · **[PM]**
- **Blocked by:** P3-04 · **Blocks:** —
- **Cel:** Async transport sync runów z poprawną propagacją tenanta + GUC RLS.
- **Scope:** Routing message'y sync w `messenger.yaml` na transport `import`/`async`; przejście przez `TenantContextRebindingMiddleware` + `TenantRlsGucMiddleware` (`app.current_tenant`). `TenantAwareMessage` stamp. Explicit routing (pinned prod/dev/test).
- **AC:** [ ] AC-1: handler async ma poprawny tenant context; [ ] AC-2: RLS GUC ustawiony przed transakcją; [ ] AC-3: cross-tenant w async = 0; [ ] AC-4: retry/dead-letter działa.
- **Files:** `config/packages/messenger.yaml`, message class, middleware wiring.
- **Testing:** Layer 2 (async + RLS), Layer 3 cross-tenant w workerze.
- **DoD:** standard.

## APIC-P3-06: feat(integration): OutboundSyncHandler — reuse Export engine to build push payload
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 12–16h · **Risk:** high · **[PM]**
- **Blocked by:** P3-03, P2-08 · **Blocks:** P3-07, P3-08
- **Cel:** Outbound push → payload budowany Export engine (nie własny serializer).
- **Scope:** `OutboundSyncHandler`: trigger (event/schedule) → serializacja obiektów przez `ExportBuilder`/`ColumnResolver`/`ValueSerializer` (lekkie wejście array zamiast pliku) → mapowanie 1:1 → `write_create`/`write_update` przez `GenericRestClient` → backoff/retry (P1-09) → dead-letter po 5 próbach → `SyncRun(Log)`.
- **AC:** [ ] AC-1: payload z Export engine; [ ] AC-2: write_create/update wołane; [ ] AC-3: backoff + dead-letter; [ ] AC-4: SyncRun z licznikami.
- **Files:** `src/Integration/Generic/Application/Sync/OutboundSyncHandler.php`, ewentualny lekki adapter ExportBuilder.
- **Testing:** Layer 1 (mapowanie payload), Layer 2 (push mock + retry).
- **DoD:** standard.

## APIC-P3-07: feat(integration): outbound trigger on object lifecycle event
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 5–7h · **Risk:** medium
- **Blocked by:** P3-06 · **Blocks:** —
- **Cel:** Wyzwalać outbound run na zmianę obiektu (reuse lifecycle subscriber).
- **Scope:** Subscriber na Catalog change events → enqueue outbound run dla pasujących `SyncBinding` (direction outbound/bidirectional, objectType match). Guard bulk flows.
- **AC:** [ ] AC-1: zmiana obiektu enqueue'uje outbound; [ ] AC-2: tylko pasujące bindingi; [ ] AC-3: bulk nie zalewa kolejki.
- **Files:** `src/Integration/Generic/Application/Subscriber/OutboundTriggerSubscriber.php`.
- **Testing:** Layer 2 (event → enqueue).
- **DoD:** standard.

## APIC-P3-08: feat(integration): ConflictResolver — LWW / pim_wins / remote_wins
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 8–10h · **Risk:** high · **[PM]**
- **Blocked by:** P3-04, P3-06 · **Blocks:** —
- **Cel:** Bidirectional: rozstrzyganie konfliktów + anti-loop.
- **Scope:** `ConflictResolver`: LWW po timestamp lub `pim_wins`/`remote_wins` per binding; `provenance` znakuje pochodzenie; anti-loop — nie wypychaj zmiany o `provenance=Integration` z tego samego connectiona, którą właśnie zaciągnąłeś.
- **AC:** [ ] AC-1: 3 polityki działają; [ ] AC-2: pętla sync przerwana (test in→out→in); [ ] AC-3: provenance rozstrzyga.
- **Files:** `src/Integration/Generic/Application/Sync/ConflictResolver.php`.
- **Testing:** Layer 1 (polityki), Layer 2 (anti-loop scenario).
- **DoD:** standard.

## APIC-P3-09: feat(integration): wire SyncBinding into ScheduleDispatcherService with cron + jitter
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P3-01, P3-02 · **Blocks:** P3-10
- **Cel:** Harmonogram sync — reuse `ScheduleDispatcherService` + `CronExpressionParser` + jitter.
- **Scope:** Wpięcie cron per `SyncBinding` w dispatcher (`computeNextRun`/`runNow`); jitter między tenantami; tracking next runs. Adaptive polling = hook (stały cron w MVP).
- **AC:** [ ] AC-1: binding z cron uruchamia się wg harmonogramu; [ ] AC-2: jitter rozprasza starty; [ ] AC-3: next runs widoczne.
- **Files:** `src/Integration/Generic/Application/Schedule/**`, reuse Import dispatcher.
- **Testing:** Layer 1 (next run calc), Layer 2 (dispatch).
- **DoD:** standard.

## APIC-P3-10: feat(integration): SyncBinding CRUD API + run-now/pause
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M3 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P3-09 · **Blocks:** P3-11, P4-01
- **Cel:** Endpointy zarządzania wiązaniem + akcje run-now/pause.
- **Scope:** ApiResource SyncBinding + `POST /api/sync-bindings/{id}/run` + `/pause`. RBAC voter. OpenAPI.
- **AC:** [ ] AC-1: CRUD + run-now + pause; [ ] AC-2: 401/403/404/422; [ ] AC-3: OpenAPI.
- **Files:** ApiPlatform + akcje + voter, OpenAPI snapshot.
- **Testing:** ApiTestCase pełny.
- **DoD:** standard.

## APIC-P3-11: feat(ui): build SyncBinding configuration screen (api-sync)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M3 · **Est:** 12–16h · **Risk:** medium
- **Blocked by:** P3-10, P0-05 · **Blocks:** P3-12
- **Cel:** Konfiguracja synchronizacji wg `api-sync.jsx`.
- **Scope:** Karta kierunku (DirDiagram + segmented + warunkowe selecty endpointów), karta harmonogramu (cron input + presety + najbliższe uruchomienia), karta kursora (inbound/bi), karta konfliktów (bi), karta match key, footer toggle aktywności + run-now/zapisz.
- **AC:** [ ] AC-1: pixel-perfect; [ ] AC-2: warunkowe panele per kierunek; [ ] AC-3: zapis na żywym API; [ ] AC-4: i18n + axe-core.
- **Files:** `features/api-configurator/consumer/sync/**`.
- **Testing:** Playwright (zmiana kierunku + zapis + run-now); axe-core.
- **DoD:** standard.

## APIC-P3-12: feat(ui): build connection detail with 5 tabs (api-detail)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M3 · **Est:** 12–16h · **Risk:** medium
- **Blocked by:** P3-11, P2-09, P1-07 · **Blocks:** P4-08
- **Cel:** Detal połączenia z 5 zakładkami wg `api-detail.jsx`.
- **Scope:** Header (status/direction/auth/baseUrl + Test/Synchronizuj). Zakładki: Przegląd (info tiles + recent runs + coverage + security card), Endpointy, Mapowanie (osadza P2-09), Synchronizacja (osadza P3-11), Historia (lista runów → drill-down). Trasowany pełnoekranowy widok.
- **AC:** [ ] AC-1: 5 zakładek pixel-perfect; [ ] AC-2: dane na żywym API; [ ] AC-3: nawigacja zakładek + deep-link; [ ] AC-4: i18n + axe-core.
- **Files:** `features/api-configurator/consumer/detail/**`.
- **Testing:** Playwright (przełączanie zakładek + akcje); axe-core.
- **DoD:** standard.

## APIC-P3-13..17: [DEF] adaptive polling · source-priority conflict · inbound webhooks · full OAuth2 · GraphQL/SOAP/XML
- Hooki §7, bez issue na starcie. Każdy osobny ticket przy wejściu w scope. Krótko:
  - **P3-13** adaptive polling (cadence wg change rate).
  - **P3-14** source-priority per-field conflict (poza LWW).
  - **P3-15** inbound webhooks (real-time zamiast pollingu).
  - **P3-16** full OAuth2 authorization-code (redirect flow) — SEC.
  - **P3-17** GraphQL/SOAP/XML adaptery (case IdoSell SOAP).

---

# M4 — Monitor + Producer (fazy §8.8 + §8.9)

`SyncRun`/`SyncRunLog` + monitor FE; domknięcie producenta + wspólny shell.

## APIC-P4-01: feat(integration): SyncRun history API + per-record drill-down
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M4 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P3-02, P3-10 · **Blocks:** P4-02
- **Cel:** API historii runów + per-rekord drill-down + re-run/pause.
- **Scope:** List/filter runów (status, connection, kierunek, czas), expose per-record errors, `re-run`/`pause`. Cursor pagination. OpenAPI.
- **AC:** [ ] AC-1: lista + filtry; [ ] AC-2: drill-down per rekord; [ ] AC-3: re-run/pause; [ ] AC-4: OpenAPI.
- **Files:** ApiPlatform/controller, OpenAPI snapshot.
- **Testing:** ApiTestCase pełny.
- **DoD:** standard.

## APIC-P4-02: feat(ui): build sync monitor screen (api-monitor)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M4 · **Est:** 10–14h · **Risk:** medium
- **Blocked by:** P4-01, P0-05 · **Blocks:** —
- **Cel:** Monitor synchronizacji wg `api-monitor.jsx`.
- **Scope:** KPI strip (syncs 24h, records in/out, błędy), toolbar search+filtry, tabela runów (HealthDot, ResultBar, cursor, status), drill-down Sheet (KPI grid + meta + tabela rekordów + footer „Pobierz log CSV"/„Wstrzymaj"/„Uruchom ponownie").
- **AC:** [ ] AC-1: pixel-perfect; [ ] AC-2: filtry + drill-down na żywym API; [ ] AC-3: re-run/pause; [ ] AC-4: i18n + axe-core.
- **Files:** `features/api-configurator/monitor/**`.
- **Testing:** Playwright (filtr + drill-down + re-run); axe-core.
- **DoD:** standard.

## APIC-P4-03: feat(apiconfigurator): per-profile OpenAPI export endpoint
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M4 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** P0-02 · **Blocks:** —
- **Cel:** Domknięcie producenta — OpenAPI ograniczony do scope profilu (ADR-0020).
- **Scope:** `GET /api/docs/profile/{id}.jsonopenapi` — spec zawężony do `objectTypeIds` + `includedAttributes` + `filters` profilu. Widoczne w głównym OpenAPI jako custom route.
- **AC:** [ ] AC-1: zwraca poprawny OpenAPI per profil; [ ] AC-2: tylko pola w scope profilu; [ ] AC-3: 401/403/404.
- **Files:** `src/ApiConfigurator/Presentation/Controller/ProfileOpenApiController.php`.
- **Testing:** ApiTestCase.
- **DoD:** standard.

## APIC-P4-04: feat(apiconfigurator): profile-builder backing endpoints
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M4 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** — · **Blocks:** P4-06, P4-07
- **Cel:** Endpointy zasilające builder profilu (ObjectTypes/atrybuty/access/filtry).
- **Scope:** Query endpointy listujące dostępne ObjectTypes + atrybuty do wyboru; walidacja zapisu profilu (multiselect). Reuse istniejących Create/Update/DeleteApiProfile handlerów. OpenAPI.
- **AC:** [ ] AC-1: listy ObjectTypes/atrybutów; [ ] AC-2: zapis profilu z multiselect; [ ] AC-3: 401/403/404/422.
- **Files:** `src/ApiConfigurator/**`, OpenAPI snapshot.
- **Testing:** ApiTestCase pełny.
- **DoD:** standard.

## APIC-P4-05: feat(apiconfigurator): webhook delivery retry + delivery-history entity
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M4 · **Est:** 8–10h · **Risk:** medium
- **Blocked by:** — · **Blocks:** P4-06
- **Cel:** Domknięcie webhooków producenta — retry/backoff + historia dostaw.
- **Scope:** Encja `WebhookDelivery` (audyt: event, status, attempts, lastError, durationMs) + retry przez Messenger (max 5, exponential) + dead-letter. Rozszerzenie `WebhookDeliveryClient`/`Subscriber`. „Wyślij testowy" endpoint.
- **AC:** [ ] AC-1: historia dostaw zapisywana; [ ] AC-2: retry + dead-letter; [ ] AC-3: test delivery działa.
- **Files:** `src/ApiConfigurator/Domain/Entity/WebhookDelivery.php`, retry wiring, migracja.
- **Testing:** Layer 1/2 (retry, history).
- **DoD:** standard.

## APIC-P4-06: feat(ui): build producer hub (Profile / Keys / Webhooks tabs)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M4 · **Est:** 12–16h · **Risk:** medium
- **Blocked by:** P4-04, P4-05, P0-05 · **Blocks:** P4-08
- **Cel:** Hub producenta wg `api-producer.jsx` — 3 zakładki + MintedKey modal.
- **Scope:** Zakładka Profile (karty profili + NewProfileCard), Klucze (tabela mint/rotate/revoke + MintedKey modal pokazany raz + SecurityNote Argon2id), Webhooki (karty events/target/secret/retry/dead-letter + „Wyślij testowy"). Reuse istniejącego `api-profiles` resource.
- **AC:** [ ] AC-1: 3 zakładki pixel-perfect; [ ] AC-2: mint/rotate/revoke na żywym API; [ ] AC-3: klucz pokazany raz; [ ] AC-4: i18n + axe-core.
- **Files:** `features/api-configurator/producer/**`.
- **Testing:** Playwright (mint key + modal + revoke); axe-core.
- **DoD:** standard.

## APIC-P4-07: feat(ui): build profile builder page (api-profile-page)
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M4 · **Est:** 10–14h · **Risk:** medium
- **Blocked by:** P4-04, P0-05 · **Blocks:** —
- **Cel:** Builder profilu wg `api-profile-page.jsx` (pełnoekranowy, trasowany).
- **Scope:** Lewy panel (name→slug, access read-only/read-write, filtry WHERE, multiselect ObjectTypes), prawy panel (atrybuty: search + checkboxy + all/none), footer zapis. New + Edit mode.
- **AC:** [ ] AC-1: pixel-perfect; [ ] AC-2: zapis profilu na żywym API; [ ] AC-3: new/edit mode; [ ] AC-4: i18n + axe-core.
- **Files:** `features/api-configurator/producer/profile-builder/**`.
- **Testing:** Playwright (utwórz/edytuj profil); axe-core.
- **DoD:** standard.

## APIC-P4-08: feat(ui): unify producer + consumer under shared shell
- **Typ:** `feat` · **Cls:** FE · **Milestone:** M4 · **Est:** 6–8h · **Risk:** low
- **Blocked by:** P4-06, P4-07, P1-07 · **Blocks:** —
- **Cel:** Spiąć oba oblicza w jeden shell „Konfigurator API" (wspólny audyt/sekrety patterns).
- **Scope:** Finalne wpięcie consumer (hub/detail/wizard/mapping/sync) + producer (profile/keys/webhooks) + monitor w `api-app.jsx` shell; spójna nawigacja, breadcrumbs, deep-linki.
- **AC:** [ ] AC-1: nawigacja między oblicza działa; [ ] AC-2: brak regresji istniejących tras; [ ] AC-3: i18n + axe-core.
- **Files:** `features/api-configurator/**` (shell wiring).
- **Testing:** Playwright (nawigacja end-to-end); axe-core.
- **DoD:** standard.

## APIC-P4-09: feat(integration): connector-pack template marketplace — [DEF]
- **Typ:** `feat` · **Cls:** BE · **Milestone:** M4 · **[DEF]** · **Est:** TBD
- **Blocked by:** P2-05
- **Cel:** Gotowe descriptor-packi (IdoSell/Shopify) jako szablony. Hook §7.

---

# M5 — Hardening (faza §8.10)

## APIC-P5-01: test(integration): cross-tenant RLS isolation suite for all consumer entities
- **Typ:** `test` · **Cls:** SEC · **Milestone:** M5 · **Est:** 6–8h · **Risk:** critical
- **Blocked by:** M3 done · **Cel:** Pełen Layer-3 cross-tenant dla Connection/RemoteEndpoint/RemoteField/FieldMapping/SyncBinding/SyncRun(Log).
- **Scope:** 2 tenanty, próba cross-read = 0 dla każdej encji; RLS w sync runach (worker GUC). 
- **AC:** [ ] AC-1: cross-read = 0 dla wszystkich encji; [ ] AC-2: worker async respektuje RLS.
- **Testing:** Layer 3 dedicated suite.
- **DoD:** standard.

## APIC-P5-02: test(security): SSRF + descriptor-validation adversarial slice
- **Typ:** `test` · **Cls:** SEC · **Milestone:** M5 · **Est:** 6–8h · **Risk:** critical
- **Blocked by:** P1-03, P1-04 · **Cel:** Adwersarialne testy SSRF/descriptor.
- **Scope:** redirect-to-private, DNS-rebind, `file://`, cloud-metadata IP (169.254.169.254), interpolacja path do prywatnych zakresów — wszystkie zablokowane przez `GenericRestClient` + DescriptorValidator.
- **AC:** [ ] AC-1: każdy wektor zablokowany; [ ] AC-2: brak wycieku w logach.
- **Testing:** Layer 2 adversarial.
- **DoD:** standard.

## APIC-P5-03: chore(integration): secret rotation + needsRotation review
- **Typ:** `chore` · **Cls:** SEC · **Milestone:** M5 · **Est:** 4–6h · **Risk:** high
- **Blocked by:** P1-02 · **Cel:** Weryfikacja ścieżki rotacji sekretów + runbook.
- **Scope:** Test `needsRotation` + lazy re-encrypt przy odczycie; runbook rotacji kluczy connection credentials.
- **AC:** [ ] AC-1: rotacja działa (stary→nowy version); [ ] AC-2: runbook w `docs/operations/`.
- **DoD:** standard.

## APIC-P5-04: perf(integration): inbound/outbound sync benchmark (keyset + batch)
- **Typ:** `perf` · **Cls:** BE · **Milestone:** M5 · **Est:** 6–8h · **Risk:** medium
- **Blocked by:** M3 done · **Cel:** Benchmark przepustowości sync + potwierdzenie reuse batchingu Import/Export.
- **Scope:** k6/skrypt: inbound 50k rekordów + outbound 50k obiektów; raport p95 endpointów <300ms (CRUD), memory worker <128MB/1000, EXPLAIN ANALYZE kluczowych query (zero N+1).
- **AC:** [ ] AC-1: raport w PR; [ ] AC-2: memory + p95 w budżecie; [ ] AC-3: zero N+1.
- **DoD:** standard + raport benchmark.

## APIC-P5-05: docs(integration): consumer connector + producer operator guide
- **Typ:** `docs` · **Cls:** DOCS · **Milestone:** M5 · **Est:** 4–6h · **Risk:** low
- **Blocked by:** M4 done · **Cel:** Dokumentacja end-user obu oblicz + noty bezpieczeństwa.
- **Scope:** Przewodnik: tworzenie połączenia, mapowanie, harmonogram, monitoring; producent: profile/klucze/webhooki; rate-limit/secret/SSRF noty.
- **AC:** [ ] AC-1: docs w `docs/` lub `Project Plan/UI/`; [ ] AC-2: pokrywa oba oblicza.
- **DoD:** standard (docs-only).

---

## Hooki (deferred §7 — bez issue na starcie)

Tworzone jako osobne tickety dopiero przy wejściu w scope (każdy = własny branch/PR/CI/merge):

1. **Transform engine wartości** (concat/split/lookup/format) — `ValueTransformerInterface` seam już w P2-10.
2. **GraphQL/SOAP/XML** adaptery (case IdoSell SOAP) — P3-17.
3. **Pełen OAuth2 authorization-code** (redirect flow) — P3-16.
4. **Inbound webhooks** (real-time zamiast pollingu) — P3-15.
5. **AI-assisted auto-mapping** (reuse AutoMapper) — P2-11.
6. **Adaptive polling** (cadence wg change rate) — P3-13.
7. **Source-priority per-field conflict** (poza LWW) — P3-14.
8. **Connector-pack marketplace** (IdoSell/Shopify jako paczki) — P4-09.

---

## Podsumowanie

| Milestone | Tickety MVP | [DEF] | Est. MVP |
|---|---|---|---|
| M0 — Foundation | 5 | 0 | 22–32h |
| M1 — Consumer Foundation | 9 | 0 | 58–82h |
| M2 — Descriptor + Mapping | 9 | 2 | 56–80h |
| M3 — Sync Engines | 12 | 5 | 92–130h |
| M4 — Monitor + Producer | 8 | 1 | 54–76h |
| M5 — Hardening | 5 | 0 | 34–48h |
| **Razem** | **48** | **8** | **≈316–448h** |

**Ścieżka krytyczna:** `P0-01 → P0-02 → P1-01 → P1-03 → P2-01 → P2-04 → P2-07 → P2-08 → P3-01 → P3-03 → P3-04 → P3-05`.
**Pierwszy działający inbound sync:** 15 ticketów BE/SEC z M0–M3 (zapis przez `ValueWriteCore`, `Provenance::Integration`).
**Tory równoległe:** producent (P4-03/04/05) niezależny od konsumenta; primitives (P0-05) równolegle do M1 BE; security (P1-02/03/04/09) po P1-01.
