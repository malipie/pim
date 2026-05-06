<?php

declare(strict_types=1);

namespace App\Asset\Application;

/**
 * Single source of truth for the MIME types accepted by the upload
 * pipeline (#438). Mirrors the frontend constant in
 * `packages/shared-types/src/asset.ts` — a snapshot test in
 * `MimeTypeWhitelistTest` keeps the two ends in sync.
 */
final class MimeTypeWhitelist
{
    /**
     * @var array<int, string>
     */
    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'image/avif',
    ];

    /**
     * @var array<int, string>
     */
    public const PDF_MIME_TYPES = [
        'application/pdf',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [...self::IMAGE_MIME_TYPES, ...self::PDF_MIME_TYPES];
    }

    public static function isImage(string $mimeType): bool
    {
        return \in_array($mimeType, self::IMAGE_MIME_TYPES, true);
    }

    public static function isPdf(string $mimeType): bool
    {
        return \in_array($mimeType, self::PDF_MIME_TYPES, true);
    }

    public static function isAccepted(string $mimeType): bool
    {
        return \in_array($mimeType, self::all(), true);
    }
}
