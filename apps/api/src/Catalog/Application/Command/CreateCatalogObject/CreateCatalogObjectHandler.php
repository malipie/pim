<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateCatalogObject;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Handler for {@see CreateCatalogObjectCommand}.
 *
 * Resolves the `ObjectType`, validates kind invariants, instantiates the
 * aggregate (constructor only — no setters per RF "0 publicznych setterów"
 * lesson), optionally re-parents it, and persists. The new aggregate's id
 * is returned via Messenger's `HandledStamp` so the API processor can
 * read it back for the response shape.
 *
 * `parentId` is only resolved when present; we re-fetch the parent through
 * the repository so it is managed in the same EntityManager context as
 * the new aggregate before flush.
 */
#[AsMessageHandler]
final readonly class CreateCatalogObjectHandler
{
    public function __construct(
        private CatalogObjectRepositoryInterface $catalogObjects,
        private ObjectTypeRepositoryInterface $objectTypes,
    ) {
    }

    public function __invoke(CreateCatalogObjectCommand $command): Uuid
    {
        $objectType = $this->objectTypes->findById($command->objectTypeId);
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf(
                'ObjectType "%s" was not found.',
                $command->objectTypeId->toRfc4122(),
            ));
        }

        if ($objectType->getKind() !== $command->expectedKind) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'ObjectType kind "%s" does not match the operation kind "%s".',
                $objectType->getKind()->value,
                $command->expectedKind->value,
            ));
        }

        $object = new CatalogObject($objectType, $command->code);

        if (null !== $command->parentId) {
            $parent = $this->catalogObjects->findById($command->parentId);
            if (null === $parent) {
                throw new NotFoundHttpException(\sprintf(
                    'Parent CatalogObject "%s" was not found.',
                    $command->parentId->toRfc4122(),
                ));
            }
            $object->assignParent($parent);
        }

        $this->catalogObjects->save($object);

        return $object->getId();
    }
}
