# Domena A — Izolacja multi-tenant (audyt adwersarski, 2026-06-16)

Audytor: subagent „A" (read-only). Stack żywy: https://pim.localhost. Connection user DB: `pim`.

## TL;DR (priorytet SaaS)

1. **CRITICAL** — Mercure SSE hub działa w trybie `anonymous` i wszystkie eventy publikowane są jako PUBLIC (`Update.private=false`), na topiku BEZ prefiksu tenanta (`https://pim.localhost/objects`). Empirycznie potwierdzone: anonimowy `curl` bez tokena/cookie do broadcast-topiku zwraca HTTP 200 + `text/event-stream` (otwarty kanał). Dowolny klient odbiera zdarzenia katalogowe (objectId, kind, code, zmienione atrybuty) WSZYSTKICH tenantów. To jest realny cross-tenant leak, nie hipoteza.
2. **CRITICAL (defence-in-depth zerowy)** — Aplikacja łączy się z Postgres jako `pim` = `rolsuper=t` + `rolbypassrls=t` + owner wszystkich 86 tabel; ZERO tabel z FORCE RLS. RLS jest martwy w RUNTIME (nie tylko w teście). Izolacja opiera się WYŁĄCZNIE na Doctrine `TenantFilter` w warstwie aplikacji — pojedynczy bug = leak głównych encji katalogowych (objects, object_values, attributes, channels, assets — wszystkie bez działającego RLS).
3. **HIGH** — `GET /api/assets/{id}/preview` jest `PUBLIC_ACCESS` (bez auth) i jawnie wyłącza `TenantFilter`, serwując bajty dowolnego tenanta po samym UUID assetu. Potwierdzone: anonimowy request nie zwraca 401 (zwraca 404 dla nieistniejącego id). „Izolacja po nieenumerowalnym UUID" jest słaba — UUID wyciekają w `attributes_indexed.previewUrl`, eksportach, logach.
4. **MEDIUM** — Drift nazwy filtra Doctrine: rejestracja `tenant` (doctrine.yaml:129), a `SuperAdminContext::FILTER_NAME='tenant_filter'` (linia 44). `useCrossTenantMode()` NIGDY nie wyłącza filtra (latentny bug break-glass; udokumentowany invariant fałszywy).
5. **MEDIUM** — GUC drift: listenery ustawiają `app.current_tenant`, ale polityki `refresh_tokens` czytają `pim.current_tenant_id` (nigdy nie ustawiany). Po włączeniu FORCE RLS w fazie 2 `refresh_tokens` odrzuci wszystkie wiersze (refresh login zepsuty), a 5 z 18 polityk będzie martwych mimo aktywacji.
6. **MEDIUM** — `mc anonymous set download local/pim-assets` (docker-compose.yml:359) — bucket assetów jest anonimowo pobieralny. W obecnym compose MinIO jest tylko `expose` (nie publikowane na host), więc wektor jest wewnątrz-sieciowy, ale to bomba zegarowa przy bezpośrednim wystawieniu.

---

## Metodyka

