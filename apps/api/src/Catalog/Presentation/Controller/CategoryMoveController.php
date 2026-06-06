<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Message\CheckSchemaDriftForCategory;
use App\Catalog\Application\Service\CategoryMoveImpactCalculator;
use App\Catalog\Application\Service\MoveCategoryService;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-04 (#408) — `PATCH /api/categories/{id}/move` (re-parent a subtree)
 * and CHC-05 (#1287) — `GET /api/categories/{id}/move-impact` plus the
 * confirm gate that warns before a move touches products.
 *
 * Heavy lifting lives in {@see MoveCategoryService}; blast-radius in
 * {@see CategoryMoveImpactCalculator}. When a move would affect products and
 * `?confirmed=true` is absent, `move()` returns HTTP 409 with the impact body
 * so the UI can warn first. On a confirmed move that affects products, a
 * {@see CheckSchemaDriftForCategory} message is dispatched for the async
 * drift re-check (CHC-04).
 */
final class CategoryMoveController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly MoveCategoryService $moveService,
        private readonly CategoryMoveImpactCalculator $impactCalculator,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route(
        '/api/categories/{id}/move-impact',
        name: 'pim_categories_move_impact',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
        format: 'json',
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'categories', action: 'add_edit')]
    public function moveImpact(string $id, Request $request): JsonResponse
    {
        $category = $this->requireCategory($id);
        $targetParentId = $this->parseTargetParentId($request->query->get('targetParentId'));

        return new JsonResponse($this->impactCalculator->calculate($category, $targetParentId));
    }

    #[Route(
        '/api/categories/{id}/move',
        name: 'pim_categories_move',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['PATCH'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'categories', action: 'add_edit')]
    public function move(string $id, Request $request): JsonResponse
    {
        $category = $this->requireCategory($id);
        $newParentId = $this->parseNewParentId($request);

        $impact = $this->impactCalculator->calculate($category, $newParentId);
        $confirmed = $request->query->getBoolean('confirmed');
        if ($impact['affectedObjectsCount'] > 0 && !$confirmed) {
            // 409 with the impact payload so the UI can warn before committing.
            return new JsonResponse($impact, Response::HTTP_CONFLICT);
        }

        $affected = $this->moveService->move($category, $newParentId);

        if ($impact['affectedObjectsCount'] > 0) {
            // Re-check the schema snapshot of every affected product off-thread.
            $this->bus->dispatch(new CheckSchemaDriftForCategory($category->getId()->toRfc4122()));
        }

        // Re-fetch after EM clear() in the service so the fresh path is reflected.
        $reloaded = $this->catalogObjects->findById($category->getId());
        if (null === $reloaded) {
            throw new NotFoundHttpException('Category disappeared after move.');
        }

        return new JsonResponse([
            'categoryId' => $reloaded->getId()->toRfc4122(),
            'newPath' => $reloaded->getPath(),
            'affectedDescendants' => $affected,
        ]);
    }

    private function requireCategory(string $id): CatalogObject
    {
        $category = $this->catalogObjects->findById(Uuid::fromString($id));
        if (null === $category) {
            throw new NotFoundHttpException(\sprintf('Category "%s" was not found.', $id));
        }
        if (ObjectKind::Category !== $category->getKind()) {
            throw new UnprocessableEntityHttpException(\sprintf('Object "%s" is not a category.', $id));
        }

        return $category;
    }

    private function parseNewParentId(Request $request): ?Uuid
    {
        $body = $request->getContent();
        try {
            $payload = '' === $body ? [] : json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BadRequestHttpException('Request body is not valid JSON.', $e);
        }
        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }
        if (!\array_key_exists('newParentId', $payload)) {
            throw new UnprocessableEntityHttpException('Field "newParentId" is required (use null for root).');
        }

        return $this->toNullableUuid($payload['newParentId'], 'newParentId');
    }

    private function parseTargetParentId(mixed $raw): ?Uuid
    {
        return $this->toNullableUuid($raw, 'targetParentId');
    }

    private function toNullableUuid(mixed $raw, string $field): ?Uuid
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        if (!\is_string($raw)) {
            throw new UnprocessableEntityHttpException(\sprintf('Field "%s" must be a UUID string or null.', $field));
        }
        try {
            return Uuid::fromString($raw);
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException(\sprintf('Field "%s" is not a valid UUID: %s.', $field, $raw), $e);
        }
    }
}
