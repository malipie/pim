<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-IMP-01 — throughput probe smoke. Exhaustive math coverage
 * lives in the unit-level {@see \App\Tests\Unit\Import\ImportThroughputCalculatorTest}.
 */
final class ImportThroughputApiTest extends CatalogApiTestCase
{
    #[Test]
    public function throughputRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/import-sessions/throughput');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function throughputReturnsZeroForFreshUser(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sessions/throughput?windowMin=5');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        $rate = $body['rows_per_sec'];
        self::assertIsNumeric($rate);
        self::assertEqualsWithDelta(0.0, (float) $rate, 0.001);
        self::assertSame(0, $body['active_sessions']);
        self::assertSame(5, $body['window_min']);
        self::assertArrayHasKey('sampled_at', $body);
    }

    #[Test]
    public function throughputRejectsWindowOver60(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sessions/throughput?windowMin=120');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function throughputRejectsWindowBelow1(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sessions/throughput?windowMin=0');

        self::assertResponseStatusCodeSame(400);
    }
}
