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

## RLS — kontrakt GUC i stan aktywacji

**Kanon nazwy GUC: `app.current_tenant`** (plus `app.is_super_admin` dla break-glass bypass). To jedyna zmienna sesyjna jaką ustawia aplikacja — patrz [`RlsContextListener`](../apps/api/src/Identity/Infrastructure/Doctrine/RlsContextListener.php) (HTTP, `kernel.request`) i [`TenantRlsGucMiddleware`](../apps/api/src/Shared/Infrastructure/Messenger/TenantRlsGucMiddleware.php) (worker async). Każda polityka RLS w bazie MUSI czytać tę nazwę. Przykład polityki:

```sql
CREATE POLICY tenant_isolation_select ON refresh_tokens FOR SELECT
    USING (tenant_id = current_setting('app.current_tenant', true)::uuid);
```

> **Historia driftu (AUD-027 / W1-2, naprawione [`Version20260617000000`](../apps/api/migrations/Version20260617000000.php)):** pierwsza fala RLS ([`Version20260428195217`](../apps/api/migrations/Version20260428195217.php)) seedowała `products` + `refresh_tokens` ze starą nazwą `pim.current_tenant_id`, której kod nigdy nie ustawia. Polityki `products` zniknęły przy migracji `products → objects` ([`Version20260428222056`](../apps/api/migrations/Version20260428222056.php)); `refresh_tokens` pozostał osamotniony na starym GUC. Era IMP2-2.x (api_tokens, audit_logs, import_logs, import_staged_files, import_undo_log, invitations, user_tenant_memberships) używa już `app.current_tenant`. Migracja W1-2 ujednoliciła `refresh_tokens` na `app.current_tenant` — **warunek wstępny** FORCE RLS (W1-1): pod FORCE niedopasowany GUC dawałby `tenant_id = NULL` (three-valued logic) → deny-all → zepsuty refresh-login. Migracja W1-2 sama **nie** włącza ani nie wymusza RLS — zmienia tylko nazwę GUC w politykach.

`current_setting('app.current_tenant', true)` — `true` (`missing_ok`) zwraca `NULL` gdy GUC nie ustawiony, `tenant_id = NULL` jest false (three-valued logic) → pod aktywnym RLS bez `SET LOCAL`/`set_config` wszystkie wiersze są deny (fail-safe).

Stan dziś: RLS **ENABLED** (ale nie FORCED) na 7 tabelach RBAC/import (api_tokens, audit_logs, import_logs, import_staged_files, import_undo_log, invitations, user_tenant_memberships); `refresh_tokens` ma polityki ale RLS jest disabled (polityki inertne). Connection user `pim` jest superuser+BYPASSRLS → RLS martwy w runtime aż do W1-1 (osobna rola `pim_app` + `FORCE`). **Wykluczone z polityk:**
- `users` — login szuka usera globalnie po email zanim tenant jest znany; aktywacja RLS wymagałaby SECURITY DEFINER bypass dla flow autoryzacji;
- `roles` — nullable `tenant_id` (built-iny mają NULL), naiwna polityka `tenant_id = X` ukryłaby je;
- infra (`tenants`, `permissions`, junction tables, `messenger_messages`, `doctrine_migration_versions`) — bez `tenant_id`.

### Aktywacja FORCE RLS (W1-1 / AUD-002, sekcja 11.1a architektury)

1. Osobna rola `pim_app` (NOSUPERUSER, NOBYPASSRLS, nie-owner) z GRANT-ami; `DATABASE_URL` na nią; owner/migracje pod `pim_owner`.
2. `ENABLE` + `FORCE ROW LEVEL SECURITY` na wszystkich ~46 tenantowanych tabelach (nie tylko 7).
3. GUC `app.current_tenant` ustawiany per request/worker — **już wpięty** (`RlsContextListener` + `TenantRlsGucMiddleware`); W1-2 zsynchronizowało wszystkie polityki na tę nazwę, więc krok ten nie wymaga zmian w kodzie.
4. Comprehensive test suite — pełen pen-test izolacji pod non-superuserem (cross-read = 0).
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
