<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Security;

use App\Shared\Application\Auth\ApiKeyPrincipal;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Per-key rate limiter for `X-API-Key` requests (#97 / 0.11.2).
 *
 * Bucket key = `keyPrefix` so two partners sharing a profile do not
 * exhaust each other's budget. 1000/h sliding window is the floor;
 * `ApiProfile.rateLimitPerHour` overrides per profile in a follow-up.
 *
 * Runs at negative priority — after the firewall has authenticated
 * the request, so `getToken()->getUser()` returns the principal.
 * Anonymous requests pass through (other firewalls reject them
 * upstream); JWT-authenticated requests pass through (admins use
 * their JWT, not the per-key limiter).
 */
#[AsEventListener(event: RequestEvent::class, priority: -8)]
final readonly class ApiKeyRateLimitListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RateLimiterFactoryInterface $apiKeyLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof ApiKeyPrincipal) {
            return;
        }

        $limiter = $this->apiKeyLimiter->create($user->getUserIdentifier());
        $consumed = $limiter->consume();
        if ($consumed->isAccepted()) {
            return;
        }

        $retryAfter = $consumed->getRetryAfter();
        $secondsUntilReset = max(1, $retryAfter->getTimestamp() - time());

        throw new TooManyRequestsHttpException(
            $secondsUntilReset,
            'API key rate limit exceeded. Try again later.',
            null,
            0,
            ['Retry-After' => (string) $secondsUntilReset],
        );
    }
}
