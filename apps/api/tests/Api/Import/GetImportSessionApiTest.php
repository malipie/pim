<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Import\Domain\Entity\ImportSession;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * IMP2-1.13 (#1476) follow-up — GET /api/import-sessions/{id} must expose the
 * ZIP source metadata (zip_file_name / zip_file_size_bytes). The fields were
 * persisted on the entity but missing from the contract, so consumers had no
 * way to read them. null for non-ZIP imports.
 */
final class GetImportSessionApiTest extends CatalogApiTestCase
{
    #[Test]
    public function getReturnsZipMetadataForZipImport(): void
    {
        $session = $this->persistSession('photos.zip', 204_800);

        $client = $this->authenticatedClient();
        $client->request('GET', \sprintf('/api/import-sessions/%s', $session->getId()->toRfc4122()));

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('photos.zip', $body['zip_file_name']);
        self::assertSame(204_800, $body['zip_file_size_bytes']);
    }

    #[Test]
    public function getReturnsNullZipMetadataForPlainImport(): void
    {
        $session = $this->persistSession(null, null);

        $client = $this->authenticatedClient();
        $client->request('GET', \sprintf('/api/import-sessions/%s', $session->getId()->toRfc4122()));

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertArrayHasKey('zip_file_name', $body);
        self::assertArrayHasKey('zip_file_size_bytes', $body);
        self::assertNull($body['zip_file_name']);
        self::assertNull($body['zip_file_size_bytes']);
    }

    private function persistSession(?string $zipFileName, ?int $zipFileSizeBytes): ImportSession
    {
        $em = $this->em();
        $tenant = $this->demoTenant();
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $product = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($product instanceof ObjectType);

        $session = new ImportSession(
            userId: $this->adminUserId(),
            targetObjectType: $product,
            fileName: $zipFileName ?? 'plain.csv',
            fileSizeBytes: 1_024,
            zipFileName: $zipFileName,
            zipFileSizeBytes: $zipFileSizeBytes,
        );
        $session->assignTenant($tenant);
        $em->persist($session);
        $em->flush();

        return $session;
    }

    private function demoTenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }

    private function adminUserId(): Uuid
    {
        $user = self::getContainer()->get(\App\Identity\Domain\Repository\UserRepositoryInterface::class)
            ->findByEmail(self::ADMIN_EMAIL);
        \assert(null !== $user);

        return $user->getId();
    }
}
