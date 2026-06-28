<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;

/**
 * The parsed `pagination` envelope of a {@see \App\Integration\Generic\Domain\Entity\RemoteEndpoint}
 * (ADR-0022, epic APIC, ticket APIC-P2-03).
 *
 * Every per-strategy knob carries a sensible default so a minimal
 * `{"strategy":"offset"}` works out of the box. Unknown strategy values fall
 * back to `none` (a single page) rather than erroring a sync.
 */
final readonly class PaginationConfig
{
    public function __construct(
        public PaginationStrategyName $strategy,
        public int $limit = 100,
        public string $limitParam = 'limit',
        public string $offsetParam = 'offset',
        public string $pageParam = 'page',
        public int $startPage = 1,
        public string $cursorParam = 'cursor',
        public string $cursorPath = '$.next_cursor',
        public string $linkRel = 'next',
    ) {
    }

    /**
     * @param array<string, mixed> $pagination
     */
    public static function fromArray(array $pagination): self
    {
        $strategy = PaginationStrategyName::tryFrom(self::str($pagination, 'strategy', 'none'))
            ?? PaginationStrategyName::None;

        return new self(
            strategy: $strategy,
            limit: self::int($pagination, 'limit', 100),
            limitParam: self::str($pagination, 'limitParam', 'limit'),
            offsetParam: self::str($pagination, 'offsetParam', 'offset'),
            pageParam: self::str($pagination, 'pageParam', 'page'),
            startPage: self::int($pagination, 'startPage', 1, 0),
            cursorParam: self::str($pagination, 'cursorParam', 'cursor'),
            cursorPath: self::str($pagination, 'cursorPath', '$.next_cursor'),
            linkRel: self::str($pagination, 'linkRel', 'next'),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function str(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function int(array $data, string $key, int $default, int $min = 1): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) && $value >= $min ? $value : $default;
    }
}
