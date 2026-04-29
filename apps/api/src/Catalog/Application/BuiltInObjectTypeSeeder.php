<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Idempotent per-tenant seeder for the three built-in ObjectTypes:
 * `product`, `category`, `asset`. Each seeded row carries
 * `is_built_in=true` so {@see ObjectTypeService::delete} blocks deletion.
 *
 * The seeder is the runtime counterpart of the inline INSERT in
 * migration `Version20260428222056`. The migration handles existing
 * tenants once; this service handles future tenants — fixtures + the
 * `tenant.create` flow (when admin onboarding lands) call `seed()`
 * before any user-visible domain row is created.
 *
 * Idempotent: re-runs are no-ops once the three rows exist.
 */
final readonly class BuiltInObjectTypeSeeder
{
    /**
     * @var array<string, array{ObjectKind, array<string, string>}>
     */
    private const array DEFINITIONS = [
        'product' => [ObjectKind::Product, ['pl' => 'Produkt', 'en' => 'Product']],
        'category' => [ObjectKind::Category, ['pl' => 'Kategoria', 'en' => 'Category']],
        'asset' => [ObjectKind::Asset, ['pl' => 'Zasób', 'en' => 'Asset']],
    ];

    public function __construct(
        private ObjectTypeRepositoryInterface $repository,
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * Seed missing built-in ObjectTypes for the given tenant. Returns the
     * number of rows actually created (0 = idempotent no-op).
     */
    public function seed(Tenant $tenant): int
    {
        $previous = $this->tenantContext->get();
        $this->tenantContext->set($tenant);

        try {
            $created = 0;
            foreach (self::DEFINITIONS as $code => [$kind, $label]) {
                $existing = $this->repository->findBuiltInByKind($kind, $tenant);
                if (null !== $existing) {
                    continue;
                }

                $type = new ObjectType($code, $kind, $label);
                $type->markBuiltIn();
                $this->em->persist($type);
                ++$created;
            }

            if ($created > 0) {
                $this->em->flush();
            }

            return $created;
        } finally {
            if (null === $previous) {
                $this->tenantContext->clear();
            } else {
                $this->tenantContext->set($previous);
            }
        }
    }
}
