<?php

declare(strict_types=1);

namespace App\Import\Domain\Message;

/**
 * IMP2-1.12 — one Asset-attribute cell's media work, scoped to a single
 * (object, attribute, locale, channel) target. Carries the pre-classified
 * existing asset UUIDs (validated tenant-scoped by the handler) plus the
 * image URLs to download; the handler writes ONE merged `{asset_id}` envelope
 * (existing + downloaded) so there is no read-modify-write of the value.
 *
 * Ids are RFC 4122 strings to keep the Messenger payload primitive.
 */
final readonly class ImageDownloadJob
{
    /**
     * @param list<string> $existingUuids already-classified existing asset UUIDs (RFC 4122)
     * @param list<string> $urls          http(s) image URLs to download
     */
    public function __construct(
        public string $objectId,
        public string $attributeCode,
        public ?string $locale,
        public ?string $channelId,
        public array $existingUuids,
        public array $urls,
        public int $rowNumber,
        public ?string $sku,
    ) {
    }
}
