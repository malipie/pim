<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Short-lived, server-side challenge for the second factor of login (#1141).
 *
 * When a user with active TOTP passes password authentication, the login
 * success handler does NOT mint a JWT — it parks the authenticated identity
 * behind an opaque challenge token stored here (cache, 5-minute TTL). The
 * dedicated `POST /api/auth/2fa/login` endpoint then exchanges
 * `{mfa_token, code}` for the real JWT once the code verifies.
 *
 * The token is opaque random bytes — NOT a JWT — so on its own it can never
 * satisfy the API firewall; it only unlocks the second-factor exchange. A
 * bounded attempt counter caps online brute-force of the 6-digit code per
 * password login without needing a separate rate limiter.
 */
final readonly class MfaLoginChallengeStore
{
    private const string KEY_PREFIX = 'mfa.login.';
    private const int TTL_SECONDS = 300;
    private const int MAX_ATTEMPTS = 5;

    public function __construct(
        private CacheItemPoolInterface $cache,
        private UserRepositoryInterface $users,
        private TotpEnrolmentService $enrolment,
    ) {
    }

    /**
     * Park a password-authenticated user behind a fresh challenge token.
     */
    public function issue(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $item = $this->cache->getItem(self::KEY_PREFIX.$token);
        $item->set(['uid' => $user->getId()->toRfc4122(), 'attempts' => 0]);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);

        return $token;
    }

    /**
     * Exchange a challenge token + second-factor code for the user.
     *
     * Returns null on an unknown/expired token, an invalid code, or once the
     * attempt budget is exhausted (the challenge is discarded in that case).
     * A successful exchange consumes the challenge so it cannot be replayed.
     */
    public function consume(string $token, string $code): ?User
    {
        if ('' === $token || '' === $code) {
            return null;
        }

        $key = self::KEY_PREFIX.$token;
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();
        $uid = null;
        $attempts = 0;
        if (\is_array($data)) {
            $rawUid = $data['uid'] ?? null;
            $uid = \is_string($rawUid) ? $rawUid : null;
            $rawAttempts = $data['attempts'] ?? null;
            $attempts = \is_int($rawAttempts) ? $rawAttempts : 0;
        }

        if (null === $uid || !Uuid::isValid($uid)) {
            $this->cache->deleteItem($key);

            return null;
        }

        $user = $this->users->findById(Uuid::fromString($uid));
        if (!$user instanceof User || !$user->isTotpEnabled()) {
            $this->cache->deleteItem($key);

            return null;
        }

        if ($this->enrolment->verify($user, $code)) {
            $this->cache->deleteItem($key);

            return $user;
        }

        if (++$attempts >= self::MAX_ATTEMPTS) {
            $this->cache->deleteItem($key);

            return null;
        }

        $item->set(['uid' => $uid, 'attempts' => $attempts]);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);

        return null;
    }
}
