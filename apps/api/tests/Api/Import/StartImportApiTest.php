<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * IMP-04 (#445) — wizard Step 4 confirm round-trips through the
 * persisting endpoint. Sync small import (<50 rows) executes inline
 * and surfaces `status=success` plus the matching CatalogObject rows
 * stamped with the session id.
 */
final class StartImportApiTest extends CatalogApiTestCase
{
    #[Test]
    public function smallImportRunsInlineAndPersistsProducts(): void
    {
        $this->seedAttributes();

        $csvPath = $this->writeSmallCsv();

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode([
                            'sku' => 'sku',
                            'name' => 'name',
                        ], JSON_THROW_ON_ERROR),
                    ],
                    'files' => [
                        'file' => new UploadedFile($csvPath, 'small.csv', 'text/csv', null, true),
                    ],
                ],
            ]);

            self::assertResponseIsSuccessful();
            $response = $client->getResponse();
            self::assertNotNull($response);
            $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);

            self::assertSame(
                'success',
                $body['status'],
                \sprintf(
                    'Expected success but body=%s',
                    json_encode($body, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                ),
            );
            self::assertSame(5, $body['success_count']);
            self::assertSame(0, $body['error_count']);
            self::assertNotNull($body['rollback_until'], 'Successful imports open the 24h rollback window.');

            // Imported products are stamped with the session id and
            // discoverable via the catalog repository.
            $em = $this->em();
            $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
            \assert($tenant instanceof Tenant);
            self::getContainer()->get(TenantContext::class)->set($tenant);

            $product = self::getContainer()
                ->get(ObjectTypeRepositoryInterface::class)
                ->findBuiltInByKind(ObjectKind::Product, $tenant);
            \assert($product instanceof ObjectType);

            $imported = $em->createQueryBuilder()
                ->select('o')
                ->from(CatalogObject::class, 'o')
                ->where('o.objectType = :type')
                ->andWhere('o.importSessionId = :sessionId')
                ->setParameter('type', $product)
                ->setParameter('sessionId', $body['id'])
                ->getQuery()
                ->getResult();

            self::assertCount(5, $imported);
        } finally {
            @unlink($csvPath);
        }
    }

    private function seedAttributes(): void
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $product = self::getContainer()
            ->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($product instanceof ObjectType);

        $sku = new Attribute('sku', ['en' => 'SKU'], AttributeType::Text);
        $name = new Attribute('name', ['en' => 'Name'], AttributeType::Text);
        $em->persist($sku);
        $em->persist($name);
        $em->persist(new ObjectTypeAttribute($product, $sku, false, 1));
        $em->persist(new ObjectTypeAttribute($product, $name, false, 2));
        $em->flush();
    }

    private function writeSmallCsv(): string
    {
        $rows = ['sku;name'];
        for ($i = 1; $i <= 5; ++$i) {
            $rows[] = \sprintf('SMALL-%d;Product %d', $i, $i);
        }
        $contents = implode("\n", $rows)."\n";

        $path = tempnam(sys_get_temp_dir(), 'pim-start-import-');
        \assert(false !== $path);
        $renamed = $path.'.csv';
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
    }
}
