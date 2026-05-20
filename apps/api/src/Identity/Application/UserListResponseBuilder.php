<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\Invitation;
use App\Identity\Domain\Entity\Role;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use DateTimeInterface;

use const MB_CASE_TITLE;

/**
 * Maps {@see User} aggregates onto the JSON shape consumed by the
 * admin Settings → Users list (RBAC-P5-001 #691).
 *
 * Sensitive fields (password hash, TOTP secret, recovery code hashes)
 * are *never* projected — the only TOTP signal exposed is the boolean
 * `mfa_enabled`. Tenant boundary is enforced upstream by the repository
 * query + Doctrine TenantFilter; this builder is intentionally
 * tenant-agnostic so it can be reused by the SuperAdmin tenant-scoped
 * view (RBAC-P5-020 #710) without duplicating the projection logic.
 */
final class UserListResponseBuilder
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {
    }

    /**
     * @param iterable<User>       $users
     * @param iterable<Invitation> $pendingInvitations
     *
     * @return list<array{
     *     id: string,
     *     kind: string,
     *     email: string,
     *     display_name: string,
     *     avatar_initial: string,
     *     status: string,
     *     roles: list<array{id: string, code: string, name: string}>,
     *     last_login_at: ?string,
     *     mfa_enabled: bool,
     *     created_at: string,
     *     invitation_id: ?string,
     *     invitation_expires_at: ?string
     * }>
     */
    public function buildList(iterable $users, iterable $pendingInvitations = []): array
    {
        $out = [];
        foreach ($users as $user) {
            $out[] = $this->buildOne($user);
        }
        foreach ($pendingInvitations as $invitation) {
            $out[] = $this->buildInvitation($invitation);
        }

        // Deterministic order: by email so the row position is stable
        // across renders + matches the operator's mental model.
        usort($out, static fn (array $a, array $b): int => strcmp($a['email'], $b['email']));

        return $out;
    }

    /**
     * @return array{
     *     id: string,
     *     kind: string,
     *     email: string,
     *     display_name: string,
     *     avatar_initial: string,
     *     status: string,
     *     roles: list<array{id: string, code: string, name: string}>,
     *     last_login_at: ?string,
     *     mfa_enabled: bool,
     *     created_at: string,
     *     invitation_id: ?string,
     *     invitation_expires_at: ?string
     * }
     */
    public function buildOne(User $user): array
    {
        $email = $user->getEmail();
        $displayName = $this->deriveDisplayName($email);

        $roles = [];
        foreach ($user->getAssignedRoles() as $role) {
            $roles[] = [
                'id' => $role->getId()->toRfc4122(),
                'code' => $role->getCode(),
                'name' => $role->getName(),
            ];
        }

        return [
            'id' => $user->getId()->toRfc4122(),
            'kind' => 'user',
            'email' => $email,
            'display_name' => $displayName,
            'avatar_initial' => $this->avatarInitial($displayName, $email),
            'status' => $user->getStatus(),
            'roles' => $roles,
            'last_login_at' => $user->getLastLoginAt()?->format(DateTimeInterface::ATOM),
            'mfa_enabled' => $user->isTotpEnabled(),
            'created_at' => $user->getCreatedAt()->format(DateTimeInterface::ATOM),
            'invitation_id' => null,
            'invitation_expires_at' => null,
        ];
    }

    /**
     * Project a pending invitation as a virtual list row (status =
     * `invited`). The id field carries the invitation uuid so the FE
     * 3-dot menu can target the invitation (resend / revoke) instead
     * of a user that does not exist yet.
     *
     * @return array{
     *     id: string,
     *     kind: string,
     *     email: string,
     *     display_name: string,
     *     avatar_initial: string,
     *     status: string,
     *     roles: list<array{id: string, code: string, name: string}>,
     *     last_login_at: ?string,
     *     mfa_enabled: bool,
     *     created_at: string,
     *     invitation_id: ?string,
     *     invitation_expires_at: ?string
     * }
     */
    public function buildInvitation(Invitation $invitation): array
    {
        $email = $invitation->getEmail();
        $displayName = $this->deriveDisplayName($email);

        $role = $this->roles->findById($invitation->getRoleId());
        $rolesProjection = null === $role ? [] : [self::projectRole($role)];

        return [
            'id' => $invitation->getId()->toRfc4122(),
            'kind' => 'invitation',
            'email' => $email,
            'display_name' => $displayName,
            'avatar_initial' => $this->avatarInitial($displayName, $email),
            'status' => 'invited',
            'roles' => $rolesProjection,
            'last_login_at' => null,
            'mfa_enabled' => false,
            'created_at' => $invitation->getCreatedAt()->format(DateTimeInterface::ATOM),
            'invitation_id' => $invitation->getId()->toRfc4122(),
            'invitation_expires_at' => $invitation->getExpiresAt()->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{id: string, code: string, name: string}
     */
    private static function projectRole(Role $role): array
    {
        return [
            'id' => $role->getId()->toRfc4122(),
            'code' => $role->getCode(),
            'name' => $role->getName(),
        ];
    }

    /**
     * Derive a human-friendly display string from the email until the
     * schema grows dedicated first_name/last_name columns (deferred to
     * a profile-fields ticket — tracked in #693 follow-up).
     */
    private function deriveDisplayName(string $email): string
    {
        // explode() always returns at least one element for a non-empty string,
        // so the local part is guaranteed; an email without `@` falls through
        // to using the whole string, which is still a sensible display name.
        $localPart = explode('@', $email, 2)[0];
        // Replace common separators with spaces and title-case so
        // `jan.kowalski` reads as `Jan Kowalski` in the list.
        $normalised = preg_replace('/[._-]+/', ' ', $localPart);
        if (null === $normalised || '' === trim($normalised)) {
            return $email;
        }

        return mb_convert_case(trim($normalised), MB_CASE_TITLE, 'UTF-8');
    }

    private function avatarInitial(string $displayName, string $email): string
    {
        $source = '' !== trim($displayName) ? $displayName : $email;
        $first = mb_substr($source, 0, 1, 'UTF-8');

        return mb_strtoupper($first, 'UTF-8');
    }
}
