<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * ADR-015 — `?categoryTargetObjectType=<uuid>` scopes `/api/categories` to a
 * single ObjectType's tree.
 *
 * After ADR-015 each categorizable ObjectType owns an independent category
 * tree (`objects.category_target_object_type_id`). The modeling UI picks a
 * tree via the ObjectType selector and lists only that tree's categories;
 * without this filter the collection returns every tenant category across
 * all trees and the FE cannot isolate one tree.
 *
 * Empty / non-UUID values are a no-op so a stale FE param never breaks the
 * collection. Tenant scoping still applies via the TenantFilter.
 */
final class CategoryTreeFilter implements FilterInterface
{
    private const string PARAMETER = 'categoryTargetObjectType';

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

        $parameter = $queryNameGenerator->generateParameterName('categoryTreeOt');
        $queryBuilder
            ->andWhere(\sprintf('IDENTITY(%s.categoryTargetObjectType) = :%s', $alias, $parameter))
            ->setParameter($parameter, $value);
    }

    /**
     * @return array<string, array{property?: string, type?: string, required?: bool, description?: string, strategy?: string, is_collection?: bool}>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER => [
                'property' => 'categoryTargetObjectType',
                'type' => 'string',
                'required' => false,
                'description' => 'ADR-015 — scope categories to one ObjectType tree (UUID).',
            ],
        ];
    }
}
