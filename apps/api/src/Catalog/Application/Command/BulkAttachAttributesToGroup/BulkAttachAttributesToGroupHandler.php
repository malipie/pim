<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\BulkAttachAttributesToGroup;

use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * VIEW-03 (#375) — bulk attach (M:N) for the "Z biblioteki" popup.
 *
 * Idempotent: codes already present in the group are silently skipped
 * so the FE multi-select can re-submit without 409s. Position is
 * auto-assigned as `max(position) + 1` per attached row to keep the
 * drag-reorder UI stable.
 *
 * Validation:
 *   - empty payload → 422 (defensive — FE disables submit but the API
 *     guard backs it up).
 *   - unknown code → 422 with the failing code in the message.
 *   - cross-tenant code → 422 (findByCode is tenant-scoped so unknown
 *     codes from another tenant collapse into the same branch).
 */
#[AsMessageHandler]
final readonly class BulkAttachAttributesToGroupHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeGroupRepositoryInterface $groups,
        private AttributeRepositoryInterface $attributes,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @return list<string> attribute codes that were newly attached (deduped against pre-existing junction rows)
     */
    public function __invoke(BulkAttachAttributesToGroupCommand $command): array
    {
        if ([] === $command->attributeCodes) {
            throw new UnprocessableEntityHttpException('attributeCodes must contain at least one entry.');
        }

        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new LogicException('Cannot bulk-attach attributes without an authenticated tenant.');
        }

        $group = $this->groups->findById($command->attributeGroupId);
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" was not found.',
                $command->attributeGroupId->toRfc4122(),
            ));
        }

        $junctionRepo = $this->em->getRepository(AttributeGroupAttribute::class);
        $maxRow = $this->em->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(position), -1) FROM attribute_group_attributes WHERE attribute_group_id = ?',
            [$command->attributeGroupId->toRfc4122()],
        );
        $existingPosition = \is_scalar($maxRow) ? (int) $maxRow : -1;

        $attached = [];
        foreach ($command->attributeCodes as $code) {
            $attribute = $this->attributes->findByCode($code, $tenant);
            if (null === $attribute) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Attribute "%s" was not found in this tenant.',
                    $code,
                ));
            }

            $existing = $junctionRepo->findOneBy(['attributeGroup' => $group, 'attribute' => $attribute]);
            if ($existing instanceof AttributeGroupAttribute) {
                continue;
            }

            $junction = new AttributeGroupAttribute(
                attributeGroup: $group,
                attribute: $attribute,
                position: ++$existingPosition,
            );
            $this->em->persist($junction);
            $attached[] = $code;
        }

        $this->em->flush();

        return $attached;
    }
}