### Co sprawdziłem i jak
- **Stan DB (z raw/)**: db-tables-tenant.txt, db-rls-enabled.txt, db-rls-policies.txt, db-owner-roles.txt, db-analysis-summary.txt — inwentarz tenant_id, RLS enabled/forced, role właściciela, polityki.
- **Rola połączenia w runtime**: odczyt `apps/api/.env` (POSTGRES_USER=pim), `docker compose exec api sh -c 'echo $DATABASE_URL'` → `postgresql://pim@database` (hasło pominięte w raporcie), oraz `psql -c "SELECT current_user, session_user"` → `pim|pim`. Potwierdza, że aplikacja łączy się jako superuser+bypassrls — RLS martwy w runtime.
- **GUC w kodzie**: grep `set_config|SET LOCAL|current_setting` → `RlsContextListener.php`, `TenantRlsGucMiddleware.php`. Odczyt obu — ustawiają `app.current_tenant` (request: is_local=true; worker: is_local=false). Porównanie z politykami (raw/db-rls-policies.txt): `refresh_tokens` czyta inny klucz `pim.current_tenant_id`.
- **TenantFilter**: odczyt `TenantFilter.php`, `TenantFilterConfigurator.php`, `RequestTenantSubscriber.php`, `CurrentTenantProvider.php`. Inwentarz `implements TenantScoped` (rg) — wszystkie główne encje katalogowe objęte.
- **disableFilter**: grep `disableFilter|disable('tenant')` → 2 miejsca: `TenantFilterConfigurator.php:35` (uzasadnione — brak tenanta), `PreviewAssetController.php:61` (PUBLIC_ACCESS, finding).
- **SuperAdminContext / break-glass**: odczyt `SuperAdminContext.php`, `BreakGlassController.php`, `SuperAdminTenantsController.php`, `PurgeDeletedTenantsCommand.php`. Weryfikacja `FilterCollection::disable/getFilter/isEnabled` przez reflection w kontenerze (`docker compose exec api php -r ...`).
- **Worker state leak (FrankenPHP)**: grep `private/protected/public static` w src → ZERO statycznych pól trzymających stan. Odczyt `TenantContext` (implements ResetInterface + reset() na kernel.reset), `TenantContextRebindingMiddleware` (rebind + finally clear), `TenantRlsGucMiddleware` (finally reset GUC). Mitygacje obecne. SuperAdminContext NIE implementuje ResetInterface — analiza call-sites.
- **Meilisearch**: odczyt `CatalogSearchService.php` (mandatory `tenantId=` filtr + empty-on-null-tenant), `ProductQuickSearchController.php`, `IndexSettingsTemplate.php` (tenantId filterable). Pojedynczy współdzielony index z filtrem tenant.
- **Mercure**: odczyt `MercurePublisher.php` (topiki bez prefiksu tenanta), reflection `Update` (private default=false), grep `new Update(...true)` → zero. docker-compose.yml mercure (`anonymous`). Frontend `use-permission-invalidation-sse.ts` (EventSource bez tokena w URL). **Probe na żywo**: `curl -sk --max-time 5 "https://pim.localhost/.well-known/mercure?topic=...objects"` → HTTP 200 + text/event-stream.
- **MinIO**: odczyt `flysystem.yaml` (1 bucket per kind, współdzielony), `AssetUploader.php` (storagePath = `<tenant-uuid>/<asset-uuid>/...`), docker-compose minio-init (`mc anonymous set download`), porty (tylko expose).
- **Probe preview**: `curl` anonimowy do `/api/assets/{random}/preview` → HTTP 404 (nie 401) potwierdza brak auth na endpoint.

### Czego NIE dało się sprawdzić (luki audytu)
- **Empiryczna matryca 2-tenant curl** (login jako tenant A, próba odczytu zasobu tenanta B przez REST/GraphQL) — poza zakresem tego przebiegu (osobny przebieg). Findings TenantFilter oparte na analizie statycznej + 1 dowodzie żywym (Mercure/preview).
- **Faktyczny payload Mercure cross-tenant** — probe potwierdził OTWARTY kanał (HTTP 200), ale nie zaobserwowałem konkretnego eventu innego tenanta (wymagałoby wywołania write w drugim tenancie i nasłuchu — zaproponowany probe w findingu A-01).
- **FilterDslResolver → SQL injection**: odczytałem nagłówek; pełna analiza budowania fragmentów JSONB/`attributes_indexed` i parametryzacji wartości to zakres domeny C (injection). Tu tylko odnotowane jako needs-review (tenant scoping fragmentu polega na Doctrine TenantFilter na `objects`, który JEST aktywny).
- **GraphQL**: nie audytowałem osobno warstwy GraphQL API Platform pod kątem TenantFilter (API Platform stosuje ten sam Doctrine filter, ale rozdzielczość custom resolverów niesprawdzona).
- **Junction bez tenant_id** (object_categories, asset_variants, user_roles itd.) — czy FK pozwala związać rekordy 2 różnych tenantów: nie zweryfikowałem app-guardów przy zapisie (potencjalny finding domeny B/C).
- **pgBouncer**: `RlsContextListener` zakłada transaction-mode pooling, ale w compose nie ma pgBouncer — `SET LOCAL` (is_local=true) w request-listenerze działa tylko jeśli zapytania lecą w tej samej transakcji; bez explicit transakcji per request `SET LOCAL` może nie obejmować kolejnych zapytań. Nie zweryfikowałem czy Doctrine owija request w transakcję. Irrelewantne dopóki RLS martwy, ale krytyczne przy aktywacji fazy 2.

