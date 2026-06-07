<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\MoveChannelCategoryNode;

use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * CHC-09 (#1302) — re-parent a channel navigation node and rewrite the ltree
 * path of the node + every descendant in one transaction.
 *
 * Mirrors {@see \App\Catalog\Application\Service\MoveCategoryService} (the
 * master-category move) but scoped to a single channel and tenant. Placements
 * (CHC-02) and node mappings (CHC-06) reference nodes by id, never by path, so
 * a move has no blast radius beyond the tree itself — hence no confirm gate.
 */
#[AsMessageHandler]
final readonly class MoveChannelCategoryNodeHandler
{
    public function __construct(
        private ChannelCategoryNodeRepositoryInterface $nodes,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(MoveChannelCategoryNodeCommand $command): void
    {
        $node = $this->nodes->findById($command->nodeId);
        if (null === $node || !$node->getChannel()->getId()->equals($command->channelId)) {
            throw new NotFoundHttpException(\sprintf(
                'Navigation node "%s" was not found in this channel.',
                $command->nodeId->toRfc4122(),
            ));
        }

        $newParent = $this->nodes->findById($command->newParentId);
        if (null === $newParent || !$newParent->getChannel()->getId()->equals($command->channelId)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Target parent "%s" does not belong to this channel.',
                $command->newParentId->toRfc4122(),
            ));
        }
        if ($newParent->getId()->equals($node->getId())) {
            throw new UnprocessableEntityHttpException('A node cannot be its own parent.');
        }

        $oldPath = $node->getPath();
        $parentPath = $newParent->getPath();
        if (null === $oldPath || '' === $oldPath || null === $parentPath || '' === $parentPath) {
            throw new UnprocessableEntityHttpException('Node or target parent has no ltree path.');
        }

        // Cycle guard: the target parent must not be the node itself or any of
        // its descendants. ltree descendant-or-equal = path prefix on dot
        // boundaries.
        if ($parentPath === $oldPath || str_starts_with($parentPath, $oldPath.'.')) {
            throw new UnprocessableEntityHttpException('Cannot move a node into its own subtree.');
        }

        $newPath = $parentPath.'.'.$node->ltreeLabel();
        if ($newPath === $oldPath) {
            return; // already under this parent — no-op
        }

        $tenant = $node->getTenant();
        \assert(null !== $tenant);

        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            // tenant-safe: WHERE id = :id — id is a tenant-scoped UUID resolved
            // through ChannelCategoryNodeRepository (TenantFilter applied), so it
            // can only match a row in the current tenant.
            $connection->executeStatement(
                'UPDATE channel_category_nodes SET path = CAST(:newPath AS ltree), parent_id = :parentId WHERE id = :id',
                [
                    'newPath' => $newPath,
                    'parentId' => $newParent->getId()->toRfc4122(),
                    'id' => $node->getId()->toRfc4122(),
                ],
                ['parentId' => Types::STRING, 'id' => Types::STRING],
            );

            // tenant-safe: explicit tenant_id + channel_id filter — the ltree
            // subtree match (`path <@ :oldPath`) alone would touch rows of other
            // tenants/channels sharing the namespace.
            $connection->executeStatement(
                'UPDATE channel_category_nodes'
                .' SET path = CAST(:newPath AS ltree) || subpath(path, :oldDepth)'
                .' WHERE tenant_id = :tenantId AND channel_id = :channelId'
                .'   AND path <@ CAST(:oldPath AS ltree) AND id <> :id',
                [
                    'newPath' => $newPath,
                    'oldDepth' => $this->ltreeDepth($oldPath),
                    'tenantId' => $tenant->getId()->toRfc4122(),
                    'channelId' => $node->getChannelId()->toRfc4122(),
                    'oldPath' => $oldPath,
                    'id' => $node->getId()->toRfc4122(),
                ],
                ['oldDepth' => Types::INTEGER, 'tenantId' => Types::STRING, 'channelId' => Types::STRING, 'id' => Types::STRING],
            );

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        // Evict so the next read re-hydrates the rewritten paths.
        $this->em->clear();
    }

    private function ltreeDepth(string $path): int
    {
        return '' === $path ? 0 : substr_count($path, '.') + 1;
    }
}
