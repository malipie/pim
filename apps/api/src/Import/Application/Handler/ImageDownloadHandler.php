<?php

declare(strict_types=1);

namespace App\Import\Application\Handler;

use App\Asset\Contracts\AssetIngestorInterface;
use App\Asset\Contracts\Exception\UnsupportedMediaFormatException;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Catalog\Contracts\Service\ProductAssetLinker;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Provenance;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Import\Application\Service\ImportProgressPublisher;
use App\Import\Application\Service\Media\SsrfGuard;
use App\Import\Application\Service\Media\ZipImageExtractor;
use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\Message\ImageDownloadJob;
use App\Import\Domain\Message\ImageDownloadMessage;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\AbstractBatchHandler;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use const PHP_URL_PATH;

/**
 * IMP2-1.12 (#1475) — downloads image URLs from Asset-attribute cells, ingests
 * them into the asset library (dedup by content-hash via the shared
 * {@see AssetIngestorInterface}), writes the canonical `{asset_id}` envelope
 * and links the assets to the product. Runs on the dedicated `import`
 * transport, one message per buffered chunk batch.
 *
 * Hard limits: only http/https + SSRF-guarded hosts ({@see SsrfGuard}), 30s
 * timeout, ≤3 redirects, ≤10 MB/file (aborted mid-stream), magic-byte format
 * sniff in the ingestor (jpg/png/webp). A repeated URL in the batch downloads
 * once. Media failures NEVER fail the session — they raise `image_not_found` /
 * `image_format_unsupported` row warnings and bump `imagesFailed`.
 *
 * Two phases, because {@see AssetIngestorInterface::ingest()} → AssetUploader
 * may flush + dispatch thumbnail work that CLEARS the shared EntityManager:
 *   1. DOWNLOAD — fetch + ingest every URL, keeping only primitives (asset id
 *      strings, counts, pending log/write data). No managed entity is held
 *      across an ingest call.
 *   2. APPLY — reload the session + re-attach the tenant on the (possibly
 *      cleared) EM, then write counters, logs, envelopes, links in one flush.
 *
 * Completion: the batch decrements the session's pending-batch counter and,
 * when it reaches zero AND the row phase is done, finalizes the session.
 */
