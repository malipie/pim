<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Provenance;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-1.4 (#1466, ADR-0019) — chunk-oriented value writer for bulk paths
 * (import). Shares every rule with the admin path via {@see ValueWriteCore},
 * but: collects violations as result issues instead of throwing, persists
 * without flushing (the batch handler owns flushAndClear), and prefetches
 * existing ObjectValue rows per chunk so the update path costs one query
 * per chunk instead of one per value (the deliberate N+1 of IMP2-1.3).
 *
 * Lifecycle per chunk: primeChunk(objects, attributes) once, then
 * writeMany() per row. EntityManager::clear() invalidates the prime —
 * the import handler re-primes after every flushAndClear.
 */
final class BatchValueWriter
{
    /** @var array<string, ObjectValue> "objectId|attributeId|locale|channelId" => row */
    private array $scopeIndex = [];

    /** @var array<string, true> "attributeId|value" of identifier values seen in this chunk (in-file duplicates) */
    private array $chunkIdentifiers = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValueWriteCore $core,
    ) {
    }

    /**
     * One query: every ObjectValue of the chunk's existing objects for the
     * mapped attributes. New (just-persisted, unflushed) objects have no
     * rows yet and simply miss the index.
     *
     * @param list<CatalogObject>      $objects
     * @param array<string, Attribute> $attributesByCode
     */
    public function primeChunk(array $objects, array $attributesByCode): void
    {
        $this->scopeIndex = [];
        $this->chunkIdentifiers = [];

        if ([] === $objects || [] === $attributesByCode) {
            return;
        }

        $rows = $this->em->createQueryBuilder()
            ->select('ov')
            ->from(ObjectValue::class, 'ov')
            ->where('ov.object IN (:objects)')
            ->andWhere('ov.attribute IN (:attributes)')
            ->setParameter('objects', $objects)
            ->setParameter('attributes', array_values($attributesByCode))
            ->getQuery()
            ->getResult();

        /** @var list<ObjectValue> $rows */
        foreach ($rows as $row) {
            $this->scopeIndex[$this->scopeKey(
                $row->getObject()->getId(),
                $row->getAttribute()->getId(),
                $row->getLocale(),
                $row->getChannelId(),
            )] = $row;
        }
    }

    /**
     * Apply pre-built envelopes to one object. Issues are returned, never
     * thrown; a value with an issue is skipped, the rest of the row writes.
     *
     * @param list<array{attribute: Attribute, envelope: array<string, mixed>, locale: ?string, channelId: ?Uuid}> $writes
     *
     * @return list<array{attributeCode: string, kind: string, message: string}>
     */
    public function writeMany(CatalogObject $object, array $writes, Provenance $provenance): array
    {
        $tenant = $object->getTenant();
        $issues = [];

        foreach ($writes as $write) {
            $attribute = $write['attribute'];
            $envelope = $this->core->normalise($attribute->getType(), $write['envelope']);

            $requiredViolation = $this->core->requiredViolation($attribute, $envelope);
            if (null !== $requiredViolation) {
                $issues[] = ['attributeCode' => $attribute->getCode(), 'kind' => 'required_empty', 'message' => $requiredViolation];

                continue;
            }

            $formatViolations = $this->core->formatViolations($attribute, $envelope);
            if ([] !== $formatViolations) {
                foreach ($formatViolations as $message) {
                    $issues[] = ['attributeCode' => $attribute->getCode(), 'kind' => 'invalid_value', 'message' => $message];
                }

                continue;
            }

            // Identifier uniqueness: in-chunk set first (file duplicates the
            // DB cannot see yet), then the per-value DB pre-check.
            $identifierCandidate = $envelope['value'] ?? null;
            if (AttributeType::Identifier === $attribute->getType()
                && \is_string($identifierCandidate) && '' !== $identifierCandidate) {
                $chunkKey = $attribute->getId()->toRfc4122().'|'.$identifierCandidate;
                if (isset($this->chunkIdentifiers[$chunkKey])) {
                    $issues[] = ['attributeCode' => $attribute->getCode(), 'kind' => 'duplicate_identifier', 'message' => \sprintf('Identifier "%s" duplicated within the imported chunk.', $identifierCandidate)];

                    continue;
                }
                $duplicate = $this->core->duplicateIdentifier($object, $attribute, $envelope);
                if (null !== $duplicate) {
                    $issues[] = ['attributeCode' => $attribute->getCode(), 'kind' => 'duplicate_identifier', 'message' => \sprintf('Identifier "%s" is already assigned to another %s.', $duplicate, $object->getObjectType()->getCode())];

                    continue;
                }
                $this->chunkIdentifiers[$chunkKey] = true;
            }

            [$targetLocale, $targetChannel] = $tenant instanceof Tenant
                ? $this->core->routeScope($attribute, $tenant, $write['locale'], $write['channelId'])
                : [$write['locale'], $write['channelId']];

            $existing = $this->scopeIndex[$this->scopeKey($object->getId(), $attribute->getId(), $targetLocale, $targetChannel)] ?? null;
            if ($existing instanceof ObjectValue) {
                $existing->updateValue($envelope);
                $existing->changeProvenance($provenance);

                continue;
            }

            $value = new ObjectValue(
                object: $object,
                attribute: $attribute,
                value: $envelope,
                provenance: $provenance,
                channelId: $targetChannel,
                locale: $targetLocale,
            );
            $this->em->persist($value);
            $this->scopeIndex[$this->scopeKey($object->getId(), $attribute->getId(), $targetLocale, $targetChannel)] = $value;
        }

        return $issues;
    }

    private function scopeKey(Uuid $objectId, Uuid $attributeId, ?string $locale, ?Uuid $channelId): string
    {
        return $objectId->toRfc4122().'|'.$attributeId->toRfc4122().'|'.($locale ?? '').'|'.($channelId?->toRfc4122() ?? '');
    }
}
