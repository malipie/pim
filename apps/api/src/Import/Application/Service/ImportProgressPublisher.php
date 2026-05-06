<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Entity\ImportSession;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

use const JSON_THROW_ON_ERROR;

/**
 * Publishes import lifecycle / progress updates to two Mercure topics
 * (spec §8.5):
 *   - `imports/{user_id}` — broadcast list view (status changes)
 *   - `imports/{session_id}` — detail (progress %, current SKU, errors)
 *
 * Hub failures are logged but never abort the underlying write — Mercure
 * is a notification channel, not the source of truth (mirrors the
 * Catalog publisher's stance).
 */
final class ImportProgressPublisher
{
    private const string TOPIC_PREFIX = 'imports';

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HubInterface $hub,
        private readonly string $topicBase = 'https://pim.localhost',
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function progress(ImportSession $session, int $processedRows, ?string $currentSku = null): void
    {
        $totalRows = $session->getTotalRows() ?? 0;
        $this->publish($session, 'progress', [
            'processed_rows' => $processedRows,
            'total_rows' => $totalRows,
            'success_count' => $session->getSuccessCount(),
            'error_count' => $session->getErrorCount(),
            'current_sku' => $currentSku,
        ]);
    }

    public function rowProcessed(ImportSession $session, int $rowNumber, ?string $sku, bool $success): void
    {
        $this->publish($session, 'row_processed', [
            'row_number' => $rowNumber,
            'sku' => $sku,
            'success' => $success,
        ]);
    }

    public function error(ImportSession $session, int $rowNumber, ?string $sku, string $errorType, string $message): void
    {
        $this->publish($session, 'error', [
            'row_number' => $rowNumber,
            'sku' => $sku,
            'error_type' => $errorType,
            'message' => $message,
        ]);
    }

    public function completed(ImportSession $session): void
    {
        $this->publish($session, 'completed', [
            'status' => $session->getStatus()->value,
            'success_count' => $session->getSuccessCount(),
            'error_count' => $session->getErrorCount(),
            'total_rows' => $session->getTotalRows() ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(ImportSession $session, string $type, array $payload): void
    {
        $sessionTopic = \sprintf('%s/%s/%s', $this->topicBase, self::TOPIC_PREFIX, $session->getId()->toRfc4122());
        $userTopic = \sprintf('%s/%s/user/%s', $this->topicBase, self::TOPIC_PREFIX, $session->getUserId()->toRfc4122());

        $update = new Update(
            topics: [$sessionTopic, $userTopic],
            data: json_encode([
                'type' => $type,
                'session_id' => $session->getId()->toRfc4122(),
                'data' => $payload,
            ], JSON_THROW_ON_ERROR),
        );

        try {
            $this->hub->publish($update);
        } catch (Throwable $exception) {
            $this->logger->warning('Mercure import progress publish failed: {message}', [
                'message' => $exception->getMessage(),
                'session_id' => $session->getId()->toRfc4122(),
                'type' => $type,
            ]);
        }
    }
}
