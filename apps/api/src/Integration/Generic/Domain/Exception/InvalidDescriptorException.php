<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Exception;

use RuntimeException;

/**
 * Thrown when a connector descriptor (a connection's base URL or an endpoint's
 * path template) fails the config-time sanity check (APIC-P1-04): non-http(s)
 * scheme, missing host, or a path template that embeds a scheme/host and could
 * override the base URL target (SSRF). The API layer maps it to a 422 Problem
 * Details (APIC-P1-06). Runtime IP-range checks stay with the SSRF-safe client.
 */
final class InvalidDescriptorException extends RuntimeException
{
    public static function baseUrl(string $value, string $reason): self
    {
        return new self(\sprintf('Invalid connector base URL "%s": %s.', $value, $reason));
    }

    public static function pathTemplate(string $value, string $reason): self
    {
        return new self(\sprintf('Invalid endpoint path template "%s": %s.', $value, $reason));
    }
}
