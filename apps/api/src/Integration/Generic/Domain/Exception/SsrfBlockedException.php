<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Exception;

use RuntimeException;

/**
 * Thrown when an outbound URL is rejected by the SSRF pre-filter (APIC-P1-03):
 * non-http(s) scheme, or a host resolving to a private / loopback / link-local
 * / reserved address. The message never contains credentials.
 */
final class SsrfBlockedException extends RuntimeException
{
    public static function forUrl(string $url): self
    {
        return new self(\sprintf('Outbound URL "%s" rejected: non-public or unsupported host.', $url));
    }
}
