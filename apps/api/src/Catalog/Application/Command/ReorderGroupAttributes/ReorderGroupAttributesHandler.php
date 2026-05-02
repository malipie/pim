<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ReorderGroupAttributes;

use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * VIEW-03 (#375) — bulk reorder attributes in a group.
 *
 * Single transaction: applies new positions in payload order. Validates
 * that the payload is a strict permutation of the existing membership
 * (same set, no duplicates, no missing) — partial reorders go through
 * the per-junction PATCH endpoint instead.
 */
#[AsMessageHandler]
final readonly class ReorderGroupAttributesHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeGroupRepositoryInterface $groups,
    ) {
    }

    public function __invoke(ReorderGroupAttributesCommand $command): void
    {
        $group = $this->groups->findById($command->attributeGroupId);
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" was not found.',
                $command->attributeGroupId->toRfc4122(),
            ));
        }

        $junctions = $this->em->getRepository(AttributeGroupAttribute::class)->findBy(['attributeGroup' => $group]);
        $byCode = [];
        foreach ($junctions as $junction) {
            $byCode[$junction->getAttribute()->getCode()] = $junction;
        }

        if (\count($command->attributeCodesInOrder) !== \count($byCode)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Reorder payload size (%d) does not match group membership size (%d).',
                \count($command->attributeCodesInOrder),
                \count($byCode),
            ));
        }

        $seen = [];
        foreach ($command->attributeCodesInOrder as $code) {
            if (isset($seen[$code])) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Duplicate attribute code "%s" in reorder payload.',
                    $code,
                ));
            }
            $seen[$code] = true;

            if (!isset($byCode[$code])) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Attribute "%s" is not a member of group "%s".',
                    $code,
                    $group->getCode(),
                ));
            }
        }

        $position = 0;
        foreach ($command->attributeCodesInOrder as $code) {
            $byCode[$code]->reorder($position++);
        }

        $this->em->flush();
    }
}
