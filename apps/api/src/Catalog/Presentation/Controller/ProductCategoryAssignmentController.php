<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Identity\Domain\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * PCAT-02 (#475) — manage product↔category assignments.
 *
 *   - `GET    /api/products/{id}/categories`
 *   - `PUT    /api/products/{id}/categories`               atomic replace
 *   - `POST   /api/products/{id}/categories`               idempotent add
 *   - `DELETE /api/products/{id}/categories/{categoryId}`  detach
 *
 * The product must be `kind=product`; every assigned object must be
 * `kind=category` in the same tenant. TenantFilter scopes the
 * repositories so cross-tenant fetches return 404 even before the kind
 * check fires. PUT enforces a 50-row cap (defensive) and requires the
 * primary id to be present in the category list (or `null` when the
 * list is empty). POST with `isPrimary=true` demotes any existing
 * primary in the same transaction. DELETE of the current primary
 * promotes the oldest remaining assignment (`position ASC, created_at
 * ASC`) — or leaves the product without a primary if it was the last
 * row.
 *
 * The semantics are intentionally separate from `PATCH /api/products`
 * so the assignment write is atomic, audited at one boundary, and not
 * tangled with arbitrary attribute edits.
 */
final class ProductCategoryAssignmentController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    /** Defensive cap on assignments per product. Picker UI mirrors this. */
    private const int MAX_ASSIGNMENTS = 50;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly ObjectCategoryRepositoryInterface $assignments,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(
        '/api/products/{id}/categories',
        name: 'pim_products_categories_list',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function list(string $id): JsonResponse
    {
        $product = $this->mustFindProduct($id);

        return new JsonResponse($this->renderList($product));
    }

    #[Route(
        '/api/products/{id}/categories',
        name: 'pim_products_categories_replace',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['PUT'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function replace(string $id, Request $request): JsonResponse
    {
        $product = $this->mustFindProduct($id);
        $payload = $this->decodePayload($request);

        $rawIds = $payload['categoryIds'] ?? null;
        if (!\is_array($rawIds)) {
            throw new UnprocessableEntityHttpException('Field "categoryIds" must be an array.');
        }
        if (\count($rawIds) > self::MAX_ASSIGNMENTS) {
            throw new UnprocessableEntityHttpException(\sprintf('At most %d categories per product (got %d).', self::MAX_ASSIGNMENTS, \count($rawIds)));
        }

        /** @var list<Uuid> $categoryUuids */
        $categoryUuids = [];
        $seen = [];
        foreach ($rawIds as $rawId) {
            if (!\is_string($rawId)) {
                throw new UnprocessableEntityHttpException('Each entry of "categoryIds" must be a UUID string.');
            }
            try {
                $uuid = Uuid::fromString($rawId);
            } catch (InvalidArgumentException $e) {
                throw new UnprocessableEntityHttpException(\sprintf('"%s" is not a valid UUID.', $rawId), $e);
            }
            $key = $uuid->toRfc4122();
            if (isset($seen[$key])) {
                throw new UnprocessableEntityHttpException(\sprintf('Duplicate category id in payload: %s.', $key));
            }
            $seen[$key] = true;
            $categoryUuids[] = $uuid;
        }

        $rawPrimary = $payload['primaryCategoryId'] ?? null;
        $primaryUuid = null;
        if (null !== $rawPrimary) {
            if (!\is_string($rawPrimary)) {
                throw new UnprocessableEntityHttpException('"primaryCategoryId" must be a UUID string or null.');
            }
            try {
                $primaryUuid = Uuid::fromString($rawPrimary);
            } catch (InvalidArgumentException $e) {
                throw new UnprocessableEntityHttpException(\sprintf('"primaryCategoryId" is not a valid UUID: %s.', $rawPrimary), $e);
            }
        }

        if ([] === $categoryUuids && null !== $primaryUuid) {
            throw new UnprocessableEntityHttpException('"primaryCategoryId" must be null when "categoryIds" is empty.');
        }
        if (null !== $primaryUuid && !$this->containsUuid($categoryUuids, $primaryUuid)) {
            throw new UnprocessableEntityHttpException('"primaryCategoryId" must appear in "categoryIds".');
        }

        // Validate that every id resolves to a tenant-scoped category before
        // touching the junction. Protects against half-applied replaces if a
        // single bad id were buried mid-list.
        foreach ($categoryUuids as $uuid) {
            $this->mustFindCategory($uuid->toRfc4122());
        }

        $this->assignments->replaceForProduct($product, $categoryUuids, $primaryUuid);

        return new JsonResponse($this->renderList($product));
    }

    #[Route(
        '/api/products/{id}/categories',
        name: 'pim_products_categories_add',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function add(string $id, Request $request): JsonResponse
    {
        $product = $this->mustFindProduct($id);
        $payload = $this->decodePayload($request);

        $rawCategory = $payload['categoryId'] ?? null;
        if (!\is_string($rawCategory) || '' === $rawCategory) {
            throw new UnprocessableEntityHttpException('Field "categoryId" is required.');
        }
        try {
            $categoryUuid = Uuid::fromString($rawCategory);
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException(\sprintf('"categoryId" is not a valid UUID: %s.', $rawCategory), $e);
        }
        $category = $this->mustFindCategory($categoryUuid->toRfc4122());

        $isPrimary = (bool) ($payload['isPrimary'] ?? false);

        $existing = $this->assignments->findOne($product, $category);
        if (null !== $existing) {
            // Idempotent on duplicate, but still honour primary toggle so a
            // POST with isPrimary=true on an already-attached category swaps
            // the primary flag (saves a follow-up call from the picker).
            if ($isPrimary && !$existing->isPrimary()) {
                $this->reassignPrimary($product, $existing);
            }

            return $this->renderAssignment($existing, Response::HTTP_OK);
        }

        $current = $this->assignments->findByProduct($product);
        if (\count($current) >= self::MAX_ASSIGNMENTS) {
            throw new UnprocessableEntityHttpException(\sprintf('At most %d categories per product (already at %d).', self::MAX_ASSIGNMENTS, \count($current)));
        }
        $position = $this->nextPosition($current);

        if ($isPrimary) {
            // PUT-style atomic replace keeps the partial unique safe by
            // wiping all rows in the same transaction. Equivalent shape
            // for POST single-add: rebuild the full list with the new
            // entry appended and the primary flag flipped.
            $allIds = array_map(static fn (ObjectCategory $a) => $a->getCategory()->getId(), $current);
            $allIds[] = $categoryUuid;
            $this->assignments->replaceForProduct($product, $allIds, $categoryUuid);
        } else {
            $assignment = new ObjectCategory(
                product: $product,
                category: $category,
                isPrimary: false,
                position: $position,
            );
            $this->assignments->save($assignment);
        }

        $persisted = $this->assignments->findOne($product, $category);
        \assert(null !== $persisted);

        return $this->renderAssignment($persisted, Response::HTTP_CREATED);
    }

    #[Route(
        '/api/products/{id}/categories/{categoryId}',
        name: 'pim_products_categories_detach',
        requirements: ['id' => self::UUID_REGEX, 'categoryId' => self::UUID_REGEX],
        methods: ['DELETE'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function detach(string $id, string $categoryId): JsonResponse
    {
        $product = $this->mustFindProduct($id);

        $category = $this->objects->findById(Uuid::fromString($categoryId));
        if (null === $category) {
            // Already gone → idempotent.
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $assignment = $this->assignments->findOne($product, $category);
        if (null === $assignment) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $wasPrimary = $assignment->isPrimary();

        if ($wasPrimary) {
            // Promote the next-oldest remaining assignment in the same
            // transaction so the partial unique index never sees a window
            // with zero primaries on a still-populated product.
            $remaining = array_values(array_filter(
                $this->assignments->findByProduct($product),
                static fn (ObjectCategory $a) => !$a->getCategory()->getId()->equals($category->getId()),
            ));
            $remainingIds = array_map(static fn (ObjectCategory $a) => $a->getCategory()->getId(), $remaining);
            $newPrimary = [] === $remainingIds ? null : $remainingIds[0];
            $this->assignments->replaceForProduct($product, $remainingIds, $newPrimary);
        } else {
            $this->assignments->remove($assignment);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Demote the existing primary (if any) and promote `$target` instead,
     * preserving the current category list and ordering. Wraps inside the
     * repository's atomic replace so the partial unique never sees two
     * primaries.
     */
    private function reassignPrimary(CatalogObject $product, ObjectCategory $target): void
    {
        $current = $this->assignments->findByProduct($product);
        $allIds = array_map(static fn (ObjectCategory $a) => $a->getCategory()->getId(), $current);
        $this->assignments->replaceForProduct($product, $allIds, $target->getCategory()->getId());
    }

    /**
     * @param list<ObjectCategory> $current
     */
    private function nextPosition(array $current): int
    {
        $max = -1;
        foreach ($current as $row) {
            if ($row->getPosition() > $max) {
                $max = $row->getPosition();
            }
        }

        return $max + 1;
    }

    private function mustFindProduct(string $id): CatalogObject
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(\sprintf('Product "%s" was not found.', $id), $e);
        }
        $product = $this->objects->findById($uuid);
        if (null === $product) {
            throw new NotFoundHttpException(\sprintf('Product "%s" was not found.', $id));
        }
        if (ObjectKind::Product !== $product->getKind()) {
            throw new NotFoundHttpException(\sprintf('Product "%s" was not found.', $id));
        }

        return $product;
    }

    private function mustFindCategory(string $id): CatalogObject
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" is not a valid UUID.', $id), $e);
        }
        $category = $this->objects->findById($uuid);
        if (null === $category) {
            throw new UnprocessableEntityHttpException(\sprintf('Category "%s" was not found.', $id));
        }
        if (ObjectKind::Category !== $category->getKind()) {
            throw new UnprocessableEntityHttpException(\sprintf('Object "%s" is not a category.', $id));
        }

        return $category;
    }

    /**
     * @param list<Uuid> $haystack
     */
    private function containsUuid(array $haystack, Uuid $needle): bool
    {
        foreach ($haystack as $candidate) {
            if ($candidate->equals($needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        $body = $request->getContent();
        if ('' === $body) {
            return [];
        }
        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BadRequestHttpException('Request body is not valid JSON.', $e);
        }
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }
        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderList(CatalogObject $product): array
    {
        $rows = $this->assignments->findByProduct($product);
        $primaryId = null;
        $assignments = [];
        foreach ($rows as $row) {
            $category = $row->getCategory();
            $assignments[] = [
                'categoryId' => $category->getId()->toRfc4122(),
                'code' => $category->getCode(),
                'isPrimary' => $row->isPrimary(),
                'position' => $row->getPosition(),
            ];
            if ($row->isPrimary()) {
                $primaryId = $category->getId()->toRfc4122();
            }
        }

        return [
            'productId' => $product->getId()->toRfc4122(),
            'primaryCategoryId' => $primaryId,
            'assignments' => $assignments,
        ];
    }

    private function renderAssignment(ObjectCategory $assignment, int $status): JsonResponse
    {
        $category = $assignment->getCategory();

        return new JsonResponse([
            'productId' => $assignment->getProduct()->getId()->toRfc4122(),
            'categoryId' => $category->getId()->toRfc4122(),
            'code' => $category->getCode(),
            'isPrimary' => $assignment->isPrimary(),
            'position' => $assignment->getPosition(),
        ], $status);
    }
}
