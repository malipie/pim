<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\ObjectKind;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * IMP-07 (#448) — wizard's "Saved profiles" CRUD round-trip. Voter
 * + per-user ownership are covered in unit tests already; this case
 * pins the HTTP shape + the duplicate-name conflict that the wizard
 * surfaces on the profile manager modal (spec §5.8).
 */
final class ImportProfileApiTest extends CatalogApiTestCase
{
    #[Test]
    public function createListAndPatchRoundTripsThroughTheEndpoint(): void
    {
        $client = $this->authenticatedClient();
        $targetId = $this->objectTypeIdFor(ObjectKind::Product);

        // POST
        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Festo Q2 2026',
                'targetObjectTypeId' => $targetId,
                'columnMapping' => ['Kod produktu' => 'sku', 'Nazwa' => 'name'],
                'encoding' => 'utf-8',
                'delimiter' => ';',
                'imageSource' => 'http',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        self::assertSame('Festo Q2 2026', $created['name']);
        $profileId = $created['id'] ?? null;
        self::assertIsString($profileId);

        // GET collection
        $client->request('GET', '/api/import-profiles');
        self::assertResponseIsSuccessful();
        $list = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($list);
        $items = $list['member'] ?? $list['hydra:member'] ?? null;
        self::assertIsArray($items);
        self::assertGreaterThanOrEqual(1, \count($items));

        // PATCH — rename
        $client->request('PATCH', \sprintf('/api/import-profiles/%s', $profileId), [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode(['name' => 'Festo Q3 2026'], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseIsSuccessful();
        $patched = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($patched);
        self::assertSame('Festo Q3 2026', $patched['name']);
    }

    #[Test]
    public function duplicateNameForSameUserReturns409(): void
    {
        $client = $this->authenticatedClient();
        $targetId = $this->objectTypeIdFor(ObjectKind::Product);

        $payload = json_encode([
            'name' => 'Festo Q4',
            'targetObjectTypeId' => $targetId,
            'columnMapping' => [],
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => $payload,
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => $payload,
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function deleteRemovesProfile(): void
    {
        $client = $this->authenticatedClient();
        $targetId = $this->objectTypeIdFor(ObjectKind::Product);

        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Trash Me',
                'targetObjectTypeId' => $targetId,
                'columnMapping' => [],
            ], JSON_THROW_ON_ERROR),
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $profileId = $created['id'] ?? null;
        self::assertIsString($profileId);

        $client->request('DELETE', \sprintf('/api/import-profiles/%s', $profileId));
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', \sprintf('/api/import-profiles/%s', $profileId));
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * IMP2-2.7 (#1483) follow-up — the error-rate abort threshold is
     * configurable through the API (API-first), round-trips on read, and
     * rejects out-of-range values.
     */
    #[Test]
    public function allowedErrorsPctIsConfigurableThroughTheApi(): void
    {
        $client = $this->authenticatedClient();
        $targetId = $this->objectTypeIdFor(ObjectKind::Product);

        // POST with a threshold → echoed back on read.
        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Threshold Profile',
                'targetObjectTypeId' => $targetId,
                'columnMapping' => [],
                'allowedErrorsPct' => 10,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        self::assertSame(10, $created['allowedErrorsPct']);
        $profileId = $created['id'] ?? null;
        self::assertIsString($profileId);

        // PATCH updates the threshold.
        $client->request('PATCH', \sprintf('/api/import-profiles/%s', $profileId), [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode(['allowedErrorsPct' => 25], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseIsSuccessful();
        $patched = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($patched);
        self::assertSame(25, $patched['allowedErrorsPct']);

        // Out-of-range value is rejected.
        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Bad Threshold',
                'targetObjectTypeId' => $targetId,
                'columnMapping' => [],
                'allowedErrorsPct' => 150,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);
    }
}
