<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\AssociationType;
use App\Catalog\Infrastructure\Doctrine\Repository\AssociationTypeRepository;
use App\Identity\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Idempotent per-tenant seeder for the four default AssociationType rows:
 * `cross_sell`, `up_sell`, `related`, `accessories`.
 *
 * Mirrors {@see BuiltInObjectTypeSeeder} — runtime counterpart of the
 * inline INSERT in the migration. Existing tenants get their seed in the
 * migration; future tenants (admin onboarding flow) get it from this
 * service. Idempotent: re-runs are no-ops once the four rows exist.
 *
 * Unlike ObjectType, AssociationType has no `is_built_in` flag — every
 * row is tenant-defined. The four codes here are the seed every tenant
 * starts with; renaming or deleting them is fair game in the admin UI.
 */
final readonly class BuiltInAssociationTypeSeeder
{
    /**
     * @var array<string, array{int, array<string, string>}>
     */
    private const array DEFINITIONS = [
        'cross_sell' => [10, ['pl' => 'Sprzedaż krzyżowa', 'en' => 'Cross-sell']],
        'up_sell' => [20, ['pl' => 'Sprzedaż dodatkowa', 'en' => 'Up-sell']],
        'related' => [30, ['pl' => 'Powiązane', 'en' => 'Related']],
        'accessories' => [40, ['pl' => 'Akcesoria', 'en' => 'Accessories']],
    ];

    public function __construct(
        private AssociationTypeRepository $repository,
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * Seed missing default AssociationTypes for the given tenant. Returns
     * the number of rows actually created (0 = idempotent no-op).
     */
    public function seed(Tenant $tenant): int
    {
        $previous = $this->tenantContext->get();
        $this->tenantContext->set($tenant);

        try {
            $created = 0;
            foreach (self::DEFINITIONS as $code => [$position, $label]) {
                if (null !== $this->repository->findByCode($code, $tenant)) {
                    continue;
                }

                $type = new AssociationType($code, $label, $position);
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
