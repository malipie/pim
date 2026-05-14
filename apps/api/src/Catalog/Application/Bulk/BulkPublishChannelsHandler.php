<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Search\Application\BulkReindexQueue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-15 (#547) — `publish_channels` bulk action.
 *
 * Soft-publish slot under `attributes_indexed['published'][channel_code]`.
 * Real channel_publications + integration adapter calls (Shopify,
 * BaseLinker) land in epik 0.6/0.9 — this handler only writes the
 * intention, not the side effect. The 24h rollback path replays the
 * previous map per row.
 */
final class BulkPublishChannelsHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
        private readonly BulkReindexQueue $reindexQueue,
    ) {
    }

    /**
     * @param list<string> $channelCodes
     *
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, array $channelCodes, bool $publish): array
    {
        $this->bulkContext->setBulk(true, $session->getId());
        try {
            $success = 0;
            $skipped = 0;
            $errors = 0;
            $chunkCounter = 0;

            foreach ($session->getTargetObjectIds() as $targetId) {
                try {
                    $product = $this->catalogObjects->findById(Uuid::fromString($targetId));
                    if (!$product instanceof CatalogObject) {
                        ++$errors;
                        ++$chunkCounter;
                        continue;
                    }

                    $indexed = $product->getAttributesIndexed();
                    /** @var array<string, mixed> $publishedRaw */
                    $publishedRaw = \is_array($indexed['published'] ?? null) ? $indexed['published'] : [];
                    $before = $publishedRaw;
                    $touched = false;
                    foreach ($channelCodes as $code) {
                        $current = (bool) ($publishedRaw[$code] ?? false);
                        if ($current === $publish) {
                            continue;
                        }
                        $publishedRaw[$code] = $publish;
                        $touched = true;
                    }

                    if (!$touched) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $product->getId(),
                            null,
                            $before,
                            $before,
                            BulkLog::LEVEL_WARNING,
                            'All channels already in the target state',
                        ));
                    } else {
                        $indexed['published'] = $publishedRaw;
                        $product->updateAttributeIndex($indexed);
                        $product->markTouchedByBulkSession($session->getId());
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $product->getId(),
                            null,
                            $before,
                            $publishedRaw,
                            BulkLog::LEVEL_INFO,
                            null,
                        ));
                        ++$success;
                    }
                } catch (Throwable $e) {
                    ++$errors;
                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        Uuid::fromString($targetId),
                        null,
                        null,
                        null,
                        BulkLog::LEVEL_ERROR,
                        $e->getMessage(),
                    ));
                }

                ++$chunkCounter;
                if ($chunkCounter >= self::CHUNK_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                    $chunkCounter = 0;
                }
            }

            if ($chunkCounter > 0) {
                $this->em->flush();
            }

            $session->complete($success, $skipped, $errors);
            $this->em->persist($session);
            $this->em->flush();

            $this->reindexQueue->queueAll($session->getTargetObjectIds());

            return ['success' => $success, 'skipped' => $skipped, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
