<?php

declare(strict_types=1);

namespace App\Export\Application\Async;

use App\Export\Domain\Entity\ExportSession;
use App\Shared\Infrastructure\Mercure\MercureSubscribeTopics;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

use const JSON_THROW_ON_ERROR;

/**
 * EXP-06 (#585) — Mercure SSE publisher for async exports.
 *
 * Publishes lifecycle events to two tenant-scoped, private topics
 * (PRD §11.5; AUD-001 #1573):
 *   - `tenant/{tid}/exports/{user_id}` — list-view broadcast so the
 *     user's "Recent exports" grid (EXP-13) refreshes when ANY of their
 *     exports changes status.
 *   - `tenant/{tid}/exports/{session_id}` — detail topic carrying
 *     per-chunk progress (rows_done, rows_total, progress_pct,
 *     estimated_seconds_remaining).
 *
 * The `tenant/{tid}` prefix + `private: true` close the AUD-001
 * cross-tenant leak: the hub only delivers a private update to a
 * subscriber whose JWT authorises that exact tenant topic.
 *
 * Hub failures are logged but never abort the underlying export — the
 * MinIO file write is the source of truth; Mercure is a notification
 * channel (mirrors `ImportProgressPublisher` and `MercurePublisher`).
 */
final class ExportProgressPublisher
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HubInterface $hub,
        private readonly string $topicBase,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Per-chunk progress tick (rows_done out of rows_total).
     */
    public function progress(ExportSession $session, int $rowsDone, ?int $estimatedSecondsRemaining): void
    {
        $total = $session->getTargetCount();
        $pct = $total > 0 ? (int) floor($rowsDone / $total * 100) : 0;

        $this->publish($session, 'progress', [
            'rows_done' => $rowsDone,
            'rows_total' => $total,
            'progress_pct' => $pct,
            'estimated_seconds_remaining' => $estimatedSecondsRemaining,
        ]);
    }

    /**
     * Status transition (pending → running → done / error). Drives the
     * Recent exports grid refresh + the bell notification (EXP-13).
     */
    public function status(ExportSession $session): void
    {
        $this->publish($session, 'status', [
            'status' => $session->getStatus()->value,
            'success_count' => $session->getSuccessCount(),
            'error_message' => $session->getErrorMessage(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(ExportSession $session, string $eventType, array $payload): void
    {
        $message = [
            'event' => $eventType,
            'session_id' => $session->getId()->toRfc4122(),
            ...$payload,
        ];

        $encoded = json_encode($message, JSON_THROW_ON_ERROR);

        $tenant = $session->getTenant();
        if (null === $tenant) {
            // Sessions are tenant-stamped before processing; a null here is
            // a misconfiguration. Skip rather than emit an un-scoped topic.
            $this->logger->warning('Export progress publish skipped: session has no tenant', [
                'session_id' => $session->getId()->toRfc4122(),
                'event' => $eventType,
            ]);

            return;
        }
        $tenantId = $tenant->getId();

        $userTopic = MercureSubscribeTopics::exportUser($tenantId, $this->topicBase, $session->getUserId()->toRfc4122());
        $sessionTopic = MercureSubscribeTopics::exportSession($tenantId, $this->topicBase, $session->getId()->toRfc4122());

        try {
            $this->hub->publish(new Update($userTopic, $encoded, private: true));
            $this->hub->publish(new Update($sessionTopic, $encoded, private: true));
        } catch (Throwable $error) {
            $this->logger->warning('Export progress publish failed', [
                'session_id' => $session->getId()->toRfc4122(),
                'event' => $eventType,
                'error' => $error->getMessage(),
            ]);
        }
    }
}
