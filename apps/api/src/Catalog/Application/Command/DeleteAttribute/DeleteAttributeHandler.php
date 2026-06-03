<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteAttribute;

use App\Catalog\Application\Query\Usage\UsageQueryService;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * VIEW-02 (#374) — deletes an Attribute. The delete trigger lives on the
 * attribute detail page (`show.tsx`); the trash button is only rendered for
 * non-system rows but the BE guards below are the source of truth.
 *
 * Two guards, both backed by Postgres FK semantics:
 *   - System attributes (`is_system=true`) are immutable per UI-08.3 (#258) → 422.
 *   - Attributes still in use are non-deletable → 409: `object_type_attributes`
 *     and `object_values` both have ON DELETE RESTRICT, so the DB would reject
 *     the delete anyway. We pre-check via UsageQueryService to return a helpful
 *     message instead of a raw 500, and catch the FK violation as a safety-net
 *     for the race window left by the 60s usage cache. The supported path for
 *     removing an in-use attribute is detach + the `/migrate-type` flow.
 *
 * Cascade behavior: `attribute_options.attribute_id` and
 * `attribute_group_attributes.attribute_id` are ON DELETE CASCADE, so options
 * and group memberships vanish with the attribute and never block deletion.
 */
#[AsMessageHandler]
final readonly class DeleteAttributeHandler
{
    public function __construct(
        private AttributeRepositoryInterface $repository,
        private UsageQueryService $usageQueryService,
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

        $usage = $this->usageQueryService->forAttribute($attribute);
        $objectTypeCount = \count($usage['objectTypes']);
        $instanceCount = $usage['instanceCount'];
        if ($objectTypeCount > 0 || $instanceCount > 0) {
            throw $this->inUseConflict($attribute->getCode(), $objectTypeCount, $instanceCount);
        }

        try {
            $this->repository->remove($attribute);
        } catch (ForeignKeyConstraintViolationException) {
            // Safety-net: usage cache (60s TTL) may have been stale and a new
            // attachment/value slipped in between the pre-check and remove.
            throw $this->inUseConflict($attribute->getCode(), $objectTypeCount, $instanceCount);
        }
    }

    private function inUseConflict(string $code, int $objectTypeCount, int $instanceCount): ConflictHttpException
    {
        return new ConflictHttpException(\sprintf(
            'Attribute "%s" is attached to %d object type(s) and used by %d object(s); detach or migrate it before deleting.',
            $code,
            $objectTypeCount,
            $instanceCount,
        ));
    }
}
