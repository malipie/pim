<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Exception\RefreshTokenException;
use App\Identity\Domain\Entity\RefreshToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Infrastructure\Doctrine\Repository\RefreshTokenRepository;
use App\Identity\Infrastructure\Doctrine\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Issue, rotate and revoke refresh tokens.
 *
 * Refresh tokens are single-use: every successful `rotate()` marks the
 * presented token `used_at` and issues a new one with the same `family_id`.
 * If a caller presents a token whose `used_at` is set, the entire family is
 * revoked — that's the theft-detection path, since a legitimate client has
 * no way to send the same token twice (the cookie was overwritten on the
 * previous rotation).
 *
 * The raw token never lives in the DB. We persist a SHA-256 hex digest;
 * lookup is by hash with a unique index. Hashing is constant-time for our
 * purposes because the lookup is by value, not by comparison — there is no
 * timing-attack window worth defending against.
 */
final readonly class RefreshTokenService
{
    public function __construct(
        private RefreshTokenRepository $tokens,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private string $ttl = 'P30D',
    ) {
    }

    /**
     * Issue a new refresh token for `$user`, opening a fresh family.
     *
     * @return array{raw: string, entity: RefreshToken}
     */
    public function issueForUser(User $user): array
    {
        return $this->issue($user, Uuid::v7());
    }

    /**
     * Consume `$rawToken`, rotate it, return the new pair.
     *
     * @return array{user: User, raw: string, entity: RefreshToken}
     */
    public function rotate(string $rawToken): array
    {
        $now = $this->clock->now();
        $token = $this->lookup($rawToken);

        if ($token->isRevoked()) {
            throw RefreshTokenException::revoked();
        }
        if ($token->isUsed()) {
            // Theft detection: someone presented a token that was already
            // rotated. Burn the whole family so the legitimate client and
            // the attacker both have to re-authenticate.
            $this->tokens->revokeFamily($token->getFamilyId(), $now);
            $this->em->flush();

            throw RefreshTokenException::reused();
        }
        if ($token->isExpired($now)) {
            throw RefreshTokenException::expired();
        }

        $user = $this->users->find($token->getUserId());
        if (null === $user) {
            // User disappeared (cascade delete) but the token survived a
            // microsecond too long — treat as invalid rather than 500.
            throw RefreshTokenException::invalid();
        }

        $token->markUsed($now);
        $issued = $this->issue($user, $token->getFamilyId());
        $this->em->flush();

        return [
            'user' => $user,
            'raw' => $issued['raw'],
            'entity' => $issued['entity'],
        ];
    }

    /**
     * Invalidate the token presented in the cookie. Idempotent: missing or
     * already-revoked tokens are silent so logout never throws.
     */
    public function revoke(?string $rawToken): void
    {
        if (null === $rawToken || '' === $rawToken) {
            return;
        }

        $token = $this->tokens->findByHash($this->hash($rawToken));
        if (null === $token) {
            return;
        }

        if (!$token->isRevoked()) {
            $token->revoke($this->clock->now());
            $this->em->flush();
        }
    }

    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private function lookup(string $rawToken): RefreshToken
    {
        $token = $this->tokens->findByHash($this->hash($rawToken));
        if (null === $token) {
            throw RefreshTokenException::invalid();
        }

        return $token;
    }

    /**
     * @return array{raw: string, entity: RefreshToken}
     */
    private function issue(User $user, Uuid $familyId): array
    {
        $raw = self::generateRawToken();
        $now = $this->clock->now();
        $expiresAt = $now->add(new DateInterval($this->ttl));

        $token = new RefreshToken(
            tenantId: $user->getTenant()->getId(),
            userId: $user->getId(),
            familyId: $familyId,
            tokenHash: $this->hash($raw),
            issuedAt: $now,
            expiresAt: $expiresAt,
        );
        $this->em->persist($token);

        return ['raw' => $raw, 'entity' => $token];
    }

    private static function generateRawToken(): string
    {
        // 32 bytes = 256 bits of entropy, base64url so it survives in a
        // Set-Cookie value without escaping. RFC 4648 §5 — `+/` → `-_` and
        // we trim the `=` padding that cookie parsers occasionally trip on.
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Helper for tests + manual probes that need to assert against `expires_at`.
     */
    public static function ttlSecondsFromInterval(string $interval): int
    {
        $now = new DateTimeImmutable('@0');
        $end = $now->add(new DateInterval($interval));

        return $end->getTimestamp() - $now->getTimestamp();
    }
}
