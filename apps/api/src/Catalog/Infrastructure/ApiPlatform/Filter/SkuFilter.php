<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?sku=ABC` — case-insensitive substring search on `CatalogObject.code`.
 *
 * Catalogue search bar / integration sync flows often look up a row by
 * SKU (or its prefix). The filter wraps a single ILIKE query — the
 * underlying `objects.code` already has `(tenant_id, kind, code)` UNIQUE
 * + composite index, so prefix matches still hit the BTree.
 *
 * Empty / non-string values are skipped — the filter is a no-op when
 * the param is absent. Exact match is achievable by passing the full
 * code; the API does not branch on a `strategy` flag in MVP.
 */
final class SkuFilter implements FilterInterface
{
    private const string PARAMETER = 'sku';

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

        $parameter = $queryNameGenerator->generateParameterName('sku');
        $queryBuilder
            ->andWhere(\sprintf('LOWER(%s.code) LIKE LOWER(:%s)', $alias, $parameter))
            ->setParameter($parameter, '%'.$value.'%');
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
                'description' => 'Case-insensitive substring search on the catalog object code (SKU).',
                'strategy' => 'partial',
            ],
        ];
    }
}
