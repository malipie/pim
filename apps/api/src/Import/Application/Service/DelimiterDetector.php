<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

/**
 * Picks the most likely CSV delimiter from a single sample line.
 *
 * Order: `;` (PL/DE/FR locale default) → `,` (US default) → `\t`
 * (TSV exports) → `|` (rare but exists). The detector prefers the
 * delimiter that yields the most consistent column count between the
 * header and the next two rows; ties break on appearance count.
 */
final class DelimiterDetector
{
    private const array CANDIDATES = [';', ',', "\t", '|'];

    public function detect(string $sample): string
    {
        $rawLines = preg_split("/\r\n|\n|\r/", $sample);
        if (false === $rawLines) {
            $rawLines = [];
        }
        $lines = array_values(array_filter($rawLines, static fn (string $line): bool => '' !== $line));
        if ([] === $lines) {
            return ';';
        }

        $header = $lines[0];
        $candidates = [];

        foreach (self::CANDIDATES as $delimiter) {
            $headerCount = substr_count($header, $delimiter);
            if (0 === $headerCount) {
                continue;
            }
            $consistent = 0;
            for ($i = 1; $i < min(3, \count($lines)); ++$i) {
                if (substr_count($lines[$i], $delimiter) === $headerCount) {
                    ++$consistent;
                }
            }
            $candidates[$delimiter] = ['count' => $headerCount, 'consistent' => $consistent];
        }

        if ([] === $candidates) {
            return ';';
        }

        uasort(
            $candidates,
            static function (array $a, array $b): int {
                $byConsistency = $b['consistent'] <=> $a['consistent'];

                return 0 !== $byConsistency ? $byConsistency : ($b['count'] <=> $a['count']);
            },
        );

        return array_key_first($candidates);
    }
}
