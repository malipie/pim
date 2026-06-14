<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

/**
 * IMP2-2.1 — deduplicates a file's column headers so duplicate non-empty
 * labels (legal per ADR D12 — Bosch/Avapax feeds repeat header names) survive
 * as distinct keys instead of colliding in the header-keyed row contract.
 *
 * Shared by {@see ImportRowReader} (data rows) and {@see FileParserService}
 * (preview) so the wizard maps against EXACTLY the labels the run will key
 * cells by. Duplicates gain a `#2`, `#3`, … suffix in occurrence order; blank
 * labels stay blank (positional placeholder — the readers drop blank-header
 * columns from the assoc row, matching the pre-2.1 behaviour).
 */
final class HeaderNormalizer
{
    /**
     * @param list<?string> $rawHeaders position → raw label
     *
     * @return list<string> position → unique label (blanks kept as '')
     */
    public static function deduplicate(array $rawHeaders): array
    {
        /** @var array<string, true> $used labels already emitted */
        $used = [];
        /** @var array<string, int> $counts per-base occurrence counter */
        $counts = [];
        $out = [];
        foreach ($rawHeaders as $raw) {
            $base = null === $raw ? '' : trim($raw);
            if ('' === $base) {
                $out[] = '';

                continue;
            }
            $n = ($counts[$base] ?? 0) + 1;
            $candidate = $n > 1 ? $base.'#'.$n : $base;
            // Resolve a collision with an already-emitted label: this covers a
            // generated `base#k` clashing with a user-supplied literal column of
            // the same name (e.g. raw [color, color, color#2] — the second
            // `color` would otherwise become `color#2` and silently overwrite
            // the real one). Bump the suffix until the key is free.
            while (isset($used[$candidate])) {
                ++$n;
                $candidate = $base.'#'.$n;
            }
            $counts[$base] = $n;
            $used[$candidate] = true;
            $out[] = $candidate;
        }

        return $out;
    }
}
