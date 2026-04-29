<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\ORM\QueryBuilder;

/**
 * Narrow GET-collection queries on the per-kind sugar paths
 * (`/api/products`, `/api/categories`, `/api/assets`) to the matching
 * `ObjectKind`.
 *
 * The sugar paths share one entity ({@see CatalogObject}) and one table
 * (`objects`); each operation declares its kind via
 * `extraProperties.kind`. Without this extension the three list endpoints
 * would return the union of all rows. The extension is a no-op for any
 * other resource class or operation lacking the marker.
 *
 * Tenant scoping comes from the existing `TenantFilter` Doctrine SQL
 * filter — no work to repeat here.
 */
final readonly class KindCollectionExtension implements QueryCollectionExtensionInterface
{
    /**
     * @param class-string $resourceClass
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (CatalogObject::class !== $resourceClass) {
            return;
        }

        $kind = $this->kindFor($operation);
        if (null === $kind) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $alias) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('kind');
        $queryBuilder
            ->andWhere(\sprintf('%s.kind = :%s', $alias, $parameter))
            ->setParameter($parameter, $kind);
    }

    private function kindFor(?Operation $operation): ?ObjectKind
    {
        if (null === $operation) {
            return null;
        }

        $value = $operation->getExtraProperties()['kind'] ?? null;
        if (!\is_string($value)) {
            return null;
        }

        return ObjectKind::tryFrom($value);
    }
}