---

## Findings szczegółowe

### A-01 [CRITICAL] Mercure: anonimowa subskrypcja + publiczne eventy bez scope tenanta = cross-tenant leak
**Dowód:**
- `docker-compose.yml:372-374` — hub `dunglas/mercure` z `MERCURE_EXTRA_DIRECTIVES: cors_origins ... \n anonymous`. Dyrektywa `anonymous` = subskrypcja bez JWT dozwolona.
- Reflection: `Symfony\Component\Mercure\Update` konstruktor `private` default = `false`. Grep `new Update(...true)` w `apps/api/src` → 0 trafień → KAŻDY event publiczny.
- `apps/api/src/Catalog/Application/Subscriber/MercurePublisher.php:126-127` — topiki `https://pim.localhost/objects/<objectId>` i broadcast `https://pim.localhost/objects` — BRAK prefiksu tenanta.
- `apps/admin/src/lib/identity/use-permission-invalidation-sse.ts:49-53` — frontend subskrybuje przez `new EventSource(url, {withCredentials:true})`, w URL tylko `?topic=...`, BEZ tokena Mercure (bo hub anonymous).
- **Probe żywy:** `curl -sk --max-time 5 -D - "https://pim.localhost/.well-known/mercure?topic=https%3A%2F%2Fpim.localhost%2Fobjects"` → `HTTP/2 200`, `content-type: text/event-stream`, kanał otwarty (keep-alive `:`).

**Atak:** Atakujący (dowolny klient sieciowy, bez konta) subskrybuje `?topic=https://pim.localhost/objects`. Backend każdego tenanta publikuje na ten sam topik przy każdym create/update/publish/archive obiektu → atakujący odbiera w czasie rzeczywistym objectId, kind, code, zmienione kody atrybutów wszystkich tenantów. Analogicznie Import/Export progress (session/user topic) i `identity/tenant/{tenantId}` — guessing tenant UUID lub nasłuch broadcastu.

**Rekomendacja:** (1) usuń `anonymous` z hub directives — wymuś subscriber JWT. (2) Publikuj `new Update(topics, data, private: true)` dla wszystkich eventów domenowych. (3) Wprowadź prefiks tenanta w topikach (`/{tenantId}/objects/...`) i mint subscriber-JWT z `mercure.subscribe` ograniczonym do topików tenanta zalogowanego usera (endpoint mintujący cookie `mercureAuthorization` — obecnie NIE istnieje w kodzie). **Probe do domknięcia:** w dwóch oknach: nasłuch anonimowy `curl` na `/objects`, w drugim oknie zaloguj się jako tenant A i edytuj produkt → sprawdź czy event pojawia się w anonimowym streamie.
**Estymacja:** M (8-16h: subscriber JWT + topic scoping + private flag + FE cookie flow).

