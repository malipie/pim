<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Media;

use App\Import\Application\Service\Media\SsrfGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * IMP2-1.12 / IMP2-2.8 — SSRF allow/deny. Literal-IP hosts are checked without
 * DNS so these assertions are network-independent (deterministic in CI).
 */
final class SsrfGuardTest extends TestCase
{
    private SsrfGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new SsrfGuard();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blocked(): iterable
    {
        yield 'loopback v4' => ['http://127.0.0.1/x.png'];
        yield 'loopback name-literal' => ['https://127.0.0.53/x.png'];
        yield 'private 10/8' => ['http://10.0.0.5/x.png'];
        yield 'private 192.168' => ['http://192.168.1.10/x.png'];
        yield 'private 172.16' => ['http://172.16.5.5/x.png'];
        yield 'link-local 169.254' => ['http://169.254.169.254/latest/meta-data'];
        yield 'cgnat 100.64' => ['http://100.64.0.1/x.png'];
        yield 'ipv6 loopback' => ['http://[::1]/x.png'];
        yield 'non-http scheme' => ['ftp://93.184.216.34/x.png'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'no host' => ['/relative/path.png'];
    }

    #[Test]
    #[DataProvider('blocked')]
    public function rejectsNonPublicOrUnsupported(string $url): void
    {
        self::assertFalse($this->guard->isAllowed($url), $url.' must be blocked');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowed(): iterable
    {
        // Literal public IPs — no DNS, deterministic.
        yield 'public v4 literal' => ['https://93.184.216.34/img/a.png'];
        yield 'public v4 http' => ['http://8.8.8.8/x.png'];
    }

    #[Test]
    #[DataProvider('allowed')]
    public function allowsPublicLiteralHosts(string $url): void
    {
        self::assertTrue($this->guard->isAllowed($url), $url.' must be allowed');
    }
}
