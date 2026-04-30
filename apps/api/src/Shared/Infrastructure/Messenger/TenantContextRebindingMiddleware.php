<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use App\Shared\Infrastructure\Messenger\Stamp\TenantStamp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Audit finding HIGH-002 (2026-04-29): on async transports the
 * worker process boots a fresh kernel and the request listener that
 * binds {@see TenantContext} from the JWT principal never fires.
 * Without explicit rebinding the {@see \App\Shared\Infrastructure\Doctrine\Filter\TenantFilter}
 * either runs against the stale context the previous handler left
 * (cross-tenant read leak) or against `null` (LogicException on
 * persist) — neither is acceptable.
 *
 * This middleware:
 *   - on a worker run (`ConsumedByWorkerStamp` present) reads the
 *     tenant id from a {@see TenantStamp} or, as a fallback, from a
 *     message implementing {@see TenantAwareMessage};
 *   - looks up the {@see Tenant} aggregate and binds it via
 *     `TenantContext::set()`;
 *   - clears the context after `$stack->next()->handle()` returns
 *     so the next handler on the same worker boot starts clean.
 *
 * Synchronous dispatches (no `ConsumedByWorkerStamp`) skip the
 * middleware — the request listener is the source of truth there.
 *
 * Behaviour for messages without any tenant source:
 *   - **sync**: passes through (current MVP default — no-op).
 *   - **async**: throws `RuntimeException`. An unbound async message
 *     is a bug — every cross-process payload must declare its tenant.
 */
final readonly class TenantContextRebindingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private TenantRepositoryInterface $tenants,
        private TenantContext $tenantContext,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $consumed = $envelope->last(ConsumedByWorkerStamp::class);
        if (null === $consumed) {
            return $stack->next()->handle($envelope, $stack);
        }

        $tenantId = $this->resolveTenantId($envelope);
        if (null === $tenantId) {
            throw new RuntimeException(\sprintf(
                'Async message %s carries no tenant context. Add a TenantStamp '.
                'on dispatch or have the message implement TenantAwareMessage.',
                $envelope->getMessage()::class,
            ));
        }

        $tenant = $this->tenants->findById($tenantId);
        if (null === $tenant) {
            throw new RuntimeException(\sprintf(
                'Tenant "%s" referenced by async message %s no longer exists.',
                $tenantId->toRfc4122(),
                $envelope->getMessage()::class,
            ));
        }

        $this->tenantContext->set($tenant);
        $this->logger->debug('Tenant context rebound for async handler', [
            'tenantId' => $tenantId->toRfc4122(),
            'message' => $envelope->getMessage()::class,
        ]);

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            // Defence in depth: a long-lived worker boots the kernel
            // once and re-uses the container. Leaving the context set
            // would leak the previous tenant into the next handler.
            $this->tenantContext->clear();
        }
    }

    private function resolveTenantId(Envelope $envelope): ?Uuid
    {
        $stamp = $envelope->last(TenantStamp::class);
        if ($stamp instanceof TenantStamp) {
            return $stamp->tenantId;
        }

        $message = $envelope->getMessage();
        if ($message instanceof TenantAwareMessage) {
            return $message->tenantId();
        }

        return null;
    }
}
