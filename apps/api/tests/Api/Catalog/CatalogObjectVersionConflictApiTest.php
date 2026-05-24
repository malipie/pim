<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-014 / MODR-10 (#932) — optimistic-lock guard on PATCH
 * `/api/products/{id}`. The relation widget's inline-edit panel reads
 * `version` from the GET payload and sends it back as `expectedVersion`;
 * a mismatch triggers HTTP 409 with a "stale data" hint.
 */
final class CatalogObjectVersionConflictApiTest extends CatalogApiTestCase
{
    #[Test]
    public function patchWithoutExpectedVersionRetainsBackwardCompat(): void
    {
        $client = $this->authenticatedClient();
        $product = $this->seedProduct('VER-BACKCOMPAT');

        $client->request('PATCH', '/api/products/'.$product->getId()->toRfc4122(), [
            'json' => ['enabled' => true],
            'headers' => ['content-type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function patchWithCorrectExpectedVersionSucceeds(): void
    {
        $client = $this->authenticatedClient();
        $product = $this->seedProduct('VER-MATCH');

        $body = $client->request('GET', '/api/products/'.$product->getId()->toRfc4122())->toArray();
        $version = $body['version'] ?? 1;
        \assert(\is_int($version));

        $client->request('PATCH', '/api/products/'.$product->getId()->toRfc4122(), [
            'json' => ['enabled' => true, 'expectedVersion' => $version],
            'headers' => ['content-type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function patchWithStaleExpectedVersionReturns409(): void
    {
        $client = $this->authenticatedClient();
        $product = $this->seedProduct('VER-STALE');

        // First PATCH flips enabled (initial=true → false) so the row
        // really mutates and Doctrine @Version bumps to 2.
        $client->request('PATCH', '/api/products/'.$product->getId()->toRfc4122(), [
            'json' => ['enabled' => false],
            'headers' => ['content-type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();

        // Second PATCH with the pre-bump version → 409 Conflict.
        $client->request('PATCH', '/api/products/'.$product->getId()->toRfc4122(), [
            'json' => ['enabled' => true, 'expectedVersion' => 1],
            'headers' => ['content-type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    private function seedProduct(string $code): CatalogObject
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $product = new CatalogObject($type, $code);
        $em = $this->em();
        $em->persist($product);
        $em->flush();

        $tenantContext->clear();

        return $product;
    }
}
