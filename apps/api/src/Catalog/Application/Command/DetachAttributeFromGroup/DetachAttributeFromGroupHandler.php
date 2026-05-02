<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DetachAttributeFromGroup;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * VIEW-03 (#375) — detach an attribute from a group (junction-only delete).
 *
 * The Attribute itself is not removed — only the membership in the group.
 * System groups (e.g. `audit`) reject detachment of system attributes
 * (`is_system=true`); the FE removes the trash button for those rows but
 * the BE guard is the source of truth.
 *
 * Cascade: ObjectValue rows referencing the detached attribute stay
 * intact; they remain visible through the global Attributes Library
 * until separately deleted/migrated.
 */
#[AsMessageHandler]
final readonly class DetachAttributeFromGroupHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeGroupRepositoryInterface $groups,
    ) {
    }

    public function __invoke(DetachAttributeFromGroupCommand $command): void
    {
        $group = $this->groups->findById($command->attributeGroupId);
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" was not found.',
                $command->attributeGroupId->toRfc4122(),
            ));
        }

        $attribute = $this->em->find(Attribute::class, $command->attributeId);
        if (null === $attribute) {
            throw new NotFoundHttpException(\sprintf(
                'Attribute "%s" was not found.',
                $command->attributeId->toRfc4122(),
            ));
        }

        $junction = $this->em->getRepository(AttributeGroupAttribute::class)->findOneBy([
            'attributeGroup' => $group,
            'attribute' => $attribute,
        ]);
        if (!$junction instanceof AttributeGroupAttribute) {
            throw new NotFoundHttpException(\sprintf(
                'Attribute "%s" is not a member of AttributeGroup "%s".',
                $attribute->getCode(),
                $group->getCode(),
            ));
        }

        if ($group->isSystemGroup() && $attribute->isSystem()) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'System attribute "%s" cannot be detached from system group "%s".',
                $attribute->getCode(),
                $group->getCode(),
            ));
        }

        $this->em->remove($junction);
        $this->em->flush();
    }
}
