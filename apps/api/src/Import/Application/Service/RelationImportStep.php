<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\ValueObject\ValidationError;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * IMP2-1.8 (#1471) — the "dedicated step" (Akeneo pattern) that wires
 * cross-object links AFTER every object of the import is written.
 *
 * Pass 1 (the chunked object write in {@see ImportRunHandler}) buffers link
 * tuples as bare CODE strings — these survive `EntityManager::clear()`
 * because they reference no managed entity. Once pass 1 finishes (all
 * objects persisted, so a variant row may appear before OR after its
 * master), pass 2 resolves the codes tenant-scoped and persists the links
 * in chunks with flush+clear (memory contract — buffer is bounded to ~200k
 * tuples, D10).
 *
 * Parent (variant → master) linking lands here first; relation attributes
 * (ObjectRelation rows) extend the same buffer in a follow-up of this ticket.
 */
final class RelationImportStep
{
    private const int CHUNK = 200;

    /** @var list<array{childSku: string, parentSku: string, rowNumber: int}> */
    private array $parentLinks = [];

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function reset(): void
    {
        $this->parentLinks = [];
    }

    public function recordParent(string $childSku, string $parentSku, int $rowNumber): void
    {
        $this->parentLinks[] = ['childSku' => $childSku, 'parentSku' => $parentSku, 'rowNumber' => $rowNumber];
    }

    public function hasWork(): bool
    {
        return [] !== $this->parentLinks;
    }

    /**
     * Pass 2 — resolve buffered links by code (tenant-scoped) and persist.
     * Returns row-level errors (logged by the handler; a non-empty result
     * makes the session `partial`). Flushes + clears every {@see CHUNK}
     * assignments; the caller re-merges its session afterwards.
     *
     * @return list<ValidationError>
     */
    public function resolve(ObjectKind $kind, Tenant $tenant): array
    {
        $errors = [];
        $written = 0;

        foreach ($this->parentLinks as $link) {
            $child = $this->catalogObjects->findByCode($link['childSku'], $kind, $tenant);
            if (null === $child) {
                // The child row failed earlier in pass 1 — nothing to link.
                continue;
            }

            $parent = $this->catalogObjects->findByCode($link['parentSku'], $kind, $tenant);
            if (null === $parent) {
                $errors[] = $this->error($link['rowNumber'], $link['childSku'], \sprintf(
                    'parent_sku "%s" was not found — variant left unparented.',
                    $link['parentSku'],
                ));

                continue;
            }
            if ($parent->getId()->equals($child->getId())) {
                $errors[] = $this->error($link['rowNumber'], $link['childSku'], 'parent_sku points to the row itself.');

                continue;
            }
            if ($this->wouldCycle($child, $parent)) {
                $errors[] = $this->error($link['rowNumber'], $link['childSku'], \sprintf(
                    'parent_sku "%s" would create a parent cycle.',
                    $link['parentSku'],
                ));

                continue;
            }

            $child->assignParent($parent);
            if (0 === ++$written % self::CHUNK) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();

        return $errors;
    }

    /**
     * True when making `$parent` the parent of `$child` would close a cycle —
     * i.e. `$child` is already an ancestor of `$parent`. Earlier-chunk
     * assignments are flushed, so the walk sees them.
     */
    private function wouldCycle(CatalogObject $child, CatalogObject $parent): bool
    {
        $childId = $child->getId();
        $ancestor = $parent;
        $guard = 0;
        while (null !== $ancestor) {
            if ($ancestor->getId()->equals($childId)) {
                return true;
            }
            if (++$guard > 1000) {
                // Defensive: a pre-existing cycle in the data — treat as cycle.
                return true;
            }
            $ancestor = $ancestor->getParent();
        }

        return false;
    }

    private function error(int $rowNumber, string $sku, string $message): ValidationError
    {
        return new ValidationError(
            rowNumber: $rowNumber,
            sku: $sku,
            errorType: ImportErrorType::InvalidValue,
            level: ImportLogLevel::Error,
            message: $message,
        );
    }
}
