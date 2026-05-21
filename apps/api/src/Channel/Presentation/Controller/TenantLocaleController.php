<?php

declare(strict_types=1);

namespace App\Channel\Presentation\Controller;

use App\Channel\Application\Locale\LocaleFallbackCycleDetector;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\LocaleRepositoryInterface;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use const JSON_THROW_ON_ERROR;

/**
 * Locales feature (#871, LOC-03) — `/api/tenant-locales` CRUD endpoints.
 *
 * Owns the per-tenant lifecycle around `TenantLocale` rows:
 *
 *  - `GET /api/tenant-locales` — list active + inactive for the current
 *    tenant, ordered by `sortOrder`. Driven by the table in
 *    `/settings/locales` (LOC-07 #875).
 *  - `POST /api/tenant-locales` — activate a locale from the ISO catalog
 *    for the current tenant.
 *  - `PATCH /api/tenant-locales/{code}` — flip `is_default` / `is_mandatory`
 *    / `fallback` / `sort_order`. Switching default atomically clears the
 *    previous default's flag.
 *  - `DELETE /api/tenant-locales/{code}` — soft delete (`is_active=false`).
 *    Default locale refuses. `object_values` rows survive untouched —
 *    reactivate brings the data back.
 *  - `POST /api/tenant-locales/{code}/reactivate` — opposite of soft delete.
 *  - `DELETE /api/tenant-locales/{code}/purge` — hard delete that drops the
 *    locale row *and* every `object_values` row carrying that locale.
 *    Requires `X-Confirm-Purge: <code>` to mirror the typed-confirm UX
 *    the FE will surface in LOC-07.
 *
 * The cycle-detection guard in `setFallback` is intentionally simple here
 * (rejects A→B when B→A already exists). LOC-04 (#872) replaces it with a
 * chain-walking resolver that handles longer cycles and adds a Redis cache
 * for read-path resolution.
 *
 * Permission gate uses `settings.tenant.manage` as a placeholder — LOC-10
 * (#878) introduces the dedicated `settings.locales.manage` permission and
 * retrofits this controller to consume it.
 */
