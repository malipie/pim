<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
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

    #[Test]
    public function importAssignsRowsToTheirCategoriesAndWarnsOnMissingOnes(): void
    {
        $this->seedAttributes();
        $this->seedCategories(['pneumatyka', 'elektronika']);

        $csvPath = $this->writeCategoryCsv();

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode([
                            'sku' => 'sku',
                            'name' => 'name',
                            'kategoria' => '__category__',
                        ], JSON_THROW_ON_ERROR),
                    ],
                    'files' => [
                        'file' => new UploadedFile($csvPath, 'category-import.csv', 'text/csv', null, true),
                    ],
                ],
            ]);

            self::assertResponseIsSuccessful();
            $response = $client->getResponse();
            self::assertNotNull($response);
            $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);

            self::assertSame('success', $body['status'], 'Rows with missing categories still import — only the assignment is dropped.');
            self::assertSame(3, $body['success_count']);
            self::assertSame(0, $body['error_count']);

            $em = $this->em();
            $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
            \assert($tenant instanceof Tenant);
            self::getContainer()->get(TenantContext::class)->set($tenant);

            $catalogObjects = self::getContainer()->get(CatalogObjectRepositoryInterface::class);
            $categories = self::getContainer()->get(ObjectCategoryRepositoryInterface::class);

            $sku1 = $catalogObjects->findByCode('CAT-1', ObjectKind::Product, $tenant);
            $sku2 = $catalogObjects->findByCode('CAT-2', ObjectKind::Product, $tenant);
            $sku3 = $catalogObjects->findByCode('CAT-3', ObjectKind::Product, $tenant);
            self::assertNotNull($sku1);
            self::assertNotNull($sku2);
            self::assertNotNull($sku3, 'CAT-3 has an unknown category code but still imports the product.');

            self::assertCount(1, $categories->findByProduct($sku1));
            $primary1 = $categories->findPrimary($sku1);
            self::assertNotNull($primary1);
            self::assertSame('pneumatyka', $primary1->getCategory()->getCode());

            self::assertCount(1, $categories->findByProduct($sku2));
            $primary2 = $categories->findPrimary($sku2);
            self::assertNotNull($primary2);
            self::assertSame('elektronika', $primary2->getCategory()->getCode());

            self::assertCount(0, $categories->findByProduct($sku3), 'Unknown category yields no assignment for CAT-3.');
            self::assertNull($categories->findPrimary($sku3));
        } finally {
            @unlink($csvPath);
        }
    }

    #[Test]
    public function importPipeSplitsCategoriesWithPrimaryPositionAndPerCodeWarning(): void
    {
        // IMP2-1.7 (AC 1,2) — `cat-a|cat-b|cat-c` → 3 assignments (first
        // primary, position by order); a code missing mid-list is dropped
        // with a per-code warning, the row still imports with the rest.
        $this->seedAttributes();
        $this->seedCategories(['cat_a', 'cat_b', 'cat_c']);

        $csvPath = $this->writeCsv(
            "sku;name;kategoria\nMULTI-1;Trzy;cat_a|cat_b|cat_c\nMULTI-2;Luka;cat_a|GHOST|cat_c\n",
            'pim-multicat-',
        );

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode([
                            'sku' => 'sku',
                            'name' => 'name',
                            'kategoria' => '__category__',
                        ], JSON_THROW_ON_ERROR),
                    ],
                    'files' => ['file' => new UploadedFile($csvPath, 'multi.csv', 'text/csv', null, true)],
                ],
            ]);

            self::assertResponseIsSuccessful();
            $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);
            self::assertSame('success', $body['status']);
            self::assertSame(2, $body['success_count']);
            self::assertSame(0, $body['error_count'], 'a missing category is a warning, not a row error');

            $em = $this->em();
            $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
            \assert($tenant instanceof Tenant);
            self::getContainer()->get(TenantContext::class)->set($tenant);
            $catalogObjects = self::getContainer()->get(CatalogObjectRepositoryInterface::class);
            $categories = self::getContainer()->get(ObjectCategoryRepositoryInterface::class);

            $m1 = $catalogObjects->findByCode('MULTI-1', ObjectKind::Product, $tenant);
            \assert(null !== $m1);
            $a1 = $categories->findByProduct($m1);
            self::assertCount(3, $a1);
            self::assertSame(['cat_a', 'cat_b', 'cat_c'], array_map(static fn ($a): string => $a->getCategory()->getCode(), $a1));
            self::assertSame([0, 1, 2], array_map(static fn ($a): int => $a->getPosition(), $a1));
            self::assertTrue($a1[0]->isPrimary());
            self::assertFalse($a1[1]->isPrimary());
            self::assertFalse($a1[2]->isPrimary());

            $m2 = $catalogObjects->findByCode('MULTI-2', ObjectKind::Product, $tenant);
            \assert(null !== $m2);
            $a2 = $categories->findByProduct($m2);
            self::assertCount(2, $a2, 'GHOST dropped, the row keeps cat_a + cat_c');
            self::assertSame(['cat_a', 'cat_c'], array_map(static fn ($a): string => $a->getCategory()->getCode(), $a2));

            $ghostWarnings = $em->getConnection()->fetchOne(
                "SELECT COUNT(*) FROM import_logs WHERE column_value = 'GHOST'",
            );
            self::assertSame(1, (int) (\is_scalar($ghostWarnings) ? $ghostWarnings : 0), 'per-code CategoryNotFound warning for GHOST');
        } finally {
            @unlink($csvPath);
        }
    }

    #[Test]
    public function importSetsStatusAndEnabledFromColumnsAndBlocksBadStatus(): void
    {
        // IMP2-1.7 (AC 4,5) — status/enabled columns set the object fields;
        // an out-of-enum status blocks the row (InvalidValue).
        $this->seedAttributes();

        $csvPath = $this->writeCsv(
            "sku;name;status;enabled\nST-1;Opublikowany;published;false\nST-2;Zly;bogus;true\n",
            'pim-status-',
        );

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode([
                            'sku' => 'sku',
                            'name' => 'name',
                            'status' => '__status__',
                            'enabled' => '__enabled__',
                        ], JSON_THROW_ON_ERROR),
                    ],
                    'files' => ['file' => new UploadedFile($csvPath, 'status.csv', 'text/csv', null, true)],
                ],
            ]);

            self::assertResponseIsSuccessful();
            $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);
            self::assertSame(1, $body['success_count']);
            self::assertSame(1, $body['error_count'], 'bogus status blocks the row');

            $em = $this->em();
            $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
            \assert($tenant instanceof Tenant);
            self::getContainer()->get(TenantContext::class)->set($tenant);
            $catalogObjects = self::getContainer()->get(CatalogObjectRepositoryInterface::class);

            $st1 = $catalogObjects->findByCode('ST-1', ObjectKind::Product, $tenant);
            \assert(null !== $st1);
            self::assertSame('published', $st1->getStatus());
            self::assertFalse($st1->isEnabled());

            self::assertNull(
                $catalogObjects->findByCode('ST-2', ObjectKind::Product, $tenant),
                'the row with a bogus status is rejected, not created',
            );
        } finally {
            @unlink($csvPath);
        }
    }

    private function writeCsv(string $contents, string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        \assert(false !== $path);
        $renamed = $path.'.csv';
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
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

    private function writeCategoryCsv(): string
    {
        $contents = "sku;name;kategoria\n"
            ."CAT-1;Czujnik;pneumatyka\n"
            ."CAT-2;Sterownik;elektronika\n"
            ."CAT-3;Bez kategorii;nonexistent-code\n";

        $path = tempnam(sys_get_temp_dir(), 'pim-start-import-cat-');
        \assert(false !== $path);
        $renamed = $path.'.csv';
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
    }

    /**
     * @param list<string> $codes
     */
    private function seedCategories(array $codes): void
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $categoryType = self::getContainer()
            ->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Category, $tenant);
        \assert($categoryType instanceof ObjectType);

        foreach ($codes as $code) {
            $category = new CatalogObject($categoryType, $code);
            $category->attachToPath($code);
            $em->persist($category);
        }
        $em->flush();
    }
}
