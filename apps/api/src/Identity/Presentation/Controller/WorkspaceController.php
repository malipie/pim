<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Shared\Application\TenantContext;
use App\Shared\Domain\Exception\CannotDisablePrimaryLocaleException;
use App\Shared\Domain\Exception\InvalidLocaleException;
use App\Shared\Domain\Exception\LocaleNotEnabledException;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * VIEW-01 (#372) — workspace endpoints driving the LocaleTabsField in
 * modeling. Three responsibilities:
 *
 *   - GET `/api/workspaces/current` — read the tenant's identity strip
 *     plus enabled locales / primary locale (consumed by `useEnabledLocales`).
 *   - POST `/api/workspaces/current/locales` — enable a locale from the
 *     `LocaleLibrary` allowlist. Idempotent on duplicate.
 *   - DELETE `/api/workspaces/current/locales/{locale}` — disable a locale.
 *     Refuses to remove the primary (409) and refuses if any
 *     `object_values` rows still carry that locale (409). The FE in
 *     VIEW-01 only adds, but the API enforces the full lifecycle so a
 *     future Settings view doesn't need a refactor.
 *   - PATCH `/api/workspaces/current` — change the primary locale. Must
 *     already be enabled.
 *
 * The endpoint lives in Identity rather than Catalog because Tenant is the
 * Identity-adjacent shared kernel — putting it under Catalog would force
 * Catalog to depend on Tenant lifecycle, which it already does only
 * indirectly via TenantContext. Identity bundles also already host the
 * RBAC + auth surface that operators reach when fiddling with workspace
 * settings.
 */
final class WorkspaceController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantRepositoryInterface $tenants,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        '/api/workspaces/current',
        name: 'pim_workspaces_current_get',
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function get(): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        return new JsonResponse([
            'id' => $tenant->getId()->toRfc4122(),
            'code' => $tenant->getCode(),
            'name' => $tenant->getName(),
            'plan' => $tenant->getPlan(),
            'enabledLocales' => $tenant->getEnabledLocales(),
            'primaryLocale' => $tenant->getPrimaryLocale(),
        ]);
    }

    #[Route(
        '/api/workspaces/current/locales',
        name: 'pim_workspaces_current_locales_add',
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addLocale(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $locale = $body['locale'] ?? null;
        if (!\is_string($locale) || '' === trim($locale)) {
            throw new BadRequestHttpException('locale is required.');
        }
        $locale = trim($locale);

        try {
            $tenant->enableLocale($locale);
        } catch (InvalidLocaleException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
        $this->tenants->save($tenant);

        return new JsonResponse([
            'enabledLocales' => $tenant->getEnabledLocales(),
            'primaryLocale' => $tenant->getPrimaryLocale(),
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/api/workspaces/current/locales/{locale}',
        name: 'pim_workspaces_current_locales_remove',
        requirements: ['locale' => '[a-z]{2}(_[A-Z]{2})?'],
        methods: ['DELETE'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeLocale(string $locale): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        // Block removal when any object_values row still uses this locale —
        // dropping it would leave orphaned data behind and break renders
        // for any objects already authored in that language.
        $usedRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM object_values WHERE locale = ?',
            [$locale],
        );
        $usedCount = \is_scalar($usedRaw) ? (int) $usedRaw : 0;
        if ($usedCount > 0) {
            throw new ConflictHttpException(\sprintf(
                'Locale "%s" is still used by %d object value(s); migrate or clear them before disabling.',
                $locale,
                $usedCount,
            ));
        }

        try {
            $tenant->disableLocale($locale);
        } catch (CannotDisablePrimaryLocaleException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }
        $this->tenants->save($tenant);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/workspaces/current',
        name: 'pim_workspaces_current_patch',
        methods: ['PATCH'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function patch(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        if (\array_key_exists('primaryLocale', $body)) {
            $primary = $body['primaryLocale'];
            if (!\is_string($primary) || '' === trim($primary)) {
                throw new BadRequestHttpException('primaryLocale must be a non-empty string.');
            }
            try {
                $tenant->changePrimaryLocale(trim($primary));
            } catch (LocaleNotEnabledException $e) {
                throw new BadRequestHttpException($e->getMessage(), $e);
            }
        }

        $this->tenants->save($tenant);

        return new JsonResponse([
            'id' => $tenant->getId()->toRfc4122(),
            'code' => $tenant->getCode(),
            'name' => $tenant->getName(),
            'plan' => $tenant->getPlan(),
            'enabledLocales' => $tenant->getEnabledLocales(),
            'primaryLocale' => $tenant->getPrimaryLocale(),
        ]);
    }
}
