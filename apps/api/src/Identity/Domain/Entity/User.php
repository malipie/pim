<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Identity\Application\TenantAware;
use App\Identity\Infrastructure\Doctrine\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
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
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'users_email_uniq', columns: ['email'])]
#[ORM\Index(name: 'users_tenant_idx', columns: ['tenant_id'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TenantAware
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Tenant $tenant;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'string')]
    private string $password;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles;

    #[ORM\Column(type: 'datetime_immutable')]
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
        // Symfony convention — every authenticated user must have ROLE_USER
        // even when not stored explicitly, so access_control rules behave as
        // documented across the framework.
        if (!\in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return $roles;
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
}
