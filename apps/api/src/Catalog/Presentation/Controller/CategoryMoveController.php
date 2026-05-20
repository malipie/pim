<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Service\MoveCategoryService;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Identity\Domain\Attribute\RequiresPermission;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-04 (#408) — `PATCH /api/categories/{id}/move`
 * body: `{ "newParentId": "uuid|null" }`
 *
 * Single endpoint for re-parenting a category subtree. Heavy lifting
 * lives in {@see MoveCategoryService} — controller only validates the
 * payload + binds the target category.
 *
 * Response shape:
 *   {
 *     "categoryId": "...",
 *     "newPath": "service.lekarz.pediatra.ortopeda",
 *     "affectedDescendants": 4
 *   }
 */
final class CategoryMoveController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly MoveCategoryService $moveService,
    ) {
    }

    #[Route(
        '/api/categories/{id}/move',
        name: 'pim_categories_move',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['PATCH'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'categories', action: 'add_edit')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $category = $this->catalogObjects->findById(Uuid::fromString($id));
        if (null === $category) {
            throw new NotFoundHttpException(\sprintf('Category "%s" was not found.', $id));
        }
        if (ObjectKind::Category !== $category->getKind()) {
            throw new UnprocessableEntityHttpException(\sprintf('Object "%s" is not a category.', $id));
        }

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
        $rawParent = $payload['newParentId'];

        $newParentId = null;
        if (null !== $rawParent) {
            if (!\is_string($rawParent) || '' === $rawParent) {
                throw new UnprocessableEntityHttpException('Field "newParentId" must be a UUID string or null.');
            }
            try {
                $newParentId = Uuid::fromString($rawParent);
            } catch (InvalidArgumentException $e) {
                throw new UnprocessableEntityHttpException(\sprintf('Field "newParentId" is not a valid UUID: %s.', $rawParent), $e);
            }
        }

        $affected = $this->moveService->move($category, $newParentId);

        // Re-fetch after EM clear() in the service so the fresh path is
        // reflected in the response shape.
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
}
