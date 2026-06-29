<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?run={id}` on `/api/sync_run_logs` — drills into one run's per-record log
 * (APIC-P4-01). Tenant scoping still comes from the upstream Doctrine
 * TenantFilter; this only adds the run equality predicate.
 */
final class SyncRunLogRunFilter implements FilterInterface
{
    private const string PARAMETER = 'run';

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

        $parameter = $queryNameGenerator->generateParameterName('runId');
        $queryBuilder
            ->andWhere(\sprintf('%s.run = :%s', $alias, $parameter))
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
                'property' => 'run',
                'type' => 'string',
                'required' => false,
                'description' => 'Exact match on the parent SyncRun UUID (tenant-scoped).',
                'strategy' => 'exact',
            ],
        ];
    }
}
