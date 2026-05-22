<?php

declare(strict_types=1);

namespace App\Channel\Presentation\Controller;

use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use const JSON_THROW_ON_ERROR;

/**
 * Locales feature (#874, LOC-06) — channel ↔ locale binding matrix.
 *
 * One read endpoint returns the matrix (rows = channels, columns =
 * tenant_locales) and one write endpoint commits a full matrix in a
 * single transaction. LOC-09 (#877) is the FE consumer; the bulk
 * shape (PUT entire matrix) keeps the UX dirty-state simple — toggle
 * cells, hit save, atomic apply.
 */
final class ChannelLocaleMatrixController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantLocaleRepositoryInterface $tenantLocales,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/api/channel-locales', name: 'pim_channel_locales_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'publications', action: 'view')]
    public function get(): JsonResponse
    {
        $tenant = $this->requireTenant();

        // tenant-safe: explicit tenant_id filter in WHERE
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id AS channel_id, c.code AS channel_code, l.code AS locale_code
             FROM channel_locales cl
             JOIN channels c ON c.id = cl.channel_id
             JOIN locales l ON l.id = cl.locale_id
             WHERE cl.tenant_id = :tenant
             ORDER BY c.code, l.code',
            ['tenant' => $tenant->getId()->toRfc4122()],
        );

        $byChannel = [];
        foreach ($rows as $row) {
            $cid = \is_string($row['channel_id']) ? $row['channel_id'] : '';
            if (!isset($byChannel[$cid])) {
                $byChannel[$cid] = [
                    'channelId' => $cid,
                    'channelCode' => \is_string($row['channel_code']) ? $row['channel_code'] : '',
                    'localeCodes' => [],
                ];
            }
            $byChannel[$cid]['localeCodes'][] = \is_string($row['locale_code']) ? $row['locale_code'] : '';
        }

        // tenant-safe: explicit tenant_id filter in WHERE
        $allChannels = $this->connection->fetchAllAssociative(
            'SELECT id, code FROM channels WHERE tenant_id = :tenant ORDER BY code',
            ['tenant' => $tenant->getId()->toRfc4122()],
        );
        foreach ($allChannels as $channel) {
            $cid = \is_string($channel['id']) ? $channel['id'] : '';
            if (!isset($byChannel[$cid])) {
                $byChannel[$cid] = [
                    'channelId' => $cid,
                    'channelCode' => \is_string($channel['code']) ? $channel['code'] : '',
                    'localeCodes' => [],
                ];
            }
        }

        return new JsonResponse([
            'items' => array_values($byChannel),
        ]);
    }

    #[Route('/api/channel-locales', name: 'pim_channel_locales_put', methods: ['PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'settings.tenant', action: 'manage')]
    public function put(Request $request): JsonResponse
    {
        $tenant = $this->requireTenant();
        $body = $this->decodeBody($request);

        if (!isset($body['items']) || !\is_array($body['items'])) {
            throw new BadRequestHttpException('Body must be `{items: [{channelId, localeCodes}]}`.');
        }

        // tenant-safe: explicit tenant_id filter in WHERE
        $tenantChannelIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM channels WHERE tenant_id = :tenant',
            ['tenant' => $tenant->getId()->toRfc4122()],
        );
        $tenantChannels = [];
        foreach ($tenantChannelIds as $id) {
            if (\is_string($id)) {
                $tenantChannels[$id] = true;
            }
        }

        $activeLocalesByCode = [];
        foreach ($this->tenantLocales->findActiveForTenant($tenant) as $tl) {
            $activeLocalesByCode[$tl->getLocale()->getCode()] = $tl->getLocale();
        }

        $plan = [];
        foreach ($body['items'] as $item) {
            if (!\is_array($item)) {
                throw new BadRequestHttpException('Each item must be an object.');
            }
            $channelId = $item['channelId'] ?? null;
            if (!\is_string($channelId)) {
                throw new BadRequestHttpException('Each item needs a `channelId` string.');
            }
            if (!isset($tenantChannels[$channelId])) {
                throw new AccessDeniedHttpException(\sprintf('Channel %s does not belong to this tenant.', $channelId));
            }

            $localeCodes = $item['localeCodes'] ?? [];
            if (!\is_array($localeCodes)) {
                throw new BadRequestHttpException('`localeCodes` must be a list of strings.');
            }

            $resolved = [];
            foreach ($localeCodes as $localeCode) {
                if (!\is_string($localeCode)) {
                    throw new BadRequestHttpException('`localeCodes` entries must be strings.');
                }
                if (!isset($activeLocalesByCode[$localeCode])) {
                    throw new UnprocessableEntityHttpException(\sprintf(
                        'Locale "%s" is not active on this tenant.',
                        $localeCode,
                    ));
                }
                $resolved[$localeCode] = $activeLocalesByCode[$localeCode];
            }

            $plan[$channelId] = $resolved;
        }

        $this->connection->beginTransaction();
        try {
            // tenant-safe: explicit tenant_id filter in WHERE
            $this->connection->executeStatement(
                'DELETE FROM channel_locales WHERE tenant_id = :tenant',
                ['tenant' => $tenant->getId()->toRfc4122()],
            );

            foreach ($plan as $channelId => $resolved) {
                foreach ($resolved as $locale) {
                    // tenant-safe: junction inherits tenant via FK chain — tenant_id supplied explicitly
                    $this->connection->executeStatement(
                        'INSERT INTO channel_locales (tenant_id, channel_id, locale_id)
                         VALUES (:tenant, :channel, :locale)',
                        [
                            'tenant' => $tenant->getId()->toRfc4122(),
                            'channel' => $channelId,
                            'locale' => $locale->getId()->toRfc4122(),
                        ],
                    );
                }
            }

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $this->get();
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
        } catch (JsonException $e) {
            throw new BadRequestHttpException('Body must be valid JSON: '.$e->getMessage());
        }
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('Body must be a JSON object.');
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
