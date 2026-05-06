<?php

declare(strict_types=1);

namespace App\Asset\Application;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Patches mutable Asset metadata: code (re-slug), tags, alt text.
 *
 * Alt text lives on the linked CatalogObject (kind=asset) per
 * ADR-009 — admins editing alt run through this service which
 * delegates to the Catalog write path. Tags + code stay on the
 * Asset row itself (storage-side concerns).
 *
 * The localised alt is forwarded as-is to the caller so the HTTP
 * layer can route it through the existing CatalogObjectProcessor
 * (CQRS write bus).
 */
final readonly class AssetMetadataUpdater
{
    public function __construct(
        private EntityManagerInterface $em,
        private AssetRepositoryInterface $assets,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<int, string>|null    $tags
     * @param array<string, string>|null $alt
     */
    public function update(
        Asset $asset,
        ?string $code = null,
        ?array $tags = null,
        ?array $alt = null,
    ): Asset {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new RuntimeException('AssetMetadataUpdater requires an active TenantContext.');
        }

        $changedCode = null;
        $changedTags = null;

        if (null !== $code && $code !== $asset->getCode()) {
            $existing = $this->assets->findByCode($code, $tenant);
            if (null !== $existing && !$existing->getId()->equals($asset->getId())) {
                throw new ConflictHttpException(\sprintf('Asset code "%s" is already in use.', $code));
            }
            $asset->rename($code);
            $changedCode = $code;
        }

        if (null !== $tags) {
            $asset->setTags($tags);
            $changedTags = $asset->getTags();
        }

        if (null !== $changedCode || null !== $changedTags || null !== $alt) {
            $asset->trackMetadataUpdated($changedCode, $alt, $changedTags);
        }

        $this->em->flush();

        return $asset;
    }
}