### A-02 [CRITICAL] Connection user = superuser+bypassrls+owner; RLS martwy w runtime, izolacja tylko app-layer
**Dowód:**
- `apps/api/.env:9` `POSTGRES_USER=pim`; `docker compose exec api` → `DATABASE_URL=postgresql://pim@database:5432/pim`; `psql -c "SELECT current_user, session_user"` → `pim|pim`.
- raw/db-owner-roles.txt:15 `pim|t|t|t` (rolsuper=t, rolbypassrls=t, rolcanlogin=t); linie 17-102 — `pim` owner wszystkich 86 tabel.
- raw/db-rls-enabled.txt — `relforcerowsecurity` = `f` dla WSZYSTKICH (kolumna 3 wszędzie `f`). 7 tabel ma `relrowsecurity=t` (api_tokens, audit_logs, import_logs, import_staged_files, import_undo_log, invitations, user_tenant_memberships).
- Trzy niezależne drogi bypassu (superuser, bypassrls, owner-bez-force) — zgodnie z db-analysis-summary.txt:8-12.
- Łamie własną regułę: `01-architektura-pim.md:867` „app user nigdy nie ma BYPASSRLS" + R-09. `docs/multi-tenancy.md:3,52` deklaruje RLS „nieaktywne w MVP" — ale baza pokazuje stan POŚREDNI (7 tabel ENABLED z 18 politykami), niespójny z deklaracją.

**Atak/awaria:** Każdy bug w `TenantFilter` (np. native SQL omijający Doctrine, zapomniany `implements TenantScoped`, `disable('tenant')` bez re-enable) = natychmiastowy cross-tenant odczyt/zapis na objects/object_values/attributes/channels/assets — BEZ żadnej drugiej linii obrony. RLS jest dekoracyjny.

**Rekomendacja:** Dla SaaS: utwórz osobną rolę aplikacyjną `pim_app` (NOSUPERUSER, NOBYPASSRLS, NIE-owner tabel), nadaj jej tylko GRANT na tabele, ustaw `DATABASE_URL` na nią; włącz `ENABLE` + `FORCE ROW LEVEL SECURITY` na wszystkich tabelach domenowych (nie tylko 7). Owner/migracje pod osobną rolą `pim_owner`. To jest warunek wstępny multi-tenant go-live, nie „faza 2 opcjonalnie".
**Estymacja:** L (16-24h zgodnie z sekcją 11.1a, ale z osobną rolą — realnie więcej: rola + grants + FORCE na ~40 tabelach + test izolacji pod non-superuserem).

### A-03 [HIGH] /api/assets/{id}/preview — PUBLIC_ACCESS + disable TenantFilter, serwuje bajty po samym UUID
**Dowód:**
- `apps/api/config/packages/security.yaml:105` — `{ path: '^/api/assets/[0-9a-f-]+/preview$', roles: PUBLIC_ACCESS }`.
- `apps/api/src/Asset/Presentation/Controller/PreviewAssetController.php:58-62` — jawne `$filters->disable('tenant')` przed `findById`/`findByObjectId`; 71-72 — 404 gdy null (ukrywa istnienie, ale lookup jest cross-tenant).
- Komentarz autora (linie 36-41) sam przyznaje: „tenant isolation is therefore by-id rather than by-context here. Faza 1 swaps this for short-lived signed URLs".
- **Probe:** `curl -sk "https://pim.localhost/api/assets/00000000-0000-7000-8000-000000000000/preview"` → HTTP 404 (NIE 401) → endpoint osiągalny bez auth.
- UUID wyciekają: `AssetUploader.php:187` zapisuje `previewUrl` = `/api/assets/{uuid}/preview` do `attributes_indexed` (zwracane w `/api/objects`, eksportach).

**Atak:** Atakujący zna/przechwytuje UUID assetu tenanta B (z eksportu, logu, response API innego usera) → pobiera bajty bez logowania i bez bycia w tenancie B. UUID v7 zawiera timestamp (częściowo przewidywalny), co osłabia „128-bit non-enumerable".

