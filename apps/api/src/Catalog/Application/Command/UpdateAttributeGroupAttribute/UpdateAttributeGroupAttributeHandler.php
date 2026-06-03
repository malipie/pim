<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateAttributeGroupAttribute;

use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Rule\VisibleWhenRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateAttributeGroupAttributeHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeGroupRepositoryInterface $groups,
    ) {
    }

    public function __invoke(UpdateAttributeGroupAttributeCommand $command): void
    {
        $group = $this->groups->findById($command->attributeGroupId);
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" was not found.',
                $command->attributeGroupId->toRfc4122(),
            ));
        }

        $attribute = $this->em->find(\App\Catalog\Domain\Entity\Attribute::class, $command->attributeId);
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
                'AttributeGroupAttribute (%s, %s) was not found.',
                $command->attributeGroupId->toRfc4122(),
                $command->attributeId->toRfc4122(),
            ));
        }

        if (null !== $command->position) {
            $junction->reorder($command->position);
        }

        if (null !== $command->isRequiredInGroup) {
            $junction->changeRequiredInGroup($command->isRequiredInGroup);
        }

        if ($command->clearVisibleWhen) {
            $junction->changeVisibleWhen(null);
        } elseif (null !== $command->visibleWhen) {
            // Validate the rule shape via VisibleWhenRule so unsupported
            // operators / missing fields fail at the API edge with 422.
            $rule = VisibleWhenRule::fromArray($command->visibleWhen);
            $this->assertFieldExistsInGroup($group, $rule->field);
            $junction->changeVisibleWhen($rule->toArray());
        }

        $this->em->flush();
    }

    /**
     * Cross-group references make rule resolution chaotic — admin UI
     * limits the field picker to attribute codes inside the same group.
     * Server-side check guards the API path: the referenced attribute
     * code must exist in the same AttributeGroup OR be one of the
     * platform-owned system audit attributes.
     */
    private function assertFieldExistsInGroup(\App\Catalog\Domain\Entity\AttributeGroup $group, string $field): void
    {
        $alwaysVisible = ['created_at', 'updated_at', 'created_by', 'updated_by'];
        if (\in_array($field, $alwaysVisible, true)) {
            return;
        }

        $count = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM attribute_group_attributes aga'
            .' JOIN attributes a ON a.id = aga.attribute_id'
            .' WHERE aga.attribute_group_id = ? AND a.code = ?',
            [$group->getId()->toRfc4122(), $field],
        );
        $found = \is_scalar($count) ? (int) $count : 0;

        if (0 === $found) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'visible_when.field "%s" must reference an attribute code inside the same AttributeGroup.',
                $field,
            ));
        }
    }
}
