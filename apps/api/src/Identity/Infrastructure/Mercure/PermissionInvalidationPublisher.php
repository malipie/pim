<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mercure;

use App\Identity\Domain\Entity\User;
use App\Shared\Infrastructure\Mercure\MercureSubscribeTopics;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Uuid;
use Throwable;

use const JSON_THROW_ON_ERROR;

/**
 * RBAC-P4-010 (#687) — Mercure publisher for permission invalidation.
 *
 * When a permission grant changes (role assignment added/removed,
 * `role_permissions` mutated, `role_attribute_permissions` /
 * `role_attribute_group_permissions` insert/update/delete,
 * `roles.default_attribute_permission` flipped), the backend must
 * tell every affected user's open SPA tab to drop its cached
 * `useIdentity()` result and refetch — otherwise the user keeps
 * acting under stale permissions until they reload manually.
 *
 * Topic naming follows the existing Export / Import publishers'
 * convention so the frontend EventSource subscribes to one shape.
 * Tenant-scoped + private after AUD-001 (#1573):
 *
 *   https://{public}/tenant/{tenantId}/identity/user/{userId}   — per-user
 *   https://{public}/tenant/{tenantId}/identity/tenant/{tenantId} —
 *                                                 tenant-wide (every user
 *                                                 inside, macierz changes)
 *
 * The matching frontend listener — `usePermissionInvalidationSse()` —
 * invalidates the `['rbac','identity']` query key so React Query
 * refetches `/api/auth/me` automatically (RBAC-P4-001 #678).
 *
 * The publisher does NOT decide *what* invalidates — callers (the
 * service-layer hooks that mutate role / permission tables) pass the
 * affected user explicitly. The publisher only emits the wire event.
 * The backend cache invalidation (PermissionResolver) is independent
 * — see {@see \App\Identity\Application\PermissionResolverInterface}.
 *
 * Mercure publish failures are logged but never thrown — a Mercure
 * outage must not block a permission mutation. The next legitimate
 * `/api/auth/me` fetch will repopulate the frontend state when the
 * cache TTL expires (5 min default).
 */
final readonly class PermissionInvalidationPublisher
{
    public const string EVENT_TYPE = 'permission.invalidated';

    public function __construct(
        private HubInterface $hub,
        private string $topicBase = 'https://pim.localhost',
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function publishForUser(User $user, string $reason = 'permission_changed'): void
    {
        $tenantId = $user->getTenant()->getId();
        $topic = MercureSubscribeTopics::identityUser($tenantId, $this->topicBase, $user->getId()->toRfc4122());

        $this->emit($topic, [
            'type' => self::EVENT_TYPE,
            'scope' => 'user',
            'user_id' => $user->getId()->toRfc4122(),
            'tenant_id' => $tenantId->toRfc4122(),
            'reason' => $reason,
        ]);
    }

    public function publishForTenant(Uuid $tenantId, string $reason = 'role_macierz_changed'): void
    {
        $topic = MercureSubscribeTopics::identityTenant($tenantId, $this->topicBase);

        $this->emit($topic, [
            'type' => self::EVENT_TYPE,
            'scope' => 'tenant',
            'tenant_id' => $tenantId->toRfc4122(),
            'reason' => $reason,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emit(string $topic, array $payload): void
    {
        try {
            $this->hub->publish(new Update($topic, json_encode($payload, JSON_THROW_ON_ERROR), private: true));
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Permission Mercure invalidation publish failed; SPA will refetch on next /api/auth/me TTL expiry.',
                [
                    'topic' => $topic,
                    'reason' => $payload['reason'] ?? 'unknown',
                    'exception' => $exception->getMessage(),
                ],
            );
        }
    }
}
