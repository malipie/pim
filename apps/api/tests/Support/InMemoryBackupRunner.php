<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Backup\Application\Service\BackupRunnerInterface;
use App\Backup\Application\Service\BackupRunResult;

/**
 * Test stub for {@see BackupRunnerInterface}. Returns a canned
 * success / failure so the handler + controller flow can be
 * exercised without ever spawning a pgbackrest process.
 */
final class InMemoryBackupRunner implements BackupRunnerInterface
{
    public bool $shouldSucceed = true;
    public int $sizeBytes = 12_345_678;
    public ?string $label = 'TEST-LABEL';
    public string $errorMessage = 'pgbackrest stub failure';

    public function run(): BackupRunResult
    {
        return $this->shouldSucceed
            ? BackupRunResult::success($this->sizeBytes, $this->label)
            : BackupRunResult::failure($this->errorMessage);
    }
}
