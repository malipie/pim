<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Contracts\Attribute\NoPermissionRequired;
use App\Identity\Domain\Entity\User;
use App\Shared\Infrastructure\Mercure\MercureSubscribeTopics;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AUD-001 (#1573) — `POST /api/mercure/authorization`.
 *
 * Mints the `mercureAuthorization` cookie the SPA must hold before it
 * opens an EventSource. The Mercure hub no longer runs in `anonymous`
 * mode, so a subscription without this cookie is refused (401) — that is
 * what stops an attacker (or any other tenant) from listening on the
 * real-time catalog / import / export / permission streams.
 *
 * The cookie is a short-lived JWT whose `mercure.subscribe` claim is a
 * closed set of URI templates, every one pinned to
 * `tenant/{callerTenant}/…` ({@see MercureSubscribeTopics::forTenant()}).
 * There is no global (prefix-less) topic and no other tenant's prefix,
 * so the hub will only deliver the caller's own tenant updates.
 *
 * The cookie scope (path `/.well-known/mercure`, httpOnly, secure on
 * https, SameSite=Strict) is set by the Mercure component's
 * {@see Authorization::createCookie()} helper from the hub `public_url`.
 *
 * Auth model: requires a valid JWT (the `^/api` firewall). The endpoint
 * carries `#[NoPermissionRequired]` because subscribing to one's own
 * tenant feed is implied by being authenticated — there is no finer
 * RBAC gate than "is this caller logged in".
 */
final readonly class MercureAuthorizationController
{
    public function __construct(
        private Security $security,
        private Authorization $authorization,
        private string $topicBase,
    ) {
    }

    #[Route(path: '/api/mercure/authorization', methods: ['POST'], name: 'api_mercure_authorization')]
    #[NoPermissionRequired(reason: 'Subscribing to the caller\'s own tenant Mercure feed is implied by JWT authentication; the subscribe claim is scoped to the caller tenant.')]
    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(
                [
                    'type' => 'about:blank',
                    'title' => 'Unauthorized',
                    'status' => Response::HTTP_UNAUTHORIZED,
                    'detail' => 'No authenticated user.',
                ],
                Response::HTTP_UNAUTHORIZED,
                ['Content-Type' => 'application/problem+json; charset=utf-8'],
            );
        }

        $tenantId = $user->getTenant()->getId();
        $subscribe = MercureSubscribeTopics::forTenant($tenantId, $this->topicBase);

        // createCookie() signs the JWT with the hub's subscriber key and
        // scopes the cookie to the hub path; we attach it to the response
        // directly (this mercure-bundle version ships no SetCookieSubscriber).
        $cookie = $this->authorization->createCookie($request, $subscribe);

        $response = new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
