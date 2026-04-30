<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Rate limiter on `/api/auth/refresh` (#97 / 0.11.2).
 *
 * 30 attempts per IP per 1-hour sliding window. A real browser
 * tab refreshes the access token every ~15 minutes — at most ~5
 * legitimate calls per hour per tab — so 30/h covers a small honest
 * burst while clamping a stolen-cookie replay loop (where a
 * malicious script polls `/refresh` to siphon fresh access tokens).
 *
 * Mirrors {@see AuthLoginRateLimitListener} — runs at priority 32 so
 * the limiter checks before the Lexik refresh-cookie consumer.
 */
#[AsEventListener(event: RequestEvent::class, priority: 32)]
final readonly class AuthRefreshRateLimitListener
{
    public function __construct(
        private RateLimiterFactoryInterface $authRefreshLimiter,
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

        if ('/api/auth/refresh' !== $request->getPathInfo()) {
            return;
        }

        $limiter = $this->authRefreshLimiter->create($request->getClientIp() ?? 'unknown');
        $consumed = $limiter->consume();
        if ($consumed->isAccepted()) {
            return;
        }

        $retryAfter = $consumed->getRetryAfter();
        $secondsUntilReset = max(1, $retryAfter->getTimestamp() - time());

        throw new TooManyRequestsHttpException(
            $secondsUntilReset,
            'Too many refresh-token attempts. Try again later.',
            null,
            0,
            ['Retry-After' => (string) $secondsUntilReset],
        );
    }
}
