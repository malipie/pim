<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?completeness[gt]=80` / `?completeness[gte]=50&completeness[lt]=100`
 * — numeric range query against the per-row `completeness.pct` JSONB
 * key (admin-side completeness percentage stamped by
 * `CompletenessRecalculator` from #38).
 *
 * Channel-aware completeness (`?completeness[gt]=80&channel=ecommerce_pl`)
 * is parked until ChannelObjectTypeMapping reads land in epic 0.6 — the
 * payload schema for completeness already reserves a `channels` map for
 * that, but the filter would need ChannelRepository injection to resolve
 * the channel code.
 */
final class CompletenessFilter implements FilterInterface
{
    private const string PARAMETER = 'completeness';

    /**
     * @var array<string, string>
     */
    private const array OPERATORS = [
        'eq' => '=',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
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

        foreach ($values as $op => $threshold) {
            $sqlOperator = self::OPERATORS[$op] ?? null;
            if (null === $sqlOperator) {
                continue;
            }
            if (!is_numeric($threshold)) {
                continue;
            }

            $parameter = $queryNameGenerator->generateParameterName('completeness_'.$op);
            $queryBuilder
                ->andWhere(\sprintf(
                    "JSONB_GET_NUMERIC(%s.completeness, 'pct') %s :%s",
                    $alias,
                    $sqlOperator,
                    $parameter,
                ))
                ->setParameter($parameter, (float) $threshold);
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
            self::PARAMETER.'[eq|gt|gte|lt|lte]' => [
                'property' => 'completeness',
                'type' => 'number',
                'required' => false,
                'description' => 'Filter by per-row completeness percentage (`completeness.pct` JSONB key).',
                'strategy' => 'numeric_range',
                'is_collection' => true,
            ],
        ];
    }
}
