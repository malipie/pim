<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\ApiPlatform\State;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Channel\Domain\Entity\ChannelObjectTypeMapping;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-06 (#418) — narrow `GET /channel_object_type_mappings` to a single
 * Channel via `?channel={uuid}` query parameter. The mapping editor in
 * `/settings/channels/:id` always lists per-channel, so the FE always
 * sends this filter. The extension is a no-op for missing parameter.
 *
 * Tenant scoping comes via the parent `Channel` (TenantFilter applies on
 * Channel join). The extension does NOT add tenant clauses.
 */
final readonly class ChannelObjectTypeMappingChannelFilterExtension implements QueryCollectionExtensionInterface
{
    /**
     * @param class-string $resourceClass
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (ChannelObjectTypeMapping::class !== $resourceClass) {
            return;
        }

        $filters = $context['filters'] ?? [];
        if (!\is_array($filters)) {
            return;
        }
        $channelId = $filters['channel'] ?? null;
        if (!\is_string($channelId) || '' === $channelId) {
            return;
        }
        if (!Uuid::isValid($channelId)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0] ?? null;
        if (null === $alias) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('channel');
        $queryBuilder
            ->andWhere(\sprintf('IDENTITY(%s.channel) = :%s', $alias, $parameter))
            ->setParameter($parameter, Uuid::fromString($channelId)->toRfc4122());
    }
}
