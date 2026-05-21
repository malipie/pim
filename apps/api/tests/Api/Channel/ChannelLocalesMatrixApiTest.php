<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * LOC-06 (#874) — `/api/channel-locales` GET + PUT smoke.
 */
final class ChannelLocalesMatrixApiTest extends ChannelApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $em = $this->em();
        $pl = $em->getRepository(Locale::class)->findOneBy(['code' => 'pl_PL']);
        $en = $em->getRepository(Locale::class)->findOneBy(['code' => 'en_US']);
        \assert($pl instanceof Locale);
        \assert($en instanceof Locale);

        $em->persist(new Locale('de_DE', 'Niemiecki (Niemcy)', null, 'de', 'DE', ['pl' => 'Niemiecki', 'en' => 'German'], true));
        $em->flush();
        $de = $em->getRepository(Locale::class)->findOneBy(['code' => 'de_DE']);
        \assert($de instanceof Locale);

        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $em->persist(new TenantLocale($pl, true, true, null, 0, $tenant));
        $em->persist(new TenantLocale($en, false, true, $pl, 1, $tenant));
        $em->persist(new TenantLocale($de, false, false, null, 2, $tenant));

        // Two channels — one with two locales bound, one bare.
        $shopify = new Channel('shopify_pl', ['pl' => 'Shopify PL']);
        $shopify->addLocale($pl);
        $shopify->addLocale($en);
        $em->persist($shopify);

        $allegro = new Channel('allegro_pl', ['pl' => 'Allegro PL']);
        $em->persist($allegro);

        $em->flush();
    }

    #[Test]
    public function getReturnsMatrixIncludingEmptyChannels(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/channel-locales');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        $items = $payload['items'];
        \assert(\is_array($items));
        self::assertCount(2, $items);

        $byCode = [];
        foreach ($items as $item) {
            \assert(\is_array($item));
            $code = $item['channelCode'];
            \assert(\is_string($code));
            $byCode[$code] = $item;
        }

        self::assertSame(['pl_PL', 'en_US'], array_values($this->sortChain($byCode['shopify_pl']['localeCodes'])));
        self::assertSame([], $byCode['allegro_pl']['localeCodes']);
    }

    #[Test]
    public function putRejectsInactiveLocale(): void
    {
        $client = $this->authenticatedClient();
        $list = $client->request('GET', '/api/channel-locales')->toArray()['items'];
        \assert(\is_array($list));
        $shopify = $this->findByCode($list, 'shopify_pl');

        $response = $client->request('PUT', '/api/channel-locales', [
            'json' => [
                'items' => [[
                    'channelId' => $shopify['channelId'],
                    'localeCodes' => ['pl_PL', 'xx_XX'],
                ]],
            ],
        ]);
        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function putRejectsCrossTenantChannelId(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('PUT', '/api/channel-locales', [
            'json' => [
                'items' => [[
                    'channelId' => '00000000-0000-0000-0000-000000000000',
                    'localeCodes' => [],
                ]],
            ],
        ]);
        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function putRewritesMatrixAtomically(): void
    {
        $client = $this->authenticatedClient();
        $list = $client->request('GET', '/api/channel-locales')->toArray()['items'];
        \assert(\is_array($list));
        $shopify = $this->findByCode($list, 'shopify_pl');
        $allegro = $this->findByCode($list, 'allegro_pl');

        $response = $client->request('PUT', '/api/channel-locales', [
            'json' => [
                'items' => [
                    ['channelId' => $shopify['channelId'], 'localeCodes' => ['pl_PL', 'de_DE']],
                    ['channelId' => $allegro['channelId'], 'localeCodes' => ['pl_PL']],
                ],
            ],
        ]);
        self::assertSame(200, $response->getStatusCode());

        $followup = $client->request('GET', '/api/channel-locales')->toArray()['items'];
        \assert(\is_array($followup));
        $shopifyAfter = $this->findByCode($followup, 'shopify_pl');
        $allegroAfter = $this->findByCode($followup, 'allegro_pl');
        self::assertSame(['de_DE', 'pl_PL'], $this->sortChain($shopifyAfter['localeCodes']));
        self::assertSame(['pl_PL'], $this->sortChain($allegroAfter['localeCodes']));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function findByCode(array $items, string $code): array
    {
        foreach ($items as $item) {
            if ($item['channelCode'] === $code) {
                return $item;
            }
        }
        self::fail("Channel $code not present in matrix.");
    }

    /**
     * @param mixed $codes
     * @return list<string>
     */
    private function sortChain(mixed $codes): array
    {
        \assert(\is_array($codes));
        /** @var list<string> $sortable */
        $sortable = array_values(array_map('strval', $codes));
        sort($sortable);

        return $sortable;
    }
}
