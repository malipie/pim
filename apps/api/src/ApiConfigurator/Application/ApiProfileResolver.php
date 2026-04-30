<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use App\Shared\Application\Auth\ApiKeyPrincipal;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Picks the {@see ApiProfile} the current API-key request scopes to.
 *
 * Selection rule:
 *   - Header `X-PIM-Profile: <code>` if present — must be in the
 *     authenticated key's scope list.
 *   - Otherwise, when the key has exactly one scope, default to it.
 *   - Otherwise — ambiguous; integrator must specify.
 *
 * Returns `null` when the request is JWT-authenticated (admin path
 * — no profile filtering) or anonymous. The decorator below treats
 * that as "no profile context", leaving the AP4 default groups in
 * place.
 */
final readonly class ApiProfileResolver
{
    public const string HEADER_PROFILE = 'X-PIM-Profile';

    public function __construct(
        private Security $security,
        private ApiProfileRepositoryInterface $profiles,
    ) {
    }

    public function resolveFromRequest(Request $request): ?ApiProfile
    {
        $user = $this->security->getUser();
        if (!$user instanceof ApiKeyPrincipal) {
            return null;
        }

        $headerCode = $request->headers->get(self::HEADER_PROFILE);
        $headerCode = \is_string($headerCode) ? trim($headerCode) : '';

        $code = $this->pickProfileCode($user, $headerCode);
        if (null === $code) {
            return null;
        }

        if (!\in_array($code, $user->scopes(), true)) {
            throw new AccessDeniedHttpException(\sprintf(
                'API key is not scoped to profile "%s".',
                $code,
            ));
        }

        return $this->profiles->findByCode($code);
    }

    private function pickProfileCode(ApiKeyPrincipal $user, string $headerCode): ?string
    {
        if ('' !== $headerCode) {
            return $headerCode;
        }

        $scopes = $user->scopes();
        if (1 === \count($scopes)) {
            return $scopes[0];
        }

        return null;
    }
}
