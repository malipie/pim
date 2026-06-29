<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?binding={id}` on `/api/sync_runs` — scopes the history to one binding's
 * runs (APIC-P4-01). Tenant scoping still comes from the upstream Doctrine
 * TenantFilter; this only adds the binding equality predicate.
 */
final class SyncRunBindingFilter implements FilterInterface
{
    private const string PARAMETER = 'binding';

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

        $parameter = $queryNameGenerator->generateParameterName('bindingId');
        $queryBuilder
            ->andWhere(\sprintf('%s.binding = :%s', $alias, $parameter))
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
                'property' => 'binding',
                'type' => 'string',
                'required' => false,
                'description' => 'Exact match on the SyncBinding UUID (tenant-scoped).',
                'strategy' => 'exact',
            ],
        ];
    }
}
