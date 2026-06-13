<?php

declare(strict_types=1);

namespace App\Import\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * IMP2-1.6 (#1469) — result of the column grammar parse. `unknownSuffix`
 * carries the unmatched modifier so the validator can emit a precise
 * column-level error instead of writing a bogus locale row. `channelId`
 * is resolved once when the tenant registry is built (not per row), so the
 * write path never re-queries the channel store.
 */
final readonly class ParsedColumnHeader
{
    public function __construct(
        public string $base,
        public ?string $locale,
        public ?string $channelCode,
        public ?Uuid $channelId = null,
        public ?string $unknownSuffix = null,
        public bool $localeChannelCollision = false,
    ) {
    }

    public static function unknownSuffix(string $base, string $suffix): self
    {
        return new self($base, null, null, null, unknownSuffix: $suffix);
    }
}
