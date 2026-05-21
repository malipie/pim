<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Lock\AttributeLockReader;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-18 (#549) — per-attribute lock endpoints.
 *
 * `GET /api/products/{id}/locks` returns the current list of locked
 * attribute codes. `PATCH /api/products/{id}/locks` replaces the full
 * list (idempotent, no merge — the FE toggle ships the final intent).
 *
 * Storage lives under `attributes_indexed['__locks']` so this controller
 * stays migration-free. Bulk handlers consult `AttributeLockReader`
 * before mutating any locked slot.
 */
final class AttributeLocksController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly AttributeLockReader $lockReader,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/products/{id}/locks', name: 'pim_products_locks_show', requirements: ['id' => self::UUID_REGEX], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function show(string $id): JsonResponse
    {
        $product = $this->loadProduct($id);

        return new JsonResponse(['locked_attributes' => $this->lockReader->getLockedCodes($product)]);
    }

    #[Route('/api/products/{id}/locks', name: 'pim_products_locks_replace', requirements: ['id' => self::UUID_REGEX], methods: ['PATCH', 'PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function replace(string $id, Request $request): JsonResponse
    {
        $product = $this->loadProduct($id);
        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $raw = $body['locked_attributes'] ?? null;
        if (!\is_array($raw)) {
            throw new BadRequestHttpException('locked_attributes must be an array.');
        }
        $codes = [];
        foreach ($raw as $code) {
            if (\is_string($code) && '' !== $code) {
                $codes[] = $code;
            }
        }

        $indexed = $product->getAttributesIndexed();
        if ([] === $codes) {
            unset($indexed[AttributeLockReader::LOCKS_META_KEY]);
        } else {
            $indexed[AttributeLockReader::LOCKS_META_KEY] = array_values(array_unique($codes));
        }
        $product->updateAttributeIndex($indexed);
        $this->em->flush();

        return new JsonResponse(['locked_attributes' => $this->lockReader->getLockedCodes($product)]);
    }

    private function loadProduct(string $id): CatalogObject
    {
        $product = $this->catalogObjects->findById(Uuid::fromString($id));
        if (!$product instanceof CatalogObject) {
            throw new NotFoundHttpException(\sprintf('Product %s not found.', $id));
        }

        return $product;
    }
}
