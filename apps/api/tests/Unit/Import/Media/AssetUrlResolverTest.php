<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Media;

use App\Import\Application\Service\Media\AssetUrlResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * IMP2-1.12 — token classification for Asset-attribute cells: UUID (existing
 * asset), http(s) URL (download job), anything else (unresolved → ZIP/transform).
 */
final class AssetUrlResolverTest extends TestCase
{
    private AssetUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AssetUrlResolver();
    }

    #[Test]
    public function splitsMixedListOnEverySeparator(): void
    {
        $uuid = '019ebfbb-ecf6-7590-879b-d079f010cbae';
        $raw = "$uuid | https://cdn.example.com/a.png ; https://cdn.example.com/b.jpg ,bare-file.png\nhttps://cdn.example.com/c.webp";

        $result = $this->resolver->classify($raw);

        self::assertSame([$uuid], $result['uuids']);
        self::assertSame([
            'https://cdn.example.com/a.png',
            'https://cdn.example.com/b.jpg',
            'https://cdn.example.com/c.webp',
        ], $result['urls']);
        self::assertSame(['bare-file.png'], $result['unresolved']);
    }

    #[Test]
    public function classifiesHttpAndHttpsCaseInsensitively(): void
    {
        self::assertTrue($this->resolver->isUrl('HTTP://x/y.png'));
        self::assertTrue($this->resolver->isUrl('https://x/y.png'));
        self::assertFalse($this->resolver->isUrl('ftp://x/y.png'));
        self::assertFalse($this->resolver->isUrl('/local/y.png'));
    }

    #[Test]
    public function recognisesUuidTokensOnly(): void
    {
        self::assertTrue($this->resolver->isUuid('019ebfbb-ecf6-7590-879b-d079f010cbae'));
        self::assertFalse($this->resolver->isUuid('not-a-uuid'));
        self::assertFalse($this->resolver->isUuid('https://x/y.png'));
    }

    #[Test]
    public function emptyCellYieldsNothing(): void
    {
        $result = $this->resolver->classify('   ');
        self::assertSame([], $result['uuids']);
        self::assertSame([], $result['urls']);
        self::assertSame([], $result['unresolved']);
    }

    #[Test]
    public function pureUrlListHasNoUuids(): void
    {
        $result = $this->resolver->classify('https://a/1.png|https://a/2.png');
        self::assertSame([], $result['uuids']);
        self::assertCount(2, $result['urls']);
    }
}
