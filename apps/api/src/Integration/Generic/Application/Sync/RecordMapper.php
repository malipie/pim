<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Sync;

use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Infrastructure\Http\RecordSelector;

/**
 * Reduces a remote record to its PIM upsert shape using a connection's inbound
 * field mappings (APIC-P3-04).
 *
 * The match key is the inbound mapping flagged `isMatchKey`; its remote path
 * yields the match value. The other inbound mappings yield the attribute
 * values. Only scalar values are carried (a composite remote field cannot map
 * 1:1 to a scalar PIM target — the validator already warns, here it is skipped).
 * Returns null when there is no match key or the record has no match value.
 */
final readonly class RecordMapper
{
    public function __construct(private RecordSelector $selector)
    {
    }

    /**
     * @param array<array-key, mixed> $record
     * @param list<FieldMapping>      $mappings
     */
    public function map(array $record, array $mappings): ?MappedRecord
    {
        $inbound = array_values(array_filter(
            $mappings,
            static fn (FieldMapping $m): bool => $m->getDirection()->appliesInbound(),
        ));

        $matchMapping = null;
        foreach ($inbound as $mapping) {
            if ($mapping->isMatchKey()) {
                $matchMapping = $mapping;
                break;
            }
        }

        if (null === $matchMapping) {
            return null;
        }

        $rawMatch = $this->scalar($this->selector->value($record, $matchMapping->getRemoteFieldPath()));
        if (null === $rawMatch) {
            return null;
        }
        $matchValue = (string) $rawMatch;
        if ('' === $matchValue) {
            return null;
        }

        $values = [];
        foreach ($inbound as $mapping) {
            $raw = $this->scalar($this->selector->value($record, $mapping->getRemoteFieldPath()));
            if (null !== $raw) {
                $values[$mapping->getPimTarget()] = $raw;
            }
        }

        return new MappedRecord($matchMapping->getPimTarget(), $matchValue, $values);
    }

    private function scalar(mixed $value): bool|float|int|string|null
    {
        return \is_scalar($value) ? $value : null;
    }
}
