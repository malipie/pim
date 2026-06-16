<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application;

use App\Catalog\Domain\ObjectKind;
use App\Identity\Application\CurrentTenantProvider;
use App\Identity\Domain\Entity\User;
use App\Search\Application\CatalogSearchService;
use App\Search\Infrastructure\MeilisearchClientFactory;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * AUD-004 (#1574) — unit-level guard for Meilisearch filter-key injection.
 *
 * Deterministic counterpart to the live-Meili ApiTest: a filter key that is
 * not a known filterable attribute (e.g. `parentId IS NULL OR tenantId`,
 * which smuggles a low-precedence `OR` that closes the tenant AND-scope)
 * must be rejected with a 400 *before* the service touches Meilisearch.
 *
 * We wire the service with a {@see MeilisearchClientFactory} that has no URL
 * configured, so `create()` throws {@see LogicException}. If the key
 * whitelist is missing (pre-fix), the service builds the filter expression
 * and reaches `create()` → `LogicException` surfaces. With the fix, the key
 * is rejected up front → `BadRequestHttpException` and `create()` is never
 * called. The exception *type* is therefore the assertion.
 */
final class CatalogSearchServiceFilterKeyTest extends TestCase
{
    #[Test]
    public function rejectsFilterKeyCarryingMeiliOperator(): void
    {
        $service = $this->serviceWithTenant();

        $this->expectException(BadRequestHttpException::class);

        $service->search(
            kind: ObjectKind::Product,
            query: '',
            filters: ['parentId IS NULL OR tenantId' => '00000000-0000-7000-8000-000000000000'],
        );
    }

    #[Test]
    public function rejectsUnknownPlainFilterKey(): void
    {
        $service = $this->serviceWithTenant();

        $this->expectException(BadRequestHttpException::class);

        $service->search(
            kind: ObjectKind::Product,
            query: '',
            filters: ['totally_unknown_field' => 'x'],
        );
    }

    #[Test]
    public function rejectsUnknownKeyInArrayValuedFilter(): void
    {
        $service = $this->serviceWithTenant();

        $this->expectException(BadRequestHttpException::class);

        $service->search(
            kind: ObjectKind::Product,
            query: '',
            filters: ['brand" OR tenantId' => ['a', 'b']],
        );
    }

    #[Test]
    public function rejectsUnknownRangeFilterKey(): void
    {
        $service = $this->serviceWithTenant();

        $this->expectException(BadRequestHttpException::class);

        $service->search(
            kind: ObjectKind::Product,
            query: '',
            rangeFilters: ['price >= 0 OR tenantId' => ['gte' => 1.0]],
        );
    }

    /**
     * Whitelisted attribute keys must NOT be rejected by the validator. The
     * service swallows the downstream Meili failure (unconfigured factory)
     * inside its try/catch and degrades to an empty result — the point here
     * is that NO {@see BadRequestHttpException} escapes for a legitimate key,
     * proving the whitelist accepts known fields instead of blanket-rejecting.
     */
    #[Test]
    public function acceptsWhitelistedFilterKey(): void
    {
        $service = $this->serviceWithTenant();

        // `enabled` is a RESERVED_FILTERABLE — must pass the key whitelist.
        // Meili is unreachable (url=null) so the service returns emptyResult
        // rather than 400; the absence of a thrown 400 is the assertion.
        $result = $service->search(
            kind: ObjectKind::Product,
            query: '',
            filters: ['enabled' => 'true'],
        );

        self::assertArrayHasKey('hits', $result);
        self::assertArrayHasKey('totalHits', $result);
    }

    private function serviceWithTenant(): CatalogSearchService
    {
        $tenant = new Tenant('demo', 'Demo Tenant');
        $user = new User($tenant, 'admin@demo.localhost', '', ['ROLE_USER']);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));

        // No DoctrineTenantRepository needed: the User implements TenantAware,
        // so CurrentTenantProvider short-circuits to $user->getTenant().
        $tenantProvider = new CurrentTenantProvider(
            $tokenStorage,
            $this->createStub(\App\Shared\Infrastructure\Doctrine\Repository\DoctrineTenantRepository::class),
        );

        // url=null → create() throws LogicException, marking "reached Meili".
        $factory = new MeilisearchClientFactory(null, null);

        return new CatalogSearchService($factory, $tenantProvider);
    }
}