**Rekomendacja:** Zamień na krótko-żyjące signed URL mintowane przez authenticated catalog API (zapowiedziane „Faza 1") — przed SaaS go-live, nie po. Alternatywnie: wymagaj auth + sprawdź tenant w handlerze (ale `<img>` nie wyśle Bearer → potrzebny signed token w query param). Nie polegaj na nieenumerowalności UUID jako granicy izolacji.
**Estymacja:** M (8-12h: signed URL infra + rotacja klucza + FE konsumpcja).

### A-04 [MEDIUM] Drift nazwy filtra: SuperAdminContext.FILTER_NAME='tenant_filter' vs zarejestrowany 'tenant' — break-glass nigdy nie wyłącza filtra
**Dowód:**
- `apps/api/config/packages/doctrine.yaml:128-131` — filtr zarejestrowany jako `tenant` (`filters: tenant: class: ...TenantFilter`).
- `SuperAdminContext.php:44` — `public const string FILTER_NAME = 'tenant_filter';`. Linie 91-103 `useCrossTenantMode`: `$filters->isEnabled('tenant_filter')` → zawsze `false` (filtr to `tenant`), więc gałąź `disable()` pomijana. Reflection `FilterCollection::getFilter` rzuca `InvalidArgumentException` na nieznanej nazwie, ale `disable()` jest wołane TYLKO gdy `$previouslyEnabled===true` → brak crashu, ale filtr `tenant` POZOSTAJE aktywny w callbacku cross-tenant.
- Maskowane: `BreakGlassController` i `SuperAdminTenantsController`/`PurgeDeletedTenantsCommand` operują głównie na encjach NIE-TenantScoped (Tenant, User) lub raw SQL `fetchAllAssociative` (`PurgeDeletedTenantsCommand.php:157-168`), więc dziś „działają" mimo aktywnego filtra.

**Atak/awaria:** Latentny. Dzień, w którym break-glass/super-admin dotknie encji `TenantScoped` (np. odczyt cross-tenant produktów), dostanie po cichu wyniki przefiltrowane do tenanta z kontekstu (lub pustki) — błędna recovery + fałszywy audyt „cross_tenant_access=true" bez realnego cross-tenant. Udokumentowany invariant („TenantFilter is disabled exactly for the duration", SuperAdminContext.php:20-21, BreakGlassController.php:50) jest FAŁSZYWY.

**Rekomendacja:** Ujednolić `FILTER_NAME` do `'tenant'` (jedna stała współdzielona z `TenantFilter::PARAMETER` kontekstem / configiem). Dodać test, że `useCrossTenantMode` faktycznie wyłącza filtr (assert `isEnabled('tenant')===false` w callbacku). Dodać `SuperAdminContext implements ResetInterface` z reset() zerującym `activeSuperAdminId` na kernel.reset (worker-safety, gdyby ktoś użył raw `useCrossTenantMode` bez finally).
**Estymacja:** S (2-4h).

### A-05 [MEDIUM] GUC name drift: listenery ustawiają app.current_tenant, polityki refresh_tokens czytają pim.current_tenant_id
**Dowód:**
- raw/db-rls-policies.txt:13-16 — `refresh_tokens` polityki (select/insert/update/delete) używają `current_setting('pim.current_tenant_id', true)::uuid`.
- raw/db-rls-policies.txt:1-12,17-18 — pozostałe 6 tabel używa `app.current_tenant`; 8 polityk super-admin używa `app.is_super_admin`.
- `RlsContextListener.php:58,67` + `TenantRlsGucMiddleware.php:62,74` — kod ustawia WYŁĄCZNIE `app.current_tenant` i `app.is_super_admin`. NIGDZIE nie ustawia `pim.current_tenant_id` (grep potwierdza — tylko w komentarzach docs).
- `docs/multi-tenancy.md:58,63` deklaruje `pim.current_tenant_id` jako kontrakt, sprzecznie z faktycznym kodem `app.current_tenant`.