#[AsMessageHandler]
final class ImageDownloadHandler extends AbstractBatchHandler
{
    private const int TIMEOUT_SECONDS = 30;
    private const int MAX_REDIRECTS = 3;
    private const int MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly HttpClientInterface $httpClient,
        private readonly SsrfGuard $ssrfGuard,
        private readonly AssetIngestorInterface $assetIngestor,
        private readonly ProductAssetLinker $assetLinker,
        private readonly AssetRepositoryInterface $assets,
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly AttributeRepositoryInterface $attributes,
        private readonly ObjectValueRepositoryInterface $objectValues,
        private readonly TenantContext $tenantContext,
        private readonly ImportProgressPublisher $progressPublisher,
        private readonly LoggerInterface $logger,
        private readonly FilesystemOperator $importsStorage,
    ) {
        parent::__construct($entityManager);
    }

    public function __invoke(ImageDownloadMessage $message): void
    {
        $session = $this->sessions->findById($message->importSessionId);
        if (!$session instanceof ImportSession) {
            return;
        }
        $tenant = $session->getTenant();
        if (!$tenant instanceof Tenant) {
            return;
        }
        $this->tenantContext->set($tenant);

        // ── Phase 1: DOWNLOAD (primitives only; ingest may clear the EM) ──
        $downloaded = 0;
        $failed = 0;
        /** @var list<array{job: ImageDownloadJob, type: ImportErrorType, message: string, value: string}> $pendingLogs */
        $pendingLogs = [];
        /** @var list<array{job: ImageDownloadJob, downloaded: list<string>}> $pendingWrites */
        $pendingWrites = [];
        $urlCache = [];

        // IMP2-1.13 — stage the run's ZIP once (streamed from MinIO) and open
        // the extractor. A zip-bomb / unreadable archive is a SYSTEMIC failure:
        // fail the session with a clear message rather than per-row.
        $stagedZip = null;
        $extractor = null;
        if (null !== $message->zipStoragePath) {
            try {
                $stagedZip = $this->stageZip($message->zipStoragePath);
                $extractor = new ZipImageExtractor($stagedZip);
            } catch (Throwable $exception) {
                if (null !== $stagedZip && is_file($stagedZip)) {
                    @unlink($stagedZip);
                }
                $this->failSessionSystemic($message->importSessionId, 'Import ZIP could not be read: '.$exception->getMessage());

                return;
            }
        }

        try {
            foreach ($message->jobs as $job) {
                $ids = [];
                foreach ($job->urls as $url) {
                    if (isset($urlCache[$url])) {
                        $ids[] = $urlCache[$url];

                        continue;
                    }
                    $assetId = $this->fetchAndIngest($job, $url, $pendingLogs);
                    if (null === $assetId) {
                        ++$failed;

                        continue;
                    }
                    $urlCache[$url] = $assetId;
                    $ids[] = $assetId;
                    ++$downloaded;
                }
                foreach ($job->zipNames as $name) {
                    $cacheKey = 'zip:'.$name;
                    if (isset($urlCache[$cacheKey])) {
                        $ids[] = $urlCache[$cacheKey];

                        continue;
                    }
                    $assetId = null !== $extractor ? $this->extractAndIngest($extractor, $job, $name, $pendingLogs) : null;
                    if (null === $assetId) {
                        ++$failed;

                        continue;
                    }
                    $urlCache[$cacheKey] = $assetId;
                    $ids[] = $assetId;
                    ++$downloaded;
                }
                $pendingWrites[] = ['job' => $job, 'downloaded' => $ids];
            }
        } finally {
            $extractor?->close();
            if (null !== $stagedZip && is_file($stagedZip)) {
                @unlink($stagedZip);
            }
        }

        // ── Phase 2: APPLY (EM may have been cleared by ingest) ──
        $tenant = $this->reattachTenant($message->tenantId);
        $session = $this->sessions->findById($message->importSessionId);
        if (!$session instanceof ImportSession) {
            return;
        }
        foreach ($pendingLogs as $entry) {
            $this->persistLog($session, $entry['job'], $entry['type'], $entry['message'], $entry['value']);
        }
        foreach ($pendingWrites as $write) {
            $this->applyWrite($session, $write['job'], $write['downloaded'], $tenant);
        }
        // The session entity is intentionally NOT mutated here — its counters
        // and the batch gate move via ONE atomic SQL statement below, so
        // concurrent media batches (multiple `import` consumers) cannot lose an
        // increment or a decrement and strand the session non-terminal.
        $this->flushAndClear();

        $row = $this->entityManager->getConnection()->fetchAssociative(
            'UPDATE import_sessions'
            .' SET images_downloaded = images_downloaded + :d,'
            .'     images_failed = images_failed + :f,'
            .'     pending_image_batches = GREATEST(0, pending_image_batches - 1)'
            .' WHERE id = :id'
            .' RETURNING pending_image_batches, row_phase_complete',
            [
                'd' => $downloaded,
                'f' => $failed,
                'id' => $message->importSessionId->toRfc4122(),
            ],
        );
        $pendingRaw = \is_array($row) ? $row['pending_image_batches'] : 1;
        $pending = \is_scalar($pendingRaw) ? (int) $pendingRaw : 1;
        $rowPhaseDone = \is_array($row) && (bool) $row['row_phase_complete'];

        // The batch that drives the counter to zero AFTER the row phase is done
        // finalizes the session (success / partial) and removes the now-spent
        // ZIP from MinIO (IMP2-1.13 item 7 — full TTL sweep is IMP2-2.2).
        if (0 === $pending && $rowPhaseDone) {
            $session = $this->sessions->findById($message->importSessionId);
            if ($session instanceof ImportSession && !$session->getStatus()->isTerminal()) {
                $session->markCompleted();
                $this->sessions->save($session);
                $this->progressPublisher->completed($session);
            }
            if (null !== $message->zipStoragePath) {
                try {
                    $this->importsStorage->delete($message->zipStoragePath);
                } catch (Throwable $exception) {
                    $this->logger->warning('Failed to delete spent import ZIP from storage.', ['path' => $message->zipStoragePath, 'exception' => $exception]);
                }
            }
        }
    }

    /**
     * @param list<array{job: ImageDownloadJob, type: ImportErrorType, message: string, value: string}> $pendingLogs by ref
     */
    private function fetchAndIngest(ImageDownloadJob $job, string $url, array &$pendingLogs): ?string
    {
        if (!$this->ssrfGuard->isAllowed($url)) {
            $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageNotFound, 'message' => \sprintf('URL "%s" rejected: non-public or unsupported host.', $url), 'value' => $url];

            return null;
        }

        $localPath = tempnam(sys_get_temp_dir(), 'pim-dl-');
        if (false === $localPath) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT_SECONDS,
                'max_redirects' => self::MAX_REDIRECTS,
            ]);
            if ($response->getStatusCode() >= 400) {
                $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageNotFound, 'message' => \sprintf('URL "%s" returned HTTP %d.', $url, $response->getStatusCode()), 'value' => $url];

                return null;
            }

            $handle = fopen($localPath, 'w');
            if (false === $handle) {
                return null;
            }
            $bytes = 0;
            try {
                foreach ($this->httpClient->stream($response) as $chunk) {
                    $content = $chunk->getContent();
                    $bytes += \strlen($content);
                    if ($bytes > self::MAX_BYTES) {
                        $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageFormatUnsupported, 'message' => \sprintf('URL "%s" exceeds the %d MB limit.', $url, self::MAX_BYTES >> 20), 'value' => $url];

                        return null;
                    }
                    fwrite($handle, $content);
                }
            } finally {
                fclose($handle);
            }

            return $this->assetIngestor->ingest($localPath, $this->filenameFor($url))->assetId->toRfc4122();
        } catch (UnsupportedMediaFormatException $exception) {
            $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageFormatUnsupported, 'message' => \sprintf('URL "%s": %s', $url, $exception->getMessage()), 'value' => $url];

            return null;
        } catch (Throwable $exception) {
            $this->logger->warning('Import image download failed.', ['url' => $url, 'exception' => $exception]);
            $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageNotFound, 'message' => \sprintf('URL "%s" could not be downloaded: %s', $url, $exception->getMessage()), 'value' => $url];

            return null;
        } finally {
            if (is_file($localPath)) {
                @unlink($localPath);
            }
        }
    }

    /**
     * @param list<string> $downloaded asset ids (RFC 4122) fetched for this job
     */
    private function applyWrite(ImportSession $session, ImageDownloadJob $job, array $downloaded, Tenant $tenant): void
    {
        // Validate the cell's existing-asset UUIDs tenant-scoped; drop (with a
        // warning) any that do not belong to this tenant (cross-tenant / gone).
        $validExisting = [];
        if ([] !== $job->existingUuids) {
            $existsSet = [];
            foreach ($this->assets->existingIds($job->existingUuids, $tenant) as $id) {
                $existsSet[strtolower($id)] = true;
            }
            foreach ($job->existingUuids as $uuid) {
                if (isset($existsSet[strtolower($uuid)])) {
                    $validExisting[] = $uuid;
                } else {
                    $this->persistLog($session, $job, ImportErrorType::ImageNotFound, \sprintf('Asset "%s" does not exist for this tenant — skipped.', $uuid), $uuid);
                }
            }
        }

        $merged = [];
        foreach ([...$validExisting, ...$downloaded] as $id) {
            $merged[strtolower($id)] = $id;
        }
        $merged = array_values($merged);
        if ([] === $merged) {
            return;
        }

        $object = $this->catalogObjects->findById(Uuid::fromString($job->objectId));
        if (!$object instanceof CatalogObject) {
            return;
        }
        $attribute = $this->attributes->findByCode($job->attributeCode, $tenant);
        if (!$attribute instanceof Attribute) {
            return;
        }

        $channelId = null !== $job->channelId ? Uuid::fromString($job->channelId) : null;
        // IMP2-1.8 gallery shape: scalar for a single asset, list for a gallery.
        $envelope = ['asset_id' => 1 === \count($merged) ? $merged[0] : $merged];

        $existing = $this->objectValues->findOneByScope($object, $attribute, $channelId, $job->locale);
        if ($existing instanceof ObjectValue) {
            $existing->updateValue($envelope);
            $existing->changeProvenance(Provenance::Import);
            $this->objectValues->save($existing);
        } else {
            $this->objectValues->save(new ObjectValue($object, $attribute, $envelope, Provenance::Import, $channelId, $job->locale));
        }

        $this->assetLinker->linkAssetsToProduct(
            $object->getId(),
            array_map(static fn (string $id): Uuid => Uuid::fromString($id), $merged),
        );
    }

    /**
     * Re-fetch the active tenant as a managed entity on the (possibly cleared)
     * EM and re-publish it to the TenantContext so the assignment listener
     * stamps new ObjectValue rows with a managed tenant.
     */
    private function reattachTenant(Uuid $tenantId): Tenant
    {
        $tenant = $this->entityManager->find(Tenant::class, $tenantId->toRfc4122());
        if (!$tenant instanceof Tenant) {
            throw new RuntimeException(\sprintf('Tenant "%s" not found while applying media downloads.', $tenantId->toRfc4122()));
        }
        $this->tenantContext->set($tenant);

        return $tenant;
    }

    /**
     * Stream the run's ZIP from MinIO to a local temp path (constant memory).
     * Caller unlinks. Throws on a missing/unreadable archive (systemic).
     */
    private function stageZip(string $storagePath): string
    {
        $stream = $this->importsStorage->readStream($storagePath);
        $local = tempnam(sys_get_temp_dir(), 'pim-zip-src-');
        if (false === $local) {
            throw new RuntimeException('Failed to allocate a temp file for the import ZIP.');
        }
        $out = fopen($local, 'w');
        if (false === $out) {
            throw new RuntimeException('Failed to open the import ZIP temp file.');
        }
        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        return $local;
    }

    /**
     * @param list<array{job: ImageDownloadJob, type: ImportErrorType, message: string, value: string}> $pendingLogs by ref
     */
    private function extractAndIngest(ZipImageExtractor $extractor, ImageDownloadJob $job, string $name, array &$pendingLogs): ?string
    {
        $tmp = null;
        try {
            $tmp = $extractor->extractToTemp($name);
            if (null === $tmp) {
                $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageNotFound, 'message' => \sprintf('File "%s" was not found in the uploaded ZIP.', $name), 'value' => $name];

                return null;
            }

            return $this->assetIngestor->ingest($tmp, $name)->assetId->toRfc4122();
        } catch (UnsupportedMediaFormatException $exception) {
            $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageFormatUnsupported, 'message' => \sprintf('ZIP entry "%s": %s', $name, $exception->getMessage()), 'value' => $name];

            return null;
        } catch (Throwable $exception) {
            $this->logger->warning('Import ZIP entry ingest failed.', ['name' => $name, 'exception' => $exception]);
            $pendingLogs[] = ['job' => $job, 'type' => ImportErrorType::ImageNotFound, 'message' => \sprintf('ZIP entry "%s" could not be extracted: %s', $name, $exception->getMessage()), 'value' => $name];

            return null;
        } finally {
            if (null !== $tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Mark the session failed for a systemic media fault (e.g. zip bomb /
     * unreadable archive) instead of letting the batch dead-letter.
     */
    private function failSessionSystemic(Uuid $sessionId, string $message): void
    {
        $session = $this->sessions->findById($sessionId);
        if ($session instanceof ImportSession && !$session->getStatus()->isTerminal()) {
            $session->markFailed($message);
            $this->sessions->save($session);
            $this->progressPublisher->completed($session);
        }
    }

    private function filenameFor(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $base = \is_string($path) ? basename($path) : '';

        return '' !== $base ? $base : 'image';
    }

    private function persistLog(ImportSession $session, ImageDownloadJob $job, ImportErrorType $type, string $message, string $value): void
    {
        $this->entityManager->persist(new ImportLog(
            importSession: $session,
            rowNumber: $job->rowNumber,
            level: ImportLogLevel::Warning,
            message: $message,
            sku: $job->sku,
            errorType: $type->value,
            columnName: $job->attributeCode,
            columnValue: $value,
        ));
    }
}
