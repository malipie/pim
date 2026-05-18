# Integration tests — Cortex PIM

> Status: MVP-Alpha. Source: [RBAC-P1-009](https://github.com/malipie/PIM/issues/648) (#648).
> The full "testcontainers + template DB caching + parallel execution" infrastructure from the ticket is deferred (see *Świadome odejścia* below); this doc captures what's already in place + the cross-tenant pattern Phase 2 RBAC work will build on.

## Existing infrastructure (no setup required)

### CI (`.github/workflows/quality-php.yml`)

The PHPUnit job already runs against a fresh Postgres on every PR + push to main:

```yaml
services:
  postgres:
    image: postgres:16-alpine
    env:
      POSTGRES_USER: pim
      POSTGRES_PASSWORD: ChangeMeInDev
      POSTGRES_DB: pim
    ports:
      - 5432:5432
```

The service container starts before the test step and stays up for the full job. Foundry's `ResetDatabase` rebuilds the schema from entity metadata at the start of each test class — no migrations step required for the existing entity surface (RBAC migrations are exercised separately by the Playwright job's `doctrine:migrations:migrate` step).

### Local dev (`pnpm stack:up`)

`docker compose up -d` brings up:

- `database` — Postgres 16, exposed on `localhost:5432` (creds `pim` / `ChangeMeInDev`)
- `redis` — Redis 7
- `minio` — S3-compatible storage
- `api` — FrankenPHP container running the API
- `caddy` — reverse proxy serving `https://pim.localhost`

The `apps/api/.env.test` defines `DATABASE_URL` pointing to the same Postgres but with `?dbname_suffix=_test` (Foundry's pattern); the test database is created and reset per test class automatically.

```bash
# Run all integration tests
docker compose exec api php bin/phpunit --testsuite=integration

# Run a single test class
docker compose exec api php bin/phpunit tests/Integration/Identity/ByokKeyManagerTest.php

# Run with coverage
docker compose exec api php bin/phpunit --coverage-html=var/coverage
```

## Writing an integration test

Reference: [`apps/api/tests/Integration/Identity/ByokKeyManagerTest.php`](../../apps/api/tests/Integration/Identity/ByokKeyManagerTest.php) — the canonical pattern.

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration\YourBundle;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class YourTest extends KernelTestCase
{
    use Factories;       // Enables Foundry static helpers (Factory::createOne(), Factory::createMany())
    use ResetDatabase;   // Rebuilds schema from entity metadata before each test class

    public function testSomething(): void
    {
        // self::getContainer() auto-boots the kernel; do not call self::bootKernel() explicitly.
        $service = self::getContainer()->get(YourService::class);

        // … your assertions
    }
}
```

Critical: place the test in **`tests/Integration/`** (not `tests/`) — `phpunit.dist.xml` only registers `tests/Integration` under the `integration` testsuite. Tests in `tests/` directly are picked up only by the catch-all `all` suite and may not get proper boot config.

## Cross-tenant isolation pattern (Phase 2+ ready)

The Phase 2 Doctrine TenantFilter ([#653](https://github.com/malipie/PIM/issues/653)) and Phase 3 Voters will need a consistent way to spin up two tenants, attach role assignments, and assert isolation. Until that base class lands, the pattern is:

```php
use App\Shared\Infrastructure\Foundry\TenantFactory;

// Two tenants
$tenantA = TenantFactory::createOne(['code' => 'tenant-a'])->_real();
$tenantB = TenantFactory::createOne(['code' => 'tenant-b'])->_real();

// Per-tenant roles / users — seed via direct entity construction or
// the cortex:tenant:seed-roles command's underlying service (RBAC-P1-007).
// Phase 2 wires this into a dedicated CrossTenantTestCase base class.

// Assert tenant A query returns no rows owned by tenant B.
```

## Świadome odejścia (deferred from the original #648 scope)

The ticket asked for a substantial test-infrastructure overhaul. The MVP-viable subset shipped today is **this documentation page** — the underlying infrastructure already covers the common cases and the gaps are narrow Phase 2+ extensions.

| Deferred | Reason | Follow-up |
|---|---|---|
| `docker-compose.test.yml` (separate test stack) | Existing `docker-compose.yml` + Foundry `ResetDatabase` + `dbname_suffix=_test` already isolates the test DB from the dev DB inside the same Postgres container. A second stack adds operational complexity without measurable test-isolation gain at our scale (50+ test classes today, ~10 min full suite in CI). | Re-evaluate if we hit 200+ classes or need parallel CI jobs. |
| `tests/IntegrationTestCase.php` base class | Existing pattern (`KernelTestCase` + `Factories` + `ResetDatabase` traits) is the de-facto base, exercised by 20+ test classes. A custom base would duplicate trait wiring without adding behaviour. | If a future cross-cutting concern emerges (e.g., RBAC token bootstrapping), introduce a focused trait instead of a base class. |
| `tests/CrossTenantTestCase.php` base class | The 2-tenant pattern is small enough to inline per test today. The dedicated base lands with Phase 2 #653 when Doctrine TenantFilter is wired and tests need to disable it explicitly. | Phase 2 #653. |
| `make test:integration*` targets | The `pnpm`-based docker-compose workflow + `pnpm test` (when wired) is the established interface; adding a Makefile fragments the entrypoint surface. | Skipped indefinitely. |
| Postgres template DB caching (`CREATE DATABASE … TEMPLATE …`) | Foundry's ResetDatabase already amortises the schema build by rebuilding once per test class. With dh-auditor's per-entity audit tables our schema is small; the cache complexity isn't justified at current cadence. | Re-evaluate if PHPUnit boot exceeds 60s. |
| `phpunit --parallel=4` | PHPUnit 12 has [paratest](https://github.com/paratestphp/paratest) as the recognised parallel runner — requires careful database isolation per worker. Until benchmarks show CI taking >15 min, sequential runs are simpler. | Re-evaluate Phase 6 hardening (#720). |

## Cross-references

- [`apps/api/tests/Integration/Identity/ByokKeyManagerTest.php`](../../apps/api/tests/Integration/Identity/ByokKeyManagerTest.php) — canonical Identity test pattern
- [`apps/api/phpunit.dist.xml`](../../apps/api/phpunit.dist.xml) — testsuite definitions
- [`.github/workflows/quality-php.yml`](../../.github/workflows/quality-php.yml) — CI test job
- [`apps/api/src/Shared/Infrastructure/Foundry/TenantFactory.php`](../../apps/api/src/Shared/Infrastructure/Foundry/TenantFactory.php) — multi-tenant test setup
- [CLAUDE.md](../../CLAUDE.md) §"No mocking integration tests" — testing policy
- [`Project Plan/07-rbac-implementation-plan.md`](../../Project%20Plan/07-rbac-implementation-plan.md) §2 — testing strategy (4 layers)
