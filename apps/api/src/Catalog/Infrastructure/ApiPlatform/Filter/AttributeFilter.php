<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

use const JSON_THROW_ON_ERROR;

/**
 * `?attribute[brand]=Nike&attribute[color]=red` — JSONB equality match
 * on the denormalised `attributes_indexed` cache.
 *
 * Each `attribute[<code>]=<value>` pair becomes a Postgres `jsonb @>`
 * containment check that the partial GIN index on
 * `objects.attributes_indexed` answers in sub-50ms even at 50k rows
 * (#34 benchmark target). Multiple keys AND together, matching the
 * UI mental model "filter where brand=Nike AND color=red".
 *
 * Localised values (`{pl: 'Czerwony', en: 'Red'}`) are not magic-cast
 * here; the caller passes the canonical attribute representation. A
 * scope/locale-aware variant lands when the admin UI search box adds
 * the language dropdown (epic 0.6).
 */
final class AttributeFilter implements FilterInterface
{
    private const string PARAMETER = 'attribute';

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

        foreach ($values as $code => $value) {
            if (!\is_string($code) || '' === $code) {
                continue;
            }
            if (!\is_scalar($value)) {
                continue;
            }

            $parameter = $queryNameGenerator->generateParameterName('attr_'.$code);
            $payload = json_encode([$code => $value], JSON_THROW_ON_ERROR);
            // JSON_CONTAINS DQL function is not portable; fall back to a
            // raw expression resolved via Postgres `@>` JSONB containment.
            $queryBuilder
                ->andWhere(\sprintf('JSONB_CONTAINS(%s.attributesIndexed, :%s) = true', $alias, $parameter))
                ->setParameter($parameter, $payload);
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
            self::PARAMETER.'[:code]' => [
                'property' => 'attributesIndexed',
                'type' => 'string',
                'required' => false,
                'description' => 'JSONB containment match on the denormalised attributes_indexed cache. Multiple pairs AND together.',
                'strategy' => 'jsonb_contains',
                'is_collection' => true,
            ],
        ];
    }
}
