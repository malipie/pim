<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * Drops duplicate-handle attempts on top of Doctrine's at-least-once
 * delivery semantics (sync now, async transports in Faza 1+). Logic:
 *
 *   1. On a worker run (ConsumedByWorkerStamp present), look up the
 *      envelope's TransportMessageIdStamp.
 *   2. INSERT the id into processed_messages with handler_class set to
 *      the receiver tag — Postgres' UNIQUE constraint on message_id
 *      surfaces a UniqueConstraintViolationException if the same id
 *      was already handled, in which case the middleware short-circuits.
 *   3. Otherwise it forwards the envelope down the stack and lets the
 *      handler run normally.
 *
 * Synchronous dispatches (no ConsumedByWorkerStamp) skip the middleware
 * entirely — sync handlers run inside the originating transaction and
 * are idempotent by construction (a single commit cannot replay).
 */
final readonly class IdempotencyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $consumed = $envelope->last(ConsumedByWorkerStamp::class);
        if (null === $consumed) {
            return $stack->next()->handle($envelope, $stack);
        }

        $idStamp = $envelope->last(TransportMessageIdStamp::class);
        if (null === $idStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $rawId = $idStamp->getId();
        if (!\is_string($rawId) && !\is_int($rawId)) {
            return $stack->next()->handle($envelope, $stack);
        }
        $messageId = (string) $rawId;
        if ('' === $messageId) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            $this->connection->insert('processed_messages', [
                'message_id' => $messageId,
                'handler_class' => $envelope->getMessage()::class,
                'processed_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already processed — short-circuit, return envelope as-is.
            return $envelope;
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
