<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteAttributeGroup;

use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteAttributeGroupHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $repository,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(DeleteAttributeGroupCommand $command): void
    {
        $group = $this->repository->findById($command->id);
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        // System-owned groups are never deletable. Legacy `audit` rows are
        // user-managed modeling configuration after #1074 and remain removable.
        if ($group->isSystemGroup() && 'audit' !== $group->getCode()) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'AttributeGroup "%s" is system-managed and cannot be deleted.',
                $group->getCode(),
            ));
        }

        // Block deletion when the group is still attached to any
        // ObjectType or Category — admin UI surfaces "Detach first from
        // N usages" with a confirm modal (epik plan §10.3).
        $objectTypeUsagesRaw = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM object_type_attribute_groups WHERE attribute_group_id = ?',
            [$group->getId()->toRfc4122()],
        );
        $categoryUsagesRaw = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM category_attribute_groups WHERE attribute_group_id = ?',
            [$group->getId()->toRfc4122()],
        );
        $objectTypeUsages = \is_scalar($objectTypeUsagesRaw) ? (int) $objectTypeUsagesRaw : 0;
        $categoryUsages = \is_scalar($categoryUsagesRaw) ? (int) $categoryUsagesRaw : 0;
        $totalUsages = $objectTypeUsages + $categoryUsages;
        if ($totalUsages > 0) {
            throw new ConflictHttpException(\sprintf(
                'AttributeGroup "%s" is attached to %d ObjectType(s) and %d category-bound row(s); detach first.',
                $group->getCode(),
                $objectTypeUsages,
                $categoryUsages,
            ));
        }

        // tenant-safe: junction inherits tenant via FK chain (the
        // attribute_group_id is tenant-scoped via the parent group
        // entity loaded through TenantFilter on line 25).
        // The two COUNT queries above (lines 45-52) read the same
        // junctions and are tenant-safe for the same reason.
        //
        // Cascade-clear the AttributeGroupAttribute junction rows. ON
        // DELETE CASCADE is set on the FK, but Doctrine's UoW does not
        // know about M:N junction rows that aren't mapped as a
        // collection on the parent — issue a DBAL DELETE so the
        // junction table is consistent before EM removes the parent.
        // (No-op when the group has no attributes.)
        $this->em->getConnection()->executeStatement(
            'DELETE FROM attribute_group_attributes WHERE attribute_group_id = ?',
            [$group->getId()->toRfc4122()],
        );

        $this->repository->remove($group);
    }
}
