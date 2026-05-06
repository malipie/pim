<?php

declare(strict_types=1);

namespace App\Asset\Presentation\Controller;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Catalog\Contracts\Service\ProductAssetLinker;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Multimedia tab endpoints (#440):
 *   - `GET    /api/products/{id}/assets`             — list linked assets
 *   - `POST   /api/products/{id}/assets`             — link assets (m2m)
 *   - `DELETE /api/products/{id}/assets/{assetId}`   — unlink
 *
 * The product detail view calls these to render the multimedia grid
 * and to attach picks from the library or fresh uploads.
 *
 * The link table only stores `(asset_id, product_id, position)` — the
 * GET endpoint resolves each id back into the same payload shape the
 * `/api/assets` listing emits so the frontend can reuse the existing
 * tile component.
 */
final readonly class ProductAssetsController
{
    public function __construct(
        private AssetRepositoryInterface $assets,
        private ProductAssetLinker $linker,
    ) {
    }

    #[Route(path: '/api/products/{id}/assets', name: 'pim_products_assets_list', methods: ['GET'], format: 'json')]
    public function list(string $id): JsonResponse
    {
        $productId = $this->parseUuid($id, 'product');
        $assetIds = $this->linker->findAssetIdsForProduct($productId);

        $payload = [];
        foreach ($assetIds as $assetId) {
            $asset = $this->assets->findById($assetId);
            if ($asset instanceof Asset) {
                $payload[] = $this->present($asset);
            }
        }

        return new JsonResponse(['member' => $payload, 'totalItems' => \count($payload)]);
    }

    #[Route(path: '/api/products/{id}/assets', name: 'pim_products_assets_link', methods: ['POST'], format: 'json')]
    public function link(string $id, Request $request): JsonResponse
    {
        $productId = $this->parseUuid($id, 'product');

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !\is_array($payload['assetIds'] ?? null)) {
            throw new BadRequestHttpException('Body must be { "assetIds": [string, ...] }.');
        }

        $assetIds = [];
        foreach ($payload['assetIds'] as $raw) {
            if (!\is_string($raw)) {
                continue;
            }
            try {
                $candidate = Uuid::fromString($raw);
            } catch (InvalidArgumentException) {
                continue;
            }
            // The id may come from the /api/assets listing (CatalogObject id)
            // or directly as Asset id. Resolve to the canonical Asset.id so
            // the link table never carries a CatalogObject id by accident.
            $asset = $this->assets->findById($candidate) ?? $this->assets->findByObjectId($candidate);
            if ($asset instanceof Asset) {
                $assetIds[] = $asset->getId();
            }
        }

        if ([] !== $assetIds) {
            $this->linker->linkAssetsToProduct($productId, $assetIds);
        }

        return new JsonResponse(['linkedCount' => \count($assetIds)], Response::HTTP_OK);
    }

    #[Route(
        path: '/api/products/{id}/assets/{assetId}',
        name: 'pim_products_assets_unlink',
        methods: ['DELETE'],
        format: 'json',
    )]
    public function unlink(string $id, string $assetId): JsonResponse
    {
        $productId = $this->parseUuid($id, 'product');
        $rawAssetId = $this->parseUuid($assetId, 'asset');

        // Same Asset-id-or-CatalogObject-id tolerance as `link()`.
        $asset = $this->assets->findById($rawAssetId) ?? $this->assets->findByObjectId($rawAssetId);
        if (null === $asset) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $this->linker->unlinkAssetFromProduct($productId, $asset->getId());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function parseUuid(string $raw, string $fieldLabel): Uuid
    {
        try {
            return Uuid::fromString($raw);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException(\sprintf('"%s" is not a valid %s id.', $raw, $fieldLabel), $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Asset $asset): array
    {
        return [
            'id' => $asset->getId()->toRfc4122(),
            'code' => $asset->getCode(),
            'originalFilename' => $asset->getOriginalFilename(),
            'mimeType' => $asset->getMimeType(),
            'size' => $asset->getSize(),
            'width' => $asset->getWidth(),
            'height' => $asset->getHeight(),
            'pageCount' => $asset->getPageCount(),
            'tags' => $asset->getTags(),
            'thumbnailsStatus' => $asset->getThumbnailsStatus()->value,
            'folderCode' => $asset->getFolderCode(),
            'previewUrl' => \sprintf('/api/assets/%s/preview', $asset->getId()->toRfc4122()),
        ];
    }
}
