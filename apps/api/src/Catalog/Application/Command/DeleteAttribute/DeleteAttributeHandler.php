<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteAttribute;

use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * VIEW-02 (#374) — deletes an Attribute. System attributes (`is_system=true`)
 * are non-deletable per UI-08.3 (#258); the FE removes the trash button for
 * system rows but the BE guard is the source of truth.
 *
 * Cascade behavior: the AttributeOption.attribute_id FK has
 * ON DELETE CASCADE so options vanish with the attribute. ObjectValue
 * rows referencing the attribute are NOT cascaded — Postgres FK is
 * RESTRICT (Sprint 0 design). If the operator wants to remove an
 * attribute that's still in use, the AttributeMigration flow (Sprint 0
 * `/migrate-type` endpoint) is the supported path.
 */
#[AsMessageHandler]
final readonly class DeleteAttributeHandler
{
    public function __construct(
        private AttributeRepositoryInterface $repository,
    ) {
    }

    public function __invoke(DeleteAttributeCommand $command): void
    {
        $attribute = $this->repository->findById($command->id);
        if (null === $attribute) {
            throw new NotFoundHttpException(\sprintf(
                'Attribute "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        if ($attribute->isSystem()) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'System attribute "%s" cannot be deleted.',
                $attribute->getCode(),
            ));
        }

        $this->repository->remove($attribute);
    }
}
