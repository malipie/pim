<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * ULV-03 (#984) — `?objectType=<uuid>` scopes `/api/objects` to instances
 * of a specific ObjectType.
 *
 * The poly-kind `/api/objects` collection (#977/#981) returns every kind
 * — products, categories, assets, brands, custom — narrowed only by the
 * tenant filter and the read voter. The universal list view (`ObjectListView`,
 * ULV-06) targets a specific ObjectType per page, so the read endpoint
 * needs a per-ObjectType filter that does not require kind discrimination
 * (custom ObjectTypes share `kind=custom` so kind alone is insufficient).
 *
 * Empty / non-UUID values are skipped — the filter is a no-op so a stale
 * FE param does not blow up the entire collection. Tenant scoping still
 * applies via {@see \App\Shared\Infrastructure\Doctrine\Filter\TenantFilter}
 * so cross-tenant lookups are impossible.
 */
final class ObjectTypeFilter implements FilterInterface
{
    private const string PARAMETER = 'objectType';

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
        if (!\is_string($value) || !Uuid::isValid($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $alias) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('objectTypeId');
        $queryBuilder
            ->andWhere(\sprintf('IDENTITY(%s.objectType) = :%s', $alias, $parameter))
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
                'property' => 'objectType',
                'type' => 'string',
                'required' => false,
                'description' => 'Scope the collection to a specific ObjectType by UUID. Drives the universal ObjectListView so a single endpoint serves every ObjectType (built-in product/category/asset/brand or custom).',
                'strategy' => 'exact',
            ],
        ];
    }
}
