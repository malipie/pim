<?php

declare(strict_types=1);

namespace App\Backup\Application\Service;

/**
 * Abstraction over the actual snapshot tool. Production wires this to
 * {@see PgBackRestRunner} which shells out to `pgbackrest`. The test
 * suite uses {@see InMemoryBackupRunner} so it stays infra-free.
 */
interface BackupRunnerInterface
{
    public function run(): BackupRunResult;
}
