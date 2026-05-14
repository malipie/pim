<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Identity\Contracts\Event\UserAuthenticated;
use App\Shared\Application\TenantAware;
use App\Shared\Application\UserIdentityAware;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LogicException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Application user with a tenant scope.
 *
 * Sprint 0 minimal shape — email + password hash + roles. Full RBAC and 2FA
 * land in epics 0.2 (#24+) and 0.11; for the gate-decision slice this is
 * enough to authenticate via JWT and resolve the active tenant from the
 * authenticated principal (replacing the APP_DEFAULT_TENANT_CODE fallback
 * once auth covers the request).
 *
 * The TenantAware interface lets CurrentTenantProvider read the tenant from
 * the security token's user without coupling Identity to Catalog.
 */
class User extends AggregateRoot implements UserInterface, PasswordAuthenticatedUserInterface, TenantAware, UserIdentityAware
{
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_DISABLED = 'disabled';

    private Uuid $id;

    private Tenant $tenant;

    private string $email;

    private string $password;

    /**
     * Legacy Sprint-0 channel for Symfony Security roles. Survives alongside
     * the M2M `$assignedRoles` relation until #27 (RBAC seeder) migrates the
     * fixture admin onto the proper role graph; #25 then merges both sources
     * inside getRoles() before this column is dropped.
     *
     * @var list<string>
     */
    private array $roles;

    /**
     * @var Collection<int, Role>
     */
    private Collection $assignedRoles;

    private string $status;

    private ?DateTimeImmutable $lastLoginAt;

    /**
     * RFC 6238 TOTP shared secret in base32 encoding. Null while the
     * user has not started 2FA enrolment, populated by
     * {@see \App\Identity\Application\TotpEnrolmentService::enrol},
     * confirmed by `confirmTotpEnrolment()`.
     *
     * Stored unencrypted in the column for now — production deployment
     * will wrap reads/writes through the BYOK encrypter introduced in
     * 0.11.12 (ADR-0017) once 2FA exits the dev fixtures.
     */
    private ?string $totpSecret;

    private ?DateTimeImmutable $totpEnabledAt;

    /**
     * One-shot recovery codes — Argon2id hashes, single-use. Replenished
     * via the dedicated rotate endpoint; consumed by the login flow's
     * fallback path for users locked out of the authenticator app.
     *
     * @var list<string>
     */
    private array $totpBackupCodes;

    private DateTimeImmutable $createdAt;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        Tenant $tenant,
        string $email,
        string $passwordHash,
        array $roles = ['ROLE_USER'],
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->tenant = $tenant;
        $this->email = $email;
        $this->password = $passwordHash;
        $this->roles = $roles;
        $this->assignedRoles = new ArrayCollection();
        $this->status = self::STATUS_ACTIVE;
        $this->lastLoginAt = null;
        $this->totpSecret = null;
        $this->totpEnabledAt = null;
        $this->totpBackupCodes = [];
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // Merge in roles assigned via the M2M graph (#27). Each Role is
        // exposed to Symfony Security as `ROLE_<UPPERCASE_CODE>`, so the
        // RBAC matrix from RbacMatrix and the `roles JSON` legacy column
        // share one resolved list. Sprint-0 fixtures still rely on the JSON
        // column; the legacy path stays until every fixture writes M2M.
        foreach ($this->assignedRoles as $role) {
            $roles[] = 'ROLE_'.strtoupper($role->getCode());
        }

        // Symfony convention — every authenticated user must have ROLE_USER
        // even when not stored explicitly, so access_control rules behave as
        // documented across the framework.
        if (!\in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return array_values(array_unique($roles));
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email, 'User email is enforced NOT NULL by the schema and never empty.');

        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // No transient sensitive data — password hash stays for re-auth flows.
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    public function disable(): void
    {
        $this->status = self::STATUS_DISABLED;
    }

    public function enable(): void
    {
        $this->status = self::STATUS_ACTIVE;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function recordLogin(?DateTimeImmutable $when = null): void
    {
        $this->lastLoginAt = $when ?? new DateTimeImmutable();
        $this->recordThat(new UserAuthenticated(
            userId: $this->id,
            tenantId: $this->tenant->getId(),
            email: $this->email,
            occurredOn: $this->lastLoginAt,
        ));
    }

    /**
     * @return Collection<int, Role>
     */
    public function getAssignedRoles(): Collection
    {
        return $this->assignedRoles;
    }

    public function addRole(Role $role): void
    {
        if (!$this->assignedRoles->contains($role)) {
            $this->assignedRoles->add($role);
        }
    }

    public function removeRole(Role $role): void
    {
        $this->assignedRoles->removeElement($role);
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function isTotpEnabled(): bool
    {
        return null !== $this->totpEnabledAt;
    }

    public function getTotpEnabledAt(): ?DateTimeImmutable
    {
        return $this->totpEnabledAt;
    }

    /**
     * @return list<string>
     */
    public function getTotpBackupCodes(): array
    {
        return $this->totpBackupCodes;
    }

    /**
     * Stamps the user with a freshly generated TOTP secret + recovery
     * codes, but does NOT enable 2FA yet — `confirmTotpEnrolment()`
     * does that after the first successful code verification, so a
     * dropped enrolment never locks the user out.
     *
     * @param list<string> $backupCodeHashes
     */
    public function startTotpEnrolment(string $secret, array $backupCodeHashes): void
    {
        $this->totpSecret = $secret;
        $this->totpBackupCodes = $backupCodeHashes;
        $this->totpEnabledAt = null;
    }

    public function confirmTotpEnrolment(?DateTimeImmutable $when = null): void
    {
        if (null === $this->totpSecret) {
            throw new LogicException('Cannot confirm TOTP enrolment before a secret has been provisioned.');
        }
        $this->totpEnabledAt = $when ?? new DateTimeImmutable();
    }

    public function disableTotp(): void
    {
        $this->totpSecret = null;
        $this->totpEnabledAt = null;
        $this->totpBackupCodes = [];
    }

    /**
     * Marks one backup code as consumed. The caller is responsible for
     * matching the cleartext code against one of the stored hashes;
     * this method just removes the matching hash from the active list.
     */
    public function consumeBackupCode(string $usedHash): void
    {
        $this->totpBackupCodes = array_values(array_filter(
            $this->totpBackupCodes,
            static fn (string $hash): bool => $hash !== $usedHash,
        ));
    }
}
