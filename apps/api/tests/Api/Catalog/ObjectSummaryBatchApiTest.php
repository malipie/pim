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
 * MODR-08 (#930) — `POST /api/objects/summaries` returns lightweight
 * `{id, code, name, objectType}` rows for a list of object UUIDs. Used
 * by the relation widget's rich preview card path.
 */
final class ObjectSummaryBatchApiTest extends CatalogApiTestCase
{
    #[Test]
    public function returnsSummariesForKnownIds(): void
    {
        $client = $this->authenticatedClient();
        $a = $this->seedProduct('SUM-A');
        $b = $this->seedProduct('SUM-B');

        $body = $client->request('POST', '/api/objects/summaries', [
            'json' => ['ids' => [$a->getId()->toRfc4122(), $b->getId()->toRfc4122()]],
        ])->toArray();

        self::assertCount(2, $body);
        $codes = [];
        foreach ($body as $row) {
            \assert(\is_array($row));
            self::assertArrayHasKey('id', $row);
            self::assertArrayHasKey('name', $row);
            self::assertArrayHasKey('objectType', $row);
            \assert(\is_array($row['objectType']));
            self::assertSame('product', $row['objectType']['code']);
            \assert(\is_string($row['code']));
            $codes[] = $row['code'];
        }
        sort($codes);
        self::assertSame(['SUM-A', 'SUM-B'], $codes);
    }

    #[Test]
    public function unknownIdsAreSilentlySkipped(): void
    {
        $client = $this->authenticatedClient();
        $a = $this->seedProduct('SUM-OK');

        $body = $client->request('POST', '/api/objects/summaries', [
            'json' => ['ids' => [$a->getId()->toRfc4122(), '01234567-1234-7000-8000-000000000000']],
        ])->toArray();

        self::assertCount(1, $body);
        \assert(\is_array($body[0]));
        self::assertSame('SUM-OK', $body[0]['code']);
    }

    #[Test]
    public function rejectsBodyMissingIds(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/objects/summaries', [
            'json' => ['something' => 'else'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[Test]
    public function rejectsTooManyIds(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/objects/summaries', [
            'json' => ['ids' => array_fill(0, 201, '01234567-1234-7000-8000-000000000000')],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
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

        $object = new CatalogObject($type, $code);
        $object->updateAttributeIndex(['name' => 'Display '.$code]);
        $em = $this->em();
        $em->persist($object);
        $em->flush();

        $tenantContext->clear();

        return $object;
    }
}
