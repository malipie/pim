<?php

declare(strict_types=1);

namespace App\Export\Application\Builder;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Export\Domain\Entity\ExportSession;
use Generator;
use LogicException;

/**
 * Core export engine — turns a stream of CatalogObjects into XLSX / CSV
 * row arrays.
 *
 * Contract (PRD-PIM-exports.md §5, §8):
 *   - Input is an `iterable<CatalogObject>` so the caller (sync runner
 *     EXP-05, async handler EXP-06) owns chunking + `EntityManager::clear()`.
 *     This keeps memory bounded under FrankenPHP worker mode
 *     (CLAUDE.md §3.10) without forcing the builder to be repository-aware.
 *   - Output is a Generator yielding `array<string,string>`: keys are the
 *     resolved column keys (`sku`, `description.pl`), values are
 *     serialised strings (PRD §8 — pipe for multi, blank for null, etc.).
 *   - The first call to {@see build()} runs the {@see ColumnResolver}
 *     once up-front; lookups inside the loop are O(1) per column.
 *
 * What the builder does NOT do:
 *   - Variant fan-out. The caller materialises masters + variants in the
 *     desired order before passing them in. Each variant carries
 *     `parent_sku` via {@see CatalogObject::getParent()} so the export
 *     row reflects flat layout (decyzja Fali 5 α, PRD §8.3).
 *   - Filter resolution. Selection / filter snapshot / all targets are
 *     resolved by the caller against the catalog list service.
 *   - Asset URL minting. {@see ValueSerializer} emits `asset_id` for the
 *     MVP; IMP-18 (#604) closes the round-trip once the CDN base URL
 *     lands. Bumping that to a real URL is a single-call swap here.
 */
final class ExportBuilder
{
    public function __construct(
        private readonly ObjectValueRepositoryInterface $values,
        private readonly ObjectCategoryRepositoryInterface $categories,
        private readonly ColumnResolver $columnResolver,
        private readonly ValueSerializer $serializer,
    ) {
    }

    /**
     * @param iterable<CatalogObject> $objects
     *
     * @return Generator<int, array<string, string>>
     */
    public function build(iterable $objects, ExportSession $session): Generator
    {
        if (null === $session->getTenant()) {
            throw new LogicException('Export session must carry a tenant before build().');
        }

        $columns = $this->columnResolver->resolve($session->getSelectedColumns());

        foreach ($objects as $object) {
            yield $this->renderRow($object, $columns);
        }
    }

    /**
     * @param array<int, ColumnDefinition> $columns
     *
     * @return array<string, string>
     */
    private function renderRow(CatalogObject $object, array $columns): array
    {
        $valueIndex = $this->indexValuesByObject($object);
        $row = [];

        foreach ($columns as $column) {
            $row[$column->key] = $this->cellFor($object, $column, $valueIndex);
        }

        return $row;
    }

    /**
     * @return array<string, ObjectValue>
     */
    private function indexValuesByObject(CatalogObject $object): array
    {
        $index = [];
        foreach ($this->values->findByObject($object) as $value) {
            $key = $this->indexKey(
                $value->getAttribute()->getCode(),
                $value->getLocale(),
                null !== $value->getChannelId() ? $value->getChannelId()->toRfc4122() : null,
            );
            $index[$key] = $value;
        }

        return $index;
    }

    private function indexKey(string $code, ?string $locale, ?string $channelId): string
    {
        return sprintf('%s|%s|%s', $code, $locale ?? '', $channelId ?? '');
    }

    /**
     * @param array<string, ObjectValue> $valueIndex
     */
    private function cellFor(
        CatalogObject $object,
        ColumnDefinition $column,
        array $valueIndex,
    ): string {
        if ($column->isBuiltIn()) {
            return $this->builtIn($object, $column->code);
        }

        // Attribute lookup. Channel-scoped variant deferred to Faza 1
        // (PRD §6.1) — MVP exports use locale-only narrowing.
        $key = $this->indexKey($column->code, $column->locale, null);
        $value = $valueIndex[$key] ?? null;

        // Stale profile / missing attribute (R-47 from PRD §14). Don't
        // 500 — emit blank cell so the rest of the row stays useful.
        if (null === $value) {
            return '';
        }

        return $this->serializer->serialize($value);
    }

    private function builtIn(CatalogObject $object, string $code): string
    {
        return match ($code) {
            'sku' => $this->serializer->serializeScalar($object->getCode()),
            'parent_sku' => $this->serializer->serializeScalar($object->getParent()?->getCode()),
            'status' => $this->serializer->serializeScalar($object->getStatus()),
            'enabled' => $this->serializer->serializeScalar($object->isEnabled()),
            'completeness_pct' => $this->serializer->serializeScalar($object->getCompletenessPct()),
            'created_at' => $this->serializer->serializeScalar($object->getCreatedAt()),
            'updated_at' => $this->serializer->serializeScalar($object->getUpdatedAt()),
            'category' => $this->resolveCategories($object),
            default => '',
        };
    }

    private function resolveCategories(CatalogObject $object): string
    {
        $assignments = $this->categories->findByProduct($object);
        if ([] === $assignments) {
            return '';
        }

        $codes = [];
        foreach ($assignments as $assignment) {
            $codes[] = $assignment->getCategory()->getCode();
        }

        return implode(ValueSerializer::MULTI_VALUE_GLUE, $codes);
    }
}
