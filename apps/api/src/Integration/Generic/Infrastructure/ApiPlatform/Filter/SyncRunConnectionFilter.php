<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?connection={id}` on `/api/sync_runs` — scopes the history to every run of a
 * connection's bindings (APIC-P4-01; the connection-detail History tab). Joins
 * SyncRun → binding → connection. Tenant scoping still comes from the upstream
 * Doctrine TenantFilter.
 */
final class SyncRunConnectionFilter implements FilterInterface
{
    private const string PARAMETER = 'connection';

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

        $bindingAlias = $queryNameGenerator->generateJoinAlias('binding');
        $parameter = $queryNameGenerator->generateParameterName('connectionId');
        $queryBuilder
            ->join(\sprintf('%s.binding', $alias), $bindingAlias)
            ->andWhere(\sprintf('%s.connection = :%s', $bindingAlias, $parameter))
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
                'property' => 'connection',
                'type' => 'string',
                'required' => false,
                'description' => 'Exact match on the parent Connection UUID via the binding join (tenant-scoped).',
                'strategy' => 'exact',
            ],
        ];
    }
}
