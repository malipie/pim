<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Media;

use const DNS_AAAA;
use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * IMP2-1.12 / IMP2-2.8 — cheap PRE-FILTER for server-side request forgery on
 * import image downloads: rejects non-http(s) schemes and hosts that resolve to
 * a non-public address BEFORE any request, with a clean log message. It is NOT
 * the sole defence — a one-shot pre-resolution cannot stop DNS-rebinding (the
 * client re-resolves at connect time) nor a redirect to a private host. The
 * authoritative connection-time + per-redirect peer-IP check is
 * {@see \Symfony\Component\HttpClient\NoPrivateNetworkHttpClient}, which wraps
 * the client injected into {@see \App\Import\Application\Handler\ImageDownloadHandler}
 * (`import.ssrf_safe_http_client` in services.yaml). This guard is the fast
 * early reject; that client is the real backstop.
 *
 * Rejected here: private (RFC1918), loopback (127.0.0.0/8, ::1), link-local
 * (169.254.0.0/16, fe80::/10), reserved/ULA (fc00::/7, 0.0.0.0/8, …) — the
 * NO_PRIV_RANGE | NO_RES_RANGE filter flags cover them, with explicit literal
 * guards as defence in depth.
 */
final class SsrfGuard
{
    public function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ('http' !== $scheme && 'https' !== $scheme) {
            return false;
        }

        $host = $parts['host'];
        // A bracketed/literal IP host bypasses DNS — validate it directly.
        $literal = trim($host, '[]');
        if (false !== filter_var($literal, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($literal);
        }

        $ips = $this->resolve($host);
        if ([] === $ips) {
            return false; // unresolvable host → refuse rather than let the client try
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        $ips = [];
        $v4 = @gethostbynamel($host);
        if (\is_array($v4)) {
            $ips = $v4;
        }
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (\is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6']) && \is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (str_contains($ip, ':')) {
            $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | $flags);

            // ::1 loopback + fc00::/7 ULA + fe80::/10 link-local are caught by
            // the flags; reject anything that fails them.
            return false !== $valid;
        }

        $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | $flags);
        if (false === $valid) {
            return false;
        }
        // Defence in depth for ranges the flags miss on some libc builds.
        $long = ip2long($ip);
        if (false === $long) {
            return false;
        }
        foreach ([['0.0.0.0', 8], ['127.0.0.0', 8], ['169.254.0.0', 16], ['10.0.0.0', 8], ['172.16.0.0', 12], ['192.168.0.0', 16], ['100.64.0.0', 10]] as [$net, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($long & $mask) === (ip2long($net) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
