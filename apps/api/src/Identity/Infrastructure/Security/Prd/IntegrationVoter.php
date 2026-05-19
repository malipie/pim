<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC-P3-006 (#669) — authorization for the Integration management
 * surface aligned with the PRD §3.2 macierz row.
 *
 * Two codes split the macierz:
 *   - `settings.integrations.manage`        — read + configure + manage
 *                                             webhooks,
 *   - `settings.integration_secrets.read`   — separate read access to
 *                                             `access_token` /
 *                                             `webhook_secret` payloads.
 *                                             Even Owner needs this to
 *                                             see the secret bodies.
 *
 * Action → permission resolution:
 *   - `view`             → `settings.integrations.manage`,
 *   - `manage_config`    → `settings.integrations.manage`,
 *   - `manage_webhooks`  → `settings.integrations.manage`,
 *   - `read_secrets`     → `settings.integration_secrets.read`.
 *
 * Subject: the Integration BC is forward-compatible (per ADR-0013 / Deptrac
 * configuration — `src/Integration/*` is empty in MVP). The voter accepts
 * either the canonical placeholder string `"integration"` or a future
 * Integration entity once the BC fills in. Phase 6 retrofit tightens the
 * subject contract when concrete adapters land.
 *
 * Last-used IP redaction (per ticket discussion) is a serializer concern
 * (RBAC-P3-012 #675), not a voter concern — this voter only decides
 * whether the action is allowed, not which fields the response carries.
 */
final class IntegrationVoter extends Voter
{
    public const string SUBJECT_PLACEHOLDER = 'integration';

    private const array ATTRIBUTES = [
        'view' => 'settings.integrations.manage',
        'manage_config' => 'settings.integrations.manage',
        'manage_webhooks' => 'settings.integrations.manage',
        'read_secrets' => 'settings.integration_secrets.read',
    ];

    public function __construct(private readonly PermissionResolverInterface $resolver)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\array_key_exists($attribute, self::ATTRIBUTES)) {
            return false;
        }

        if (\is_string($subject)) {
            return self::SUBJECT_PLACEHOLDER === $subject
                || str_contains($subject, '\\Integration\\');
        }

        if (\is_object($subject)) {
            return str_contains($subject::class, '\\Integration\\');
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $code = self::ATTRIBUTES[$attribute] ?? null;
        if (null === $code) {
            return false;
        }

        return $this->resolver->resolve($user)->has($code);
    }
}
