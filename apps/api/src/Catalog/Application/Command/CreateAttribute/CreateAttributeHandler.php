<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateAttribute;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Shared\Application\TenantContext;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-02 (#374) — creates a tenant-scoped Attribute. Mirrors the
 * CreateAttributeGroupHandler (#260) pattern: tenant guard, code
 * uniqueness check, then save through the repository which flushes
 * the EM under the hood (single attribute create path).
 */
#[AsMessageHandler]
final readonly class CreateAttributeHandler
{
    public function __construct(
        private AttributeRepositoryInterface $repository,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(CreateAttributeCommand $command): Uuid
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new LogicException('Cannot create Attribute without an authenticated tenant.');
        }
        if (null !== $this->repository->findByCode($command->code, $tenant)) {
            throw new ConflictHttpException(\sprintf(
                'Attribute with code "%s" already exists for this tenant.',
                $command->code,
            ));
        }

        $attribute = new Attribute(
            code: $command->code,
            label: $command->label,
            type: AttributeType::from($command->type),
        );
        $attribute->updateHelp($command->help);
        $attribute->changeLocalizable($command->localizable);
        $attribute->changeScopable($command->scopable);
        $attribute->changeRequired($command->required);
        $attribute->updateValidationRules($command->validationRules);
        $attribute->reorder($command->position);

        $this->repository->save($attribute);

        return $attribute->getId();
    }
}