**Awaria:** Po włączeniu FORCE RLS w fazie 2 (jak planuje multi-tenancy.md:72-74): `refresh_tokens` polityki porównają `tenant_id = NULL::uuid` (GUC nieustawiony) → 3-valued logic → 0 wierszy → refresh-token flow (re-login) zepsuty dla wszystkich. 5 z 18 polityk martwe mimo aktywacji.

**Rekomendacja:** Ujednolicić wszystkie polityki do JEDNEGO klucza GUC (`app.current_tenant`), zaktualizować migrację refresh_tokens (lub kod, ale spójność na `app.*` jest tańsza). Zaktualizować docs/multi-tenancy.md (kontrakt GUC = `app.current_tenant`, nie `pim.current_tenant_id`).
**Estymacja:** S (2-4h: migracja DROP/CREATE POLICY + sync docs).

### A-06 [MEDIUM] MinIO bucket pim-assets anonimowo pobieralny
**Dowód:**
- `docker-compose.yml:359` — `mc anonymous set download local/pim-assets || true`. Cały bucket dostępny do pobrania bez credentiali.
- `flysystem.yaml` — 1 współdzielony bucket `pim-assets` dla wszystkich tenantów; izolacja tylko przez prefiks ścieżki `<tenant-uuid>/<asset-uuid>/` (AssetUploader.php:89-94).
- Mitygacja obecna: MinIO porty `9000/9001` tylko `expose` (docker-compose.yml:320-321), NIE w `ports:` (jedyny published port to Caddy `443`, linie 101-104) → bucket nieosiągalny z hosta w tym compose.

**Atak:** Jeśli ktokolwiek wystawi MinIO bezpośrednio (prod, debug, inny compose), anonimowy `GET /pim-assets/<tenant>/<asset>/original.ext` pobiera bajty dowolnego tenanta przy znajomości obu UUID (wyciekają jak w A-03). „Coarse path-level isolation" (komentarz AssetUploader.php:29-31) nie jest izolacją — to security-by-obscurity.

**Rekomendacja:** Usuń `mc anonymous set download` — wymuś presigned URL z backendu (spójne z rekomendacją A-03). Bucket prywatny domyślnie. Rozważ bucket-per-tenant lub IAM policy z prefix-condition na poziomie S3 jako defence-in-depth.
**Estymacja:** S (2-4h, łączone z A-03 signed URL).

### A-07 [LOW] APP_DEFAULT_TENANT_CODE=demo commitowany; fallback tenanta dla nieuwierzytelnionych
**Dowód:**
- `apps/api/.env:61` `APP_DEFAULT_TENANT_CODE=demo` (plik w repo, nie .env.local).
- `CurrentTenantProvider.php:51-53` — gdy brak `TenantAware`/`ApiKeyPrincipal` user, fallback `findByCode($defaultTenantCode)`. Czyli każdy request bez uwierzytelnionego usera (ale po RequestTenantSubscriber) dostaje tenant `demo` w kontekście.

**Awaria:** W MVP single-tenant celowe (demo). W SaaS multi-tenant: nieuwierzytelniony/źle-uwierzytelniony request może operować w kontekście „demo" zamiast deny. RequestTenantSubscriber.php:51-55 ustawia TenantContext tylko gdy `!=null`, więc realne ryzyko zależy od tego czy `demo` istnieje w prod — ale env-fallback to anty-wzorzec dla SaaS.

**Rekomendacja:** W konfiguracji SaaS ustaw `APP_DEFAULT_TENANT_CODE=''` (pusty → null → deny). Fallback po env tylko dla CLI/dev. Rozważ usunięcie ścieżki env-override z produkcyjnej gałęzi.
**Estymacja:** S (1-2h).

