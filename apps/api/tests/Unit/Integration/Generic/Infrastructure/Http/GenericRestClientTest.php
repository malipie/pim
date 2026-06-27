<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Application\ConnectionCredentialsCipher;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Exception\SsrfBlockedException;
use App\Integration\Generic\Infrastructure\Http\GenericRestClient;
use App\Integration\Generic\Infrastructure\Http\SsrfGuard;
use App\Shared\Application\Crypto\EncryptedSecret;
use App\Shared\Application\Crypto\EncryptionServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Uses Symfony's MockHttpClient (no network) + a literal public-IP URL so the
 * real SsrfGuard passes without DNS. Asserts per-AuthType header injection, the
 * SSRF pre-filter, and response parsing.
 */
final class GenericRestClientTest extends TestCase
{
    private const string PUBLIC_URL = 'https://93.184.216.34/products';

    #[Test]
    public function injectsApiKeyHeaderAndParsesResponse(): void
    {
        $mock = new MockResponse('{"items":[]}', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
        $client = $this->client($mock);

        $connection = new Connection('idosell', 'IdoSell', 'https://api.idosell.com', AuthType::ApiKey);
        $this->cipher()->apply($connection, ['header' => 'X-Api-Key', 'value' => 's3cr3t']);

        $response = $client->request($connection, 'GET', self::PUBLIC_URL);

        self::assertSame(200, $response->statusCode);
        self::assertTrue($response->isSuccessful());
        self::assertSame('{"items":[]}', $response->body);
        self::assertSame('application/json', $response->contentType());
        self::assertContains('X-Api-Key: s3cr3t', $this->sentHeaders($mock));
    }

    #[Test]
    public function injectsBearerHeader(): void
    {
        $mock = new MockResponse('{}', ['http_code' => 200]);
        $client = $this->client($mock);

        $connection = new Connection('shopify', 'Shopify', 'https://x.myshopify.com', AuthType::Bearer);
        $this->cipher()->apply($connection, ['token' => 'abc123']);

        $client->request($connection, 'GET', self::PUBLIC_URL);

        self::assertContains('Authorization: Bearer abc123', $this->sentHeaders($mock));
    }

    #[Test]
    public function noneAuthSendsNoAuthorizationHeader(): void
    {
        $mock = new MockResponse('{}', ['http_code' => 200]);
        $client = $this->client($mock);

        $connection = new Connection('open', 'Open API', 'https://api.open.com');

        $client->request($connection, 'GET', self::PUBLIC_URL);

        foreach ($this->sentHeaders($mock) as $header) {
            self::assertStringStartsNotWith('Authorization:', $header);
        }
    }

    #[Test]
    public function rejectsPrivateTargetBeforeAnyRequest(): void
    {
        $client = $this->client(new MockResponse('{}'));
        $connection = new Connection('open', 'Open API', 'https://api.open.com');

        $this->expectException(SsrfBlockedException::class);
        $client->request($connection, 'GET', 'https://127.0.0.1/admin');
    }

    #[Test]
    public function surfacesRetryAfterOnThrottledResponse(): void
    {
        $mock = new MockResponse('rate limited', [
            'http_code' => 429,
            'response_headers' => ['retry-after' => '30'],
        ]);
        $client = $this->client($mock);
        $connection = new Connection('open', 'Open API', 'https://api.open.com');

        $response = $client->request($connection, 'GET', self::PUBLIC_URL);

        self::assertSame(429, $response->statusCode);
        self::assertFalse($response->isSuccessful());
        self::assertSame(30, $response->retryAfterSeconds());
    }

    private function client(MockResponse $response): GenericRestClient
    {
        return new GenericRestClient(new MockHttpClient($response), new SsrfGuard(), $this->cipher());
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

    /**
     * @return list<string>
     */
    private function sentHeaders(MockResponse $response): array
    {
        $headers = $response->getRequestOptions()['headers'] ?? [];
        if (!\is_array($headers)) {
            return [];
        }

        return array_values(array_filter($headers, 'is_string'));
    }
}
