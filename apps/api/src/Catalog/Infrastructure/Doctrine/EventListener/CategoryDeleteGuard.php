<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\EventListener;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * VIEW-04 (#408) — blocks the destructive `DELETE /api/categories/{id}`
 * when removing the row would orphan descendants or attached objects.
 *
 *   - **Descendants** → another `kind=category` row whose `path <@ this.path`.
 *     Operator must move/empty children first.
 *   - **Attached objects** → any `parent_id = this.id` (variants are kept
 *     out of categories so this only counts category-membership rows).
 *
 * The guard fires on `preRemove`. If either count > 0 it throws an
 * HTTP 409 Conflict with a structured RFC 7807 `detail` containing
 * the exact obstruction counts so the FE can render a precise error
 * banner.
 *
 * The bundled `category_attribute_groups` junction rows do **not** block
 * deletion — those cascade automatically via the new FK introduced in
 * the VIEW-04 migration. The guard is concerned only with logical
 * orphans visible to operators, not infrastructure rows.
 */
#[AsDoctrineListener(event: Events::preRemove)]
final class CategoryDeleteGuard
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof CatalogObject) {
            return;
        }
        if (ObjectKind::Category !== $entity->getKind()) {
            return;
        }

        $thisId = $entity->getId()->toRfc4122();
        $thisPath = $entity->getPath();

        $descendantCount = 0;
        if (null !== $thisPath && '' !== $thisPath) {
            $descendantCount = self::toInt($this->connection->fetchOne(
                "SELECT COUNT(*) FROM objects WHERE kind = 'category' AND path <@ CAST(:path AS ltree) AND id <> CAST(:id AS uuid)",
                ['path' => $thisPath, 'id' => $thisId],
            ));
        }

        // The category itself may briefly self-reference during construction;
        // it never can after persist (validator catches it). The query
        // counts children of any kind — variants don't go under categories
        // so for a real deployment this only matches category-membership
        // rows in phase 2+ associations, but we count defensively today.
        $childObjectCount = self::toInt($this->connection->fetchOne(
            'SELECT COUNT(*) FROM objects WHERE parent_id = CAST(:id AS uuid)',
            ['id' => $thisId],
        )) - ($descendantCount > 0 ? $descendantCount : 0);
        if ($childObjectCount < 0) {
            $childObjectCount = 0;
        }

        if (0 === $descendantCount && 0 === $childObjectCount) {
            return;
        }

        throw new HttpException(
            Response::HTTP_CONFLICT,
            \sprintf(
                'Category cannot be deleted: %d descendant categor%s, %d attached object%s.',
                $descendantCount,
                1 === $descendantCount ? 'y' : 'ies',
                $childObjectCount,
                1 === $childObjectCount ? '' : 's',
            ),
        );
    }

    /**
     * Coerce a DBAL scalar (string|int|null per driver) into int. Aggregate
     * results from `COUNT(*)` arrive as strings under the pdo_pgsql driver
     * but as ints under others; PHPStan rejects a direct (int) cast on
     * `mixed`, so this helper centralises the assertion.
     */
    private static function toInt(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && '' !== $value) {
            return (int) $value;
        }

        return 0;
    }
}
