<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?profile={id}` on `/api/webhook_deliveries` — scopes the delivery history to
 * one profile (APIC-P4-06; the producer hub's Webhooks tab). Tenant scoping
 * still comes from the upstream Doctrine TenantFilter; this only adds the
 * profile equality predicate.
 */
final class WebhookDeliveryProfileFilter implements FilterInterface
{
    private const string PARAMETER = 'profile';

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

        $parameter = $queryNameGenerator->generateParameterName('profileId');
        $queryBuilder
            ->andWhere(\sprintf('%s.profileId = :%s', $alias, $parameter))
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
                'property' => 'profileId',
                'type' => 'string',
                'required' => false,
                'description' => 'Exact match on the ApiProfile UUID (tenant-scoped).',
                'strategy' => 'exact',
            ],
        ];
    }
}
