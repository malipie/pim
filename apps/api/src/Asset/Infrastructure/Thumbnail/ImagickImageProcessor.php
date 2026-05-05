<?php

declare(strict_types=1);

namespace App\Asset\Infrastructure\Thumbnail;

use App\Asset\Application\MimeTypeWhitelist;
use App\Asset\Application\Thumbnail\ImageProcessorInterface;
use App\Asset\Application\Thumbnail\ProcessedImage;
use Imagick;
use ImagickException;
use RuntimeException;

/**
 * Imagick-backed thumbnail generator (#438).
 *
 * For raster inputs (`image/jpeg`, `image/png`, `image/webp`,
 * `image/gif`, `image/avif`) we decode the source, capture native
 * dimensions and write two WebP derivatives sized to fit inside a
 * 200×200 (thumb) and 800×800 (medium) bounding box.
 *
 * For PDFs we set the rasterisation density (150 DPI), read the
 * first page through `pdf[0]`, capture the page count from the
 * un-paged image, then continue down the same fit-inside pipeline as
 * raster images. This requires Ghostscript to be installed alongside
 * the Imagick PHP extension (handled in the API Dockerfile).
 *
 * SVG inputs are skipped — they are vector and the grid renders the
 * original directly. The handler reads `width`/`height` straight from
 * the SVG markup if it carries them; otherwise both stay null.
 */
final class ImagickImageProcessor implements ImageProcessorInterface
{
    public const VARIANT_MIME = 'image/webp';
    public const VARIANT_EXTENSION = 'webp';

    private const THUMB_DIMENSION = 200;
    private const MEDIUM_DIMENSION = 800;
    private const PDF_DENSITY = 150;

    public function process(string $sourcePath, string $mimeType): ProcessedImage
    {
        if (!class_exists(Imagick::class)) {
            throw new RuntimeException('Imagick extension is required for thumbnail generation.');
        }

        if (MimeTypeWhitelist::isPdf($mimeType)) {
            return $this->processPdf($sourcePath);
        }

        if ('image/svg+xml' === $mimeType) {
            throw new RuntimeException('SVG inputs are rendered directly and skip the thumbnail pipeline.');
        }

        if (!MimeTypeWhitelist::isImage($mimeType)) {
            throw new RuntimeException(\sprintf('Unsupported MIME type "%s" for thumbnail generation.', $mimeType));
        }

        return $this->processImage($sourcePath);
    }

    private function processImage(string $sourcePath): ProcessedImage
    {
        try {
            $image = new Imagick();
            $image->readImage($sourcePath);
            $image->autoOrient();

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();

            $thumb = $this->encodeFitInside(clone $image, self::THUMB_DIMENSION);
            $medium = $this->encodeFitInside($image, self::MEDIUM_DIMENSION);
        } catch (ImagickException $e) {
            throw new RuntimeException(\sprintf('Failed to process image: %s.', $e->getMessage()), previous: $e);
        }

        return new ProcessedImage(
            thumbBytes: $thumb,
            mediumBytes: $medium,
            variantMimeType: self::VARIANT_MIME,
            variantExtension: self::VARIANT_EXTENSION,
            width: $width,
            height: $height,
        );
    }

    private function processPdf(string $sourcePath): ProcessedImage
    {
        try {
            $document = new Imagick();
            $document->setResolution(self::PDF_DENSITY, self::PDF_DENSITY);
            $document->readImage($sourcePath);
            $pageCount = $document->getNumberImages();
            $document->clear();

            $firstPage = new Imagick();
            $firstPage->setResolution(self::PDF_DENSITY, self::PDF_DENSITY);
            $firstPage->readImage($sourcePath.'[0]');
            $firstPage->setImageBackgroundColor('white');
            $firstPage = $firstPage->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $firstPage->setImageFormat(self::VARIANT_EXTENSION);

            $width = $firstPage->getImageWidth();
            $height = $firstPage->getImageHeight();

            $thumb = $this->encodeFitInside(clone $firstPage, self::THUMB_DIMENSION);
            $medium = $this->encodeFitInside($firstPage, self::MEDIUM_DIMENSION);
        } catch (ImagickException $e) {
            throw new RuntimeException(\sprintf('Failed to render PDF first page: %s.', $e->getMessage()), previous: $e);
        }

        return new ProcessedImage(
            thumbBytes: $thumb,
            mediumBytes: $medium,
            variantMimeType: self::VARIANT_MIME,
            variantExtension: self::VARIANT_EXTENSION,
            width: $width,
            height: $height,
            pageCount: $pageCount,
        );
    }

    private function encodeFitInside(Imagick $image, int $box): string
    {
        $image->setImageFormat(self::VARIANT_EXTENSION);
        $image->thumbnailImage($box, $box, true);
        $image->setImageCompressionQuality(82);
        $bytes = $image->getImageBlob();
        $image->clear();

        return $bytes;
    }
}
