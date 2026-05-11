<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-IMP-01 — listing endpoint smoke for the sessions hub.
 *
 * Covers the Hydra-shaped response, auth gating, status filter
 * validation, and pagination defaults. End-to-end "imported rows
 * surface in the list" coverage lives in the existing import
 * fixtures-driven tests ({@see StartImportApiTest},
 * {@see RollbackAndReportApiTest}) which already exercise the full
 * storage path; here we focus on the endpoint contract.
 */
final class ListImportSessionsApiTest extends CatalogApiTestCase
{
    #[Test]
    public function listingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/import-sessions');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function listingReturnsEmptyCollectionForFreshUser(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sessions?page=1&pageSize=10');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame([], $body['member']);
        self::assertSame(0, $body['totalItems']);
        self::assertSame(1, $body['page']);
        self::assertSame(10, $body['pageSize']);
    }

    #[Test]
    public function unknownStatusFilterReturns400(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sessions?status=bogus');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function knownStatusFilterPassesThrough(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sessions?status=success');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame([], $body['member']);
    }

    #[Test]
    public function pageSizeIsClampedToMaximum(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sessions?pageSize=9999');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame(200, $body['pageSize']);
    }
}
