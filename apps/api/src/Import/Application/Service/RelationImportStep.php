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
                $this->flushClearReattach();
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
