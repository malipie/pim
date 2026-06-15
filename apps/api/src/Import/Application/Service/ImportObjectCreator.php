<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Application\BatchValueWriter;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Provenance;
use App\Import\Application\Service\Media\AssetUrlResolver;
use App\Import\Domain\ValueObject\ResolvedImportValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Creates a single {@see CatalogObject} (and its {@see ObjectValue} rows)
 * from one parsed import row. Persists via the EntityManager — the
 * {@see \App\Import\Application\Handler\ImportRunHandler} flushes in
 * batches via {@see \App\Shared\Application\AbstractBatchHandler}.
 *
 * Provenance is hardcoded to `import` so the post-import UI can flag
 * every value pulled from a CSV / xlsx for review.
 *
 * Cross-attribute logic (e.g. parent SKU resolution for variants) is
 * intentionally out of scope MVP — the flat-row reader feeds master
 * products only, mirroring spec decision §3 ("Variants … master rows
 * only in MVP").
 */
final class ImportObjectCreator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BatchValueWriter $valueWriter,
        private readonly CompositeValueParser $compositeValueParser,
        private readonly AssetUrlResolver $assetUrlResolver,
    ) {
    }

    /**
     * @param list<ResolvedImportValue>                        $resolvedValues   mapped cells with the locale parsed from
     *                                                                           their dotted header (`name.pl` → `pl`)
     * @param array<string, Attribute>                         $attributesByCode
     * @param list<CatalogObject>                              $categories       resolved category objects in cell order
     *                                                                           (IMP2-1.7) — first becomes primary,
     *                                                                           index drives `position`. The handler
     *                                                                           resolves codes once per chunk and emits
     *                                                                           a per-code CategoryNotFound warning for
     *                                                                           any that did not resolve.
     * @param ?string                                          $status           validated publication status (draft|published|archived) or null = untouched
     * @param ?bool                                            $enabled          parsed enabled flag or null = untouched
     * @param ?list<array{code: string, values: list<string>}> $variantAxes      parsed variant axes or null = untouched (IMP2-1.8)
     * @param array<string, true>                              $existingAssetIds set of asset ids (lower-cased RFC 4122)
     *                                                                           that exist for the tenant, prefetched per
     *                                                                           chunk (IMP2-1.8). A gallery cell id absent
     *                                                                           from the set is dropped with a row warning
     *                                                                           instead of writing a dangling asset_id.
     */
    public function create(
        ObjectType $objectType,
        string $sku,
        array $resolvedValues,
        array $attributesByCode,
        Uuid $importSessionId,
        array $categories = [],
        ?string $status = null,
        ?bool $enabled = null,
        ?array $variantAxes = null,
        array $existingAssetIds = [],
    ): CreatedImportObject {
        $object = new CatalogObject($objectType, $sku);
        $object->assignImportSession($importSessionId);
        $this->applyState($object, $status, $enabled, $variantAxes);
        $this->em->persist($object);

        $assetIssues = [];
        $writes = $this->buildWrites($resolvedValues, $attributesByCode, $existingAssetIds, $assetIssues);
        // A freshly created object has no existing values, so the `changed`
        // count is irrelevant (a created row is never a no-op skip) — only the
        // value issues matter here.
        $issues = array_merge($assetIssues, $this->valueWriter->writeMany($object, $writes, Provenance::Import)['issues']);

        // New object has no existing assignments, so batch-persist the
        // junction rows directly (no flush — the chunk handler owns it).
        // Replace/append on EXISTING objects runs after the value pass via
        // ObjectCategoryRepository (IMP2-1.7) because it must DELETE-then-INSERT
        // around the primary partial-unique index.
        $position = 0;
        foreach ($categories as $category) {
            $this->em->persist(new ObjectCategory(
                product: $object,
                category: $category,
                isPrimary: 0 === $position,
                position: $position,
            ));
            ++$position;
        }

        return new CreatedImportObject($object, $issues);
    }

    /**
     * IMP2-1.7 — applies publication status / enabled flag pulled from the
     * row's reserved columns. Null means "column absent or empty" (D2 — do
     * not touch). Values are pre-validated by {@see ImportValidationService}.
     */
    /**
     * @param ?list<array{code: string, values: list<string>}> $variantAxes
     */
    private function applyState(CatalogObject $object, ?string $status, ?bool $enabled, ?array $variantAxes): void
    {
        if (null !== $status) {
            $object->transitionTo($status);
        }
        if (null !== $enabled) {
            $object->changeEnabled($enabled);
        }
        if (null !== $variantAxes) {
            $object->declareVariantAxes($variantAxes);
        }
    }

    /**
     * IMP2-1.3 (#1465, ADR-0019 D2/D11) — applies row values to an EXISTING
     * object. Empty cells never touch stored values, categories are left
     * untouched on update, and import_session_id is NOT stamped (created-by
     * marker only — a delete-rollback must never reach pre-existing catalog).
     * Per-value findOneByScope is the deliberate N+1 of this ticket; the
     * chunk prefetch lands with ImportValueWriter (#1466).
     *
     * @param list<ResolvedImportValue> $resolvedValues
     * @param array<string, Attribute>  $attributesByCode
     */
    /**
     * @param list<ResolvedImportValue>                        $resolvedValues
     * @param array<string, Attribute>                         $attributesByCode
     * @param ?string                                          $status           validated status or null = untouched (IMP2-1.7)
     * @param ?bool                                            $enabled          parsed enabled flag or null = untouched (IMP2-1.7)
     * @param ?list<array{code: string, values: list<string>}> $variantAxes      parsed variant axes or null = untouched (IMP2-1.8)
     * @param array<string, true>                              $existingAssetIds set of existing asset ids (IMP2-1.8)
     *
     * @return array{issues: list<array{attributeCode: string, kind: string, message: string}>, changed: int}
     *                                                                                                        `changed` is 0 when every value already matched (IMP2-2.6
     *                                                                                                        no-op re-import) — the caller counts that row as `skipped`
     */
    public function update(
        CatalogObject $object,
        array $resolvedValues,
        array $attributesByCode,
        ?string $status = null,
        ?bool $enabled = null,
        ?array $variantAxes = null,
        array $existingAssetIds = [],
    ): array {
        $this->applyState($object, $status, $enabled, $variantAxes);

        $assetIssues = [];
        $writes = $this->buildWrites($resolvedValues, $attributesByCode, $existingAssetIds, $assetIssues);
        $result = $this->valueWriter->writeMany($object, $writes, Provenance::Import);

        return [
            'issues' => array_merge($assetIssues, $result['issues']),
            'changed' => $result['changed'],
        ];
    }

    /**
     * Map resolved cells to writer entries. Empty cells never become writes
     * (ADR-0019 D2 — absent/empty means "do not touch"); cells the composite
     * parsers cannot interpret are dropped here because the row validator
     * already emitted InvalidType for them.
     *
     * @param list<ResolvedImportValue>                                         $resolvedValues
     * @param array<string, Attribute>                                          $attributesByCode
     * @param array<string, true>                                               $existingAssetIds
     * @param list<array{attributeCode: string, kind: string, message: string}> $issues           collects per-cell warnings
     *                                                                                            (dangling asset ids) — by ref
     *
     * @return list<array{attribute: Attribute, envelope: array<string, mixed>, locale: ?string, channelId: ?Uuid}>
     */
    private function buildWrites(array $resolvedValues, array $attributesByCode, array $existingAssetIds, array &$issues): array
    {
        $writes = [];
        foreach ($resolvedValues as $resolved) {
            $rawValue = $resolved->rawValue;
            if (null === $rawValue || '' === $rawValue) {
                continue;
            }
            $attribute = $attributesByCode[$resolved->attributeCode] ?? null;
            if (!$attribute instanceof Attribute) {
                continue;
            }
            $valuePayload = AttributeType::Asset === $attribute->getType()
                ? $this->assetPayload($attribute, $rawValue, $existingAssetIds, $issues)
                : $this->buildValuePayload($attribute, $rawValue);
            if (null === $valuePayload) {
                continue;
            }
            // channelId was resolved once per session by ImportColumnGrammar
            // (a dead channel code never reaches here — the grammar emits an
            // unknownSuffix column error instead). routeScope in the writer
            // drops it for non-scopable attributes.
            $writes[] = ['attribute' => $attribute, 'envelope' => $valuePayload, 'locale' => $resolved->locale, 'channelId' => $resolved->channelId];
        }

        return $writes;
    }

    /**
     * Maps the raw CSV / XLSX cell into the JSONB shape stored on
     * {@see ObjectValue::$value} for the attribute type (shapes documented
     * on the entity + docs/api/jsonb-schemas.md). Returns null when the
     * value is uninterpretable — that path stays out of the import (the
     * validator already flagged it as InvalidType).
     *
     * @return array<string, mixed>|null
     */
    private function buildValuePayload(Attribute $attribute, string $raw): ?array
    {
        return match ($attribute->getType()) {
            AttributeType::Number => $this->numericPayload($raw),
            // Composite envelopes mirror the exporter: price keeps
            // {amount, currency}, metric keeps {value, unit} (#1130).
            AttributeType::Price => $this->compositeValueParser->parsePrice($raw),
            AttributeType::Metric => $this->compositeValueParser->parseMetric($raw),
            AttributeType::Boolean => ['value' => $this->parseBoolean($raw)],
            AttributeType::Select => ['option_code' => $raw],
            AttributeType::Multiselect => $this->multiSelectPayload($raw),
            // IMP2-1.8: Asset cells are handled by assetPayload() (pipe-split +
            // tenant-scoped existence validation) before this match is reached.
            // IMP2-1.8: Relation/Reference cells are NOT written as
            // ObjectValue{object_id} anymore — the two-pass RelationImportStep
            // resolves their targets by code and writes object_relations rows.
            AttributeType::Relation, AttributeType::Reference => null,
            default => ['value' => $raw],
        };
    }

    /**
     * IMP2-1.8 galleries — pipe-split an Asset cell (`id1|id2`) and validate
     * every id against the per-chunk existence set (tenant-scoped). Missing ids
     * are dropped with a row warning so a dangling id never lands in JSONB
     * (ticket AC). A single surviving id keeps the scalar `{asset_id: <uuid>}`
     * shape (round-trips with admin-authored single assets); two or more keep
     * the list `{asset_id: [...]}` shape. Returns null when nothing survives.
     *
     * @param array<string, true>                                               $existingAssetIds
     * @param list<array{attributeCode: string, kind: string, message: string}> $issues           by ref
     *
     * @return array{asset_id: string|list<string>}|null
     */
    private function assetPayload(Attribute $attribute, string $raw, array $existingAssetIds, array &$issues): ?array
    {
        // IMP2-1.12 — a cell carrying any http(s) URL is owned by the media
        // download path: the whole Asset value (existing UUIDs + downloaded
        // ids) is written by ImageDownloadHandler after the row phase, so the
        // raw URL never lands in JSONB. Skip it here entirely.
        if ($this->cellHasUrl($raw)) {
            return null;
        }

        $ids = array_values(array_filter(
            array_map('trim', explode('|', $raw)),
            static fn (string $id): bool => '' !== $id,
        ));

        $survivors = [];
        foreach ($ids as $id) {
            if (isset($existingAssetIds[strtolower($id)])) {
                $survivors[] = $id;

                continue;
            }
            $issues[] = [
                'attributeCode' => $attribute->getCode(),
                'kind' => 'invalid_value',
                'message' => \sprintf('Asset "%s" does not exist for this tenant — skipped.', $id),
            ];
        }

        if ([] === $survivors) {
            return null;
        }

        return ['asset_id' => 1 === \count($survivors) ? $survivors[0] : $survivors];
    }

    /**
     * IMP2-1.12 — true when the cell yields any http(s) URL token. Uses the
     * SAME tokenizer ({@see AssetUrlResolver}) as ImportRunHandler's media-job
     * collection, so "owned by the media path" is decided in exactly one place
     * (no substring/tokenizer drift).
     */
    private function cellHasUrl(string $raw): bool
    {
        return [] !== $this->assetUrlResolver->classify($raw)['urls'];
    }

    /**
     * @return array{value: float}|null
     */
    private function numericPayload(string $raw): ?array
    {
        $normalised = str_replace(',', '.', $raw);
        if (!is_numeric($normalised)) {
            return null;
        }

        return ['value' => (float) $normalised];
    }

    /**
     * Splits the pipe-joined token list the exporter writes for
     * multiselect values (`ValueSerializer::MULTI_VALUE_GLUE`) back into
     * an option-code array.
     *
     * @return array{option_codes: list<string>}
     */
    private function multiSelectPayload(string $raw): array
    {
        $codes = array_values(array_filter(
            array_map('trim', explode('|', $raw)),
            static fn (string $code): bool => '' !== $code,
        ));

        return ['option_codes' => $codes];
    }

    private function parseBoolean(string $raw): bool
    {
        return \in_array(strtolower($raw), ['1', 'true', 'yes', 'tak'], true);
    }
}
