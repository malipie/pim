# Multi-tenancy — strategia, kontrakty, RLS

PIM jest **multi-tenant ready, single-tenant deployed** (ADR-003). Każda tabela domenowa niesie `tenant_id UUID` od dnia 1, izolacja na poziomie aplikacji działa od Sprintu 0, polityki RLS w Postgresie są przygotowane (stworzone przez migrację, **nieaktywne** w MVP). Aktywacja RLS wraz z multi-tenant SaaS = faza 2 (sekcja 11.1a [`Project Plan/01-architektura-pim.md`](../Project%20Plan/01-architektura-pim.md)).

## Tabele i ich tenant scope

| Tabela | `tenant_id` | Uwagi |
|---|---|---|
| `tenants` | — | sama encja Tenant; nie scopowana |
| `users` | `NOT NULL` (FK) | tenant scope, ale **bez** `TenantScoped` w PHP — login musi znaleźć usera po email zanim tenant będzie znany |
| `products` | `NOT NULL` (FK) | domain table, `TenantScoped` |
| `roles` | nullable (FK) | `NULL` = globalna rola wbudowana (super_admin / catalog_manager / integration_manager / viewer) |
| `permissions` | — | globalny katalog `(resource, action)`, intencjonalnie unscopowany |
| `role_permissions`, `user_roles` | — | M2M junction; tenant scope dziedziczony po wierszach rodziców |
| `refresh_tokens` | `NOT NULL` (denormalised UUID) | lookup po `token_hash` UNIQUE; tenant_id dla integralności + hipotetycznej RLS w fazie 2 |
| `messenger_messages` | — | infra (Symfony Messenger) |
| `doctrine_migration_versions` | — | bookkeeping Doctrine |

`pim:tenant:audit` (poniżej) sprawdza ten zestaw automatycznie — gdy w epiku 0.3 dochodzą `Object`/`Channel`/`Asset`, audit alarmuje jeśli któraś tabela zapomni `tenant_id`.

## Interfejsy

Dwa marker interface'y w `App\Identity\Application\`:

- **`TenantAware`** — *"ten obiekt umie zwrócić aktywny tenant"*. Implementuje `User`. Używany przez `CurrentTenantProvider` żeby resolver wyciągnął tenant z autoryzowanego principal'a (JWT → User → tenant).
- **`TenantScoped`** — *"ta encja niesie `tenant_id` na poziomie schematu, listener auto-stempluje + filter scopuje"*. Implementuje `Product`. Wymaga `getTenant(): ?Tenant` + `assignTenant(Tenant): void`.

Można implementować oba (User by mógł — w MVP nie potrzebuje, lookup po email globalny), ale 95% domain entities chce tylko `TenantScoped`.

## Listener — auto-stempling przy `prePersist`

[`TenantAssignmentListener`](../apps/api/src/Identity/Infrastructure/Doctrine/EventListener/TenantAssignmentListener.php) dispatchuje przez `instanceof TenantScoped`:

1. encja nie jest `TenantScoped` → ignore;
2. `getTenant()` już ustawiony → respect (caller stamped manually);
3. `TenantContext::get()` jest `null` → throw `LogicException` (clearer niż NOT NULL constraint violation z DB);
4. inaczej → `$entity->assignTenant($tenant)`.

Konsekwencje:
- nowe encje opt-in przez `implements TenantScoped` — bez modyfikacji listener'a;
- bulk path (`COPY`, raw INSERT, `RefreshTokenService`) ustawia `tenant_id` ręcznie w kodzie — bypass listener'a jest świadomy.

## Filter — auto-scope w SELECT/UPDATE/DELETE

[`TenantFilter`](../apps/api/src/Identity/Infrastructure/Doctrine/Filter/TenantFilter.php) (Doctrine `SQLFilter`) dokleja `WHERE <alias>.tenant_id = :current_tenant` do każdego query którego `targetEntity implements TenantScoped`. Parametr `current_tenant` ustawia `TenantFilterConfigurator` po resolve'owaniu tenant'a z requestu.