### Pozytywy zweryfikowane (nie chwalę bez dowodu)
- **Worker state leak — mitygowany.** Grep `static` w `apps/api/src` → 0 pól statycznych trzymających stan. `TenantContext implements ResetInterface` z `reset()` (TenantContext.php:20,45-48) na `kernel.reset`. `RequestTenantSubscriber` clear na TERMINATE (priorytet -255). `TenantContextRebindingMiddleware` rebind + `finally { clear() }` (linie 89-96). `TenantRlsGucMiddleware` `finally { set_config('app.current_tenant','') }` (linie 70-75). Worker-leak tenant context = poprawnie zamknięty.
- **Meilisearch — tenant scoping wymuszony.** `CatalogSearchService.php:67-75,107` — `null` tenant → empty result; `tenantId="<uuid>"` zawsze pierwszym członem `AND`; `customFilterExpression` (FilterDsl) owinięty w `(...)` i AND-owany PO tenant filtrze → nie da się uciec z sub-wyrażenia w składni filtra Meili. `IndexSettingsTemplate` deklaruje `tenantId` jako filterable. (FilterDsl injection = zakres domeny C.)
- **Pokrycie TenantScoped — kompletne na encjach katalogowych.** rg potwierdza `implements TenantScoped` na CatalogObject, ObjectValue, Attribute, AttributeGroup, AttributeOption, ObjectType, Channel(+nodes/mappings/placement/profile), Asset, wszystkie Import/Export entities, ApiKey/ApiProfile, SavedView, BulkSession/Job, TenantAgentConfig, TenantLocale, MenuConfiguration. `SmartFilterPreset` + `SystemShipped` (intencjonalna widoczność built-inów przez `tenant_id IS NULL`, TenantFilter.php:56-65).

---

## Aktualizacja — przebieg empiryczny matrycy 2-tenant (2026-06-16 wieczór)

Wykonano kontrolowaną matrycę cross-tenant na żywym stacku (tenanty demo↔acme, JWT obu userów, GET-only; transkrypty: `../probes/matrix-2tenant.txt`, `mercure-leak.txt`, `mercure-stream.txt`, `asset-preview.txt`, `token-dev-only.txt`).

**Krytyczna uwaga metodologiczna:** oba konta testowe mają `ROLE_SUPER_ADMIN` (najtwardszy przypadek dla tenant-filtera).

1. **Izolacja danych domenowych — POPRAWNA (hipoteza wycieku OBALONA).** Kolekcje: każdy token widzi tylko swoje (demo objects=6747 vs acme=3; attributes 37 vs 9; assets 10 vs 0; channels 1 vs 0; import-sessions 42 vs 0). Cross-read po ID = **404 w obie strony** dla products/objects/attributes/channels/assets/import-sessions. Nagłówek `X-Tenant-Id` **ignorowany** (tenant z JWT). `TenantFilterConfigurator` włącza filtr per request bez wyjątku dla super-admina. → Doctrine TenantFilter jest solidną granicą izolacji danych domenowych.
2. **`/api/admin/tenants` (B-01/AUD-003) — operator panel by-design, NIE wyciek danych.** `requireSuperAdmin()` (non-super → 403), zwraca tylko metadane (code/name/plan/active_users), audyt `cross_tenant_access=true`. Severity AUD-003 zrewidowane CRITICAL→HIGH; problem realny = model uprawnień platform-vs-tenant (fixtures nadają globalny super_admin Ownerom), nie data-leak.
3. **Mercure (A-01/AUD-001) — POTWIERDZONE CRITICAL.** Anonim odebrał realne eventy demo (`object.enabled_changed`, RPT-1).
4. **token_dev_only (AUD-007) — POTWIERDZONE.** `password-reset/request` → 200 z 64-znakowym tokenem bezwarunkowo. Severity HIGH→CRITICAL.
5. **Asset preview (A-03/AUD-006) — wektor potwierdzony, bajty nieodtworzone** (brak blobów w dev; właściciel demo też 404 „Variant blob missing" → handler dociera do rekordu mimo `disable('tenant')`). Confidence wycieku bajtów = probable.

**Domknięcie pozostawione:** pozostałe publishery Mercure (export/import/permission), B-01 write-path, asset bytes z realnym blobem, benchmark 50k.
