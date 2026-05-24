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
 * Idempotent per-tenant seeder for the four built-in ObjectTypes:
 * `product`, `category`, `asset`, `brand`. Each seeded row carries
 * `is_built_in=true` so {@see ObjectTypeService::delete} blocks deletion,
 * plus `code_immutable=true` + `deletable=false` from UI-08.2.
 *
 * The seeder is the runtime counterpart of the inline INSERTs in
 * migrations `Version20260428222056` (product/category/asset) and
 * `Version20260501110000` (brand). The migrations handle existing
 * tenants once; this service handles future tenants and fixtures —
 * `pim:db:reset --with-fixtures` purges the database, so without a
 * runtime seeder the built-in rows would vanish on every reset.
 *
 * Idempotent: re-runs are no-ops once the four rows exist.
 */
final readonly class BuiltInObjectTypeSeeder
{
    /**
     * @var array<string, array{ObjectKind, array<string, string>, string, string}>
     */
    private const array DEFINITIONS = [
        'product' => [ObjectKind::Product, ['pl' => 'Produkt', 'en' => 'Product'], 'Package', '#3B82F6'],
        'category' => [ObjectKind::Category, ['pl' => 'Kategoria', 'en' => 'Category'], 'FolderTree', '#10B981'],
        'asset' => [ObjectKind::Asset, ['pl' => 'Zasób', 'en' => 'Asset'], 'Image', '#8B5CF6'],
        'brand' => [ObjectKind::Brand, ['pl' => 'Marka', 'en' => 'Brand'], 'Tag', '#F59E0B'],
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
            foreach (self::DEFINITIONS as $code => [$kind, $label, $icon, $color]) {
                $existing = $this->repository->findBuiltInByKind($kind, $tenant);
                if (null !== $existing) {
                    continue;
                }

                $type = new ObjectType($code, $kind, $label);
                $type->markBuiltIn();
                $type->lockCode();
                $type->markUndeletable();
                $type->setIcon($icon);
                $type->setColor($color);

                // VIEW-01 (#372): the modeling UI surfaces hierarchical /
                // hasVariants / abstract toggles per ObjectType; the built-in
                // defaults match the historical hard-coded behavior so the
                // badges in the list view remain accurate after the migration.
                if (ObjectKind::Product === $kind) {
                    $type->setHasVariants(true);
                    // VIEW-08 (#427): seed Product as exposed-to-menu so the
                    // default sidebar reproduces the legacy "Produkty" entry.
                    $type->setExposeToMainMenu(true);
                    // ADR-014 / MOD-01 (#893): Product is the only built-in
                    // ObjectType whose instances participate in primary-
                    // category-driven attribute distribution.
                    $type->setCategorizable(true);
                } elseif (ObjectKind::Category === $kind) {
                    $type->setHierarchical(true);
                }

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
