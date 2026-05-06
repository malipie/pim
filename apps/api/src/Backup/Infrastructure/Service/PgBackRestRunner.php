<?php

declare(strict_types=1);

namespace App\Backup\Infrastructure\Service;

use App\Backup\Application\Service\BackupRunnerInterface;
use App\Backup\Application\Service\BackupRunResult;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Calls the local pgBackRest binary against the configured stanza.
 *
 * The container layout (docker-compose `database` service) ships
 * pgBackRest 2.x with the `pim` stanza pre-configured against MinIO.
 * In production the operator wires the `repo1-s3-*` env vars; this
 * runner just invokes the CLI from inside the api container so the
 * Symfony worker keeps its lifecycle separate from the database
 * server.
 *
 * Output discipline: pgBackRest writes its progress to stderr and the
 * final summary to stdout. We capture both so the failure path can
 * surface the operator-actionable line, not just "exit code 50".
 */
final readonly class PgBackRestRunner implements BackupRunnerInterface
{
    private const string DEFAULT_STANZA = 'pim';
    private const int TIMEOUT_SECONDS = 1800;

    public function __construct(
        private string $stanza = self::DEFAULT_STANZA,
    ) {
    }

    public function run(): BackupRunResult
    {
        $process = new Process([
            'pgbackrest',
            \sprintf('--stanza=%s', $this->stanza),
            'backup',
        ]);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $message = trim($process->getErrorOutput());
            if ('' === $message) {
                $message = $exception->getMessage();
            }

            return BackupRunResult::failure($message);
        }

        $infoProcess = new Process([
            'pgbackrest',
            \sprintf('--stanza=%s', $this->stanza),
            '--output=json',
            'info',
        ]);
        $infoProcess->setTimeout(60);

        try {
            $infoProcess->mustRun();
            $payload = $infoProcess->getOutput();
        } catch (ProcessFailedException) {
            $payload = '';
        }

        return BackupRunResult::success(
            sizeBytes: $this->extractSizeFromInfo($payload),
            label: $this->extractLatestLabelFromInfo($payload),
        );
    }

    private function extractSizeFromInfo(string $payload): int
    {
        $latest = $this->latestBackupEntry($payload);
        if (null === $latest) {
            return 0;
        }
        $info = $latest['info'] ?? null;
        if (!\is_array($info)) {
            return 0;
        }
        $size = $info['size'] ?? null;
        if (\is_int($size)) {
            return $size;
        }

        return is_numeric($size) ? (int) $size : 0;
    }

    private function extractLatestLabelFromInfo(string $payload): ?string
    {
        $latest = $this->latestBackupEntry($payload);
        if (null === $latest) {
            return null;
        }
        $label = $latest['label'] ?? null;

        return \is_string($label) ? $label : null;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function latestBackupEntry(string $payload): ?array
    {
        $decoded = json_decode($payload, true);
        if (!\is_array($decoded) || !\is_array($decoded[0] ?? null)) {
            return null;
        }
        $stanzas = $decoded[0]['backup'] ?? null;
        if (!\is_array($stanzas) || [] === $stanzas) {
            return null;
        }
        $latest = end($stanzas);

        return \is_array($latest) ? $latest : null;
    }
}
