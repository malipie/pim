<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC-P3-007 (#670) — audit log read authorization aligned with the
 * PRD §3.2 macierz row.
 *
 * Three actions split the macierz:
 *
 *   - `view_own`                    — every authenticated user; service
 *                                     layer scopes the query to
 *                                     `WHERE user_id = current_user`,
 *   - `view_cross_user`             — Owner / Admin / Approver / Viewer
 *                                     scope (tenant-wide audit view),
 *   - `view_platform_cross_tenant`  — Super Admin only, no tenant filter.
 *
 * Permission codes:
 *
 *   - `audit.view_own`              — granted to every tenant-level role
 *                                     in the macierz,
 *   - `audit.view_cross_user`       — tenant-wide,
 *   - `platform.audit.view_all`     — platform-scope, Super Admin only.
 *
 * Subject: the AuditLog entity ships with the audit log listener
 * (RBAC-P3-013 #676). Until then the voter operates on the placeholder
 * string `'audit_log'` — controller-side calls pass that literal until
 * the entity replaces it. Voter does not implement query filtering;
 * that is the responsibility of {@see \App\Identity\Application\Policy\OwnershipPolicy}
 * (RBAC-P3-010 #673) plus the service layer once AuditLog lands.
 */
final class AuditLogVoter extends Voter
{
    public const string SUBJECT_PLACEHOLDER = 'audit_log';

    private const array ATTRIBUTES = [
        'view_own' => 'audit.view_own',
        'view_cross_user' => 'audit.view_cross_user',
        'view_platform_cross_tenant' => 'platform.audit.view_all',
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
                || str_contains($subject, '\\AuditLog');
        }

        if (\is_object($subject)) {
            return str_contains($subject::class, 'AuditLog');
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
