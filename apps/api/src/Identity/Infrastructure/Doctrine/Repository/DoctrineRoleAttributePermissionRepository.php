<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\RoleAttributePermission;
use App\Identity\Domain\Repository\RoleAttributePermissionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RoleAttributePermission>
 */
class DoctrineRoleAttributePermissionRepository extends ServiceEntityRepository implements RoleAttributePermissionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleAttributePermission::class);
    }

    public function findById(Uuid $id): ?RoleAttributePermission
    {
        return parent::find($id->toRfc4122());
    }

    public function findByRoleAndAttribute(Uuid $roleId, Uuid $attributeId): ?RoleAttributePermission
    {
        return $this->findOneBy([
            'roleId' => $roleId,
            'attributeId' => $attributeId,
        ]);
    }

    public function findByRole(Uuid $roleId): array
    {
        /** @var list<RoleAttributePermission> $result */
        $result = $this->createQueryBuilder('rap')
            ->where('rap.roleId = :roleId')
            ->setParameter('roleId', $roleId)
            ->orderBy('rap.attributeId', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function save(RoleAttributePermission $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(RoleAttributePermission $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }

    public function replaceForRole(Uuid $roleId, array $next): void
    {
        $em = $this->getEntityManager();
        $em->wrapInTransaction(function () use ($em, $roleId, $next): void {
            // Index existing rows for this role for O(1) lookup against the new set.
            $existing = $this->findByRole($roleId);
            $existingByAttr = [];
            foreach ($existing as $row) {
                $existingByAttr[$row->getAttributeId()->toRfc4122()] = $row;
            }

            $nextByAttr = [];
            foreach ($next as $row) {
                if (!$row->getRoleId()->equals($roleId)) {
                    throw new LogicException('All replacement rows must share the target roleId.');
                }
                $nextByAttr[$row->getAttributeId()->toRfc4122()] = $row;
            }

            // Drop rows no longer in the replacement set.
            foreach ($existingByAttr as $attrId => $row) {
                if (!isset($nextByAttr[$attrId])) {
                    $em->remove($row);
                }
            }

            // Update existing rows in place + persist new ones.
            foreach ($nextByAttr as $attrId => $row) {
                if (isset($existingByAttr[$attrId])) {
                    $existingByAttr[$attrId]->setPermissionLevel($row->getPermissionLevel());
                } else {
                    $em->persist($row);
                }
            }

            $em->flush();
        });
    }
}
