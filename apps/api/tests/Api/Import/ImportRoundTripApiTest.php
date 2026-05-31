<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use const JSON_THROW_ON_ERROR;

/**
 * #1130 — the exporter's own output must re-import without spurious
 * type / required errors (round-trip contract, PRD-PIM-exports.md §8).
 *
 * Covers the three validation-side contract fixes in one dry-run:
 *   - a system column (`created_at`) is skipped even when mapped to an
 *     attribute, so its ISO timestamp never trips the numeric check;
 *   - a localised column (`name.pl`) satisfies the required `name`;
 *   - composite price / metric cells (`20.99 EUR`, `0.3 g`) validate.
 */
final class ImportRoundTripApiTest extends CatalogApiTestCase
{
    #[Test]
    public function exportFormatRowValidatesWithoutBlockingErrors(): void
    {
        $this->seedRoundTripAttributes();
        $csvPath = $this->writeRoundTripCsv();

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions/validate-dry-run', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode([
                            'sku' => 'sku',
                            // Deliberately mis-mapped to a numeric attribute:
                            // the system-column skip must win regardless.
                            'created_at' => 'price',
                            'name.pl' => 'name',
                            'price' => 'price',
                            'weight' => 'weight',
                        ], JSON_THROW_ON_ERROR),
                    ],
                    'files' => [
                        'file' => new UploadedFile($csvPath, 'round-trip.csv', 'text/csv', null, true),
                    ],
                ],
            ]);

            self::assertResponseIsSuccessful();
            $response = $client->getResponse();
            self::assertNotNull($response);
            $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);

            self::assertSame(1, $body['total_rows']);
            self::assertSame(1, $body['success_count'], 'Round-trip export row validates clean.');
            self::assertSame(0, $body['error_count'], 'No spurious type / required errors on re-import.');
        } finally {
            @unlink($csvPath);
        }
    }

    private function seedRoundTripAttributes(): void
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
        $price = new Attribute('price', ['en' => 'Price'], AttributeType::Price);
        $weight = new Attribute('weight', ['en' => 'Weight'], AttributeType::Metric);
        $position = 1;
        foreach ([$sku, $name, $price, $weight] as $attribute) {
            $em->persist($attribute);
            $em->persist(new ObjectTypeAttribute($product, $attribute, false, $position++));
        }
        $em->flush();
    }

    private function writeRoundTripCsv(): string
    {
        $contents = "sku;created_at;name.pl;price;weight\n"
            ."RT-1;2026-05-28T20:14:59+00:00;Buty sportowe;20.99 EUR;0.3 g\n";
        $path = tempnam(sys_get_temp_dir(), 'imp-round-trip-');
        \assert(false !== $path);
        $renamed = $path.'.csv';
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
    }
}
