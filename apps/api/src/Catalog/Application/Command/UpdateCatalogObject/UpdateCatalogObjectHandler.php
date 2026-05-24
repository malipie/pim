<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateCatalogObject;

use App\Catalog\Application\ObjectAttributesUpserter;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCatalogObjectHandler
{
    public function __construct(
        private CatalogObjectRepositoryInterface $catalogObjects,
        private ObjectAttributesUpserter $attributesUpserter,
    ) {
    }

    public function __invoke(UpdateCatalogObjectCommand $command): void
    {
        $object = $this->catalogObjects->findById($command->id);
        if (null === $object) {
            throw new NotFoundHttpException(\sprintf(
                'CatalogObject "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        // MODR-10 (#932) — optimistic-lock guard. Pre-flight check against
        // the in-memory version covers the common case (a stale tab); the
        // Doctrine `@Version` flush below also throws OptimisticLockException
        // if a concurrent write slipped between our load + save.
        if (null !== $command->expectedVersion && $command->expectedVersion !== $object->getVersion()) {
            throw new ConflictHttpException(\sprintf(
                'CatalogObject "%s" was modified by another user (expected v%d, current v%d). Refresh and try again.',
                $command->id->toRfc4122(),
                $command->expectedVersion,
                $object->getVersion(),
            ));
        }

        if (null !== $command->enabled) {
            $object->changeEnabled($command->enabled);
        }
        if (null !== $command->status) {
            $object->transitionTo($command->status);
        }

        if ($command->clearParent) {
            $object->assignParent(null);
        } elseif (null !== $command->parentId) {
            $parent = $this->catalogObjects->findById($command->parentId);
            if (null === $parent) {
                throw new NotFoundHttpException(\sprintf(
                    'Parent CatalogObject "%s" was not found.',
                    $command->parentId->toRfc4122(),
                ));
            }
            $object->assignParent($parent);
        }

        if ($command->clearPath) {
            $object->attachToPath(null);
        } elseif (null !== $command->path) {
            $object->attachToPath($command->path);
        }

        try {
            $this->catalogObjects->save($object);
        } catch (OptimisticLockException $e) {
            throw new ConflictHttpException(\sprintf(
                'CatalogObject "%s" was modified by another user during save. Refresh and try again.',
                $command->id->toRfc4122(),
            ), $e);
        }

        if (null !== $command->attributes && [] !== $command->attributes) {
            $this->attributesUpserter->upsert($object, $command->attributes);
        }
    }
}
