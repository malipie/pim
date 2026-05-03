<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateCatalogObject;

use App\Catalog\Application\ObjectAttributesUpserter;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
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
        private ObjectAttributesUpserter $attributesUpserter,
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
        $parent = null;

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

        // VIEW-04 (#408) — derive the ltree `path` here rather than in a
        // Doctrine prePersist listener. ORM 3 freezes the insert
        // change-set before listeners run, so a `prePersist` write to
        // `path` does not reach the INSERT and the row lands with `path
        // = NULL` despite the in-memory aggregate carrying the value.
        // Setting it on the aggregate before `save()` keeps the change
        // inside the natural change-set computation.
        if (ObjectKind::Category === $command->expectedKind && null === $object->getPath()) {
            $code = $object->getCode();
            if (1 === preg_match('/^[a-z_][a-z0-9_]*$/', $code)) {
                $parentPath = $parent?->getPath();
                $object->attachToPath(
                    null !== $parentPath && '' !== $parentPath ? $parentPath.'.'.$code : $code,
                );
            }
        }

        $this->catalogObjects->save($object);

        if ([] !== $command->attributes) {
            $this->attributesUpserter->upsert($object, $command->attributes);
        }

        return $object->getId();
    }
}
