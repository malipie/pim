<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Media;

/**
 * IMP2-1.12 — classifies the tokens of an Asset-attribute import cell.
 *
 * A cell may carry a pipe/semicolon/comma/whitespace-separated list mixing
 * existing asset UUIDs and image URLs (supplier feeds — e.g. Avapax — list
 * several `https://…png` in one cell). This resolver only TOKENISES and
 * CLASSIFIES; it performs no I/O:
 *   - UUID            → an existing asset reference (existence + tenant-scope
 *                       validated later against the per-chunk prefetch);
 *   - http(s) URL     → a download job (IMP2-1.12 ImageDownloadHandler);
 *   - anything else   → unresolved (bare filename → ZIP IMP2-1.13 / prefix
 *                       transform IMP2-3.4) — surfaced as ImageNotFound.
 *
 * Shared by {@see \App\Import\Application\Service\ImportObjectCreator}
 * (writes the UUID subset as `{asset_id}`, skips URLs) and
 * {@see \App\Import\Application\Handler\ImportRunHandler} (buffers URL
 * download jobs, logs unresolved tokens).
 */
final class AssetUrlResolver
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Split a raw cell into trimmed, non-empty tokens. Separators: pipe
     * (the exporter glue), semicolon, comma, and any whitespace/newline.
     *
     * @return list<string>
     */
    public function tokenize(string $raw): array
    {
        $parts = preg_split('/[|;,\s]+/u', trim($raw));
        if (false === $parts) {
            return [];
        }

        return array_values(array_filter($parts, static fn (string $token): bool => '' !== $token));
    }

    public function isUuid(string $token): bool
    {
        return 1 === preg_match(self::UUID_PATTERN, $token);
    }

    public function isUrl(string $token): bool
    {
        $lower = strtolower($token);

        return str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://');
    }

    /**
     * Classify a raw cell into its three buckets, order preserved.
     *
     * @return array{uuids: list<string>, urls: list<string>, unresolved: list<string>}
     */
    public function classify(string $raw): array
    {
        $uuids = [];
        $urls = [];
        $unresolved = [];
        foreach ($this->tokenize($raw) as $token) {
            if ($this->isUuid($token)) {
                $uuids[] = $token;
            } elseif ($this->isUrl($token)) {
                $urls[] = $token;
            } else {
                $unresolved[] = $token;
            }
        }

        return ['uuids' => $uuids, 'urls' => $urls, 'unresolved' => $unresolved];
    }
}
