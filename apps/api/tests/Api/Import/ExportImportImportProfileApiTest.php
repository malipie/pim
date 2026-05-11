<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\ObjectKind;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-IMP-02 (#498) — export/import round-trip. Exports a profile
 * as a versioned JSON envelope and imports it back under a different
 * name/code so we land in a clean slot.
 */
final class ExportImportImportProfileApiTest extends CatalogApiTestCase
{
    #[Test]
    public function exportReturnsVersionedEnvelopeWithAttachmentDisposition(): void
    {
        $client = $this->authenticatedClient();
        $targetId = $this->objectTypeIdFor(ObjectKind::Product);

        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Catalogue Wave A',
                'code' => 'catalogue-wave-a',
                'mode' => 'UPDATE',
                'targetObjectTypeId' => $targetId,
                'columnMapping' => ['SKU' => 'sku'],
                'locale' => 'pl_PL',
                'encoding' => 'utf-8',
                'delimiter' => ';',
            ], JSON_THROW_ON_ERROR),
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $profileId = $created['id'];
        self::assertIsString($profileId);

        $client->request('GET', \sprintf('/api/import-profiles/%s/export', $profileId));
        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('Content-Disposition');
        $disposition = (string) $client->getResponse()?->getHeaders()['content-disposition'][0];
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('catalogue-wave-a', $disposition);

        $envelope = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($envelope);
        self::assertSame('1.0', $envelope['schemaVersion']);
        $profileBlock = $envelope['profile'];
        self::assertIsArray($profileBlock);
        self::assertSame('Catalogue Wave A', $profileBlock['name']);
        self::assertSame('catalogue-wave-a', $profileBlock['code']);
        self::assertSame('UPDATE', $profileBlock['mode']);
        self::assertSame('product', $profileBlock['target_object_type_code']);
    }

    #[Test]
    public function importEnvelopeCreatesProfileUnderCallingUser(): void
    {
        $envelope = [
            'schemaVersion' => '1.0',
            'exportedAt' => '2026-05-12T10:00:00+00:00',
            'profile' => [
                'name' => 'Imported Profile',
                'code' => 'imported-profile',
                'mode' => 'UPSERT',
                'target_object_type_code' => 'product',
                'column_mapping' => ['SKU' => 'sku', 'Name' => 'name'],
                'locale' => 'en_US',
                'encoding' => 'utf-8',
                'delimiter' => ',',
                'image_source' => 'none',
                'image_zip_naming_convention' => null,
            ],
        ];

        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-profiles/import', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode($envelope, JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        self::assertSame('Imported Profile', $created['name']);
        self::assertSame('imported-profile', $created['code']);
        self::assertSame('UPSERT', $created['mode']);
    }

    #[Test]
    public function importRejectsUnknownSchemaVersion(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-profiles/import', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'schemaVersion' => '2.0',
                'profile' => ['name' => 'X', 'code' => 'x', 'mode' => 'UPDATE', 'target_object_type_code' => 'product'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function importRejectsUnknownObjectTypeCode(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-profiles/import', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'schemaVersion' => '1.0',
                'profile' => [
                    'name' => 'Bad Target',
                    'code' => 'bad-target',
                    'mode' => 'UPDATE',
                    'target_object_type_code' => 'nonexistent-type-xyz',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(404);
    }
}
