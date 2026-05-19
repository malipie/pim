<?php

declare(strict_types=1);

namespace App\Identity\Application\Audit;

/**
 * RBAC-P3-013 (#676) — strips sensitive fields from the `oldValue` /
 * `newValue` JSONB payloads before they hit the audit_logs table.
 *
 * Same scrubbing list as the serializer (RBAC-P3-012 #675) — keeping
 * one source of truth so a sensitive field can never leak through the
 * audit channel even when the response normaliser does its job:
 *
 *   - `password`, `password_hash`     — never logged,
 *   - `mfa_secret`, `totp_secret`     — never logged,
 *   - `token`, `token_hash`,
 *     `access_token`, `webhook_secret`,
 *     `refresh_token`                 — never logged,
 *   - `private_key`, `client_secret`  — never logged.
 *
 * Scrubbing replaces values with the sentinel `'[REDACTED]'` (matching
 * the dh-auditor convention used elsewhere); the field NAME stays so
 * forensics knows *that* it changed, just not the literal bytes.
 *
 * Nested structures are walked recursively; non-array values that
 * happen to land on a sensitive key are still redacted.
 */
final class AuditLogScrubber
{
    public const string REDACTION_SENTINEL = '[REDACTED]';

    private const array SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'plain_password',
        'mfa_secret',
        'totp_secret',
        'token',
        'token_hash',
        'access_token',
        'refresh_token',
        'webhook_secret',
        'private_key',
        'client_secret',
        'api_key',
    ];

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>|null
     */
    public function scrub(?array $payload): ?array
    {
        if (null === $payload) {
            return null;
        }

        return $this->scrubAssoc($payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function scrubAssoc(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (\is_string($key) && $this->isSensitive($key)) {
                $out[$key] = self::REDACTION_SENTINEL;
                continue;
            }

            if (\is_array($value)) {
                /* @var array<string, mixed> $value */
                $out[$key] = $this->scrubAssoc($value);
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    private function isSensitive(string $key): bool
    {
        return \in_array(strtolower($key), self::SENSITIVE_KEYS, true);
    }
}
