<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Application\ConnectionCredentialsCipher;
use App\Integration\Generic\Application\Validation\DescriptorValidator;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Exception\InvalidDescriptorException;
use App\Integration\Generic\Domain\Exception\RemoteRequestFailedException;
use App\Integration\Generic\Domain\Exception\SsrfBlockedException;
use App\Integration\Generic\Infrastructure\Http\GenericRestClient;
use App\Integration\Generic\Infrastructure\Http\SsrfGuard;
use App\Shared\Application\Crypto\EncryptedSecret;
use App\Shared\Application\Crypto\EncryptionServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * APIC-P5-02 — adversarial SSRF + descriptor-validation slice. Hammers the
 * pre-filter ({@see SsrfGuard}) and the descriptor wall ({@see DescriptorValidator})
 * with the classic SSRF vectors and asserts each is blocked, that the
 * {@see GenericRestClient} surfaces the block, and that a failed request never
 * leaks credentials into the logs.
 *
 * The connection-time + per-redirect peer-IP re-validation (redirect-to-private,
 * DNS-rebinding) is Symfony's {@see \Symfony\Component\HttpClient\NoPrivateNetworkHttpClient}
 * — wired as `generic.ssrf_safe_http_client` (services.yaml) and exercised by the
 * upstream component; here the pre-filter's own DNS-resolution check covers
 * hostnames that resolve into private space.
 */
#[CoversClass(SsrfGuard::class)]
final class SsrfAdversarialTest extends TestCase
{
    /**
     * @param non-empty-string $url
     */
    #[DataProvider('blockedVectors')]
    #[Test]
    public function ssrfGuardBlocksAdversarialVectors(string $url): void
    {
        self::assertFalse(new SsrfGuard()->isAllowed($url), \sprintf('SSRF vector should be blocked: %s', $url));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function blockedVectors(): iterable
    {
        // Loopback / unspecified / reserved (FILTER_FLAG_NO_RES_RANGE).
        yield 'ipv4 loopback' => ['https://127.0.0.1/x'];
        yield 'ipv4 unspecified' => ['https://0.0.0.0/x'];
        yield 'localhost name' => ['https://localhost/x'];
        // Cloud metadata (link-local 169.254/16).
        yield 'cloud metadata' => ['https://169.254.169.254/latest/meta-data/'];
        yield 'metadata via userinfo trick' => ['https://trusted.example.com@169.254.169.254/'];
        // Private RFC1918.
        yield 'private 10/8' => ['http://10.1.2.3/x'];
        yield 'private 192.168' => ['http://192.168.0.1/x'];
        yield 'private 172.16' => ['http://172.16.5.5/x'];
        // IPv6 loopback / ULA / link-local (bracketed).
        yield 'ipv6 loopback' => ['https://[::1]/x'];
        yield 'ipv6 ula' => ['https://[fc00::1]/x'];
        yield 'ipv6 link-local' => ['https://[fe80::1]/x'];
        // Unsupported schemes.
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'ftp scheme' => ['ftp://10.0.0.1/x'];
        yield 'gopher scheme' => ['gopher://127.0.0.1:70/_x'];
        // Malformed / host-less.
        yield 'no host' => ['https:///x'];
        yield 'scheme only' => ['notaurl'];
    }

    #[Test]
    public function ssrfGuardAllowsPublicLiteralIp(): void
    {
        // Control — a public IP literal (no DNS) is permitted by the pre-filter.
        self::assertTrue(new SsrfGuard()->isAllowed('https://93.184.216.34/products'));
    }

    /**
     * @param non-empty-string $template
     */
    #[DataProvider('hostInjectionTemplates')]
    #[Test]
    public function descriptorValidatorBlocksPathHostInjection(string $template): void
    {
        $this->expectException(InvalidDescriptorException::class);
        new DescriptorValidator()->assertValidPathTemplate($template);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function hostInjectionTemplates(): iterable
    {
        yield 'embedded scheme/host' => ['http://169.254.169.254/meta'];
        yield 'protocol-relative' => ['//169.254.169.254/meta'];
        yield 'backslash trick' => ['/products\\..\\admin'];
    }

    /**
     * @param non-empty-string $baseUrl
     */
    #[DataProvider('invalidBaseUrls')]
    #[Test]
    public function descriptorValidatorBlocksNonHttpBaseUrls(string $baseUrl): void
    {
        $this->expectException(InvalidDescriptorException::class);
        new DescriptorValidator()->assertValidBaseUrl($baseUrl);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function invalidBaseUrls(): iterable
    {
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com/x'];
        yield 'no host' => ['https:///x'];
    }

    #[Test]
    public function genericRestClientThrowsOnBlockedUrl(): void
    {
        $client = new GenericRestClient(
            new MockHttpClient(new MockResponse('should-not-run')),
            new SsrfGuard(),
            $this->cipher(),
        );

        $this->expectException(SsrfBlockedException::class);
        $client->request(new Connection('m', 'M', 'https://api.test'), 'GET', 'https://169.254.169.254/latest/');
    }

    #[Test]
    public function failedRequestDoesNotLeakCredentialsToLogs(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->lines[] = (string) $message.' '.json_encode($context);
            }
        };

        $client = new GenericRestClient(
            new MockHttpClient(new MockResponse('', ['error' => 'connection reset'])),
            new SsrfGuard(),
            $this->cipher(),
            $logger,
        );

        $connection = new Connection('shop', 'Shop', 'https://93.184.216.34');
        $connection->setDefaultHeaders(['X-Partner-Token' => 'SUPER-SECRET-TOKEN-123']);

        try {
            $client->request($connection, 'GET', 'https://93.184.216.34/products');
            self::fail('Expected a transport failure.');
        } catch (RemoteRequestFailedException) {
            // expected
        }

        self::assertNotEmpty($logger->lines, 'the transport failure should be logged');
        foreach ($logger->lines as $line) {
            self::assertStringNotContainsString('SUPER-SECRET-TOKEN-123', $line, 'credentials must never reach the logs');
        }
    }

    private function cipher(): ConnectionCredentialsCipher
    {
        return new ConnectionCredentialsCipher(new class implements EncryptionServiceInterface {
            public function encrypt(string $plaintext): EncryptedSecret
            {
                return new EncryptedSecret(base64_encode($plaintext), 1);
            }

            public function decrypt(EncryptedSecret $secret): string
            {
                $decoded = base64_decode($secret->ciphertext, true);

                return false === $decoded ? '' : $decoded;
            }

            public function needsRotation(EncryptedSecret $secret): bool
            {
                return false;
            }
        });
    }
}
