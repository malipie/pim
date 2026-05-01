<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateAttributeGroup;

use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAttributeGroupHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $repository,
    ) {
    }

    public function __invoke(UpdateAttributeGroupCommand $command): void
    {
        $group = $this->repository->findById($command->id);
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        if (null !== $command->label) {
            $group->rename($command->label);
        }
        if (null !== $command->position) {
            $group->reorder($command->position);
        }
        if ($command->clearDescription) {
            $group->describe(null);
        } elseif (null !== $command->description) {
            $group->describe($command->description);
        }
        if ($command->clearIcon) {
            $group->setIcon(null);
        } elseif (null !== $command->icon) {
            $group->setIcon($command->icon);
        }
        if ($command->clearColor) {
            $group->setColor(null);
        } elseif (null !== $command->color) {
            $group->setColor($command->color);
        }

        $this->repository->save($group);
    }
}
