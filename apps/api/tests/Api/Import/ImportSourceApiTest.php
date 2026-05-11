<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-IMP-03 (#500) — ApiTestCase smoke for import sources.
 *
 * Round-trips CRUD + test-connection probe against the live HTTP
 * surface so we pin the wire shape the FE depends on. The folder
 * driver does a real readability check against `/tmp` (always
 * available in the test container), the other drivers ship as stubs
 * that return `ok` until the polling daemon follow-up lands.
 */
final class ImportSourceApiTest extends CatalogApiTestCase
{
    #[Test]
    public function listRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/import-sources');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function listReturnsEmptyCollectionForFreshUser(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-sources');

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        $items = $body['member'] ?? $body['hydra:member'] ?? null;
        self::assertIsArray($items);
        self::assertSame([], $items);
    }

    #[Test]
    public function postCreatesSourceAndPersistsBackToList(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-sources', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Demo Watch Folder',
                'code' => 'demo-watch-folder',
                'type' => 'folder',
                'path' => '/tmp',
                'filePattern' => '*.csv',
                'pollIntervalSec' => 300,
                'autotrigger' => false,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        self::assertSame('Demo Watch Folder', $created['name']);
        self::assertSame('demo-watch-folder', $created['code']);
        self::assertSame('folder', $created['type']);

        $client->request('GET', '/api/import-sources');
        self::assertResponseIsSuccessful();
        $list = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($list);
        $items = $list['member'] ?? $list['hydra:member'] ?? null;
        self::assertIsArray($items);
        self::assertGreaterThanOrEqual(1, \count($items));
    }

    #[Test]
    public function postRejectsUnknownTransportType(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-sources', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Bad type',
                'code' => 'bad-type',
                'type' => 'gopher',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function testConnectionFolderProbeRunsRealReadabilityCheck(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-sources', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Tmp watcher',
                'code' => 'tmp-watcher',
                'type' => 'folder',
                'path' => '/tmp',
            ], JSON_THROW_ON_ERROR),
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $sourceId = $created['id'];
        self::assertIsString($sourceId);

        $client->request('POST', \sprintf('/api/import-sources/%s/test-connection', $sourceId));
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertContains($body['health'], ['ok', 'warn']);
        self::assertArrayHasKey('latency_ms', $body);
        self::assertArrayHasKey('checked_at', $body);
    }

    #[Test]
    public function testConnectionFolderProbeReportsErrorForMissingPath(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-sources', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Missing folder',
                'code' => 'missing-folder',
                'type' => 'folder',
                'path' => '/this-path-definitely-does-not-exist',
            ], JSON_THROW_ON_ERROR),
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $sourceId = $created['id'];
        self::assertIsString($sourceId);

        $client->request('POST', \sprintf('/api/import-sources/%s/test-connection', $sourceId));
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('error', $body['health']);
    }

    #[Test]
    public function testConnectionStubProbeForSftpReturnsOk(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-sources', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Stub SFTP',
                'code' => 'stub-sftp',
                'type' => 'sftp',
                'host' => 'sftp.example.com',
                'path' => '/incoming',
            ], JSON_THROW_ON_ERROR),
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $sourceId = $created['id'];
        self::assertIsString($sourceId);

        $client->request('POST', \sprintf('/api/import-sources/%s/test-connection', $sourceId));
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('ok', $body['health']);
        self::assertIsString($body['note']);
        self::assertStringContainsString('polling daemon follow-up', $body['note']);
    }
}
