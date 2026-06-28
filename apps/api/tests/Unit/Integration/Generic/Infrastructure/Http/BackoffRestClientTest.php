<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Application\ConnectionCredentialsCipher;
use App\Integration\Generic\Application\Sleeper;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Infrastructure\Http\BackoffRestClient;
use App\Integration\Generic\Infrastructure\Http\GenericRestClient;
use App\Integration\Generic\Infrastructure\Http\SsrfGuard;
use App\Shared\Application\Crypto\EncryptedSecret;
use App\Shared\Application\Crypto\EncryptionServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Backoff policy exercised with a recording sleeper (no wall-clock) + a
 * MockHttpClient queue and a literal public-IP URL so the real SsrfGuard
 * passes offline.
 */
final class BackoffRestClientTest extends TestCase
{
    private const string PUBLIC_URL = 'https://93.184.216.34/products';

    #[Test]
    public function retriesOnceOn429ThenReturnsSuccess(): void
    {
        $sleeper = $this->recordingSleeper();
        $client = $this->backoff([
            new MockResponse('throttled', ['http_code' => 429]),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ], $sleeper);

        $response = $client->request($this->connection(), 'GET', self::PUBLIC_URL);

        self::assertSame(200, $response->statusCode);
        self::assertCount(1, $sleeper->delays);
    }

    #[Test]
    public function exhaustsAttemptsAndReturnsTheLastThrottledResponse(): void
    {
        $sleeper = $this->recordingSleeper();
        $client = $this->backoff(array_fill(0, 5, new MockResponse('throttled', ['http_code' => 429])), $sleeper);

        $response = $client->request($this->connection(), 'GET', self::PUBLIC_URL);

        self::assertSame(429, $response->statusCode);
        // 5 attempts → 4 backoffs between them.
        self::assertCount(4, $sleeper->delays);
    }

    #[Test]
    public function respectsRetryAfterHeaderCappedAtSixtySeconds(): void
    {
        $sleeper = $this->recordingSleeper();
        $client = $this->backoff([
            new MockResponse('throttled', ['http_code' => 429, 'response_headers' => ['retry-after' => '120']]),
            new MockResponse('{}', ['http_code' => 200]),
        ], $sleeper);

        $client->request($this->connection(), 'GET', self::PUBLIC_URL);

        self::assertSame([60], $sleeper->delays);
    }

    #[Test]
    public function fallsBackToExponentialDelayWhenNoRetryAfter(): void
    {
        $sleeper = $this->recordingSleeper();
        $client = $this->backoff([
            new MockResponse('throttled', ['http_code' => 429]),
            new MockResponse('throttled', ['http_code' => 429]),
            new MockResponse('{}', ['http_code' => 200]),
        ], $sleeper);

        $client->request($this->connection(), 'GET', self::PUBLIC_URL);

        // 2^0, 2^1
        self::assertSame([1, 2], $sleeper->delays);
    }

    #[Test]
    public function doesNotRetrySuccessfulResponses(): void
    {
        $sleeper = $this->recordingSleeper();
        $client = $this->backoff([new MockResponse('{}', ['http_code' => 200])], $sleeper);

        $response = $client->request($this->connection(), 'GET', self::PUBLIC_URL);

        self::assertSame(200, $response->statusCode);
        self::assertSame([], $sleeper->delays);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function backoff(array $responses, Sleeper $sleeper): BackoffRestClient
    {
        $generic = new GenericRestClient(new MockHttpClient($responses), new SsrfGuard(), $this->cipher());

        return new BackoffRestClient($generic, $sleeper);
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell', 'https://api.idosell.com', AuthType::None);
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

    private function recordingSleeper(): RecordingSleeper
    {
        return new RecordingSleeper();
    }
}

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $delays = [];

    public function sleep(int $seconds): void
    {
        $this->delays[] = $seconds;
    }
}
