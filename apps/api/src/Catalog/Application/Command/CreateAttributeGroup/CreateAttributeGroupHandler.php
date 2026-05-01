<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateAttributeGroup;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Shared\Application\TenantContext;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateAttributeGroupHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $repository,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(CreateAttributeGroupCommand $command): Uuid
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new LogicException('Cannot create AttributeGroup without an authenticated tenant.');
        }
        if (null !== $this->repository->findByCode($command->code, $tenant)) {
            throw new ConflictHttpException(\sprintf(
                'AttributeGroup with code "%s" already exists for this tenant.',
                $command->code,
            ));
        }

        $group = new AttributeGroup(
            code: $command->code,
            label: $command->label,
            position: $command->position,
            description: $command->description,
            icon: $command->icon,
            color: $command->color,
        );

        $this->repository->save($group);

        return $group->getId();
    }
}
