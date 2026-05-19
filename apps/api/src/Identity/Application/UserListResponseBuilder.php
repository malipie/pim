<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\User;
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
    /**
     * @param iterable<User> $users
     *
     * @return list<array{
     *     id: string,
     *     email: string,
     *     display_name: string,
     *     avatar_initial: string,
     *     status: string,
     *     roles: list<array{id: string, code: string, name: string}>,
     *     last_login_at: ?string,
     *     mfa_enabled: bool,
     *     created_at: string
     * }>
     */
    public function buildList(iterable $users): array
    {
        $out = [];
        foreach ($users as $user) {
            $out[] = $this->buildOne($user);
        }

        return $out;
    }

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     display_name: string,
     *     avatar_initial: string,
     *     status: string,
     *     roles: list<array{id: string, code: string, name: string}>,
     *     last_login_at: ?string,
     *     mfa_enabled: bool,
     *     created_at: string
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
            'email' => $email,
            'display_name' => $displayName,
            'avatar_initial' => $this->avatarInitial($displayName, $email),
            'status' => $user->getStatus(),
            'roles' => $roles,
            'last_login_at' => $user->getLastLoginAt()?->format(DateTimeInterface::ATOM),
            'mfa_enabled' => $user->isTotpEnabled(),
            'created_at' => $user->getCreatedAt()->format(DateTimeInterface::ATOM),
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
