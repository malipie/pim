<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * #1012 — `?code=samochody` on `/api/object_types` exact-match filter.
 *
 * The universal `ObjectListPage` (ULV-08) resolves the URL slug to an
 * ObjectType UUID by calling `/api/object_types?code={slug}&itemsPerPage=1`.
 * Without this filter ApiPlatform ignores the `code` query param entirely
 * and the collection returns every tenant ObjectType — `members[0]`
 * ended up being alphabetically `product`, so navigating to
 * `/objects/samochody` rendered the Product list. Reproduced post-marathon.
 *
 * Tenant scoping still applies through the upstream `TenantFilter`
 * Doctrine extension; this filter only adds the code equality predicate.
 */
final class ObjectTypeCodeFilter implements FilterInterface
{
    private const string PARAMETER = 'code';

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $filters = $context['filters'] ?? [];
        if (!\is_array($filters)) {
            return;
        }

        $value = $filters[self::PARAMETER] ?? null;
        if (!\is_string($value) || '' === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $alias) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('objectTypeCode');
        $queryBuilder
            ->andWhere(\sprintf('%s.code = :%s', $alias, $parameter))
            ->setParameter($parameter, $value);
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<string, array{property?: string, type?: string, required?: bool, description?: string, strategy?: string, is_collection?: bool}>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER => [
                'property' => 'code',
                'type' => 'string',
                'required' => false,
                'description' => 'Exact match on ObjectType.code (tenant-scoped). Used by the universal ObjectListPage to resolve URL slug → ObjectType UUID.',
                'strategy' => 'exact',
            ],
        ];
    }
}
