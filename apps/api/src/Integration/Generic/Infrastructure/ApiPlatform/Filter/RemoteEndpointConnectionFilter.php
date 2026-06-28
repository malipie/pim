<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?connection={id}` on `/api/remote_endpoints` — scopes the descriptor list to
 * one connection (APIC-P2-05). Tenant scoping still comes from the upstream
 * Doctrine TenantFilter; this only adds the connection equality predicate.
 */
final class RemoteEndpointConnectionFilter implements FilterInterface
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

        $parameter = $queryNameGenerator->generateParameterName('connectionId');
        $queryBuilder
            ->andWhere(\sprintf('%s.connection = :%s', $alias, $parameter))
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
                'description' => 'Exact match on the parent Connection UUID (tenant-scoped).',
                'strategy' => 'exact',
            ],
        ];
    }
}
