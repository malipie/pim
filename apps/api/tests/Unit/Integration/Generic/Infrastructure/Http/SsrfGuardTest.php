<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Infrastructure\Http\SsrfGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Uses literal-IP hosts only — `isAllowed()` validates them directly without
 * DNS, so the suite is deterministic and network-free.
 */
final class SsrfGuardTest extends TestCase
{
    #[Test]
    #[DataProvider('publicUrls')]
    public function allowsPublicHosts(string $url): void
    {
        self::assertTrue(new SsrfGuard()->isAllowed($url));
    }

    #[Test]
    #[DataProvider('blockedUrls')]
    public function blocksPrivateReservedAndUnsupportedUrls(string $url): void
    {
        self::assertFalse(new SsrfGuard()->isAllowed($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicUrls(): iterable
    {
        yield 'public ipv4' => ['https://93.184.216.34/products'];
        yield 'google dns' => ['http://8.8.8.8/'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedUrls(): iterable
    {
        yield 'loopback' => ['https://127.0.0.1/x'];
        yield 'private 10/8' => ['https://10.0.0.5/x'];
        yield 'private 192.168' => ['https://192.168.1.1/x'];
        yield 'private 172.16' => ['https://172.16.0.9/x'];
        yield 'link-local / cloud metadata' => ['https://169.254.169.254/latest/meta-data'];
        yield 'carrier-grade nat' => ['https://100.64.0.1/x'];
        yield 'ipv6 loopback' => ['https://[::1]/x'];
        yield 'non-http scheme' => ['ftp://93.184.216.34/x'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'no host' => ['https:///x'];
        yield 'garbage' => ['not a url at all'];
    }
}
