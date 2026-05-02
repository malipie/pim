<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\ObjectTypeService;
use App\Catalog\Domain\Exception\BuiltInObjectTypeException;
use App\Catalog\Domain\Exception\ObjectTypeHasInstancesException;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-01 (#372) — `DELETE /api/object_types/{id}` for the modeling Detail
 * view's Danger zone. Service enforces the two invariants:
 *
 *   - 403 if the row is built-in (via `BuiltInObjectTypeException`),
 *   - 409 if the custom row still has live `objects` rows
 *     (via `ObjectTypeHasInstancesException`, which carries the count so
 *     the FE error toast can report exactly how many instances block).
 *
 * 204 on success — no body, no resource left to return.
 */
final class DeleteObjectTypeController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ObjectTypeService $service,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
    ) {
    }

    #[Route(
        '/api/object_types/{id}',
        name: 'pim_object_types_delete',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['DELETE'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $id): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        try {
            $this->service->delete($objectType);
        } catch (BuiltInObjectTypeException $e) {
            throw new HttpException(Response::HTTP_FORBIDDEN, $e->getMessage(), $e);
        } catch (ObjectTypeHasInstancesException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
