<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Application\ConnectionCredentialsCipher;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Exception\RemoteRequestFailedException;
use App\Integration\Generic\Domain\Exception\SsrfBlockedException;
use App\Integration\Generic\Domain\GenericRestResponse;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The single HTTP transport for the consumer side of the API Configurator
 * (APIC-P1-03, ADR-0022). Every external call goes through here so SSRF
 * protection and credential injection are guaranteed and centralised.
 *
 * Two-layer SSRF defence: a cheap {@see SsrfGuard} pre-filter (reject obvious
 * private/loopback/reserved targets before any I/O) on top of the injected
 * `generic.ssrf_safe_http_client` — Symfony's `NoPrivateNetworkHttpClient`,
 * which re-validates the actually-connected peer IP on every redirect hop
 * (closing the DNS-rebinding + redirect-to-private gaps the pre-filter cannot).
 *
 * Credentials are decrypted on demand ({@see ConnectionCredentialsCipher}) and
 * injected per {@see AuthType}; they are never written to logs. A non-2xx
 * status is returned on the {@see GenericRestResponse}, not thrown — only true
 * transport failures (DNS/TLS/timeout) and over-size responses raise.
 */
final readonly class GenericRestClient
{
    private const int TIMEOUT_SECONDS = 30;
    private const int MAX_DURATION_SECONDS = 60;
    private const int MAX_REDIRECTS = 3;
    private const int MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private HttpClientInterface $httpClient,
        private SsrfGuard $ssrfGuard,
        private ConnectionCredentialsCipher $cipher,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, string|int> $query
     * @param array<string, string>     $headers extra per-request headers
     *
     * @throws SsrfBlockedException         when the URL fails the SSRF pre-filter
     * @throws RemoteRequestFailedException on transport failure or over-size response
     */
    public function request(
        Connection $connection,
        string $method,
        string $url,
        array $query = [],
        array $headers = [],
        ?string $body = null,
    ): GenericRestResponse {
        if (!$this->ssrfGuard->isAllowed($url)) {
            throw SsrfBlockedException::forUrl($url);
        }

        $options = [
            'headers' => array_merge($connection->getDefaultHeaders(), $this->authHeaders($connection), $headers),
            'timeout' => self::TIMEOUT_SECONDS,
            'max_duration' => self::MAX_DURATION_SECONDS,
            'max_redirects' => self::MAX_REDIRECTS,
        ];
        if ([] !== $query) {
            $options['query'] = $query;
        }
        if (null !== $body) {
            $options['body'] = $body;
        }

        $startedAt = microtime(true);
        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $responseHeaders = $response->getHeaders(false);

            $payload = '';
            $bytes = 0;
            foreach ($this->httpClient->stream($response) as $chunk) {
                $content = $chunk->getContent();
                $bytes += \strlen($content);
                if ($bytes > self::MAX_BYTES) {
                    throw RemoteRequestFailedException::responseTooLarge(self::MAX_BYTES);
                }
                $payload .= $content;
            }
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('External connection request failed.', [
                'connection' => $connection->getCode(),
                'method' => $method,
                'exception' => $exception->getMessage(),
            ]);

            throw RemoteRequestFailedException::transport($exception->getMessage(), $exception);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return new GenericRestResponse($statusCode, $responseHeaders, $payload, $durationMs, $bytes);
    }

    /**
     * Builds the auth headers for the connection's scheme, decrypting the
     * stored credentials on demand. Credentials are never logged.
     *
     * @return array<string, string>
     */
    private function authHeaders(Connection $connection): array
    {
        $credentials = $this->cipher->reveal($connection);

        return match ($connection->getAuthType()) {
            AuthType::None => [],
            AuthType::ApiKey => isset($credentials['header'], $credentials['value'])
                ? [$credentials['header'] => $credentials['value']]
                : [],
            AuthType::Bearer, AuthType::Oauth2Token => isset($credentials['token'])
                ? ['Authorization' => 'Bearer '.$credentials['token']]
                : [],
            AuthType::Basic => isset($credentials['user'], $credentials['pass'])
                ? ['Authorization' => 'Basic '.base64_encode($credentials['user'].':'.$credentials['pass'])]
                : [],
        };
    }
}
