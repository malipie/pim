<?php

declare(strict_types=1);

namespace App\Import\Application\Service\HealthCheck;

use App\Import\Domain\Entity\ImportSource;
use App\Import\Domain\Enum\ImportSourceHealth;
use App\Import\Domain\Enum\ImportSourceType;

/**
 * VIEW-IMP-03 (#500) — only real driver in the MVP. Verifies that the
 * configured `path` exists, is a directory, and is readable. Returns a
 * warning when the path is reachable but empty (so the operator
 * notices misconfiguration before scheduling a polling cycle).
 */
final class FolderHealthCheckDriver implements HealthCheckDriverInterface
{
    public function supports(ImportSourceType $type): bool
    {
        return ImportSourceType::Folder === $type;
    }

    public function probe(ImportSource $source): HealthCheckResult
    {
        $start = microtime(true);
        $path = $source->getPath();

        if (null === $path || '' === $path) {
            return new HealthCheckResult(
                ImportSourceHealth::Error,
                'Folder path is required.',
                $this->elapsedMs($start),
            );
        }
        if (!is_dir($path)) {
            return new HealthCheckResult(
                ImportSourceHealth::Error,
                \sprintf('Path "%s" is not a directory.', $path),
                $this->elapsedMs($start),
            );
        }
        if (!is_readable($path)) {
            return new HealthCheckResult(
                ImportSourceHealth::Error,
                \sprintf('Path "%s" is not readable.', $path),
                $this->elapsedMs($start),
            );
        }

        $entries = @scandir($path);
        if (false === $entries) {
            return new HealthCheckResult(
                ImportSourceHealth::Warn,
                \sprintf('Path "%s" is readable but its contents could not be listed.', $path),
                $this->elapsedMs($start),
            );
        }
        $files = array_values(array_filter($entries, static fn (string $e): bool => '.' !== $e && '..' !== $e));
        if ([] === $files) {
            return new HealthCheckResult(
                ImportSourceHealth::Warn,
                'Folder is empty.',
                $this->elapsedMs($start),
            );
        }

        return new HealthCheckResult(
            ImportSourceHealth::Ok,
            \sprintf('%d file(s) reachable.', \count($files)),
            $this->elapsedMs($start),
        );
    }

    private function elapsedMs(float $start): int
    {
        return (int) ((microtime(true) - $start) * 1000);
    }
}