final class TenantLocaleController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantLocaleRepositoryInterface $tenantLocales,
        private readonly LocaleRepositoryInterface $locales,
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly LocaleFallbackCycleDetector $cycleDetector,
    ) {
    }

    #[Route('/api/tenant-locales', name: 'pim_tenant_locales_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function list(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant();

        $includeInactive = $request->query->getBoolean('include_inactive', true);
        $rows = $includeInactive
            ? $this->tenantLocales->findAllForTenant($tenant)
            : $this->tenantLocales->findActiveForTenant($tenant);

        return new JsonResponse([
            'items' => array_map(fn (TenantLocale $tl): array => $this->serialize($tl), $rows),
        ]);
    }

    #[Route('/api/tenant-locales/{code}', name: 'pim_tenant_locales_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function get(string $code): JsonResponse
    {
        $tenant = $this->requireTenant();
        $tl = $this->tenantLocales->findByTenantAndCode($tenant, $code);
        if (null === $tl) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not activated on this tenant.', $code));
        }

        return new JsonResponse($this->serialize($tl));
    }

    #[Route('/api/tenant-locales', name: 'pim_tenant_locales_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function create(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant();
        $body = $this->decodeBody($request);

        $code = $this->requireStringField($body, 'code');
        $locale = $this->locales->findByCode($code);
        if (null === $locale) {
            throw new UnprocessableEntityHttpException(\sprintf('Locale code "%s" is not in the catalog.', $code));
        }

        $existing = $this->tenantLocales->findByTenantAndLocale($tenant, $locale);
        if (null !== $existing) {
            throw new ConflictHttpException(\sprintf('Locale "%s" is already activated for this tenant.', $code));
        }

        $isDefault = (bool) ($body['isDefault'] ?? false);
        $isMandatory = (bool) ($body['isMandatory'] ?? $isDefault);
        $sortOrderRaw = $body['sortOrder'] ?? $this->nextSortOrder($tenant);
        $sortOrder = \is_int($sortOrderRaw) ? $sortOrderRaw : (int) (\is_string($sortOrderRaw) ? $sortOrderRaw : 0);
        $fallback = $this->resolveFallback($tenant, $locale, $body['fallbackCode'] ?? null);

        $tenantLocale = new TenantLocale($locale, $isDefault, $isMandatory, $fallback, $sortOrder, $tenant);

        if ($isDefault) {
            $this->clearExistingDefault($tenant);
        }

        $this->tenantLocales->save($tenantLocale);

        return new JsonResponse($this->serialize($tenantLocale), Response::HTTP_CREATED);
    }

    #[Route('/api/tenant-locales/{code}', name: 'pim_tenant_locales_patch', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function patch(string $code, Request $request): JsonResponse
    {
        $tenant = $this->requireTenant();
        $tl = $this->tenantLocales->findByTenantAndCode($tenant, $code);
        if (null === $tl) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not activated on this tenant.', $code));
        }

        $body = $this->decodeBody($request);

        if (\array_key_exists('isDefault', $body)) {
            $isDefault = (bool) $body['isDefault'];
            if ($isDefault && !$tl->isDefault()) {
                if (!$tl->isActive()) {
                    throw new ConflictHttpException('Cannot set inactive locale as default. Reactivate first.');
                }
                $this->clearExistingDefault($tenant);
                $tl->markAsDefault();
            } elseif (!$isDefault && $tl->isDefault()) {
                throw new ConflictHttpException('Cannot unset the only default locale. Set another locale as default first.');
            }
        }

        if (\array_key_exists('isMandatory', $body)) {
            $tl->setMandatory((bool) $body['isMandatory']);
        }

        if (\array_key_exists('fallbackCode', $body)) {
            $fallbackCode = $body['fallbackCode'];
            if (null === $fallbackCode || '' === $fallbackCode) {
                $tl->setFallback(null);
            } else {
                if (!\is_string($fallbackCode)) {
                    throw new BadRequestHttpException('`fallbackCode` must be a string or null.');
                }
                $fallback = $this->resolveFallback($tenant, $tl->getLocale(), $fallbackCode);
                $tl->setFallback($fallback);
            }
        }

        if (\array_key_exists('sortOrder', $body)) {
            $sortOrderRaw = $body['sortOrder'];
            if (!\is_int($sortOrderRaw) && !(\is_string($sortOrderRaw) && ctype_digit($sortOrderRaw))) {
                throw new BadRequestHttpException('`sortOrder` must be an integer.');
            }
            $tl->setSortOrder((int) $sortOrderRaw);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($tl));
    }

    #[Route('/api/tenant-locales/{code}', name: 'pim_tenant_locales_delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function delete(string $code): JsonResponse
    {
        $tenant = $this->requireTenant();
        $tl = $this->tenantLocales->findByTenantAndCode($tenant, $code);
        if (null === $tl) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not activated on this tenant.', $code));
        }

        if ($tl->isDefault()) {
            throw new ConflictHttpException('Default locale cannot be deactivated. Set another locale as default first.');
        }

        $tl->deactivate();
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/tenant-locales/{code}/reactivate', name: 'pim_tenant_locales_reactivate', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function reactivate(string $code): JsonResponse
    {
        $tenant = $this->requireTenant();
        $tl = $this->tenantLocales->findByTenantAndCode($tenant, $code);
        if (null === $tl) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not activated on this tenant.', $code));
        }

        $tl->reactivate();
        $this->em->flush();

        return new JsonResponse($this->serialize($tl));
    }

    #[Route('/api/tenant-locales/{code}/purge', name: 'pim_tenant_locales_purge', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function purge(string $code, Request $request): JsonResponse
    {
        $confirm = $request->headers->get('X-Confirm-Purge');
        if ($confirm !== $code) {
            throw new BadRequestHttpException(\sprintf(
                'Hard delete requires `X-Confirm-Purge: %s` header.',
                $code,
            ));
        }

        $tenant = $this->requireTenant();
        $tl = $this->tenantLocales->findByTenantAndCode($tenant, $code);
        if (null === $tl) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not activated on this tenant.', $code));
        }

        if ($tl->isDefault()) {
            throw new ConflictHttpException('Default locale cannot be purged.');
        }

        // tenant-safe: explicit tenant_id filter in WHERE
        // Clear inbound fallback references first to avoid FK SET NULL noise.
        $this->connection->executeStatement(
            'UPDATE tenant_locales SET fallback_locale_id = NULL WHERE tenant_id = :tenant AND fallback_locale_id = :loc',
            ['tenant' => $tenant->getId()->toRfc4122(), 'loc' => $tl->getLocale()->getId()->toRfc4122()],
        );
        // tenant-safe: explicit tenant_id filter in WHERE
        $this->connection->executeStatement(
            'DELETE FROM object_values WHERE tenant_id = :tenant AND locale = :code',
            ['tenant' => $tenant->getId()->toRfc4122(), 'code' => $code],
        );

        $this->tenantLocales->remove($tl);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function requireTenant(): Tenant
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        return $tenant;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Body must be valid JSON: '.$e->getMessage());
        }
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('Body must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireStringField(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            throw new BadRequestHttpException(\sprintf('`%s` must be a non-empty string.', $key));
        }

        return $value;
    }

    private function nextSortOrder(Tenant $tenant): int
    {
        $rows = $this->tenantLocales->findAllForTenant($tenant);
        $max = -1;
        foreach ($rows as $row) {
            if ($row->getSortOrder() > $max) {
                $max = $row->getSortOrder();
            }
        }

        return $max + 1;
    }

    /**
     * Resolves a fallback locale by code, validating it exists for this
     * tenant, is active, is not self, and would not create any N-cycle
     * (delegates the chain walk to LocaleFallbackCycleDetector #872).
     */
    private function resolveFallback(Tenant $tenant, Locale $for, mixed $fallbackCode): ?Locale
    {
        if (null === $fallbackCode || '' === $fallbackCode) {
            return null;
        }
        if (!\is_string($fallbackCode)) {
            throw new BadRequestHttpException('`fallbackCode` must be a string or null.');
        }

        $fallbackTenantLocale = $this->tenantLocales->findByTenantAndCode($tenant, $fallbackCode);
        if (null === $fallbackTenantLocale) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Fallback locale "%s" is not activated on this tenant.',
                $fallbackCode,
            ));
        }
        if (!$fallbackTenantLocale->isActive()) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Fallback locale "%s" is deactivated. Reactivate it before using as fallback.',
                $fallbackCode,
            ));
        }

        $fallback = $fallbackTenantLocale->getLocale();
        if ($fallback->getId()->equals($for->getId())) {
            throw new UnprocessableEntityHttpException('Locale cannot fall back to itself.');
        }

        if ($this->cycleDetector->wouldCreateCycle($for->getCode(), $fallbackCode, $tenant)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Setting "%s" as fallback would create a cycle reachable from %s.',
                $fallbackCode,
                $for->getCode(),
            ));
        }

        return $fallback;
    }

    private function clearExistingDefault(Tenant $tenant): void
    {
        $current = $this->tenantLocales->findDefaultForTenant($tenant);
        if (null !== $current) {
            $current->unmarkAsDefault();
            $this->em->flush();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(TenantLocale $tl): array
    {
        $locale = $tl->getLocale();
        $fallback = $tl->getFallback();

        return [
            'id' => $tl->getId()->toRfc4122(),
            'code' => $locale->getCode(),
            'label' => $locale->getLabel(),
            'language' => $locale->getLanguage(),
            'region' => $locale->getRegion(),
            'displayName' => $locale->getDisplayName(),
            'isDefault' => $tl->isDefault(),
            'isMandatory' => $tl->isMandatory(),
            'fallbackCode' => null === $fallback ? null : $fallback->getCode(),
            'sortOrder' => $tl->getSortOrder(),
            'isActive' => $tl->isActive(),
            'createdAt' => $tl->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
