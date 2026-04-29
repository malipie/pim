<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\EventListener;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use InvalidArgumentException;

/**
 * Enforces the kind ↔ path invariant on `objects` writes.
 *
 *   - `kind=category` MAY have a non-null path (must validate as ltree).
 *   - any other kind MUST have `path = NULL`.
 *
 * The CHECK constraint in migration `Version20260428222056` is the
 * second line of defence — this listener gives a clearer error message
 * before the SQL gets issued and validates the ltree label format
 * (Postgres would also reject malformed input but with a less friendly
 * message). Together they make the invariant tamper-resistant from both
 * the application and the database side.
 *
 * Why a listener instead of a Symfony Validator constraint? The
 * invariant is universal — every CatalogObject persist or update must
 * honour it, not just those flowing through the API Platform validator.
 * The Doctrine event covers fixtures, bulk imports, raw entity manager
 * usage, and console commands too.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class CategoryPathValidator
{
    /**
     * Postgres ltree label syntax: dot-separated labels of `[A-Za-z0-9_]`,
     * each starting with a letter or underscore. We use the conservative
     * lowercase-only form for clarity and because category codes already
     * follow it; admins building paths from non-conforming codes would
     * trip the Postgres validator before this regex anyway.
     */
    private const string LTREE_LABEL = '/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)*$/i';

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if ($entity instanceof CatalogObject) {
            $this->assertInvariant($entity);
        }
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        if ($entity instanceof CatalogObject) {
            $this->assertInvariant($entity);
        }
    }

    private function assertInvariant(CatalogObject $object): void
    {
        $path = $object->getPath();
        $kind = $object->getKind();

        if (null === $path) {
            return;
        }

        if (ObjectKind::Category !== $kind) {
            throw new InvalidArgumentException(\sprintf(
                'CatalogObject path is only valid for kind=category, got kind=%s on object code=%s.',
                $kind->value,
                $object->getCode(),
            ));
        }

        if (1 !== preg_match(self::LTREE_LABEL, $path)) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid ltree path format: %s (expected dot-separated lowercase labels, e.g. "root.men.shoes").',
                $path,
            ));
        }
    }
}
