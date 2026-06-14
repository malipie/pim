<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Exception;

use RuntimeException;

/**
 * IMP2-1.12 — thrown by {@see \App\Asset\Contracts\AssetIngestorInterface}
 * when the supplied bytes are not an accepted image (magic-byte sniff:
 * only jpg/jpeg, png, webp are ingestible by the import media path). The
 * import handler maps this to an `image_format_unsupported` row finding.
 */
final class UnsupportedMediaFormatException extends RuntimeException
{
    public static function forFilename(string $filename): self
    {
        return new self(\sprintf('File "%s" is not an accepted image (jpg/png/webp expected).', $filename));
    }
}
