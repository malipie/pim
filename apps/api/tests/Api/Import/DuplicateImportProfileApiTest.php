<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\ObjectKind;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-IMP-02 (#498) — duplicate endpoint smoke. Owner-only, conflict
 * resolution on the (copy) / -copy suffix is verified by chaining two
 * duplicates and asserting both succeed with unique name/code.
 */
final class DuplicateImportProfileApiTest extends CatalogApiTestCase
{
    #[Test]
    public function duplicateRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/import-profiles/00000000-0000-0000-0000-000000000000/duplicate');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function duplicateClonesProfileWithSuffixedNameAndCode(): void
    {
        $client = $this->authenticatedClient();
        $targetId = $this->objectTypeIdFor(ObjectKind::Product);

        $client->request('POST', '/api/import-profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Quarterly Catalogue',
                'code' => 'quarterly-catalogue',
                'mode' => 'UPSERT',
                'targetObjectTypeId' => $targetId,
                'columnMapping' => ['SKU' => 'sku', 'Title' => 'name'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $profileId = $created['id'];
        self::assertIsString($profileId);

        // First duplicate.
        $client->request('POST', \sprintf('/api/import-profiles/%s/duplicate', $profileId));
        self::assertResponseStatusCodeSame(201);
        $first = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($first);
        self::assertIsString($first['name']);
        self::assertIsString($first['code']);
        self::assertStringContainsString('(copy)', $first['name']);
        self::assertStringContainsString('-copy', $first['code']);
        self::assertSame('UPSERT', $first['mode']);

        // Second duplicate of the original → must surface a different
        // suffix to avoid colliding with the first copy.
        $client->request('POST', \sprintf('/api/import-profiles/%s/duplicate', $profileId));
        self::assertResponseStatusCodeSame(201);
        $second = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($second);
        self::assertNotSame($first['name'], $second['name']);
        self::assertNotSame($first['code'], $second['code']);
    }

    #[Test]
    public function duplicateOfUnknownProfileReturns404(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-profiles/00000000-0000-0000-0000-000000000000/duplicate');
        self::assertResponseStatusCodeSame(404);
    }
}
