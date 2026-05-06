<?php

declare(strict_types=1);

namespace App\Import\Application\Handler;

use App\Import\Application\Service\ImportObjectCreator;
use App\Import\Application\Service\ImportProgressPublisher;
use App\Import\Application\Service\ImportRowReader;
use App\Import\Application\Service\ImportValidationService;
use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Message\ImportRunMessage;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\AbstractBatchHandler;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use LogicException;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

use const PATHINFO_EXTENSION;

/**
 * IMP-04 (#445) — async (and inline-for-small-files) import execution.
 *
 * Streams rows from the uploaded file (downloaded from MinIO into a temp
 * path), runs the IMP-03 row validation, and persists every clean row
 * via {@see ImportObjectCreator}. Per-row findings land in
 * {@see ImportLog} so the post-import CSV report (IMP-05) and the
 * Mercure progress stream see the same data.
 *
 * Memory contract (R-25): batches of 200 rows funnel through
 * {@see flushAndClear()} so the worker stays under 256 MiB on a 5k-row
 * import; the long-lived `ImportSession` row is re-merged into the EM
 * after each clear so counters stay current without straggling
 * proxy state.
 */
#[AsMessageHandler]
final class ImportRunHandler extends AbstractBatchHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly ImportRowReader $rowReader,
        private readonly ImportValidationService $validator,
        private readonly ImportObjectCreator $creator,
        private readonly ImportProgressPublisher $progressPublisher,
        private readonly TenantContext $tenantContext,
        private readonly FilesystemOperator $importsStorage,
        int $batchSize = 200,
    ) {
        parent::__construct($entityManager, $batchSize);
    }

    public function __invoke(ImportRunMessage $message): void
    {
        $session = $this->sessions->findById($message->importSessionId);
        if (!$session instanceof ImportSession) {
            return;
        }

        $tenant = $session->getTenant();
        if (!$tenant instanceof Tenant) {
            $session->markFailed('Import session has no tenant assignment.');
            $this->sessions->save($session);

            return;
        }

        $this->tenantContext->set($tenant);
        $this->run($session);
    }

    /**
     * Drives the chunked persistence loop. Public so the synchronous
     * `<50 rows` path on the start endpoint can call into the same logic
     * without re-routing through Messenger.
     */
    public function run(ImportSession $session): void
    {
        $tenant = $session->getTenant();
        if (!$tenant instanceof Tenant) {
            $session->markFailed('Import session has no tenant assignment.');
            $this->sessions->save($session);

            return;
        }

        try {
            $session->markRunning();
            $this->sessions->save($session);
        } catch (LogicException $exception) {
            // Already running / paused / failed — bail without state churn.
            return;
        }

        $sourcePath = null;
        try {
            $columnMapping = $this->resolveColumnMapping($session);
            $attributesByCode = $this->validator->loadAttributesByCode($tenant, $columnMapping);
            $sourcePath = $this->stageSourceFile($session, $tenant);

            $skuSeenInFile = [];
            $processed = 0;
            $totalRows = 0;

            foreach ($this->rowReader->read($sourcePath) as $rowNumber => $cells) {
                ++$processed;
                ++$totalRows;
                $errors = $this->validator->validateRow(
                    rowNumber: $rowNumber,
                    cells: $cells,
                    columnMapping: $columnMapping,
                    attributesByCode: $attributesByCode,
                    tenant: $tenant,
                    skuSeenInFile: $skuSeenInFile,
                );

                $sku = $cells[$this->skuColumnHeader($columnMapping)] ?? null;
                $rowOk = [] === $errors;

                if ($rowOk) {
                    $valueByAttributeCode = $this->materialiseValues($cells, $columnMapping);
                    $this->creator->create(
                        objectType: $session->getTargetObjectType(),
                        sku: $valueByAttributeCode['sku'] ?? \sprintf('IMPORT-%d', $rowNumber),
                        valueByAttributeCode: $valueByAttributeCode,
                        attributesByCode: $attributesByCode,
                        importSessionId: $session->getId(),
                    );
                    $session->incrementSuccess();
                } else {
                    foreach ($errors as $error) {
                        $this->entityManager->persist(new ImportLog(
                            importSession: $session,
                            rowNumber: $error->rowNumber,
                            level: $error->level,
                            message: $error->message,
                            sku: $error->sku,
                            errorType: $error->errorType->value,
                            columnName: $error->columnName,
                            columnValue: $error->columnValue,
                        ));
                    }
                    $session->incrementError();
                }

                $this->progressPublisher->rowProcessed($session, $rowNumber, $sku, $rowOk);

                if ($this->shouldFlush($processed)) {
                    $this->flushAndClear();
                    $session = $this->refreshSession($session);
                    $this->progressPublisher->progress($session, $processed, $sku);
                }
            }

            $session->setTotalRows($totalRows);
            $session = $this->refreshSession($session);
            $session->markCompleted();
            $this->sessions->save($session);
            $this->progressPublisher->completed($session);
        } catch (Throwable $exception) {
            $current = $this->sessions->findById($session->getId());
            if ($current instanceof ImportSession && !$current->getStatus()->isTerminal()) {
                $current->markFailed($exception->getMessage());
                $this->sessions->save($current);
                $this->progressPublisher->completed($current);
            }
            throw $exception;
        } finally {
            if (null !== $sourcePath && file_exists($sourcePath)) {
                @unlink($sourcePath);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function resolveColumnMapping(ImportSession $session): array
    {
        $sessionMapping = $session->getColumnMapping();
        if ([] !== $sessionMapping) {
            return $sessionMapping;
        }

        $profile = $session->getProfile();
        if (null === $profile) {
            return [];
        }

        return $profile->getColumnMapping();
    }

    /**
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     *
     * @return array<string, string|null>
     */
    private function materialiseValues(array $cells, array $columnMapping): array
    {
        $out = [];
        foreach ($columnMapping as $columnHeader => $attributeCode) {
            if ('skip' === $attributeCode || '' === $attributeCode) {
                continue;
            }
            $out[$attributeCode] = $cells[$columnHeader] ?? null;
        }

        return $out;
    }

    /**
     * @param array<string, string> $columnMapping
     */
    private function skuColumnHeader(array $columnMapping): string
    {
        foreach ($columnMapping as $header => $attributeCode) {
            if ('sku' === $attributeCode) {
                return $header;
            }
        }

        return 'sku';
    }

    /**
     * Pulls the uploaded source file from the imports bucket into a
     * local temp path so PhpSpreadsheet / league-csv can read it
     * through a regular filesystem handle. The temp path is unlinked
     * in the finally block of the caller.
     *
     * Convention (spec §8.6): `{tenant_id}/{session_id}/{file_name}`.
     */
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
            throw new RuntimeException(\sprintf(
                'Failed to read uploaded file at "%s".',
                $remotePath,
            ), previous: $exception);
        }

        $extension = pathinfo($session->getFileName(), PATHINFO_EXTENSION);
        if ('' === $extension) {
            $extension = 'csv';
        }
        $localPath = tempnam(sys_get_temp_dir(), 'pim-import-').'.'.$extension;
        $local = fopen($localPath, 'w');
        if (false === $local) {
            throw new RuntimeException(\sprintf('Failed to open temp file "%s" for writing.', $localPath));
        }
        stream_copy_to_stream($stream, $local);
        fclose($local);

        return $localPath;
    }

    /**
     * After {@see flushAndClear()} the EM detached the active session,
     * so any further state mutation has to happen on a fresh managed
     * instance. Repository lookup re-merges and keeps counters live.
     */
    private function refreshSession(ImportSession $session): ImportSession
    {
        $reloaded = $this->sessions->findById($session->getId());
        if (!$reloaded instanceof ImportSession) {
            throw new RuntimeException(\sprintf(
                'Import session "%s" disappeared mid-run.',
                $session->getId()->toRfc4122(),
            ));
        }

        return $reloaded;
    }
}
