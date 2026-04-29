<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\ORM\QueryBuilder;

/**
 * Item-query counterpart to {@see KindCollectionExtension}: GET on
 * `/api/products/{id}` for a row with `kind=category` must 404, not
 * leak the category through the products' sugar path.
 */
final readonly class KindItemExtension implements QueryItemExtensionInterface
{
    /**
     * @param class-string         $resourceClass
     * @param array<string, mixed> $identifiers
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
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
