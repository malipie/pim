<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Enum\MappingConfidence;
use App\Import\Domain\ReservedMappingTarget;
use App\Import\Domain\SystemColumn;
use App\Import\Domain\ValueObject\ColumnMappingSuggestion;

/**
 * Rules-based column → attribute matcher.
 *
 * Algorithm (spec §5.3):
 *   1. Lower-case the source header and strip non-alphanumeric chars.
 *   2. Look up the normalised key in the alias index → exact match.
 *   3. Else: scan the alias index with Levenshtein < 2 → "did you mean".
 *   4. Else: manual.
 *
 * The matcher is intentionally *not* aware of the user's tenant attribute
 * codes — the dictionary owns the canonical names. The wizard validates
 * the mapping payload against the live ObjectType attributes when the
 * user clicks "Dalej" on Step 2.
 */
final readonly class AutoMapper
{
    public function __construct(
        private MappingDictionaryProvider $dictionary,
    ) {
    }

    /**
     * @param list<string>            $availableAttributeCodes attribute codes on the target ObjectType
     * @param list<string>            $columnHeaders
     * @param list<list<string|null>> $sampleRows
     *
     * @return list<ColumnMappingSuggestion>
     */
    public function map(array $availableAttributeCodes, array $columnHeaders, array $sampleRows): array
    {
        $aliasIndex = $this->dictionary->aliasIndex();
        $availableSet = array_flip($availableAttributeCodes);

        $suggestions = [];
        foreach ($columnHeaders as $index => $header) {
            $sampleValues = $this->sliceSample($sampleRows, $index);

            // Read-only / system export columns (timestamps, status,
            // completeness, …) have no Attribute behind them — suggest
            // Skip so re-importing an export is a one-click round-trip
            // rather than a wall of manual rows (#1130).
            if (SystemColumn::isSystem($header)) {
                $suggestions[] = new ColumnMappingSuggestion(
                    columnIndex: $index,
                    columnHeader: $header,
                    suggestedAttributeCode: null,
                    confidence: MappingConfidence::Skip,
                    sampleValues: $sampleValues,
                );
                continue;
            }

            // Localised export columns carry a dotted locale suffix
            // (`name.pl`). Match on the attribute base so they auto-map to
            // their attribute; the locale is re-derived from the header at
            // validation / persistence time.
            $normalised = $this->normalise(ImportColumnGrammar::baseOf($header));

            if ('' === $normalised) {
                $suggestions[] = new ColumnMappingSuggestion(
                    columnIndex: $index,
                    columnHeader: $header,
                    suggestedAttributeCode: null,
                    confidence: MappingConfidence::Skip,
                    sampleValues: $sampleValues,
                );
                continue;
            }

            // IMP2-1.7: `status` / `enabled` headers map to their reserved
            // targets (object state, not Attribute values), so a re-imported
            // export wires them automatically.
            $reservedTarget = match ($normalised) {
                'status' => ReservedMappingTarget::STATUS,
                'enabled' => ReservedMappingTarget::ENABLED,
                default => null,
            };
            if (null !== $reservedTarget) {
                $suggestions[] = new ColumnMappingSuggestion(
                    columnIndex: $index,
                    columnHeader: $header,
                    suggestedAttributeCode: $reservedTarget,
                    confidence: MappingConfidence::Auto,
                    sampleValues: $sampleValues,
                );
                continue;
            }

            // Exact alias hit.
            if (isset($aliasIndex[$normalised])) {
                $candidate = $aliasIndex[$normalised];
                if (isset($availableSet[$candidate])) {
                    $suggestions[] = new ColumnMappingSuggestion(
                        columnIndex: $index,
                        columnHeader: $header,
                        suggestedAttributeCode: $candidate,
                        confidence: MappingConfidence::Auto,
                        sampleValues: $sampleValues,
                    );
                    continue;
                }
            }

            // Direct hit on attribute code (operator already normalised
            // the header to match — happens with re-exports).
            if (isset($availableSet[$normalised])) {
                $suggestions[] = new ColumnMappingSuggestion(
                    columnIndex: $index,
                    columnHeader: $header,
                    suggestedAttributeCode: $normalised,
                    confidence: MappingConfidence::Auto,
                    sampleValues: $sampleValues,
                );
                continue;
            }

            // Fuzzy match (Levenshtein < 2) on aliases that resolve to
            // available attributes. Cheap because the alias index is
            // bounded by the dictionary size (~150 entries).
            $fuzzyHit = $this->fuzzyHit($normalised, $aliasIndex, $availableSet);
            if (null !== $fuzzyHit) {
                $suggestions[] = new ColumnMappingSuggestion(
                    columnIndex: $index,
                    columnHeader: $header,
                    suggestedAttributeCode: $fuzzyHit,
                    confidence: MappingConfidence::Fuzzy,
                    sampleValues: $sampleValues,
                );
                continue;
            }

            $suggestions[] = new ColumnMappingSuggestion(
                columnIndex: $index,
                columnHeader: $header,
                suggestedAttributeCode: null,
                confidence: MappingConfidence::Manual,
                sampleValues: $sampleValues,
            );
        }

        return $suggestions;
    }

    public function normalise(string $value): string
    {
        $lower = mb_strtolower($value);
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', '', $lower) ?? '';

        return $this->stripDiacritics($stripped);
    }

    /**
     * @param array<string, string> $aliasIndex
     * @param array<string, int>    $availableSet
     */
    private function fuzzyHit(string $needle, array $aliasIndex, array $availableSet): ?string
    {
        $needleLength = mb_strlen($needle);
        // Skip very short headers — Levenshtein on 1-2 chars is noise.
        if ($needleLength < 3) {
            return null;
        }

        foreach ($aliasIndex as $alias => $attributeCode) {
            if (!isset($availableSet[$attributeCode])) {
                continue;
            }
            // Cheap pre-filter: skip aliases with length distance ≥ 2.
            if (abs(mb_strlen($alias) - $needleLength) >= 2) {
                continue;
            }
            if (levenshtein($alias, $needle) < 2) {
                return $attributeCode;
            }
        }

        return null;
    }

    /**
     * @param list<list<string|null>> $sampleRows
     *
     * @return list<string|null>
     */
    private function sliceSample(array $sampleRows, int $columnIndex): array
    {
        $values = [];
        foreach ($sampleRows as $row) {
            $values[] = $row[$columnIndex] ?? null;
        }

        return $values;
    }

    private function stripDiacritics(string $value): string
    {
        $map = [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
            'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
        ];

        return strtr($value, $map);
    }
}
