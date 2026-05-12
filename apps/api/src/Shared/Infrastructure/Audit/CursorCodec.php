<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use JsonException;

use const JSON_THROW_ON_ERROR;

/**
 * HARD-05 — opaque cursor encoder/decoder for audit log endpoints.
 *
 * The audit-log endpoints (`/api/products/{id}/audit-log`,
 * `/api/object_types/{id}/audit_log`) used a plain LIMIT with no
 * forward continuation. After a year of production volume per-product
 * audit history can grow into thousands of entries — the UI had no
 * way to reach beyond the most recent N changes.
 *
 * Cursor shape: `(created_at, id)` tuple. `created_at` alone is not
 * stable (two events in the same second tie); pairing it with the
 * monotonically-increasing `id` of the audit row gives a total
 * ordering that survives concurrent writes.
 *
 * Encoding: base64 of a compact JSON `{t: ISO timestamp, i: integer}`.
 * The result is opaque to clients — they round-trip it as `?cursor=`.
 * Invalid cursors decode to `null` (the controller skips the cursor
 * predicate and serves the first page) — frontends survive operator
 * fat-fingering or stale URLs without error.
 */
final class CursorCodec
{
    public static function encode(string $createdAt, int $id): string
    {
        return base64_encode(json_encode(['t' => $createdAt, 'i' => $id], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{t: string, i: int}|null
     */
    public static function decode(?string $cursor): ?array
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }
        $raw = base64_decode($cursor, true);
        if (false === $raw) {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!\is_array($decoded)) {
            return null;
        }
        $t = $decoded['t'] ?? null;
        $i = $decoded['i'] ?? null;
        if (!\is_string($t) || !\is_int($i)) {
            return null;
        }

        return ['t' => $t, 'i' => $i];
    }
}
