<?php

declare(strict_types=1);

namespace App\Import\Application\Handler;

use App\Catalog\Domain\Entity\Attribute;
use App\Import\Application\Service\ImportColumnGrammar;
use App\Import\Application\Service\ImportObjectCreator;
use App\Import\Application\Service\ImportProgressPublisher;
use App\Import\Application\Service\ImportRowDecision;
use App\Import\Application\Service\ImportRowReader;
use App\Import\Application\Service\ImportValidationService;
use App\Import\Application\Service\ObjectResolver;
use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\Message\ImportRunMessage;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Import\Domain\ReservedMappingTarget;
use App\Import\Domain\SystemColumn;
use App\Import\Domain\ValueObject\ResolvedImportValue;
use App\Import\Domain\ValueObject\ValidationError;
use App\Shared\Application\AbstractBatchHandler;
use App\Shared\Application\BulkOperationInProgressException;
use App\Shared\Application\BulkOperationLock;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use LogicException;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
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
        private readonly ObjectResolver $objectResolver,
        private readonly ImportColumnGrammar $columnGrammar,
        private readonly \App\Catalog\Application\BatchValueWriter $valueWriter,
        private readonly ImportProgressPublisher $progressPublisher,
        private readonly TenantContext $tenantContext,
        private readonly FilesystemOperator $importsStorage,
        private readonly BulkOperationLock $bulkLock,
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

        try {
            $this->run($session);
        } catch (BulkOperationInProgressException $exception) {
            // PROD-05 — Messenger entry point: re-throw as recoverable so
            // the queue retries with backoff instead of dead-lettering.
            // The synchronous controller path in StartImportController
            // catches the same domain exception and returns HTTP 409.
            throw new RecoverableMessageHandlingException(
                $exception->getMessage().' Will retry.',
                previous: $exception,
            );
        }
    }

    /**
     * Drives the chunked persistence loop. Public so the synchronous
     * `<50 rows` path on the start endpoint can call into the same logic
     * without re-routing through Messenger.
     */
    public function run(ImportSession $session): void
    {
        // Streaming + per-row Doctrine flushes scale with file size.
        // A 100-row XLSX easily exceeds FrankenPHP's default 30s HTTP
        // budget (max_execution_time is 0 in CLI but the worker resets
        // it to 30 for each request). Without this opt-out the import
        // handler gets killed mid-loop, the response still returns 200
        // because the headers were already flushed, but the session
        // stays `pending` forever and 0 import_logs are written.
        set_time_limit(0);

        $tenant = $session->getTenant();
        if (!$tenant instanceof Tenant) {
            $session->markFailed('Import session has no tenant assignment.');
            $this->sessions->save($session);

            return;
        }

        // PROD-05 — at-most-one bulk job per tenant. Non-blocking acquire;
        // on collision raise a domain exception so the caller decides
        // (controller -> 409 Conflict, async handler -> recoverable
        // retry). ImportSession status stays `pending` so the operator
        // sees the job is waiting, not dropped.
        $lock = $this->bulkLock->acquire($tenant);
        if (null === $lock) {
            throw new BulkOperationInProgressException($tenant);
        }

        try {
            $session->markRunning();
            $this->sessions->save($session);
        } catch (LogicException $exception) {
            // Already running / paused / failed — bail without state churn.
            $lock->release();

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
            /** @var list<array{rowNumber: int, cells: array<string, string|null>}> $buffer */
            $buffer = [];

            // IMP2-1.4 (#1466): rows are buffered per chunk so the resolver
            // and the value writer pay one query per chunk (resolveMany +
            // primeChunk) instead of one per row. flushAndClear() detaches
            // everything, so the re-merge block below replays the lookups.
            foreach ($this->rowReader->read($sourcePath) as $rowNumber => $cells) {
                ++$totalRows;
                $buffer[] = ['rowNumber' => $rowNumber, 'cells' => $cells];

                if (!$this->shouldFlush(\count($buffer))) {
                    continue;
                }

                $processed += $this->processChunk($session, $tenant, $buffer, $columnMapping, $attributesByCode, $skuSeenInFile);
                $buffer = [];

                $this->flushAndClear();
                // After clear() every previously-loaded entity is detached —
                // re-merge by reloading the session and tenant, replaying the
                // attribute lookup and resetting TenantContext.
                $session = $this->refreshSession($session);
                $tenant = $session->getTenant();
                if (!$tenant instanceof Tenant) {
                    throw new RuntimeException(\sprintf(
                        'Import session "%s" lost its tenant assignment mid-run.',
                        $session->getId()->toRfc4122(),
                    ));
                }
                $this->tenantContext->set($tenant);
                $attributesByCode = $this->validator->loadAttributesByCode($tenant, $columnMapping);
                $this->progressPublisher->progress($session, $processed, null);
            }

            if ([] !== $buffer) {
                $processed += $this->processChunk($session, $tenant, $buffer, $columnMapping, $attributesByCode, $skuSeenInFile);
                $this->flushAndClear();
                $session = $this->refreshSession($session);
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
            $lock->release();
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
     * Resolves every mapped, non-system column into a {@see ResolvedImportValue}
     * carrying the attribute code + the locale parsed from its dotted
     * header. A list (not a flat map) keeps several localised columns that
     * target the same attribute (`name.pl`, `name.en`) distinct (#1130).
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     *
     * @return list<ResolvedImportValue>
     */
    private function materialiseValues(array $cells, array $columnMapping, Tenant $tenant): array
    {
        $out = [];
        foreach ($columnMapping as $columnHeader => $attributeCode) {
            if (SystemColumn::isSystem($columnHeader)) {
                continue;
            }
            if ('' === $attributeCode || ReservedMappingTarget::isReserved($attributeCode)) {
                continue;
            }
            $parsed = $this->columnGrammar->parse($columnHeader, $tenant);
            if (null !== $parsed->unknownSuffix) {
                // The validator already flagged the column; never write a
                // bogus locale row (pre-1.6 silent corruption).
                continue;
            }
            $out[] = new ResolvedImportValue(
                attributeCode: $attributeCode,
                locale: $parsed->locale,
                rawValue: $cells[$columnHeader] ?? null,
                channelId: $parsed->channelId,
            );
        }

        return $out;
    }

    /**
     * SKU is non-localised — the first resolved `sku` value with content
     * wins. Falls back to a synthetic code so a row that somehow passed
     * validation without one still persists rather than collides on an
     * empty unique key.
     *
     * @param list<ResolvedImportValue> $resolvedValues
     */
    private function skuFrom(array $resolvedValues, int $rowNumber): string
    {
        foreach ($resolvedValues as $resolved) {
            if ('sku' === $resolved->attributeCode
                && null !== $resolved->rawValue
                && '' !== $resolved->rawValue) {
                return $resolved->rawValue;
            }
        }

        return \sprintf('IMPORT-%d', $rowNumber);
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
     * Finds the first non-empty cell whose mapping targets the reserved
     * __category__ marker. The validator already emitted a warning when
     * the lookup did not resolve, so we only need to surface the raw
     * code here — the creator drops the assignment silently if the row
     * imports without a category match.
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     */
    private function extractCategoryCode(array $cells, array $columnMapping): ?string
    {
        foreach ($columnMapping as $columnHeader => $target) {
            if (ReservedMappingTarget::CATEGORY !== $target) {
                continue;
            }
            $cell = $cells[$columnHeader] ?? null;
            if (null !== $cell && '' !== $cell) {
                return $cell;
            }
        }

        return null;
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

    /**
     * IMP2-1.3 — the row match key: the cell mapped to the configured
     * identifier attribute, or the SKU cell by default (trimmed; the
     * synthetic IMPORT-{row} fallback only feeds brand-new rows).
     *
     * @param array<string, string> $columnMapping
     */
    /**
     * IMP2-1.4 (#1466) — validate + resolve + write one buffered chunk.
     * Returns the number of rows consumed (== chunk size).
     *
     * @param list<array{rowNumber: int, cells: array<string, string|null>}> $buffer
     * @param array<string, string>                                          $columnMapping
     * @param array<string, Attribute>                                       $attributesByCode
     * @param array<string, int>                                             $skuSeenInFile
     */
    private function processChunk(
        ImportSession $session,
        Tenant $tenant,
        array $buffer,
        array $columnMapping,
        array $attributesByCode,
        array &$skuSeenInFile,
    ): int {
        // Pass 1 — validate rows and gather match keys for the batch resolve.
        /** @var list<array{rowNumber: int, cells: array<string, string|null>, sku: ?string, errors: list<ValidationError>, rowOk: bool, resolvedValues: list<ResolvedImportValue>, matchKey: string}> $prepared */
        $prepared = [];
        $matchKeys = [];
        foreach ($buffer as $entry) {
            $rowNumber = $entry['rowNumber'];
            $cells = $entry['cells'];
            $errors = $this->validator->validateRow(
                rowNumber: $rowNumber,
                cells: $cells,
                columnMapping: $columnMapping,
                attributesByCode: $attributesByCode,
                tenant: $tenant,
                skuSeenInFile: $skuSeenInFile,
            );
            $sku = $cells[$this->skuColumnHeader($columnMapping)] ?? null;
            $blocking = array_values(array_filter(
                $errors,
                static fn (ValidationError $error): bool => $error->isRowBlocking(),
            ));
            $rowOk = [] === $blocking;
            $resolvedValues = $rowOk ? $this->materialiseValues($cells, $columnMapping, $tenant) : [];
            $matchKey = $rowOk ? $this->matchKey($session, $cells, $columnMapping, $resolvedValues, $rowNumber) : '';
            if ('' !== $matchKey) {
                $matchKeys[] = $matchKey;
            }
            $prepared[] = [
                'rowNumber' => $rowNumber,
                'cells' => $cells,
                'sku' => $sku,
                'errors' => $errors,
                'rowOk' => $rowOk,
                'resolvedValues' => $resolvedValues,
                'matchKey' => $matchKey,
            ];
        }

        $existingByKey = $this->objectResolver->resolveMany(
            $matchKeys,
            $session->getTargetObjectType(),
            $tenant,
            $session->getMatchAttributeCode(),
        );
        $this->valueWriter->primeChunk(array_values($existingByKey), $attributesByCode);

        // Pass 2 — decide + write per row.
        foreach ($prepared as $row) {
            $errors = $row['errors'];
            $issues = [];

            if ($row['rowOk']) {
                $existing = $existingByKey[$row['matchKey']] ?? null;
                $decision = $this->objectResolver->decide($session->getMode(), $existing);

                if (ImportRowDecision::Create === $decision) {
                    $created = $this->creator->create(
                        objectType: $session->getTargetObjectType(),
                        sku: $this->skuFrom($row['resolvedValues'], $row['rowNumber']),
                        resolvedValues: $row['resolvedValues'],
                        attributesByCode: $attributesByCode,
                        importSessionId: $session->getId(),
                        categoryCode: $this->extractCategoryCode($row['cells'], $columnMapping),
                        tenant: $tenant,
                    );
                    $issues = $created->issues;
                    $session->incrementSuccess();
                } elseif (ImportRowDecision::Update === $decision && null !== $existing) {
                    $issues = $this->creator->update($existing, $row['resolvedValues'], $attributesByCode);
                    $session->incrementSuccess();
                    $session->incrementUpdated();
                } else {
                    $session->incrementSkipped();
                    $errors[] = ImportRowDecision::SkipExists === $decision
                        ? new ValidationError(
                            rowNumber: $row['rowNumber'],
                            sku: $row['sku'],
                            errorType: ImportErrorType::DuplicateSkuInDb,
                            level: ImportLogLevel::Warning,
                            message: \sprintf('Match key "%s" already exists — row skipped (mode CREATE).', $row['matchKey']),
                        )
                        : new ValidationError(
                            rowNumber: $row['rowNumber'],
                            sku: $row['sku'],
                            errorType: ImportErrorType::NoMatchInDb,
                            level: ImportLogLevel::Warning,
                            message: \sprintf('No object matches key "%s" — row skipped (mode UPDATE).', $row['matchKey']),
                        );
                }
            } else {
                $session->incrementError();
            }

            // Writer issues are value-level: the row stays imported, the
            // offending value is skipped — surfaced as warnings.
            foreach ($issues as $issue) {
                $errors[] = new ValidationError(
                    rowNumber: $row['rowNumber'],
                    sku: $row['sku'],
                    errorType: 'required_empty' === $issue['kind'] ? ImportErrorType::MissingRequired : ImportErrorType::InvalidValue,
                    level: ImportLogLevel::Warning,
                    message: $issue['message'],
                    columnName: $issue['attributeCode'],
                );
            }

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

            $this->progressPublisher->rowProcessed($session, $row['rowNumber'], $row['sku'], $row['rowOk']);
        }

        return \count($prepared);
    }

    /**
     * IMP2-1.3 — the row match key: the cell mapped to the configured
     * identifier attribute, or the SKU cell by default (trimmed).
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     * @param list<ResolvedImportValue>  $resolvedValues
     */
    private function matchKey(
        ImportSession $session,
        array $cells,
        array $columnMapping,
        array $resolvedValues,
        int $rowNumber,
    ): string {
        $matchCode = $session->getMatchAttributeCode();
        if (null !== $matchCode) {
            foreach ($columnMapping as $header => $target) {
                if ($target === $matchCode) {
                    return trim($cells[$header] ?? '');
                }
            }

            return '';
        }

        return trim($this->skuFrom($resolvedValues, $rowNumber));
    }
}
