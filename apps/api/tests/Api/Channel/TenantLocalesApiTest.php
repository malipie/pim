<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * LOC-03 (#871) — `/api/tenant-locales` CRUD smoke.
 *
 * Builds on top of `ChannelApiTestCase` for the tenant + admin + auth
 * scaffolding, then seeds three extra locales (de_DE, fr_FR, cs_CZ) into
 * the global catalog plus a default + a fallback row in `tenant_locales`
 * for the demo tenant. Each test exercises a single endpoint.
 */
final class TenantLocalesApiTest extends ChannelApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $em = $this->em();
        $pl = $em->getRepository(Locale::class)->findOneBy(['code' => 'pl_PL']);
        $en = $em->getRepository(Locale::class)->findOneBy(['code' => 'en_US']);
        \assert($pl instanceof Locale, 'pl_PL must be seeded by ChannelApiTestCase::setUp.');
        \assert($en instanceof Locale, 'en_US must be seeded by ChannelApiTestCase::setUp.');

        // Catalog rows for the activation flow.
        $em->persist(new Locale('de_DE', 'Niemiecki (Niemcy)', null, 'de', 'DE', ['pl' => 'Niemiecki (Niemcy)', 'en' => 'German (Germany)'], true));
        $em->persist(new Locale('fr_FR', 'Francuski (Francja)', null, 'fr', 'FR', ['pl' => 'Francuski (Francja)', 'en' => 'French (France)'], true));
        $em->persist(new Locale('cs_CZ', 'Czeski (Czechy)', null, 'cs', 'CZ', ['pl' => 'Czeski (Czechy)', 'en' => 'Czech (Czechia)'], true));

        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        // pl_PL default+mandatory, en_US mandatory+fallback=pl_PL.
        $em->persist(new TenantLocale($pl, true, true, null, 0, $tenant));
        $em->persist(new TenantLocale($en, false, true, $pl, 1, $tenant));
        $em->flush();
    }

    #[Test]
    public function listReturnsActivatedLocalesForCurrentTenant(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/tenant-locales');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        $items = $payload['items'];
        \assert(\is_array($items));
        self::assertCount(2, $items);
        $first = $items[0];
        \assert(\is_array($first));
        $second = $items[1];
        \assert(\is_array($second));
        self::assertSame('pl_PL', $first['code']);
        self::assertTrue($first['isDefault']);
        self::assertSame('en_US', $second['code']);
        self::assertSame('pl_PL', $second['fallbackCode']);
    }

    #[Test]
    public function getReturnsSingleLocaleByCode(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/tenant-locales/en_US');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame('en_US', $payload['code']);
        self::assertSame('en', $payload['language']);
        $displayName = $payload['displayName'];
        \assert(\is_array($displayName));
        self::assertSame('English (United States)', $displayName['en']);
    }

    #[Test]
    public function getUnknownLocaleReturns404(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/tenant-locales/de_DE');

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function postActivatesNewLocale(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/tenant-locales', [
            'json' => [
                'code' => 'de_DE',
                'isMandatory' => false,
                'fallbackCode' => 'en_US',
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame('de_DE', $payload['code']);
        self::assertFalse($payload['isDefault']);
        self::assertFalse($payload['isMandatory']);
        self::assertSame('en_US', $payload['fallbackCode']);
        self::assertTrue($payload['isActive']);
        self::assertSame(2, $payload['sortOrder']);
    }

    #[Test]
    public function postRejectsUnknownCatalogCode(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/tenant-locales', [
            'json' => ['code' => 'xx_XX'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function postRejectsDuplicateActivation(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/tenant-locales', [
            'json' => ['code' => 'pl_PL'],
        ]);

        self::assertSame(409, $response->getStatusCode());
    }

    #[Test]
    public function postRejectsUnactivatedFallback(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/tenant-locales', [
            'json' => [
                'code' => 'de_DE',
                'fallbackCode' => 'fr_FR',
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function patchSwitchesDefaultLocale(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('PATCH', '/api/tenant-locales/en_US', [
            'json' => ['isDefault' => true],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertTrue($payload['isDefault']);

        // The previous default (pl_PL) must have its flag cleared.
        $listResponse = $client->request('GET', '/api/tenant-locales');
        $listPayload = $listResponse->toArray();
        $list = $listPayload['items'];
        \assert(\is_array($list));
        $byCode = [];
        foreach ($list as $item) {
            \assert(\is_array($item));
            $code = $item['code'];
            \assert(\is_string($code));
            $byCode[$code] = $item;
        }
        self::assertFalse($byCode['pl_PL']['isDefault']);
        self::assertTrue($byCode['en_US']['isDefault']);
    }

    #[Test]
    public function patchRejectsCycleAttempt(): void
    {
        $client = $this->authenticatedClient();
        // pl_PL has no fallback. en_US fallback = pl_PL. Setting pl_PL.fallback
        // = en_US would create the cycle pl_PL → en_US → pl_PL.
        $response = $client->request('PATCH', '/api/tenant-locales/pl_PL', [
            'json' => ['fallbackCode' => 'en_US'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function deleteSoftDeactivatesLocale(): void
    {
        // First, activate de_DE so we have a non-default to deactivate.
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/tenant-locales', ['json' => ['code' => 'de_DE']]);

        $response = $client->request('DELETE', '/api/tenant-locales/de_DE');
        self::assertSame(204, $response->getStatusCode());

        $followup = $client->request('GET', '/api/tenant-locales/de_DE');
        $payload = $followup->toArray();
        self::assertFalse($payload['isActive']);
    }

    #[Test]
    public function deleteRefusesDefault(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('DELETE', '/api/tenant-locales/pl_PL');

        self::assertSame(409, $response->getStatusCode());
    }

    #[Test]
    public function reactivateRestoresInactiveLocale(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/tenant-locales', ['json' => ['code' => 'de_DE']]);
        $client->request('DELETE', '/api/tenant-locales/de_DE');

        $response = $client->request('POST', '/api/tenant-locales/de_DE/reactivate');
        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertTrue($payload['isActive']);
    }

    #[Test]
    public function purgeRequiresConfirmHeader(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/tenant-locales', ['json' => ['code' => 'de_DE']]);

        $response = $client->request('DELETE', '/api/tenant-locales/de_DE/purge');
        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function purgeRefusesDefault(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('DELETE', '/api/tenant-locales/pl_PL/purge', [
            'headers' => ['X-Confirm-Purge' => 'pl_PL'],
        ]);

        self::assertSame(409, $response->getStatusCode());
    }

    #[Test]
    public function purgeDeletesRowAndObjectValues(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/tenant-locales', ['json' => ['code' => 'de_DE']]);

        $response = $client->request('DELETE', '/api/tenant-locales/de_DE/purge', [
            'headers' => ['X-Confirm-Purge' => 'de_DE'],
        ]);
        self::assertSame(204, $response->getStatusCode());

        $followup = $client->request('GET', '/api/tenant-locales/de_DE');
        self::assertSame(404, $followup->getStatusCode());
    }
}
