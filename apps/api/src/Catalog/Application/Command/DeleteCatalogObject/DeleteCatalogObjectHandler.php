<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteCatalogObject;

use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteCatalogObjectHandler
{
    public function __construct(
        private CatalogObjectRepositoryInterface $catalogObjects,
    ) {
    }

    public function __invoke(DeleteCatalogObjectCommand $command): void
    {
        $object = $this->catalogObjects->findById($command->id);
        if (null === $object) {
            throw new NotFoundHttpException(\sprintf(
                'CatalogObject "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        $this->catalogObjects->remove($object);
    }
}
