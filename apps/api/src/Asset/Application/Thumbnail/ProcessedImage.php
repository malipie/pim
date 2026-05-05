<?php

declare(strict_types=1);

namespace App\Asset\Application\Thumbnail;

/**
 * Result of running a source asset through the thumbnail pipeline:
 * the encoded bytes for both derivative sizes plus the dimensions
 * captured from the source.
 */
final readonly class ProcessedImage
{
    public function __construct(
        public string $thumbBytes,
        public string $mediumBytes,
        public string $variantMimeType,
        public string $variantExtension,
        public ?int $width = null,
        public ?int $height = null,
        public ?int $pageCount = null,
    ) {
    }
}
