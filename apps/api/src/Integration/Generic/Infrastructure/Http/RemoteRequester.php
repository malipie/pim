<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Exception\RemoteRequestFailedException;
use App\Integration\Generic\Domain\Exception\SsrfBlockedException;
use App\Integration\Generic\Domain\GenericRestResponse;

/**
 * The narrow HTTP seam the consumer side depends on (ADR-0022, epic APIC).
 *
 * Both {@see GenericRestClient} (SSRF-safe transport + auth injection) and
 * {@see BackoffRestClient} (429/503 retry on top) implement it. Callers that
 * want resilience type-hint this interface; the service alias binds it to the
 * backoff-wrapped client. Keeping the seam an interface also lets pagination
 * and discovery be unit-tested with a fake, since the concrete clients are
 * `final readonly`.
 */
interface RemoteRequester
{
    /**
     * @param array<string, string|int> $query
     * @param array<string, string>     $headers
     *
     * @throws SsrfBlockedException
     * @throws RemoteRequestFailedException
     */
    public function request(
        Connection $connection,
        string $method,
        string $url,
        array $query = [],
        array $headers = [],
        ?string $body = null,
    ): GenericRestResponse;
}
