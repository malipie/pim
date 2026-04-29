<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Rate limiter on `/api/auth/login` (#48 / 0.4.8).
 *
 * Five attempts per IP per 15-minute fixed window; the 6th request
 * gets a 429 with a `Retry-After` header. Both successful and failed
 * logins consume the budget — a stolen credential should not let the
 * attacker re-arm the limiter just because the password happens to
 * match.
 *
 * IP fingerprinting is the only signal available on a pre-auth POST
 * (no JWT yet, no session). It's a coarse signal — corporate NATs
 * + privacy proxies hash to one IP — but it's the standard defence
 * for brute-force prevention. The dedicated hardening ticket (0.11.2)
 * adds CIDR allowlisting + per-email lockouts on top.
 *
 * The listener fires on `kernel.request` with priority high enough
 * to run before Lexik's `JsonLogin` authenticator. We do not swallow
 * the limiter's logic into the authenticator itself because Lexik
 * is vendor code and the limiter is a cross-cutting concern that
 * future endpoints (`/api/auth/refresh`, `/api/agent/run`) will
 * register similar listeners against.
 */
#[AsEventListener(event: RequestEvent::class, priority: 32)]
final readonly class AuthLoginRateLimitListener
{
    public function __construct(
        private RateLimiterFactoryInterface $authLoginLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('POST' !== $request->getMethod()) {
            return;
        }

        if ('/api/auth/login' !== $request->getPathInfo()) {
            return;
        }

        $limiter = $this->authLoginLimiter->create($request->getClientIp() ?? 'unknown');
        $consumed = $limiter->consume();
        if ($consumed->isAccepted()) {
            return;
        }

        $retryAfter = $consumed->getRetryAfter();
        $secondsUntilReset = max(1, $retryAfter->getTimestamp() - time());

        throw new TooManyRequestsHttpException(
            $secondsUntilReset,
            'Too many login attempts. Try again later.',
            null,
            0,
            ['Retry-After' => (string) $secondsUntilReset],
        );
    }
}
