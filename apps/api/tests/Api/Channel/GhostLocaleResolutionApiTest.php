<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * #1352 (reopen #2) — the workspace locale strip derives from ACTIVE
 * `tenant_locales`, not the legacy `Tenant.enabledLocales` JSONB. A
 * locale enabled through the retired "+ Dodaj język" dialog and then
 * deactivated (or never mirrored) in Settings must NOT haunt the i18n
 * forms as a ghost tab.
 */
final class GhostLocaleResolutionApiTest extends ChannelApiTestCase
{
    #[Test]
    public function workspaceStripIgnoresGhostAndDeactivatedLocales(): void
    {
        $em = $this->em();
        $pl = $em->getRepository(Locale::class)->findOneBy(['code' => 'pl_PL']);
        $en = $em->getRepository(Locale::class)->findOneBy(['code' => 'en_US']);
        \assert($pl instanceof Locale && $en instanceof Locale);
        $de = new Locale('de_DE', 'Niemiecki (Niemcy)', null, 'de', 'DE', ['pl' => 'Niemiecki (Niemcy)', 'en' => 'German (Germany)'], true);
        $em->persist($de);

        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        // Active rows: pl (default) + en. de is DEACTIVATED (soft delete).
        $em->persist(new TenantLocale($pl, true, true, null, 0, $tenant));
        $em->persist(new TenantLocale($en, false, false, $pl, 1, $tenant));
        $deRow = new TenantLocale($de, false, false, $pl, 2, $tenant);
        $deRow->deactivate();
        $em->persist($deRow);

        // Legacy JSONB carries a ghost "it" (added via the old dialog,
        // never present in tenant_locales) plus the stale "de".
        $tenant->enableLocale('pl');
        $tenant->enableLocale('en');
        $tenant->enableLocale('de');
        $tenant->enableLocale('it');
        $em->flush();

        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/workspaces/current', [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        self::assertSame(['pl', 'en'], $payload['enabledLocales'], 'Ghost "it" and deactivated "de" must not surface.');
        self::assertSame('pl', $payload['primaryLocale']);
    }

    #[Test]
    public function workspaceStripFallsBackToLegacyListWithoutTenantLocaleRows(): void
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $tenant->enableLocale('pl');
        $tenant->enableLocale('en');
        $em->flush();

        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/workspaces/current', [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        // No tenant_locales rows at all (legacy/dev tenant) → the JSONB
        // list keeps working so nothing regresses before LOC-07 seeding.
        self::assertSame(['pl', 'en'], $payload['enabledLocales']);
    }
}
