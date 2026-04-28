# RBAC — built-in roles and permissions

Cztery globalne role seedowane przez `pim:rbac:seed` (`#27` / `0.2.4`). Source of truth: [`src/Identity/Domain/Rbac/RbacMatrix.php`](../apps/api/src/Identity/Domain/Rbac/RbacMatrix.php) — ten plik jest tylko streszczeniem czytanym przez ludzi.

## Permissions (resource × action)

Każda permission to para `(resource, action)` z `code` w formie `resource.action`. Cztery actions: `read`, `write`, `delete`, `admin`.

Resources zdefiniowane w MVP (post-ADR-009):

| Resource          | Pochodzi z                                |
|-------------------|-------------------------------------------|
| `object`          | epik 0.3 (Catalog — generic ObjectType)   |
| `object_type`     | epik 0.3                                  |
| `attribute`       | epik 0.3                                  |
| `attribute_group` | epik 0.3                                  |
| `category`        | epik 0.3 (sugar: `kind='category'`)       |
| `asset`           | epik 0.3 (sugar: `kind='asset'`)          |
| `brand`           | epik 0.3 (sugar: `kind='brand'`)          |
| `channel`         | epik 0.6                                  |
| `integration`     | epik 0.8 / 0.9 (BaseLinker / Shopify)     |
| `api_profile`     | epik 0.10 (API Configurator)              |
| `tenant`          | epik 0.2 (Identity — `tenant.admin` = settings)  |
| `user`            | epik 0.2 (Identity — `user.admin` = invite/disable) |
| `role`            | epik 0.2 (Identity)                       |

Seeder wpisuje **wszystkie** kombinacje resource × action (13 × 4 = 52 permissions na dzisiaj). Voters i API surface'y nadrabiają stopniowo wraz z dorzucanymi epikami — matrix jest source of truth, nie odwrotnie.

## Role matrix (MVP)

| Role                  | Permissions                                                                                           |
|-----------------------|-------------------------------------------------------------------------------------------------------|
| `super_admin`         | **wszystkie** (full read/write/delete/admin na każdym resource)                                        |
| `catalog_manager`     | read/write/delete na: `object`, `object_type`, `attribute`, `attribute_group`, `category`, `asset`, `brand` |
| `integration_manager` | read/write/delete na: `channel`, `integration`, `api_profile` + read na: `object`, `object_type`, `attribute`, `category`, `brand`, `asset` |
| `viewer`              | tylko `read` na każdym resource                                                                        |

### Dlaczego taki podział

- **`super_admin`** — admin tenant operatora, pełen dostęp. Persony: Tomasz (CEO/Owner), Marcin (founder/dogfood).
- **`catalog_manager`** — codzienna obsługa katalogu produktów/kategorii/atrybutów/zdjęć. Brak dostępu do channel/integration — to wymaga osobnego review (rate limits, mappings). Persony: Kasia (Catalog Manager).
- **`integration_manager`** — konfiguracja kanałów dystrybucji + API endpointów. Read-only na katalog (musi widzieć produkty żeby je mapować, ale nie edytować). Persony: Piotr (IT/Integration Specialist).
- **`viewer`** — read-only na całość. Audit, BI, ad-hoc reportowanie. Bez persony w MVP — Magda (Marketing) trafia w `catalog_manager` w MVP, dedykowany `marketing_manager` dochodzi w Fazie 1 (sekcja workflow).

Custom roles per-tenant (Faza 2+) są w bazie wspierane od dnia 1 — `roles.tenant_id IS NOT NULL` wystarczy. Admin UI do zarządzania custom rolami: ticket post-MVP.

## Symfony Security mapping

`User::getRoles()` zwraca:

1. `roles JSON` (legacy column z Sprint-0, drop w post-MVP cleanup)
2. `'ROLE_'.strtoupper($role->getCode())` dla każdego z `$assignedRoles` (M2M)
3. `ROLE_USER` jako floor (Symfony convention)

W praktyce `super_admin` rola = `ROLE_SUPER_ADMIN` w access_control. Voters (#26) sprawdzają granular permissions przez relację Role↔Permission, nie przez stringi `ROLE_*`.

## Operacja seedowania

```bash
docker compose exec api php bin/console pim:rbac:seed
```

Seeder jest **idempotentny**:

- Pierwsza próba: tworzy 52 permissions + 4 roles.
- Każda kolejna: 0 utworzeń, 0 update'ów (no-op).
- Zmiana `RbacMatrix::roles()` (np. dodanie permission do `catalog_manager`): re-run podbije licznik `rolesUpdated` o 1.
- Zmiana `RoleDefinition.name`: re-run podbije `rolesUpdated`.

Fixture'y dev (`AppFixtures`) wołają seeder automatycznie przed persistencją userów — fixture admin dostaje `super_admin` przez M2M.

## Dodawanie nowego permission/role

1. Edytuj `src/Identity/Domain/Rbac/RbacMatrix.php`:
   - Nowy resource → dodaj do `RESOURCES`.
   - Nowa rola → dodaj do `roles()`.
   - Modyfikacja matrix istniejącej roli → edytuj odpowiedni `RoleDefinition`.
2. Zaktualizuj sekcję "Role matrix" w tym pliku (ten plik jest streszczeniem, nie source of truth).
3. Re-run `pim:rbac:seed` — sprawdź licznik. PHPUnit `RbacSeederTest::seedsAllFourBuiltInRolesWithMatrixPermissions` może wymagać aktualizacji asercji.
