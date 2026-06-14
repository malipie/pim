<?php

declare(strict_types=1);

namespace App\Import\Application\Handler;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\ObjectKind;
use App\Import\Application\Service\ImportColumnGrammar;
use App\Import\Application\Service\ImportObjectCreator;
use App\Import\Application\Service\ImportProgressPublisher;
use App\Import\Application\Service\ImportRowDecision;
use App\Import\Application\Service\ImportRowReader;
use App\Import\Application\Service\ImportValidationService;
use App\Import\Application\Service\ObjectResolver;
use App\Import\Application\Service\RelationImportStep;
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
use Symfony\Component\Uid\Uuid;
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
        private readonly RelationImportStep $relationStep,
        private readonly ImportColumnGrammar $columnGrammar,
        private readonly \App\Catalog\Application\BatchValueWriter $valueWriter,
        private readonly \App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface $catalogObjects,
        private readonly \App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface $objectCategories,
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

        // IMP2-1.8: the cross-object link buffer accumulates across all chunks
        // of THIS run; reset so a long-lived worker never carries stale tuples.
        $this->relationStep->reset();

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

            // IMP2-1.8 — pass 2: wire buffered cross-object links (parent →
            // master) now that EVERY object exists, so variant rows may appear
            // before or after their master. resolve() flush+clears in chunks.
            if ($this->relationStep->hasWork()) {
                $linkTenant = $session->getTenant();
                if ($linkTenant instanceof Tenant) {
                    $linkErrors = $this->relationStep->resolve($session->getTargetObjectType()->getKind(), $linkTenant);
                    $session = $this->refreshSession($session);
                    foreach ($linkErrors as $error) {
                        $session->incrementError();
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
                }
            }

            $session->setTotalRows($totalRows);
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
     * IMP2-1.7 — pipe-split list of category codes from the cell mapped to a
     * reserved category target (`code-a|code-b|code-c`). Trimmed, empties
     * dropped, order preserved (first becomes primary). The validator emits a
     * per-code CategoryNotFound warning; unresolved codes are simply absent
     * from the resolved set the writer assigns.
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     *
     * @return list<string>
     */
    private function extractCategoryCodes(array $cells, array $columnMapping): array
    {
        foreach ($columnMapping as $columnHeader => $target) {
            if (!ReservedMappingTarget::isCategory($target)) {
                continue;
            }
            $cell = $cells[$columnHeader] ?? null;
            if (null === $cell || '' === $cell) {
                continue;
            }

            return array_values(array_filter(
                array_map('trim', explode('|', $cell)),
                static fn (string $code): bool => '' !== $code,
            ));
        }

        return [];
    }

    /**
     * IMP2-1.7 (D2 collection policy) — true when the category column maps to
     * the append target; default (plain `__category__`) is replace.
     *
     * @param array<string, string> $columnMapping
     */
    private function categoryAppend(array $columnMapping): bool
    {
        return \in_array(ReservedMappingTarget::CATEGORY_APPEND, $columnMapping, true);
    }

    /**
     * IMP2-1.7 — validated publication status pulled from the `__status__`
     * column (lower-cased), or null when the column is absent/empty (D2 — do
     * not touch). The validator already rejected out-of-enum values.
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     */
    private function extractStatus(array $cells, array $columnMapping): ?string
    {
        $raw = $this->reservedCell($cells, $columnMapping, ReservedMappingTarget::STATUS);

        return null === $raw ? null : strtolower($raw);
    }

    /**
     * IMP2-1.7 — enabled flag from the `__enabled__` column, or null when
     * absent/empty. Accepts true|1 (→true) / false|0 (→false); the validator
     * rejected anything else.
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     */
    private function extractEnabled(array $cells, array $columnMapping): ?bool
    {
        $raw = $this->reservedCell($cells, $columnMapping, ReservedMappingTarget::ENABLED);
        if (null === $raw) {
            return null;
        }

        return \in_array(strtolower($raw), ['1', 'true'], true);
    }

    /**
     * IMP2-1.8 — parse the `__variant_axes__` cell (`code:v1,v2|code:v3`) into
     * the stored shape, or null when absent/empty (D2 — do not touch).
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     *
     * @return ?list<array{code: string, values: list<string>}>
     */
    private function extractVariantAxes(array $cells, array $columnMapping): ?array
    {
        $raw = $this->reservedCell($cells, $columnMapping, ReservedMappingTarget::VARIANT_AXES);
        if (null === $raw) {
            return null;
        }

        $axes = [];
        foreach (explode('|', $raw) as $part) {
            $part = trim($part);
            if ('' === $part) {
                continue;
            }
            [$code, $valuesRaw] = array_pad(explode(':', $part, 2), 2, '');
            $code = trim($code);
            if ('' === $code) {
                continue;
            }
            $values = array_values(array_filter(
                array_map('trim', explode(',', $valuesRaw)),
                static fn (string $value): bool => '' !== $value,
            ));
            $axes[] = ['code' => $code, 'values' => $values];
        }

        return [] === $axes ? null : $axes;
    }

    /**
     * IMP2-1.8 — buffer a parent link for pass 2 when the row carries a
     * non-empty `__parent_sku__` cell. Existence / cycle validation happens
     * in pass 2 once every object is written.
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     */
    private function recordParentLink(string $childSku, array $cells, array $columnMapping, int $rowNumber): void
    {
        $parentSku = $this->reservedCell($cells, $columnMapping, ReservedMappingTarget::PARENT_SKU);
        if (null !== $parentSku) {
            $this->relationStep->recordParent($childSku, $parentSku, $rowNumber);
        }
    }

    /**
     * IMP2-1.8 — buffer relation links from Relation/Reference cells for
     * pass 2. The values were NOT written as ObjectValue (the creator skips
     * them); their pipe-separated target codes become object_relations rows.
     *
     * @param list<ResolvedImportValue> $resolvedValues
     * @param array<string, Attribute>  $attributesByCode
     */
    private function recordRelationLinks(string $sourceSku, array $resolvedValues, array $attributesByCode, int $rowNumber): void
    {
        foreach ($resolvedValues as $resolved) {
            $attribute = $attributesByCode[$resolved->attributeCode] ?? null;
            if (!$attribute instanceof Attribute) {
                continue;
            }
            if (AttributeType::Relation !== $attribute->getType() && AttributeType::Reference !== $attribute->getType()) {
                continue;
            }
            $raw = $resolved->rawValue;
            if (null === $raw || '' === $raw) {
                continue;
            }
            $targetCodes = array_values(array_filter(
                array_map('trim', explode('|', $raw)),
                static fn (string $code): bool => '' !== $code,
            ));
            if ([] !== $targetCodes) {
                $this->relationStep->recordRelation($sourceSku, $attribute->getCode(), $targetCodes, $rowNumber);
            }
        }
    }

    /**
     * First non-empty (trimmed) cell whose mapping targets the given reserved
     * marker, or null.
     *
     * @param array<string, string|null> $cells
     * @param array<string, string>      $columnMapping
     */
    private function reservedCell(array $cells, array $columnMapping, string $target): ?string
    {
        foreach ($columnMapping as $columnHeader => $mapped) {
            if ($mapped !== $target) {
                continue;
            }
            $cell = $cells[$columnHeader] ?? null;
            if (null !== $cell && '' !== trim($cell)) {
                return trim($cell);
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

        // IMP2-1.7: resolve every distinct category code in the chunk once
        // (per-chunk prefetch — not one SELECT per code per row).
        $categoryByCode = $this->resolveChunkCategories($prepared, $columnMapping, $tenant);
        /** @var list<array{product: CatalogObject, codes: list<string>, append: bool}> $pendingCategoryOps */
        $pendingCategoryOps = [];

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
                        categories: $this->resolveCategories($this->extractCategoryCodes($row['cells'], $columnMapping), $categoryByCode),
                        status: $this->extractStatus($row['cells'], $columnMapping),
                        enabled: $this->extractEnabled($row['cells'], $columnMapping),
                        variantAxes: $this->extractVariantAxes($row['cells'], $columnMapping),
                    );
                    $issues = $created->issues;
                    $session->incrementSuccess();
                    // IMP2-1.8: buffer parent + relation links; resolved in
                    // pass 2 once every object exists (target row may precede).
                    $createdSku = $this->skuFrom($row['resolvedValues'], $row['rowNumber']);
                    $this->recordParentLink($createdSku, $row['cells'], $columnMapping, $row['rowNumber']);
                    $this->recordRelationLinks($createdSku, $row['resolvedValues'], $attributesByCode, $row['rowNumber']);
                } elseif (ImportRowDecision::Update === $decision && null !== $existing) {
                    $issues = $this->creator->update(
                        $existing,
                        $row['resolvedValues'],
                        $attributesByCode,
                        $this->extractStatus($row['cells'], $columnMapping),
                        $this->extractEnabled($row['cells'], $columnMapping),
                        $this->extractVariantAxes($row['cells'], $columnMapping),
                    );
                    $session->incrementSuccess();
                    $session->incrementUpdated();
                    $this->recordParentLink($existing->getCode(), $row['cells'], $columnMapping, $row['rowNumber']);
                    $this->recordRelationLinks($existing->getCode(), $row['resolvedValues'], $attributesByCode, $row['rowNumber']);
                    // IMP2-1.7: category replace/append runs after the value
                    // pass (replaceForProduct flushes around the primary index).
                    // Empty cell = untouched (D2), so only collect non-empty.
                    $categoryCodes = $this->extractCategoryCodes($row['cells'], $columnMapping);
                    if ([] !== $categoryCodes) {
                        $pendingCategoryOps[] = [
                            'product' => $existing,
                            'codes' => $categoryCodes,
                            'append' => $this->categoryAppend($columnMapping),
                        ];
                    }
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

        // IMP2-1.7: category replace/append for UPDATE rows, after the value
        // writes are staged (replaceForProduct flushes the EM in its own
        // transaction to DELETE-then-INSERT around the primary unique index).
        $this->applyCategoryOps($pendingCategoryOps, $categoryByCode);

        return \count($prepared);
    }

    /**
     * Resolve every distinct category code referenced in the chunk to its
     * {@see CatalogObject} once. Unresolved codes are dropped (the validator
     * emitted the per-code CategoryNotFound warning).
     *
     * @param list<array{rowNumber: int, cells: array<string, string|null>, sku: ?string, errors: list<ValidationError>, rowOk: bool, resolvedValues: list<ResolvedImportValue>, matchKey: string}> $prepared
     * @param array<string, string>                                                                                                                                                                 $columnMapping
     *
     * @return array<string, CatalogObject>
     */
    private function resolveChunkCategories(array $prepared, array $columnMapping, Tenant $tenant): array
    {
        $codes = [];
        foreach ($prepared as $row) {
            if (!$row['rowOk']) {
                continue;
            }
            foreach ($this->extractCategoryCodes($row['cells'], $columnMapping) as $code) {
                $codes[$code] = true;
            }
        }

        $map = [];
        foreach (array_keys($codes) as $code) {
            $category = $this->catalogObjects->findByCode($code, ObjectKind::Category, $tenant);
            if (null !== $category) {
                $map[$code] = $category;
            }
        }

        return $map;
    }

    /**
     * @param list<string>                 $codes
     * @param array<string, CatalogObject> $categoryByCode
     *
     * @return list<CatalogObject>
     */
    private function resolveCategories(array $codes, array $categoryByCode): array
    {
        $out = [];
        foreach ($codes as $code) {
            if (isset($categoryByCode[$code])) {
                $out[] = $categoryByCode[$code];
            }
        }

        return $out;
    }

    /**
     * @param list<array{product: CatalogObject, codes: list<string>, append: bool}> $ops
     * @param array<string, CatalogObject>                                           $categoryByCode
     */
    private function applyCategoryOps(array $ops, array $categoryByCode): void
    {
        foreach ($ops as $op) {
            $categories = $this->resolveCategories($op['codes'], $categoryByCode);
            if ([] === $categories) {
                // All codes unresolved — leave existing assignments untouched
                // rather than wiping a product from a typo (D2-safe; per-code
                // warnings already surfaced).
                continue;
            }

            if ($op['append']) {
                $this->appendCategories($op['product'], $categories);

                continue;
            }

            $ids = array_map(static fn (CatalogObject $c): Uuid => $c->getId(), $categories);
            $this->objectCategories->replaceForProduct($op['product'], $ids, $ids[0]);
        }
    }

    /**
     * Append categories to an object's existing assignments without
     * duplicates (D2 append policy). Position continues after the current
     * max; if the object had no primary, the first appended becomes primary.
     *
     * @param list<CatalogObject> $categories
     */
    private function appendCategories(CatalogObject $product, array $categories): void
    {
        $existingIds = [];
        $maxPosition = -1;
        $hasPrimary = false;
        foreach ($this->objectCategories->findByProduct($product) as $assignment) {
            $existingIds[$assignment->getCategory()->getId()->toRfc4122()] = true;
            $maxPosition = max($maxPosition, $assignment->getPosition());
            $hasPrimary = $hasPrimary || $assignment->isPrimary();
        }

        $position = $maxPosition + 1;
        foreach ($categories as $category) {
            if (isset($existingIds[$category->getId()->toRfc4122()])) {
                continue;
            }
            $primary = !$hasPrimary;
            $this->entityManager->persist(new ObjectCategory(
                product: $product,
                category: $category,
                isPrimary: $primary,
                position: $position++,
            ));
            $hasPrimary = $hasPrimary || $primary;
        }
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
