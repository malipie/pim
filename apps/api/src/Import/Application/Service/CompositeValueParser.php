<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

/**
 * Parses the composite scalar strings the exporter emits for `price` and
 * `metric` attributes back into the JSONB envelope the catalog stores.
 *
 * Export side ({@see \App\Export\Application\Builder\ValueSerializer}):
 *   - price  → `"20.99 EUR"`  (`sprintf('%s %s', amount, currency)`)
 *   - metric → `"0.3 g"`      (`sprintf('%s %s', value, unit)`)
 *
 * Import side (this class) reverses that so the round-trip is loss-free
 * (#1130). The numeric part accepts both `.` and `,` decimal separators
 * (operator-friendly CSVs); the trailing token becomes the currency /
 * unit. A bare number (`"20.99"`) parses with no currency / unit — valid
 * input even if the catalog later prefers a currency.
 *
 * Returns null when the leading token is not numeric, so the validator can
 * surface an InvalidType error instead of persisting garbage.
 */
final class CompositeValueParser
{
    /**
     * @return array{amount: float, currency?: string}|null
     */
    public function parsePrice(string $raw): ?array
    {
        [$amount, $rest] = $this->split($raw);
        if (null === $amount) {
            return null;
        }

        $out = ['amount' => $amount];
        if (null !== $rest) {
            $out['currency'] = strtoupper($rest);
        }

        return $out;
    }

    /**
     * @return array{value: float, unit?: string}|null
     */
    public function parseMetric(string $raw): ?array
    {
        [$value, $rest] = $this->split($raw);
        if (null === $value) {
            return null;
        }

        $out = ['value' => $value];
        if (null !== $rest) {
            $out['unit'] = $rest;
        }

        return $out;
    }

    /**
     * Whether the raw cell is a plain number or a `<number> <token>`
     * composite — drives the validator's accept / reject decision for
     * price + metric columns.
     */
    public function isNumericOrComposite(string $raw): bool
    {
        return null !== $this->split($raw)[0];
    }

    /**
     * Splits a cell into its leading numeric token and the trailing
     * remainder (currency / unit), or [null, null] when the leading token
     * is not numeric.
     *
     * @return array{0: float|null, 1: string|null}
     */
    private function split(string $raw): array
    {
        $trimmed = trim($raw);
        if ('' === $trimmed) {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $trimmed, 2);
        if (false === $parts) {
            return [null, null];
        }

        $numeric = str_replace(',', '.', $parts[0]);
        if (!is_numeric($numeric)) {
            return [null, null];
        }

        $rest = isset($parts[1]) ? trim($parts[1]) : '';

        return [(float) $numeric, '' === $rest ? null : $rest];
    }
}
