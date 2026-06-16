<?php

declare(strict_types=1);

namespace App\Import\Application\Service\HealthCheck;

use const DIRECTORY_SEPARATOR;

/**
 * IMP2-2.8 (#1484) — containment guard for folder-type import sources. A folder
 * source's `path` must resolve INSIDE the configured base directory; otherwise a
 * health-check probe (or a saved source) would let a caller enumerate arbitrary
 * container directories (`/etc`, `/var/www`, …).
 *
 * Canonicalises both sides with realpath() before comparing, so `..` traversal
 * and symlinks that escape the base are caught. A non-existent path (or base)
 * resolves to false and is therefore treated as "outside" — a folder source must
 * point at an existing directory within the base.
 */
final readonly class FolderPathGuard
{
    public function __construct(private string $basePath)
    {
    }

    public function isWithinBase(string $path): bool
    {
        $base = realpath($this->basePath);
        $real = realpath($path);
        if (false === $base || false === $real) {
            return false;
        }

        return $real === $base || str_starts_with($real, $base.DIRECTORY_SEPARATOR);
    }
}
