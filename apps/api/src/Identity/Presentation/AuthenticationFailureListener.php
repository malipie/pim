<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

/**
 * Rewrites LexikJWT's default failure response into RFC 7807 Problem Details.
 *
 * Lexik ships its own JWTAuthenticationFailureResponse with a non-standard
 * shape (`{ "code": 401, "message": "..." }`). The rest of the API returns
 * application/problem+json on errors via API Platform, so admin/integration
 * clients can rely on a single error format. Without this listener the login
 * endpoint would be the one place a client has to special-case its parser.
 */
#[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
final readonly class AuthenticationFailureListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $original = $event->getResponse();
        $status = $original?->getStatusCode() ?? Response::HTTP_UNAUTHORIZED;
        $detail = (null !== $original ? $this->extractDetail($original) : null) ?? 'Invalid credentials.';

        $event->setResponse(new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => Response::$statusTexts[$status] ?? 'Unauthorized',
                'status' => $status,
                'detail' => $detail,
            ],
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        ));
    }

    private function extractDetail(Response $response): ?string
    {
        $body = (string) $response->getContent();
        if ('' === $body) {
            return null;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $message = $decoded['message'] ?? null;

        return \is_string($message) ? $message : null;
    }
}
