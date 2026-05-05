<?php

declare(strict_types=1);

namespace App\Asset\Presentation\Controller;

use App\Asset\Application\AssetMetadataUpdater;
use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * `PATCH /api/assets/{id}` (#438) — edits Asset-side metadata that
 * lives on the storage row: `code` (re-slug, unique per tenant) and
 * `tags` (chip multi-select).
 *
 * Localised `alt` text is owned by the linked CatalogObject's
 * `attributes_indexed` and edited via the catalog write path — Deptrac
 * (ADR-0013) blocks Asset_Internals from reaching into
 * Catalog_Internals to dispatch the update directly. A follow-up adds
 * a `Catalog_Contracts` writer for cross-context use; until then the
 * frontend renders alt as read-only on the show view.
 */
final readonly class PatchAssetController
{
    public function __construct(
        private AssetRepositoryInterface $assets,
        private AssetMetadataUpdater $metadataUpdater,
        private AuthorizationCheckerInterface $authorisation,
    ) {
    }

    #[Route(path: '/api/assets/{id}', name: 'pim_assets_patch', methods: ['PATCH'], format: 'json')]
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $assetId = Uuid::fromString($id);
        $asset = $this->assets->findById($assetId);
        if (null === $asset) {
            throw new NotFoundHttpException(\sprintf('Asset "%s" was not found.', $id));
        }

        if (!$this->authorisation->isGranted('UPDATE', $asset)) {
            throw new AccessDeniedHttpException();
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }

        $code = \array_key_exists('code', $payload) ? $this->stringField($payload['code'], 'code') : null;
        $tags = \array_key_exists('tags', $payload) ? $this->tagsField($payload['tags']) : null;

        $this->metadataUpdater->update($asset, code: $code, tags: $tags);

        $refreshed = $this->assets->findById($assetId);
        \assert($refreshed instanceof Asset);

        return new JsonResponse($this->present($refreshed), Response::HTTP_OK);
    }

    private function stringField(mixed $value, string $name): string
    {
        if (!\is_string($value) || '' === trim($value)) {
            throw new BadRequestHttpException(\sprintf('Field "%s" must be a non-empty string.', $name));
        }

        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    private function tagsField(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new BadRequestHttpException('Field "tags" must be a JSON array.');
        }
        $tags = [];
        foreach ($value as $entry) {
            if (\is_string($entry)) {
                $tags[] = trim($entry);
            }
        }

        return $tags;
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
        ];
    }
}
