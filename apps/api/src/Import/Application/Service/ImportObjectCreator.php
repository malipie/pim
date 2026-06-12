<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Provenance;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Import\Domain\ValueObject\ResolvedImportValue;
use App\Shared\Domain\Tenant;
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
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectValueRepositoryInterface $objectValues,
        private readonly CompositeValueParser $compositeValueParser,
    ) {
    }

    /**
     * @param list<ResolvedImportValue> $resolvedValues   mapped cells with the locale parsed from
     *                                                    their dotted header (`name.pl` → `pl`)
     * @param array<string, Attribute>  $attributesByCode
     * @param ?string                   $categoryCode     `code` of the category to assign the new
     *                                                    product to. Single category per row
     *                                                    (becomes primary). Silently skipped when
     *                                                    the lookup fails — the validator emitted
     *                                                    the CategoryNotFound warning earlier.
     */
    public function create(
        ObjectType $objectType,
        string $sku,
        array $resolvedValues,
        array $attributesByCode,
        Uuid $importSessionId,
        ?string $categoryCode = null,
        ?Tenant $tenant = null,
    ): CatalogObject {
        $object = new CatalogObject($objectType, $sku);
        $object->assignImportSession($importSessionId);
        $this->em->persist($object);

        foreach ($resolvedValues as $resolved) {
            $rawValue = $resolved->rawValue;
            if (null === $rawValue || '' === $rawValue) {
                continue;
            }
            $attribute = $attributesByCode[$resolved->attributeCode] ?? null;
            if (!$attribute instanceof Attribute) {
                continue;
            }

            $valuePayload = $this->buildValuePayload($attribute, $rawValue);
            if (null === $valuePayload) {
                continue;
            }

            $this->em->persist(new ObjectValue(
                object: $object,
                attribute: $attribute,
                value: $valuePayload,
                provenance: Provenance::Import,
                locale: $resolved->locale,
            ));
        }

        if (null !== $categoryCode && '' !== $categoryCode && $tenant instanceof Tenant) {
            $category = $this->catalogObjects->findByCode($categoryCode, ObjectKind::Category, $tenant);
            if (null !== $category) {
                $this->em->persist(new ObjectCategory(
                    product: $object,
                    category: $category,
                    isPrimary: true,
                    position: 0,
                ));
            }
        }

        return $object;
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
    public function update(
        CatalogObject $object,
        array $resolvedValues,
        array $attributesByCode,
    ): void {
        foreach ($resolvedValues as $resolved) {
            $rawValue = $resolved->rawValue;
            if (null === $rawValue || '' === $rawValue) {
                continue;
            }
            $attribute = $attributesByCode[$resolved->attributeCode] ?? null;
            if (!$attribute instanceof Attribute) {
                continue;
            }

            $valuePayload = $this->buildValuePayload($attribute, $rawValue);
            if (null === $valuePayload) {
                continue;
            }

            $existing = $this->objectValues->findOneByScope($object, $attribute, null, $resolved->locale);
            if ($existing instanceof ObjectValue) {
                $existing->updateValue($valuePayload);
                $existing->changeProvenance(Provenance::Import);
                continue;
            }

            $this->em->persist(new ObjectValue(
                object: $object,
                attribute: $attribute,
                value: $valuePayload,
                provenance: Provenance::Import,
                locale: $resolved->locale,
            ));
        }
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
            AttributeType::Asset => ['asset_id' => $raw],
            AttributeType::Relation, AttributeType::Reference => ['object_id' => $raw],
            default => ['value' => $raw],
        };
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
