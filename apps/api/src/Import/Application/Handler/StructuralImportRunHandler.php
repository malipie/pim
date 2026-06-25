<?php

declare(strict_types=1);

namespace App\Import\Application\Handler;

use App\Import\Application\Service\ImportProgressPublisher;
use App\Import\Application\Service\ImportRowReader;
use App\Import\Application\Service\Structural\AttributeGroupImportCreator;
use App\Import\Application\Service\Structural\AttributeImportCreator;
use App\Import\Application\Service\Structural\StructuralImportRowResult;
use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Import\Domain\Repository\ImportLogRepositoryInterface;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\BulkOperationInProgressException;
use App\Shared\Application\BulkOperationLock;
use App\Shared\Domain\Tenant;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use LogicException;
use RuntimeException;

use const PATHINFO_EXTENSION;

/**
 * Runs a structural import (attribute / attribute-group definitions) — the
 * mirror of the `attributes_groups` / `attribute_groups` exports.
 *
 * Reuses the import shell (ImportSession status/counters, ImportLog, Mercure
 * progress, MinIO staging, the per-tenant bulk lock) but, unlike
 * {@see ImportRunHandler}, dispatches each row to a structural creator that
 * upserts configuration entities through the Catalog CQRS commands instead of
 * writing CatalogObject + ObjectValue rows. No media / relation / rebuild
 * phases — these entities have none.
 *
 * Structural data is bounded-small by nature, so the start controller always
 * calls {@see run()} inline — there is no async/worker path (and thus no
 * dependency on the Messenger transport).
 */
final class StructuralImportRunHandler
{
    /** Defensive cap on persisted ImportLog rows (structural files are small). */
    private const int MAX_PERSISTED_LOGS = 5_000;

    public function __construct(
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly ImportRowReader $rowReader,
        private readonly ImportLogRepositoryInterface $importLogs,
        private readonly ImportProgressPublisher $progress,
        private readonly AttributeImportCreator $attributeCreator,
        private readonly AttributeGroupImportCreator $attributeGroupCreator,
        private readonly FilesystemOperator $importsStorage,
        private readonly BulkOperationLock $bulkLock,
    ) {
    }

    /**
     * Drives the row loop. Called inline from the start controller; throws
     * {@see BulkOperationInProgressException} when another bulk job holds the
     * per-tenant lock (the controller maps it to 409).
     */
    public function run(ImportSession $session): void
    {
        set_time_limit(0);

        $tenant = $session->getTenant();
        if (!$tenant instanceof Tenant) {
            $session->markFailed('Import session has no tenant assignment.');
            $this->sessions->save($session);

            return;
        }
        if (ImportSessionStatus::Paused === $session->getStatus()) {
            return;
        }
        $kind = $session->getStructuralKind();
        if ('attributes' !== $kind && 'attribute_groups' !== $kind) {
            $session->markFailed(\sprintf('Unsupported structural import kind "%s".', $kind ?? 'null'));
            $this->sessions->save($session);

            return;
        }

        $lock = $this->bulkLock->acquire($tenant);
        if (null === $lock) {
            throw new BulkOperationInProgressException($tenant);
        }

        try {
            $session->markRunning();
            $this->sessions->save($session);
        } catch (LogicException) {
            $lock->release();

            return;
        }

        $persistedLogs = 0;
        $sourcePath = null;
        try {
            $sourcePath = $this->stageSourceFile($session, $tenant);

            $processed = 0;
            foreach ($this->rowReader->read($sourcePath) as $rowNumber => $cells) {
                $result = 'attribute_groups' === $kind
                    ? $this->attributeGroupCreator->create($rowNumber, $cells, $tenant)
                    : $this->attributeCreator->create($rowNumber, $cells, $tenant);
                $this->applyOutcome($session, $result);
                $persistedLogs = $this->persistLogs($session, $rowNumber, $result, $persistedLogs);
                ++$processed;
                $session->setTotalRows($processed);
                $this->progress->progress($session, $processed, $result->code);
            }

            $session->markCompleted();
            $this->sessions->save($session);
            $this->progress->completed($session);
        } catch (FilesystemException|RuntimeException $exception) {
            $session->markFailed($exception->getMessage());
            $this->sessions->save($session);
        } finally {
            $lock->release();
            if (null !== $sourcePath && file_exists($sourcePath)) {
                @unlink($sourcePath);
            }
        }
    }

    private function applyOutcome(ImportSession $session, StructuralImportRowResult $result): void
    {
        match ($result->outcome) {
            StructuralImportRowResult::OUTCOME_CREATED => $session->incrementSuccess(),
            StructuralImportRowResult::OUTCOME_UPDATED => $session->incrementUpdated(),
            StructuralImportRowResult::OUTCOME_ERROR => $session->incrementError(),
            default => $session->incrementSkipped(),
        };
    }

    private function persistLogs(ImportSession $session, int $rowNumber, StructuralImportRowResult $result, int $persistedLogs): int
    {
        foreach ($result->logs as $log) {
            if ($persistedLogs >= self::MAX_PERSISTED_LOGS) {
                break;
            }
            $this->importLogs->save(new ImportLog(
                importSession: $session,
                rowNumber: $rowNumber,
                level: $log['level'],
                message: $log['message'],
                sku: $result->code,
                errorType: $log['errorType'],
                columnName: $log['columnName'],
                columnValue: $log['columnValue'],
            ));
            if (ImportLogLevel::Error === $log['level']) {
                $this->progress->error(
                    $session,
                    $rowNumber,
                    $result->code,
                    $log['errorType'] ?? 'invalid_value',
                    $log['message'],
                );
            }
            ++$persistedLogs;
        }

        return $persistedLogs;
    }

    private function stageSourceFile(ImportSession $session, Tenant $tenant): string
    {
        $remotePath = \sprintf(
            '%s/%s/%s',
            $tenant->getId()->toRfc4122(),
            $session->getId()->toRfc4122(),
            $session->getFileName(),
        );

        try {
            $stream = $this->importsStorage->readStream($remotePath);
        } catch (FilesystemException $exception) {
            throw new RuntimeException(\sprintf('Failed to read uploaded file at "%s".', $remotePath), previous: $exception);
        }

        $extension = pathinfo($session->getFileName(), PATHINFO_EXTENSION);
        if ('' === $extension) {
            $extension = 'csv';
        }
        $localPath = tempnam(sys_get_temp_dir(), 'pim-structural-import-').'.'.$extension;
        $local = fopen($localPath, 'w');
        if (false === $local) {
            throw new RuntimeException(\sprintf('Failed to open temp file "%s" for writing.', $localPath));
        }
        stream_copy_to_stream($stream, $local);
        fclose($local);

        return $localPath;
    }
}
