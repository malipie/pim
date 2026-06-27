<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain;

/**
 * The outcome of a single {@see \App\Integration\Generic\Infrastructure\Http\GenericRestClient}
 * call (APIC-P1-03): the raw HTTP status, response headers, body, and the
 * latency/size telemetry the connection tester (P1-05) and sync logs (P3-02)
 * record. Error status codes (4xx/5xx) are surfaced here, not thrown — callers
 * decide what a non-2xx means for their flow.
 */
final readonly class GenericRestResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
        public int $durationMs,
        public int $sizeBytes,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function contentType(): ?string
    {
        return $this->headers['content-type'][0] ?? null;
    }

    /**
     * The `Retry-After` header value in seconds, when the remote signalled
     * throttling (HTTP 429 / 503). Only the integer-seconds form is parsed;
     * the HTTP-date form returns null (callers fall back to exponential
     * backoff — APIC-P1-09).
     */
    public function retryAfterSeconds(): ?int
    {
        $value = $this->headers['retry-after'][0] ?? null;
        if (null === $value || '' === $value || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
