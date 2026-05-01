<?php

declare(strict_types=1);

namespace App\Catalog\Application\Migration;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Service\AttributeTypeMigrationCompatibility;
use App\Catalog\Domain\Service\MigrationCompatibility;
use Doctrine\DBAL\Connection;

/**
 * UI-08.6 (#261) — analyzes the corpus of `object_values` for an
 * Attribute and produces a {@see AttributeMigrationAnalysis} the admin
 * UI renders in the impact analyzer (`#UI-08.12`).
 *
 * Strategy:
 *   1. Query distinct values from `object_values` for this attribute.
 *   2. Compute counts per distinct value.
 *   3. Apply the mapping plan + auto-detect rules:
 *      - exact match → use mapping target.
 *      - case-insensitive whitespace-trimmed match → suggest mapping.
 *      - otherwise → unmapped (subject to unmappedAction at execute
 *        time).
 *
 * Read-only — never mutates the database. Tenant scope inherited from
 * the attribute (TenantFilter applied by Doctrine when the query goes
 * through the EM, but here we go through DBAL for performance — so we
 * narrow by `attribute_id` which is already tenant-scoped via the
 * attribute row itself).
 */
final readonly class AttributeMigrationPlanner
{
    public function __construct(
        private Connection $connection,
        private AttributeTypeMigrationCompatibility $compatibility,
    ) {
    }

    public function analyze(Attribute $attribute, AttributeMigrationPlan $plan): AttributeMigrationAnalysis
    {
        $compat = $this->compatibility->evaluate($attribute->getType(), $plan->targetType);

        if (MigrationCompatibility::Blocked === $compat) {
            return new AttributeMigrationAnalysis(
                compatibility: MigrationCompatibility::Blocked->value,
                rowCount: 0,
                distinctValues: 0,
                mappings: [],
                unmapped: [],
                forceRequired: false,
                blockedReason: \sprintf(
                    'Migration from "%s" to "%s" is not supported in MVP.',
                    $attribute->getType()->value,
                    $plan->targetType->value,
                ),
            );
        }

        $forceRequired = MigrationCompatibility::RequiresForce === $compat && !$plan->force;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT value, COUNT(*) AS cnt FROM object_values WHERE attribute_id = ? GROUP BY value',
            [$attribute->getId()->toRfc4122()],
        );

        $rowCount = 0;
        $distinct = 0;
        $mappingIndex = $this->indexMappings($plan->mappings);
        $appliedMappings = [];
        $unmapped = [];

        foreach ($rows as $row) {
            $rawValue = $row['value'];
            $rawCount = $row['cnt'];
            $count = \is_scalar($rawCount) ? (int) $rawCount : 0;
            $valueString = $this->extractStringValue($rawValue);
            if (null === $valueString) {
                continue;
            }

            ++$distinct;
            $rowCount += $count;

            $normalizedKey = $this->normalize($valueString);
            $mappedTo = $mappingIndex[$normalizedKey] ?? null;
            if (null !== $mappedTo) {
                $appliedMappings[] = [
                    'from' => $valueString,
                    'to' => $mappedTo,
                    'count' => $count,
                ];
            } else {
                $unmapped[] = [
                    'value' => $valueString,
                    'count' => $count,
                ];
            }
        }

        return new AttributeMigrationAnalysis(
            compatibility: $compat->value,
            rowCount: $rowCount,
            distinctValues: $distinct,
            mappings: $appliedMappings,
            unmapped: $unmapped,
            forceRequired: $forceRequired,
        );
    }

    /**
     * @param list<array{from: string, to: string}> $mappings
     *
     * @return array<string, string> normalized key → target string
     */
    private function indexMappings(array $mappings): array
    {
        $idx = [];
        foreach ($mappings as $entry) {
            $idx[$this->normalize($entry['from'])] = $entry['to'];
        }

        return $idx;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function extractStringValue(mixed $rawValue): ?string
    {
        $decoded = match (true) {
            \is_array($rawValue) => $rawValue,
            \is_string($rawValue) => json_decode($rawValue, true),
            default => null,
        };
        if (!\is_array($decoded)) {
            return null;
        }

        // Hybrid value shapes per ADR-006: text → {value: string}, select →
        // {option_code: string}, multiselect → {option_codes: [string,...]}.
        if (\array_key_exists('value', $decoded) && \is_scalar($decoded['value'])) {
            return (string) $decoded['value'];
        }
        if (\array_key_exists('option_code', $decoded) && \is_string($decoded['option_code'])) {
            return $decoded['option_code'];
        }
        if (\array_key_exists('option_codes', $decoded) && \is_array($decoded['option_codes'])) {
            $first = $decoded['option_codes'][0] ?? null;

            return \is_string($first) ? $first : null;
        }

        return null;
    }
}
