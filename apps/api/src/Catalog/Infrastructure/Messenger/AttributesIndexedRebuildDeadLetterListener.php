<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Messenger;

use App\Catalog\Application\Message\ObjectValuesChangedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * AUD-039 / G-01 — dead-letter guard for the async attributes_indexed rebuild.
 *
 * When a {@see ObjectValuesChangedMessage} exhausts the async transport's
 * retries (the per-id rebuild kept hitting version conflicts, see
 * {@see \App\Catalog\Application\Handler\RebuildAttributesIndexedHandler}), the
 * envelope lands in the `failed` transport. Previously the handler swallowed
 * that case with a Warning + a "successful" return, so the cache drifted from
 * `object_values` with no trace. This listener logs the final failure at error
 * level with the affected object ids, so the drift is visible in logs /
 * alerting and `pim:catalog:detect-attributes-drift --reconcile` can repair it.
 *
 * Mirrors {@see \App\Import\Infrastructure\Messenger\ImportRunDeadLetterListener}:
 * fires only on the FINAL failure (`willRetry() === false`) so retriable
 * failures are left to the long-backoff retry policy.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class AttributesIndexedRebuildDeadLetterListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ObjectValuesChangedMessage) {
            return;
        }

        $this->logger->error(
            'attributes_indexed rebuild dead-lettered after exhausting retries — '
            .'cache may drift from object_values; run pim:catalog:detect-attributes-drift --reconcile',
            [
                'object_ids' => $message->objectIds,
                'count' => \count($message->objectIds),
                'exception' => $event->getThrowable()->getMessage(),
            ],
        );
    }
}
