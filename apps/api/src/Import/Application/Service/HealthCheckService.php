<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Application\Service\HealthCheck\HealthCheckDriverInterface;
use App\Import\Application\Service\HealthCheck\HealthCheckResult;
use App\Import\Domain\Entity\ImportSource;
use App\Import\Domain\Entity\ImportSourceLog;
use App\Import\Domain\Enum\ImportSourceHealth;
use App\Import\Domain\Repository\ImportSourceLogRepositoryInterface;
use App\Import\Domain\Repository\ImportSourceRepositoryInterface;
use DateTimeImmutable;

/**
 * VIEW-IMP-03 (#500) — orchestrates per-type drivers and writes the
 * outcome both to the source row (so the UI can render the latest
 * health) and to `import_source_logs` (so the operator can audit the
 * history of probes).
 */
final readonly class HealthCheckService
{
    /**
     * @param iterable<HealthCheckDriverInterface> $drivers
     */
    public function __construct(
        private iterable $drivers,
        private ImportSourceRepositoryInterface $sources,
        private ImportSourceLogRepositoryInterface $logs,
    ) {
    }

    public function check(ImportSource $source): HealthCheckResult
    {
        $type = $source->getType();
        foreach ($this->drivers as $driver) {
            if ($driver->supports($type)) {
                $result = $driver->probe($source);
                $source->recordHealth($result->health, $result->note, new DateTimeImmutable());
                $this->sources->save($source);

                $tenant = $source->getTenant();
                if (null !== $tenant) {
                    $log = new ImportSourceLog(
                        sourceId: $source->getId(),
                        eventType: ImportSourceLog::EVENT_HEALTH_CHECK,
                        severity: $this->severityFor($result->health),
                        payload: [
                            'health' => $result->health->value,
                            'note' => $result->note,
                            'latency_ms' => $result->latencyMs,
                        ],
                    );
                    $log->assignTenant($tenant);
                    $this->logs->save($log);
                }

                return $result;
            }
        }

        $fallback = new HealthCheckResult(
            ImportSourceHealth::Off,
            \sprintf('No health-check driver registered for type "%s".', $type->value),
            0,
        );
        $source->recordHealth($fallback->health, $fallback->note, new DateTimeImmutable());
        $this->sources->save($source);

        return $fallback;
    }

    private function severityFor(ImportSourceHealth $health): string
    {
        return match ($health) {
            ImportSourceHealth::Ok => ImportSourceLog::SEVERITY_INFO,
            ImportSourceHealth::Warn => ImportSourceLog::SEVERITY_WARN,
            ImportSourceHealth::Error => ImportSourceLog::SEVERITY_ERROR,
            ImportSourceHealth::Off => ImportSourceLog::SEVERITY_INFO,
        };
    }
}
