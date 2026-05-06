<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Provenance;
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
    ) {
    }

    /**
     * @param array<string, string|null> $valueByAttributeCode
     * @param array<string, Attribute>   $attributesByCode
     */
    public function create(
        ObjectType $objectType,
        string $sku,
        array $valueByAttributeCode,
        array $attributesByCode,
        Uuid $importSessionId,
    ): CatalogObject {
        $object = new CatalogObject($objectType, $sku);
        $object->assignImportSession($importSessionId);
        $this->em->persist($object);

        foreach ($valueByAttributeCode as $attributeCode => $rawValue) {
            if (null === $rawValue || '' === $rawValue) {
                continue;
            }
            $attribute = $attributesByCode[$attributeCode] ?? null;
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
            ));
        }

        return $object;
    }

    /**
     * Maps the raw CSV cell into the JSONB shape expected by
     * {@see ObjectValue::$value}. Returns null when the value is
     * uninterpretable for the attribute type — that path stays out of
     * the import (the validator already flagged it as InvalidType).
     *
     * @return array<string, mixed>|null
     */
    private function buildValuePayload(Attribute $attribute, string $raw): ?array
    {
        return match ($attribute->getType()) {
            AttributeType::Number, AttributeType::Price, AttributeType::Metric => $this->numericPayload($raw),
            AttributeType::Boolean => ['value' => $this->parseBoolean($raw)],
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

    private function parseBoolean(string $raw): bool
    {
        return \in_array(strtolower($raw), ['1', 'true', 'yes', 'tak'], true);
    }
}
