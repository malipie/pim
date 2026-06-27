<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when a request to an external connection fails at the transport level
 * (DNS, TLS, connection refused, timeout) or the response exceeds the size cap
 * (APIC-P1-03). A non-2xx HTTP status is NOT a failure — that is surfaced on
 * {@see \App\Integration\Generic\Domain\GenericRestResponse}. The message never
 * contains credentials.
 */
final class RemoteRequestFailedException extends RuntimeException
{
    public static function transport(string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('External request failed: %s', $reason), 0, $previous);
    }

    public static function responseTooLarge(int $limitBytes): self
    {
        return new self(\sprintf('External response exceeds the %d byte limit.', $limitBytes));
    }
}
