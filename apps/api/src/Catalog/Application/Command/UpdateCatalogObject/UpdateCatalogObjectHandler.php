<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateCatalogObject;

use App\Catalog\Application\ObjectAttributesUpserter;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
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

        $this->catalogObjects->save($object);

        if (null !== $command->attributes && [] !== $command->attributes) {
            $this->attributesUpserter->upsert($object, $command->attributes);
        }
    }
}
