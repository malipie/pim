<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;

/**
 * VIEW-15 (#547) — `publish_channels` bulk action.
 *
 * Soft-publish slot under `attributes_indexed['published'][channel_code]`.
 * Real channel_publications + integration adapter calls (Shopify,
 * BaseLinker) land in epik 0.6/0.9 — this handler only writes the
 * intention, not the side effect. The 24h rollback path replays the
 * previous map per row. Shared lifecycle: {@see AbstractBulkHandler}.
 */
final class BulkPublishChannelsHandler extends AbstractBulkHandler
{
    /** @var list<string> */
    private array $channelCodes = [];
    private bool $publish = true;

    /**
     * @param list<string> $channelCodes
     *
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, array $channelCodes, bool $publish): array
    {
        $this->channelCodes = $channelCodes;
        $this->publish = $publish;

        return $this->runBatch($session);
    }

    protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void
    {
        $indexed = $object->getAttributesIndexed();
        /** @var array<string, mixed> $publishedRaw */
        $publishedRaw = \is_array($indexed['published'] ?? null) ? $indexed['published'] : [];
        $before = $publishedRaw;
        $touched = false;
        foreach ($this->channelCodes as $code) {
            $current = (bool) ($publishedRaw[$code] ?? false);
            if ($current === $this->publish) {
                continue;
            }
            $publishedRaw[$code] = $this->publish;
            $touched = true;
        }

        if (!$touched) {
            ++$counters->skipped;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $before,
                $before,
                BulkLog::LEVEL_WARNING,
                'All channels already in the target state',
            ));

            return;
        }

        $indexed['published'] = $publishedRaw;
        $object->updateAttributeIndex($indexed);
        $object->markTouchedByBulkSession($session->getId());
        $this->em->persist(new BulkLog(
            $session->getId(),
            $object->getId(),
            null,
            $before,
            $publishedRaw,
            BulkLog::LEVEL_INFO,
            null,
        ));
        ++$counters->success;
    }
}
