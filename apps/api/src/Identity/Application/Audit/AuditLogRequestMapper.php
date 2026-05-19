<?php

declare(strict_types=1);

namespace App\Identity\Application\Audit;

/**
 * RBAC-P3-013 (#676) — pure helpers that map an HTTP request /
 * response into the small set of metadata the audit log stores. Split
 * out of {@see \App\Identity\Infrastructure\Audit\AuditLogListener} so
 * the decisions stay testable without booting Security / TenantContext
 * stacks.
 */
final class AuditLogRequestMapper
{
    /**
     * Maps an HTTP status code onto the `permission_check_result`
     * column's CHECK constraint values (granted / denied / n_a /
     * super_admin_bypass).
     */
    public function resolvePermissionCheckResult(int $status): string
    {
        if ($status >= 200 && $status < 300) {
            return 'granted';
        }

        return match ($status) {
            403 => 'denied',
            401 => 'n_a',
            default => 'n_a',
        };
    }

    /**
     * Reads `id` / `slug` / `code` / `uuid` from the route attributes —
     * the standard names used across the API. Non-scalar values
     * (sub-arrays, objects) are skipped because the column is a plain
     * VARCHAR.
     *
     * @param array<string, mixed> $attributes
     */
    public function resolveResourceId(array $attributes): ?string
    {
        foreach (['id', 'slug', 'code', 'uuid'] as $candidate) {
            if (isset($attributes[$candidate]) && \is_scalar($attributes[$candidate])) {
                return (string) $attributes[$candidate];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function ignoredPathPrefixes(): array
    {
        return [
            '/_wdt',
            '/_profiler',
            '/_error',
            '/assets/',
            '/build/',
            '/health',
            '/_health',
            '/.well-known/',
        ];
    }

    public function shouldSkip(string $path): bool
    {
        foreach ($this->ignoredPathPrefixes() as $ignored) {
            if (str_starts_with($path, $ignored)) {
                return true;
            }
        }

        return false;
    }
}
