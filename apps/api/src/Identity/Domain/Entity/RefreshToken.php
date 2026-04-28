<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Identity\Infrastructure\Doctrine\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Persisted refresh token issued at login and rotated on every /api/auth/refresh.
 *
 * Stored as a SHA-256 hex digest (`tokenHash`) — the raw secret only ever
 * leaves the server in the httpOnly cookie. `familyId` groups every token
 * derived from a single login; reuse of an already-used token revokes the
 * whole family at once (theft detection — see RefreshTokenService::rotate).
 *
 * `tenantId`/`userId` are denormalised UUIDs (no Doctrine relation) so token
 * lookups never join: the refresh path is hot and the only reads are by hash.
 * The schema-level FKs in the migration enforce referential integrity.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'refresh_tokens_tenant_idx', columns: ['tenant_id'])]
#[ORM\Index(name: 'refresh_tokens_user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'refresh_tokens_family_idx', columns: ['family_id'])]
#[ORM\UniqueConstraint(name: 'refresh_tokens_token_hash_uniq', columns: ['token_hash'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'tenant_id', type: 'uuid')]
    private Uuid $tenantId;

    #[ORM\Column(name: 'user_id', type: 'uuid')]
    private Uuid $userId;

    #[ORM\Column(name: 'family_id', type: 'uuid')]
    private Uuid $familyId;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'issued_at', type: 'datetime_immutable')]
    private DateTimeImmutable $issuedAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    public function __construct(
        Uuid $tenantId,
        Uuid $userId,
        Uuid $familyId,
        string $tokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->familyId = $familyId;
        $this->tokenHash = $tokenHash;
        $this->issuedAt = $issuedAt;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getFamilyId(): Uuid
    {
        return $this->familyId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getIssuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function markUsed(DateTimeImmutable $when): void
    {
        if (null === $this->usedAt) {
            $this->usedAt = $when;
        }
    }

    public function revoke(DateTimeImmutable $when): void
    {
        if (null === $this->revokedAt) {
            $this->revokedAt = $when;
        }
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return !$this->isExpired($now) && !$this->isUsed() && !$this->isRevoked();
    }
}
