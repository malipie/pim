<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Cursor-pagination range filter pinned to `id`.
 *
 * AP4 vendor `RangeFilter` declines to apply on Uuid columns even when
 * `properties: ['id']` is configured (mapping checks in `isPropertyMapped`
 * pass, but the WHERE clause silently drops at higher level — the
 * cursor walk loops on the same page). This drop-in implementation
 * issues `WHERE id <op> :param` directly so cursor pagination works
 * with Uuid v7 keys (#44 / 0.4.4).
 *
 * Operators mirror RangeFilter's: `lt`, `gt`, `lte`, `gte`. Values are
 * passed as strings — Postgres uuid type accepts Uuid string input
 * and orders lexicographically (Uuid v7 makes that chronologically
 * monotonic for new rows).
 */
final class RangeOnId implements FilterInterface
{
    private const string PARAMETER = 'id';

    /**
     * @var array<string, string>
     */
    private const array OPERATORS = [
        'lt' => '<',
        'gt' => '>',
        'lte' => '<=',
        'gte' => '>=',
    ];

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

        $values = $filters[self::PARAMETER] ?? null;
        if (!\is_array($values) || [] === $values) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $alias) {
            return;
        }

        foreach ($values as $op => $value) {
            $sqlOperator = self::OPERATORS[$op] ?? null;
            if (null === $sqlOperator) {
                continue;
            }
            if (!\is_string($value) || '' === $value) {
                continue;
            }
            // Postgres `uuid` rejects malformed input with SQLSTATE 22P02
            // — bubble up as 500. Validate up-front: standard UUID is
            // 32 hex digits + 4 dashes (8-4-4-4-12).
            if (1 !== preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)) {
                continue;
            }

            $parameter = $queryNameGenerator->generateParameterName('id_'.$op);
            $queryBuilder
                ->andWhere(\sprintf('%s.id %s :%s', $alias, $sqlOperator, $parameter))
                ->setParameter($parameter, $value);
        }
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<string, array{property?: string, type?: string, required?: bool, description?: string, strategy?: string, is_collection?: bool}>
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER.'[lt|gt|lte|gte]' => [
                'property' => 'id',
                'type' => 'string',
                'required' => false,
                'description' => 'Uuid range filter on the cursor pagination key.',
                'strategy' => 'uuid_range',
                'is_collection' => true,
            ],
        ];
    }
}