Co filter NIE łapie:
- native SQL przez DBAL (smoke #12 / `TenantIsolationTest::nativeSqlBypassesTheDoctrineFilterByDesign` udowadnia że granica leży na warstwie aplikacji);
- `COPY` i raw `INSERT … SELECT` (patrz "Bulk operations" niżej);
- query po encjach NIE-`TenantScoped` (User, Role, Permission, RefreshToken).

## RLS — gotowe, nieaktywne

Migracja [`Version20260428195217`](../apps/api/migrations/Version20260428195217.php) tworzy 4 polityki (SELECT/INSERT/UPDATE/DELETE) na każdej tabeli `TenantScoped`:

```sql
CREATE POLICY tenant_isolation_select ON products FOR SELECT
    USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid);
```

Postgres traktuje `CREATE POLICY` na tabeli **bez aktywnego RLS** jako **inertne** — polityki istnieją w katalogu, ale każde query działa jakby ich nie było. Aktywacja w fazie 2 to jeden `ALTER TABLE … ENABLE ROW LEVEL SECURITY`.

`current_setting('pim.current_tenant_id', true)` — `true` (`missing_ok`) zwraca `NULL` gdy GUC nie ustawiony, `tenant_id = NULL` jest false (three-valued logic) → bez `SET LOCAL` wszystkie wiersze są deny. Chroni przed przypadkowym wyciekiem gdy ktoś włączy RLS przed wpięciem ustawienia GUC w request lifecycle.

Tabele objęte politykami: `products`, `refresh_tokens`. **Wykluczone:**
- `users` — login szuka usera globalnie po email zanim tenant jest znany; aktywacja RLS wymagałaby SECURITY DEFINER bypass dla flow autoryzacji (zaprojektowanie w fazie 2);
- `roles` — nullable `tenant_id` (built-iny mają NULL), naiwna polityka `tenant_id = X` ukryłaby je;
- infra (`tenants`, `permissions`, junction tables, `messenger_messages`, `doctrine_migration_versions`) — bez `tenant_id`.

### Aktywacja w fazie 2 (16-24h, sekcja 11.1a architektury)

1. `ALTER TABLE products       ENABLE ROW LEVEL SECURITY; ALTER TABLE products       FORCE ROW LEVEL SECURITY;`
2. `ALTER TABLE refresh_tokens ENABLE ROW LEVEL SECURITY; ALTER TABLE refresh_tokens FORCE ROW LEVEL SECURITY;` — **chyba że** decyzja "refresh_tokens to infra, nie domain" → `DROP POLICY` zamiast `ENABLE`.
3. Ustawianie `SET LOCAL pim.current_tenant_id = :id` na każdej transakcji w `TenantFilterConfigurator` (rozszerzenie ponad istniejące ustawianie Doctrine filter param).
4. Comprehensive test suite — pełen pen-test izolacji.
5. Pen-test izolacji przez zewnętrznego audytora.

## Bulk operations — runbook

`COPY`, raw `INSERT … SELECT`, oraz każdy worker bulk-import używający `EntityManager::clear()` wymagają osobnej dyscypliny:

- **`COPY`** ignoruje RLS i Doctrine filter. Zawsze ustaw `tenant_id` w SELECT klauzuli źródłowej (`COPY products FROM stdin … WHERE tenant_id = '…'`) lub jako stałą kolumnę przy imporcie.
- **`INSERT … SELECT`** respektuje RLS (gdy aktywne), bo to normalny INSERT — używaj go zamiast `COPY` dla cross-tenant exportów.
- **bulk worker** (`AbstractBatchHandler` z #13) musi po każdym `clear()` zrobić `find()` na `Tenant` i `TenantContext::set()` przed kontynuacją batch'a (lekcja z #13 — bez tego `TenantAssignmentListener` rzuca na detached referencję).
- gdy aktywujemy RLS w fazie 2, runbook backup/restore (`docs/runbook/restore.md`) musi explicit:
  - przed `pgBackRest restore` → `ALTER TABLE … DISABLE ROW LEVEL SECURITY` (tylko dla supera);
  - po restore → `ALTER TABLE … ENABLE ROW LEVEL SECURITY`.
  Inaczej `pgBackRest` `COPY` na pusty target z aktywnym RLS = wszystkie wiersze deny.

## CLI — `pim:tenant:audit`

```bash
docker compose exec api bin/console pim:tenant:audit
```

Read-only audit każdej tabeli z `public` schema:

- **FAIL** — domena bez `tenant_id` (najczęściej: zapomniana migracja przy nowej encji);
- **FAIL** — domena z `tenant_id` nullable (poza `roles`);
- **WARN** — domena bez indeksu na `tenant_id` (perf concern);
- **OK** — wszystko gra.

Exit code 0/1 (success/failure) — nadaje się do CI gate'u w przyszłości. Idempotent, nieinwazyjny.

## Smoke test izolacji

[`TenantIsolationTest`](../apps/api/tests/Functional/Identity/TenantIsolationTest.php) (#12 / 0.0.12, rozszerzony przez voters w #26) tworzy dwa tenanty, dwie pary userów + produktów, autentykuje admin'em tenant'u A i sprawdza że:

- `GET /api/products` zwraca tylko produkty A (5 z 10);
- `GET /api/products/{id-z-tenant-B}` zwraca **404** (filter ukrywa istnienie wiersza, nie 403);
- `PATCH` cross-tenant zwraca **404**;
- raw SQL przez DBAL widzi obie strony — explicit, dokumentuje gdzie kończy się boundary.

W epiku 0.3 ten test rozszerzy się o `Object`/`Channel`/`Asset`. Po fazie 2 dochodzi pen-test RLS — patrz wyżej.
