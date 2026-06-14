<?php

declare(strict_types=1);

namespace App\Export\Application\Builder;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Channel\Contracts\ChannelResolverInterface;
use App\Export\Domain\Entity\ExportSession;
use App\Shared\Domain\Tenant;
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
        private readonly ChannelResolverInterface $channels,
        private readonly \App\Catalog\Domain\Repository\ObjectRelationRepositoryInterface $relations,
        private readonly \App\Catalog\Domain\Repository\AttributeRepositoryInterface $attributes,
    ) {
    }

    /**
     * @param iterable<CatalogObject> $objects
     *
     * @return Generator<int, array<string, string>>
     */
    public function build(iterable $objects, ExportSession $session): Generator
    {
        $tenant = $session->getTenant();
        if (null === $tenant) {
            throw new LogicException('Export session must carry a tenant before build().');
        }

        $channelCodes = $session->getChannels() ?? [];
        $columns = $this->columnResolver->resolve($session->getSelectedColumns(), $channelCodes);

        // #1229: ObjectValue rows key the channel scope by UUID, so resolve
        // each session channel code to its id once up-front. A code with no
        // matching tenant channel is dropped here and degrades to a blank
        // cell in cellFor() (stale profile, R-47) rather than 500-ing.
        $channelIdByCode = [];
        foreach ($channelCodes as $code) {
            $id = $this->channels->resolveId($code, $tenant);
            if (null !== $id) {
                $channelIdByCode[$code] = $id->toRfc4122();
            }
        }

        // IMP2-1.6 (R-47): a channel-scoped column whose channel no longer
        // resolves fails the whole export loudly. The pre-1.6 silent blank
        // column, combined with clear_if_empty downstream, could wipe a
        // destination. Preflight catches this as a 422; this is the
        // defensive guard for a bypassed preflight.
        $this->assertChannelColumnsResolvable($columns, $channelIdByCode);

        // IMP2-1.8: resolve the attribute behind each attribute column once so
        // cellFor can route Relation/Reference columns to object_relations.
        $attributeMap = $this->resolveColumnAttributes($columns, $tenant);

        $primaryLocale = $tenant->getPrimaryLocale();
        foreach ($objects as $object) {
            yield $this->renderRow($object, $columns, $channelIdByCode, $primaryLocale, $attributeMap);
        }
    }

    /**
     * @param array<int, ColumnDefinition> $columns
     *
     * @return array<string, Attribute>
     */
    private function resolveColumnAttributes(array $columns, Tenant $tenant): array
    {
        $map = [];
        foreach ($columns as $column) {
            if (!$column->isAttribute() || isset($map[$column->code])) {
                continue;
            }
            $attribute = $this->attributes->findByCode($column->code, $tenant);
            if (null !== $attribute) {
                $map[$column->code] = $attribute;
            }
        }

        return $map;
    }

    /**
     * @param array<int, ColumnDefinition> $columns
     * @param array<string, string>        $channelIdByCode channel code => UUID RFC 4122 (#1229)
     */
    private function assertChannelColumnsResolvable(array $columns, array $channelIdByCode): void
    {
        $unresolved = [];
        foreach ($columns as $column) {
            if (null !== $column->channel && !isset($channelIdByCode[$column->channel])) {
                $unresolved[$column->channel] = true;
            }
        }

        if ([] !== $unresolved) {
            throw new UnresolvedExportChannelException(array_keys($unresolved));
        }
    }

    /**
     * @param array<int, ColumnDefinition> $columns
     * @param array<string, string>        $channelIdByCode channel code => UUID RFC 4122 (#1229)
     * @param array<string, Attribute>     $attributeMap    attribute column code => Attribute (#1471)
     *
     * @return array<string, string>
     */
    private function renderRow(CatalogObject $object, array $columns, array $channelIdByCode, string $primaryLocale, array $attributeMap): array
    {
        $valueIndex = $this->indexValuesByObject($object);
        $row = [];

        foreach ($columns as $column) {
            $row[$column->key] = $this->cellFor($object, $column, $valueIndex, $channelIdByCode, $primaryLocale, $attributeMap);
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
     * @param array<string, string>      $channelIdByCode channel code => UUID RFC 4122 (#1229)
     * @param array<string, Attribute>   $attributeMap    attribute column code => Attribute (#1471)
     */
    private function cellFor(
        CatalogObject $object,
        ColumnDefinition $column,
        array $valueIndex,
        array $channelIdByCode,
        string $primaryLocale,
        array $attributeMap,
    ): string {
        if ($column->isBuiltIn()) {
            return $this->builtIn($object, $column->code);
        }

        // IMP2-1.8 (D5): Relation/Reference columns emit pipe-joined target
        // CODES read from object_relations (symmetry with the import, which
        // writes relations there — not as ObjectValue).
        $attribute = $attributeMap[$column->code] ?? null;
        if ($attribute instanceof Attribute
            && (AttributeType::Relation === $attribute->getType() || AttributeType::Reference === $attribute->getType())) {
            $codes = [];
            foreach ($this->relations->findBySourceAndAttribute($object, $attribute) as $relation) {
                $codes[] = $relation->getTarget()->getCode();
            }

            return implode(ValueSerializer::MULTI_VALUE_GLUE, $codes);
        }

        // #1229: a channel-scoped column narrows the lookup to the channel's
        // UUID. Resolvability is guaranteed by assertChannelColumnsResolvable().
        $channelId = null !== $column->channel ? ($channelIdByCode[$column->channel] ?? null) : null;

        $value = $valueIndex[$this->indexKey($column->code, $column->locale, $channelId)] ?? null;

        // #1146 fan-out: the writer collapses the PRIMARY locale into the
        // global (locale=NULL) row, so a `name.pl` column (pl primary) finds
        // nothing under its own locale — fall back to the global value,
        // keeping the channel scope. Non-primary locales never fan out: that
        // would leak the primary value into a locale that has none and break
        // the round-trip.
        if (null === $value && null !== $column->locale && $column->locale === $primaryLocale) {
            $value = $valueIndex[$this->indexKey($column->code, null, $channelId)] ?? null;
        }

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
