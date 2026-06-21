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
 * Algorithm (spec §5.3, extended for export round-trips #1683):
 *   1. Lower-case the source header and strip non-alphanumeric chars.
 *   2. Direct hit on a target attribute code (both sides normalised) →
 *      exact. This is what makes re-importing an export auto-map: the
 *      export writes raw attribute codes (`material_composition`) as
 *      headers and `normalise()` strips the underscores on both sides.
 *   3. Exact hit on a target attribute label (`{pl,en,…}`) → exact, so
 *      hand-built files with human-readable headers also auto-map.
 *   4. Look up the normalised key in the alias dictionary → exact match.
 *   5. Else: scan aliases ∪ codes ∪ labels with Levenshtein < 2 → "did
 *      you mean".
 *   6. Else: manual.
 *
 * The dictionary owns generic PL/EN aliases; codes and labels come from
 * the live ObjectType so tenant-specific attributes round-trip cleanly.
 * The wizard validates the mapping payload against the live ObjectType
 * attributes when the user clicks "Dalej" on Step 2.
 */
final readonly class AutoMapper
{
    public function __construct(
        private MappingDictionaryProvider $dictionary,
    ) {
    }

    /**
     * @param list<string>                $availableAttributeCodes attribute codes on the target ObjectType
     * @param list<string>                $columnHeaders
     * @param list<list<string|null>>     $sampleRows
     * @param array<string, list<string>> $attributeLabelsByCode   code → its localised labels (`{pl,en,…}` values)
     *
     * @return list<ColumnMappingSuggestion>
     */
    public function map(
        array $availableAttributeCodes,
        array $columnHeaders,
        array $sampleRows,
        array $attributeLabelsByCode = [],
    ): array {
        $aliasIndex = $this->dictionary->aliasIndex();
        $availableSet = array_flip($availableAttributeCodes);

        // Index target codes by their normalised form so a header that
        // normalises identically (snake_case underscores stripped on both
        // sides) maps straight through — the core export round-trip fix.
        $availableByNormalised = [];
        foreach ($availableAttributeCodes as $code) {
            $normalisedCode = $this->normalise($code);
            if ('' !== $normalisedCode) {
                $availableByNormalised[$normalisedCode] = $code;
            }
        }

        // Index target labels by normalised form. Drop collisions (two
        // attributes sharing a normalised label) — we never auto-guess an
        // ambiguous label, the user picks it manually.
        $labelByNormalised = $this->buildLabelIndex($attributeLabelsByCode, $availableSet);

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
                'parentsku' => ReservedMappingTarget::PARENT_SKU,
                'variantaxes' => ReservedMappingTarget::VARIANT_AXES,
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

            // Direct hit on a target attribute code (both sides
            // normalised). Strongest signal — a column whose code equals a
            // real attribute wins over a generic dictionary alias. This is
            // what makes re-importing an export a one-click round-trip
            // (#1683): the export header is the raw code.
            if (isset($availableByNormalised[$normalised])) {
                $suggestions[] = new ColumnMappingSuggestion(
                    columnIndex: $index,
                    columnHeader: $header,
                    suggestedAttributeCode: $availableByNormalised[$normalised],
                    confidence: MappingConfidence::Auto,
                    sampleValues: $sampleValues,
                );
                continue;
            }

            // Exact hit on a target attribute label (`{pl,en,…}`), so files
            // with human-readable headers ("Material composition") auto-map.
            if (isset($labelByNormalised[$normalised])) {
                $suggestions[] = new ColumnMappingSuggestion(
                    columnIndex: $index,
                    columnHeader: $header,
                    suggestedAttributeCode: $labelByNormalised[$normalised],
                    confidence: MappingConfidence::Auto,
                    sampleValues: $sampleValues,
                );
                continue;
            }

            // Exact alias hit (generic PL/EN dictionary).
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

            // Fuzzy match (Levenshtein < 2) across dictionary aliases,
            // target codes and target labels that resolve to available
            // attributes — catches typos in hand-built files. All candidate
            // sets are bounded (dictionary ~150, codes/labels per type), so
            // the scan stays cheap.
            $fuzzyHit = $this->fuzzyHit(
                $normalised,
                $aliasIndex + $availableByNormalised + $labelByNormalised,
                $availableSet,
            );
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
     * Reverse index: normalised label → attribute code, restricted to
     * available attributes. Normalised labels shared by more than one
     * attribute are dropped — an ambiguous label is never auto-guessed.
     *
     * @param array<string, list<string>> $attributeLabelsByCode
     * @param array<string, int>          $availableSet
     *
     * @return array<string, string>
     */
    private function buildLabelIndex(array $attributeLabelsByCode, array $availableSet): array
    {
        /** @var array<string, string> $index */
        $index = [];
        /** @var array<string, true> $ambiguous */
        $ambiguous = [];

        foreach ($attributeLabelsByCode as $code => $labels) {
            if (!isset($availableSet[$code])) {
                continue;
            }
            foreach ($labels as $label) {
                $normalised = $this->normalise($label);
                if ('' === $normalised || isset($ambiguous[$normalised])) {
                    continue;
                }
                if (isset($index[$normalised]) && $index[$normalised] !== $code) {
                    // Collision across distinct attributes → ambiguous.
                    unset($index[$normalised]);
                    $ambiguous[$normalised] = true;

                    continue;
                }
                $index[$normalised] = $code;
            }
        }

        return $index;
    }

    /**
     * @param array<string, string> $candidateIndex normalised candidate → attribute code
     * @param array<string, int>    $availableSet
     */
    private function fuzzyHit(string $needle, array $candidateIndex, array $availableSet): ?string
    {
        $needleLength = mb_strlen($needle);
        // Skip very short headers — Levenshtein on 1-2 chars is noise.
        if ($needleLength < 3) {
            return null;
        }

        foreach ($candidateIndex as $candidate => $attributeCode) {
            if (!isset($availableSet[$attributeCode])) {
                continue;
            }
            // Cheap pre-filter: skip candidates with length distance ≥ 2.
            if (abs(mb_strlen($candidate) - $needleLength) >= 2) {
                continue;
            }
            if (levenshtein($candidate, $needle) < 2) {
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
