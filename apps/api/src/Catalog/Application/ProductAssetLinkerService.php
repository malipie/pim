<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Contracts\Service\ProductAssetLinker;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Implementation of {@see ProductAssetLinker} (#440).
 *
 * The link table `product_assets` is small (asset_id, product_id,
 * position, created_at) and never carries domain logic. We talk to it
 * through the DBAL Connection rather than wrapping it in a Doctrine
 * entity — fewer moving parts, no risk of orphaned identity-map state
 * inside a long-running FrankenPHP worker.
 *
 * `position` defaults to `MAX(position) + 1` for the product so the
 * picker / drag-drop reorder follow-up has a sortable column to play
 * with from day one.
 */
final readonly class ProductAssetLinkerService implements ProductAssetLinker
{
    public function __construct(private Connection $connection)
    {
    }

    public function linkAssetsToProduct(Uuid $productId, array $assetIds): void
    {
        if ([] === $assetIds) {
            return;
        }

        $rawPosition = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(position), 0) FROM product_assets WHERE product_id = :productId',
            ['productId' => $productId->toRfc4122()],
        );
        $nextPosition = is_numeric($rawPosition) ? (int) $rawPosition : 0;

        foreach ($assetIds as $assetId) {
            // tenant-safe: junction table inherits tenant via FK chain
            // — both asset_id and product_id are tenant-scoped UUIDs
            // resolved by callers through TenantFilter-aware repositories.
            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO product_assets (asset_id, product_id, position, created_at)
                    VALUES (:assetId, :productId, :position, NOW())
                    ON CONFLICT (asset_id, product_id) DO NOTHING
                    SQL,
                [
                    'assetId' => $assetId->toRfc4122(),
                    'productId' => $productId->toRfc4122(),
                    'position' => ++$nextPosition,
                ],
            );
        }
    }

    public function unlinkAssetFromProduct(Uuid $productId, Uuid $assetId): void
    {
        // tenant-safe: junction table inherits tenant via FK chain
        // (product_id + asset_id are tenant-scoped UUIDs).
        $this->connection->executeStatement(
            'DELETE FROM product_assets WHERE product_id = :productId AND asset_id = :assetId',
            [
                'productId' => $productId->toRfc4122(),
                'assetId' => $assetId->toRfc4122(),
            ],
        );
    }

    public function findAssetIdsForProduct(Uuid $productId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT asset_id FROM product_assets
                WHERE product_id = :productId
                ORDER BY COALESCE(position, 2147483647), created_at
                SQL,
            ['productId' => $productId->toRfc4122()],
        );

        $ids = [];
        foreach ($rows as $raw) {
            if (\is_string($raw)) {
                $ids[] = Uuid::fromString($raw);
            }
        }

        return $ids;
    }
}
