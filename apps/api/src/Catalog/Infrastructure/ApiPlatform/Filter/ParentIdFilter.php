<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * `?parent_id=<uuid>` — exact match on `CatalogObject.parent_id`.
 *
 * Drives the `<VariantsListCard />` (VIEW-07 #420) which lists variants
 * of a master product (`/api/products?parent_id={master_id}`). Without
 * this filter AP4 silently ignored the param and returned the first
 * cursor page — UI naïvely rendered DEMO-099/DEMO-098/... as „variants
 * of DEMO-100", which is a lie. Issue #429.
 *
 * Empty / non-UUID values are skipped — the filter is a no-op when the
 * param is absent or malformed (returning an unfiltered list is a less
 * surprising fallback than a 500). Tenant scoping still applies via
 * `TenantFilter` so cross-tenant lookups are impossible.
 */
final class ParentIdFilter implements FilterInterface
{
    private const string PARAMETER = 'parent_id';

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

        $parameter = $queryNameGenerator->generateParameterName('parentId');
        $queryBuilder
            ->andWhere(\sprintf('IDENTITY(%s.parent) = :%s', $alias, $parameter))
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
                'property' => 'parent',
                'type' => 'string',
                'required' => false,
                'description' => 'Exact match on parent_id — returns rows whose parent points at the given UUID (variants of a master product, children of a category).',
                'strategy' => 'exact',
            ],
        ];
    }
}
