<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Validation;

use App\Integration\Generic\Domain\Exception\InvalidDescriptorException;

/**
 * Config-time sanity check for user-defined connector descriptors (APIC-P1-04,
 * ADR-0022). It is the first SSRF wall — it rejects obviously-abusable shapes
 * before they are ever stored:
 *
 *  - base URL must be an absolute http(s) URL with a host (no `file://`,
 *    `gopher://`, schemeless, …);
 *  - an endpoint path template must stay relative to that base URL — it may
 *    never embed its own scheme/host (`https://evil/…`) or be protocol-relative
 *    (`//evil/…`), which would let a request target an arbitrary host and
 *    bypass the connection's base URL entirely.
 *
 * It deliberately does NOT resolve hosts to IPs: config-time DNS is a TOCTOU
 * trap and would be flaky. The authoritative per-request, per-redirect IP-range
 * check lives in the SSRF-safe {@see \App\Integration\Generic\Infrastructure\Http\GenericRestClient}.
 */
final class DescriptorValidator
{
    /** @var list<string> */
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @throws InvalidDescriptorException
     */
    public function assertValidBaseUrl(string $baseUrl): void
    {
        $trimmed = trim($baseUrl);
        if ('' === $trimmed) {
            throw InvalidDescriptorException::baseUrl($baseUrl, 'must not be empty');
        }

        $parts = parse_url($trimmed);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw InvalidDescriptorException::baseUrl($baseUrl, 'must be an absolute http(s) URL with a host');
        }

        if (!\in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            throw InvalidDescriptorException::baseUrl($baseUrl, 'scheme must be http or https');
        }
    }

    /**
     * @throws InvalidDescriptorException
     */
    public function assertValidPathTemplate(string $pathTemplate): void
    {
        if (str_contains($pathTemplate, '://')) {
            throw InvalidDescriptorException::pathTemplate($pathTemplate, 'must not embed a scheme/host');
        }

        if (str_starts_with($pathTemplate, '//')) {
            throw InvalidDescriptorException::pathTemplate($pathTemplate, 'must not be protocol-relative');
        }

        if (str_contains($pathTemplate, '\\')) {
            throw InvalidDescriptorException::pathTemplate($pathTemplate, 'must not contain backslashes');
        }
    }
}
