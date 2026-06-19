<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectRelation;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectRelationRepositoryInterface;
use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\ValueObject\ValidationError;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * IMP2-1.8 (#1471) — the "dedicated step" (Akeneo pattern) that wires
 * cross-object links AFTER every object of the import is written.
 *
 * Pass 1 (the chunked object write in {@see ImportRunHandler}) buffers link
 * tuples as bare CODE strings — these survive `EntityManager::clear()`
 * because they reference no managed entity. Once pass 1 finishes (all
 * objects persisted, so a variant / relation target row may appear before
 * OR after the source), pass 2 resolves the codes tenant-scoped and persists
 * the links in chunks with flush+clear (memory contract, D10).
 *
 * Two kinds of links:
 *   - parent (variant → master) via `parent_sku`;
 *   - relations (Relation/Reference attribute cells) → {@see ObjectRelation}
 *     rows (ADR-014), targets resolved by code within the attribute's allowed
 *     ObjectTypes. The tenant filter guarantees a code that exists only in
 *     another tenant resolves to null — never a cross-tenant link.
 *
 * After every clear() the active Tenant detaches, so it is re-fetched and
 * re-published to {@see TenantContext} — otherwise the TenantAssignmentListener
 * would stamp new ObjectRelation rows with a detached tenant.
 */
final class RelationImportStep
{
    private const int CHUNK = 200;

    /**
     * AUD-069 (W3-5.3) — hard cap on the count of pass-1 link tuples buffered
     * before pass 2 runs.
     *
     * Unlike the pass-2 dedupe set (cleared per flush above), these two buffers
     * CANNOT be flushed incrementally: the two-pass design (Akeneo pattern,
     * class docblock) exists precisely because a variant master or relation
     * target may appear AFTER its source in the file, so a link is resolvable
     * only once EVERY object is written. Flushing mid-pass-1 would resolve
     * against a half-written catalog and drop legitimate forward references —
     * a correctness regression. The buffers therefore stay O(links) by design.
     *
     * The risk this cap addresses is a pathological/misconfigured import — e.g.
     * a Relation column whose cells fan out to thousands of targets each, or a
     * near-cartesian self-reference — where the link count explodes far beyond
     * the row count and the buffers alone exhaust the 256 MiB import worker.
     * Rather than let the worker OOM (SIGKILL → session stuck `running`, no
     * diagnostic), we fail loud: a normal 50k-SKU import with a handful of
     * relations per row sits at ~hundreds of thousands of tuples, well under
     * the cap; crossing 1,000,000 buffered links (~150 MiB of small string
     * arrays, still inside budget with headroom) signals runaway fan-out and
     * aborts with a readable message. The handler's row-phase try/catch turns
     * this into a `partial`/`failed` session with the reason logged, not a
     * crash.
     */
    public const int DEFAULT_MAX_BUFFERED_LINKS = 1_000_000;

    /** @var list<array{childSku: string, parentSku: string, rowNumber: int}> */
    private array $parentLinks = [];

    /** @var list<array{sourceSku: string, attributeCode: string, targetCodes: list<string>, rowNumber: int}> */
    private array $relationLinks = [];

    private ?Tenant $tenant = null;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectRelationRepositoryInterface $relations,
        private readonly AttributeRepositoryInterface $attributes,
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
        // AUD-069 — overridable only so a regression test can drive the
        // fail-loud cap with a small threshold; prod autowires the default.
        private readonly int $maxBufferedLinks = self::DEFAULT_MAX_BUFFERED_LINKS,
    ) {
    }

    public function reset(): void
    {
        $this->parentLinks = [];
        $this->relationLinks = [];
        $this->tenant = null;
    }

    public function recordParent(string $childSku, string $parentSku, int $rowNumber): void
    {
        $this->parentLinks[] = ['childSku' => $childSku, 'parentSku' => $parentSku, 'rowNumber' => $rowNumber];
        $this->guardBufferSize();
    }

    /**
     * @param list<string> $targetCodes
     */
    public function recordRelation(string $sourceSku, string $attributeCode, array $targetCodes, int $rowNumber): void
    {
        $this->relationLinks[] = [
            'sourceSku' => $sourceSku,
            'attributeCode' => $attributeCode,
            'targetCodes' => $targetCodes,
            'rowNumber' => $rowNumber,
        ];
        $this->guardBufferSize();
    }

    /**
     * AUD-069 — abort before the buffered link tuples exhaust the worker's
     * memory budget. See {@see self::MAX_BUFFERED_LINKS} for why these buffers
     * can't be flushed incrementally and why fail-loud beats a silent OOM.
     */
    private function guardBufferSize(): void
    {
        if (\count($this->parentLinks) + \count($this->relationLinks) > $this->maxBufferedLinks) {
            throw new RuntimeException(\sprintf(
                'Import relation buffer exceeded %d links — the file declares far more parent/relation links than rows (runaway fan-out). Aborting before the worker runs out of memory; split the import or reduce per-cell relation targets.',
                $this->maxBufferedLinks,
            ));
        }
    }

    public function hasWork(): bool
    {
        return [] !== $this->parentLinks || [] !== $this->relationLinks;
    }

    /**
     * Pass 2 — resolve buffered links by code (tenant-scoped) and persist.
     * Returns row-level errors (logged by the handler; a non-empty result
     * makes the session `partial`). Flushes + clears in chunks; the caller
     * re-merges its session afterwards.
     *
     * @return list<ValidationError>
     */
    public function resolve(ObjectKind $kind, Tenant $tenant): array
    {
        $this->tenant = $tenant;
        $this->reattachTenant();

        $errors = [...$this->resolveParents($kind), ...$this->resolveRelations($kind)];

        $this->em->flush();
        $this->reattachTenant();

        return $errors;
    }

    /**
     * @return list<ValidationError>
     */
    private function resolveParents(ObjectKind $kind): array
    {
        \assert($this->tenant instanceof Tenant);
        $errors = [];
        $written = 0;

        foreach ($this->parentLinks as $link) {
            $child = $this->catalogObjects->findByCode($link['childSku'], $kind, $this->tenant);
            if (null === $child) {
                continue;
            }

            $parent = $this->catalogObjects->findByCode($link['parentSku'], $kind, $this->tenant);
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
                $this->flushClearReattach();
            }
        }

        $this->flushClearReattach();

        return $errors;
    }

    /**
     * @return list<ValidationError>
     */
    private function resolveRelations(ObjectKind $kind): array
    {
        \assert($this->tenant instanceof Tenant);
        $errors = [];
        // Cross-link dedupe of the unique (source, target, attribute) triple —
        // catches the same relation declared in two rows before a flush makes
        // it visible to findBySourceAndAttribute.
        //
        // AUD-069 (W3-5.3) — this set is bounded to ONE flush window, not the
        // whole pass. It only needs to catch a duplicate triple that is still
        // pending (not yet INSERTed): once a chunk is flushed, the rows are in
        // the DB and findBySourceAndAttribute (a DQL read, $existingTargets
        // below) sees them, so the in-memory entry is redundant. Carrying it
        // for the entire pass would grow O(total relations) — a dense 50k
        // import (every product referencing many others) would accumulate
        // millions of triple strings and OOM the 256 MiB import worker. We
        // therefore clear it on every flush ({@see flushClearReattachRelations}),
        // capping it at O(one chunk) while keeping dedup correct.
        $seenTriples = [];
        $linkCount = 0;

        foreach ($this->relationLinks as $link) {
            $source = $this->catalogObjects->findByCode($link['sourceSku'], $kind, $this->tenant);
            $attribute = $this->attributes->findByCode($link['attributeCode'], $this->tenant);
            if (null === $source || null === $attribute) {
                continue;
            }

            $targetTypeIds = $attribute->getRelationTargetObjectTypeIds();
            $sourceId = $source->getId()->toRfc4122();
            $attributeId = $attribute->getId()->toRfc4122();

            $position = 0;
            $existingTargets = [];
            foreach ($this->relations->findBySourceAndAttribute($source, $attribute) as $existing) {
                $existingTargets[$existing->getTarget()->getId()->toRfc4122()] = true;
                ++$position;
            }

            $seenInCell = [];
            foreach ($link['targetCodes'] as $targetCode) {
                if (isset($seenInCell[$targetCode])) {
                    continue;
                }
                $seenInCell[$targetCode] = true;

                $target = $this->catalogObjects->findByCodeInObjectTypes($targetCode, $targetTypeIds, $this->tenant);
                if (null === $target) {
                    $errors[] = $this->error($link['rowNumber'], $link['sourceSku'], \sprintf(
                        'relation target "%s" (%s) was not found in this tenant.',
                        $targetCode,
                        $attribute->getCode(),
                    ));

                    continue;
                }

                $targetId = $target->getId()->toRfc4122();
                if ($targetId === $sourceId) {
                    $errors[] = $this->error($link['rowNumber'], $link['sourceSku'], \sprintf(
                        'relation "%s" points to the row itself.',
                        $attribute->getCode(),
                    ));

                    continue;
                }

                $triple = $sourceId.'|'.$attributeId.'|'.$targetId;
                if (isset($existingTargets[$targetId]) || isset($seenTriples[$triple])) {
                    continue;
                }

                $this->relations->add(new ObjectRelation($source, $target, $attribute, $position++));
                $seenTriples[$triple] = true;
            }

            if (0 === ++$linkCount % self::CHUNK) {
                // AUD-069 — flush+clear the chunk AND drop the per-window dedupe
                // set: the just-flushed triples are now visible to the DQL
                // findBySourceAndAttribute read above, so keeping them in memory
                // is redundant and would grow unbounded over a dense import.
                $this->flushClearReattach();
                $seenTriples = [];
            }
        }

        return $errors;
    }

    private function flushClearReattach(): void
    {
        $this->em->flush();
        $this->em->clear();
        $this->reattachTenant();
    }

    /**
     * Re-fetch the active tenant as a managed entity and re-publish it to the
     * TenantContext so the assignment listener stamps new rows correctly.
     */
    private function reattachTenant(): void
    {
        \assert($this->tenant instanceof Tenant);
        $managed = $this->em->find(Tenant::class, $this->tenant->getId()->toRfc4122());
        if ($managed instanceof Tenant) {
            $this->tenant = $managed;
            $this->tenantContext->set($managed);
        }
    }

    /**
     * True when making `$parent` the parent of `$child` would close a cycle —
     * i.e. `$child` is already an ancestor of `$parent`.
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
