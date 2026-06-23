<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

/**
 * Splits a single import cell that packs several values into one field
 * (multiselect option codes, `__category__` code lists).
 *
 * Accepts BOTH the PIM exporter's pipe glue
 * ({@see \App\Catalog\Application\ValueSerializer} `MULTI_VALUE_GLUE`) AND
 * newlines / carriage returns, because external exports (IdoSell/IAI and
 * other e-commerce platforms) wrap multi-value lists with embedded newlines
 * inside a single quoted CSV cell (#1719). The pipe path stays fully
 * round-trip compatible with PIM's own exports.
 *
 * Tokens are trimmed and empties dropped. Scalar attributes (`text`,
 * `select`, `wysiwyg`) deliberately do NOT use this — there a newline is
 * legitimate content, not a separator.
 */
final class MultiValueSplitter
{
    /**
     * @return list<string>
     */
    public static function split(string $raw): array
    {
        $parts = preg_split('/[|\r\n]+/u', $raw);
        if (false === $parts) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $token): bool => '' !== $token,
        ));
    }
}
