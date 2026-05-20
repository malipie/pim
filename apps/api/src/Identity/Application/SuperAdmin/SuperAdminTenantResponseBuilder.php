<?php

declare(strict_types=1);

namespace App\Identity\Application\SuperAdmin;

use App\Shared\Domain\Tenant;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P5-019 (#709) — projection helper for the Super Admin operator
 * panel's tenant listing.
 *
 * Privacy boundary: this builder NEVER touches per-tenant domain rows
 * (products, attributes, values) — only metadata: tenant identity,
 * plan, audit timestamps, and aggregate counters (active user count).
 * PRD §11 forbids Super Admin from reading domain data; the audit log
 * tag `cross_tenant_access=true` plus this projection's narrow shape
 * make the boundary mechanical.
 */
final readonly class SuperAdminTenantResponseBuilder
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param iterable<Tenant> $rows
     *
     * @return list<array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     domain: ?string,
     *     plan: string,
     *     primary_locale: string,
     *     enabled_locales: list<string>,
     *     active_users: int,
     *     created_at: string
     * }>
     */
    public function buildList(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $tenant) {
            $out[] = $this->buildOne($tenant);
        }

        return $out;
    }

    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     domain: ?string,
     *     plan: string,
     *     primary_locale: string,
     *     enabled_locales: list<string>,
     *     active_users: int,
     *     created_at: string
     * }
     */
    public function buildOne(Tenant $tenant): array
    {
        return [
            'id' => $tenant->getId()->toRfc4122(),
            'code' => $tenant->getCode(),
            'name' => $tenant->getName(),
            'domain' => $tenant->getDomain(),
            'plan' => $tenant->getPlan(),
            'primary_locale' => $tenant->getPrimaryLocale(),
            'enabled_locales' => $tenant->getEnabledLocales(),
            'active_users' => $this->countActiveUsers($tenant->getId()),
            'created_at' => $tenant->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Counter via raw DBAL — cheaper than booting the full User aggregate
     * just to count rows. Filters by `status = 'active'` so suspended
     * accounts don't inflate the headline number.
     */
    private function countActiveUsers(Uuid $tenantId): int
    {
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND status = 'active'",
            ['tenant_id' => $tenantId->toRfc4122()],
        );

        return \is_numeric($count) ? (int) $count : 0;
    }
}
