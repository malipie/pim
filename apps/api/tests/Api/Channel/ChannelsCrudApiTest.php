<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-06 (#418) — Channel CRUD ApiResource smoke + invariants.
 */
final class ChannelsCrudApiTest extends ChannelApiTestCase
{
    #[Test]
    public function postCreatesChannel(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/channels', [
            'json' => [
                'code' => 'shopify_pl',
                'name' => 'Shopify PL',
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame('shopify_pl', $payload['code']);
        self::assertSame('Shopify PL', $payload['name']);
        self::assertArrayNotHasKey('locales', $payload);
        self::assertArrayNotHasKey('currencies', $payload);
    }

    #[Test]
    public function postRejectsDuplicateCode(): void
    {
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/channels', [
            'json' => ['code' => 'shopify_pl', 'name' => 'X'],
        ]);
        $second = $client->request('POST', '/api/channels', [
            'json' => ['code' => 'shopify_pl', 'name' => 'Y'],
        ]);

        self::assertSame(409, $second->getStatusCode());
    }

    #[Test]
    public function postRejectsInvalidCodeFormat(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/channels', [
            'json' => ['code' => 'INVALID-WITH-DASH', 'name' => 'X'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function postRejectsBlankName(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/channels', [
            'json' => ['code' => 'shop', 'name' => ''],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function patchUpdatesName(): void
    {
        $client = $this->authenticatedClient();

        $created = $client->request('POST', '/api/channels', [
            'json' => ['code' => 'shop', 'name' => 'Sklep'],
        ]);
        $id = self::extractId($created->toArray());

        $patched = $client->request('PATCH', "/api/channels/{$id}", [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Sklep PL'],
        ]);

        self::assertSame(200, $patched->getStatusCode());
        self::assertSame('Sklep PL', $patched->toArray()['name']);
    }

    #[Test]
    public function deleteRemovesChannelAndCascadesMappings(): void
    {
        $client = $this->authenticatedClient();

        $created = $client->request('POST', '/api/channels', [
            'json' => ['code' => 'shop', 'name' => 'Sklep'],
        ]);
        $id = self::extractId($created->toArray());

        $delete = $client->request('DELETE', "/api/channels/{$id}");
        self::assertSame(204, $delete->getStatusCode());

        $get = $client->request('GET', "/api/channels/{$id}");
        self::assertSame(404, $get->getStatusCode());
    }

    #[Test]
    public function unauthenticatedListReturns401(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/channels');

        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function localesEndpointReturnsSeededRows(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/locales');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertGreaterThanOrEqual(2, $payload['totalItems']);
    }

    #[Test]
    public function currenciesEndpointIsGone(): void
    {
        // #1282 — Currency entity + /api/currencies removed entirely.
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/currencies');

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function patchOnUnknownChannelReturns404(): void
    {
        $client = $this->authenticatedClient();
        $randomId = Uuid::v7()->toRfc4122();

        $response = $client->request('PATCH', "/api/channels/{$randomId}", [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => ['name' => 'X'],
        ]);

        self::assertSame(404, $response->getStatusCode());
    }
}
