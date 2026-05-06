<?php

declare(strict_types=1);

namespace App\Asset\Application\Thumbnail;

interface ImageProcessorInterface
{
    /**
     * Decode the source bytes, resize to the two derivative sizes
     * (200×200 thumb + 800×800 medium, both fit-inside) and return the
     * encoded bytes. Throws on unsupported source.
     */
    public function process(string $sourcePath, string $mimeType): ProcessedImage;
}
