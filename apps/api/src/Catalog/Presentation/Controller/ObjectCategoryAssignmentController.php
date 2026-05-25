<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
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
 * UP-03 (#1019) — poly-kind mirror of {@see ProductCategoryAssignmentController}.
 *
 * Same 4 routes (GET / PUT / POST / DELETE) but rooted at
 * `/api/objects/{id}/categories` so any ObjectType with
 * `isCategorizable=true` participates in the category-link flow.
 *
 *   - `GET    /api/objects/{id}/categories`
 *   - `PUT    /api/objects/{id}/categories`                — atomic replace
 *   - `POST   /api/objects/{id}/categories`                — idempotent add
 *   - `DELETE /api/objects/{id}/categories/{categoryId}`   — detach
 *
 * Kind-check difference vs the product-only controller: instead of
 * rejecting `kind != product`, we reject `ObjectType.isCategorizable == false`.
 * That keeps Product working (built-in product is seeded categorizable) and
 * unlocks custom kinds the operator explicitly flagged as categorizable via
 * the modeling wizard.
 *
 * Reuses `ObjectCategoryRepositoryInterface::replaceForProduct` /
 * `save` / `findByProduct` — the repo signature is generic across kinds
 * (the column is `object_id`, the alias is historical).
 *
 * Legacy `/api/products/{id}/categories` controller stays during the
 * UP-10 dual-maintenance window. UI consumers gradually swap to this
 * route as `UniversalDetailPage` rolls out (UP-07).
 */
final class ObjectCategoryAssignmentController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    private const int MAX_ASSIGNMENTS = 50;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly ObjectCategoryRepositoryInterface $assignments,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(
        '/api/objects/{id}/categories',
        name: 'pim_objects_categories_list',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function list(string $id): JsonResponse
    {
        $object = $this->mustFindCategorizableObject($id);

        return new JsonResponse($this->renderList($object));
    }

    #[Route(
        '/api/objects/{id}/categories',
        name: 'pim_objects_categories_replace',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['PUT'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function replace(string $id, Request $request): JsonResponse
    {
        $object = $this->mustFindCategorizableObject($id);
        $payload = $this->decodePayload($request);

        $rawIds = $payload['categoryIds'] ?? null;
        if (!\is_array($rawIds)) {
            throw new UnprocessableEntityHttpException('Field "categoryIds" must be an array.');
        }
        if (\count($rawIds) > self::MAX_ASSIGNMENTS) {
            throw new UnprocessableEntityHttpException(\sprintf('At most %d categories per object (got %d).', self::MAX_ASSIGNMENTS, \count($rawIds)));
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

        foreach ($categoryUuids as $uuid) {
            $this->mustFindCategory($uuid->toRfc4122());
        }

        $this->assignments->replaceForProduct($object, $categoryUuids, $primaryUuid);

        return new JsonResponse($this->renderList($object));
    }

    #[Route(
        '/api/objects/{id}/categories',
        name: 'pim_objects_categories_add',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function add(string $id, Request $request): JsonResponse
    {
        $object = $this->mustFindCategorizableObject($id);
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

        $existing = $this->assignments->findOne($object, $category);
        if (null !== $existing) {
            if ($isPrimary && !$existing->isPrimary()) {
                $this->reassignPrimary($object, $existing);
            }

            return $this->renderAssignment($existing, Response::HTTP_OK);
        }

        $current = $this->assignments->findByProduct($object);
        if (\count($current) >= self::MAX_ASSIGNMENTS) {
            throw new UnprocessableEntityHttpException(\sprintf('At most %d categories per object (already at %d).', self::MAX_ASSIGNMENTS, \count($current)));
        }
        $position = $this->nextPosition($current);

        if ($isPrimary) {
            $allIds = array_map(static fn (ObjectCategory $a) => $a->getCategory()->getId(), $current);
            $allIds[] = $categoryUuid;
            $this->assignments->replaceForProduct($object, $allIds, $categoryUuid);
        } else {
            $assignment = new ObjectCategory(
                product: $object,
                category: $category,
                isPrimary: false,
                position: $position,
            );
            $this->assignments->save($assignment);
        }

        $persisted = $this->assignments->findOne($object, $category);
        \assert(null !== $persisted);

        return $this->renderAssignment($persisted, Response::HTTP_CREATED);
    }

    #[Route(
        '/api/objects/{id}/categories/{categoryId}',
        name: 'pim_objects_categories_detach',
        requirements: ['id' => self::UUID_REGEX, 'categoryId' => self::UUID_REGEX],
        methods: ['DELETE'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function detach(string $id, string $categoryId): JsonResponse
    {
        $object = $this->mustFindCategorizableObject($id);

        $category = $this->objects->findById(Uuid::fromString($categoryId));
        if (null === $category) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $assignment = $this->assignments->findOne($object, $category);
        if (null === $assignment) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $wasPrimary = $assignment->isPrimary();

        if ($wasPrimary) {
            $remaining = array_values(array_filter(
                $this->assignments->findByProduct($object),
                static fn (ObjectCategory $a) => !$a->getCategory()->getId()->equals($category->getId()),
            ));
            $remainingIds = array_map(static fn (ObjectCategory $a) => $a->getCategory()->getId(), $remaining);
            $newPrimary = [] === $remainingIds ? null : $remainingIds[0];
            $this->assignments->replaceForProduct($object, $remainingIds, $newPrimary);
        } else {
            $this->assignments->remove($assignment);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function reassignPrimary(CatalogObject $object, ObjectCategory $target): void
    {
        $current = $this->assignments->findByProduct($object);
        $allIds = array_map(static fn (ObjectCategory $a) => $a->getCategory()->getId(), $current);
        $this->assignments->replaceForProduct($object, $allIds, $target->getCategory()->getId());
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

    /**
     * UP-03 capability gate — replaces the product-only kind check. The
     * ObjectType must be flagged `isCategorizable=true` (built-in product
     * is; custom kinds opt-in via modeling wizard).
     */
    private function mustFindCategorizableObject(string $id): CatalogObject
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(\sprintf('Object "%s" was not found.', $id), $e);
        }
        $object = $this->objects->findById($uuid);
        if (null === $object) {
            throw new NotFoundHttpException(\sprintf('Object "%s" was not found.', $id));
        }
        if (!$object->getObjectType()->isCategorizable()) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'ObjectType "%s" is not categorizable; flip the capability flag in the modeling wizard before assigning categories.',
                $object->getObjectType()->getCode(),
            ));
        }

        return $object;
    }

    private function mustFindCategory(string $id): CatalogObject
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException(\sprintf('Category "%s" is not a valid UUID.', $id), $e);
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
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BadRequestHttpException('Body must be a JSON object.', $e);
        }
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('Body must be a JSON object.');
        }

        $coerced = [];
        foreach ($decoded as $key => $value) {
            $coerced[(string) $key] = $value;
        }

        return $coerced;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderList(CatalogObject $object): array
    {
        $assignments = $this->assignments->findByProduct($object);

        return [
            'objectId' => $object->getId()->toRfc4122(),
            'assignments' => array_map(
                fn (ObjectCategory $a) => $this->serialize($a),
                $assignments,
            ),
        ];
    }

    private function renderAssignment(ObjectCategory $assignment, int $status): JsonResponse
    {
        return new JsonResponse($this->serialize($assignment), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ObjectCategory $assignment): array
    {
        return [
            'categoryId' => $assignment->getCategory()->getId()->toRfc4122(),
            'categoryCode' => $assignment->getCategory()->getCode(),
            'isPrimary' => $assignment->isPrimary(),
            'position' => $assignment->getPosition(),
        ];
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
}
