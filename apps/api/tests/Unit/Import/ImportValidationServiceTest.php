<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Import\Application\Service\DelimiterDetector;
use App\Import\Application\Service\EncodingDetector;
use App\Import\Application\Service\ImportRowReader;
use App\Import\Application\Service\ImportValidationService;
use App\Import\Domain\Enum\ImportErrorType;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ImportValidationServiceTest extends TestCase
{
    #[Test]
    public function validatesMissingRequiredAndDuplicateSkuTypes(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $skuAttribute = new Attribute('sku', ['en' => 'SKU'], AttributeType::Text);
        $nameAttribute = new Attribute('name', ['en' => 'Name'], AttributeType::Text);
        $priceAttribute = new Attribute('price', ['en' => 'Price'], AttributeType::Number);

        $attributeRepo = new InMemoryAttributeRepository([
            'sku' => $skuAttribute,
            'name' => $nameAttribute,
            'price' => $priceAttribute,
        ]);

        $existingProduct = new CatalogObject(
            new ObjectType('product', ObjectKind::Product, ['en' => 'Product']),
            'EXISTING-1',
        );
        $catalogRepo = new InMemoryCatalogObjectRepository(['EXISTING-1' => $existingProduct]);

        $tenantContext = new TenantContext();
        $tenantContext->set($tenant);

        $service = new ImportValidationService(
            attributes: $attributeRepo,
            catalogObjects: $catalogRepo,
            tenantContext: $tenantContext,
            rowReader: new ImportRowReader(new EncodingDetector(), new DelimiterDetector()),
        );

        $csv = "sku;name;price\nOK-1;Foo;9.99\nEXISTING-1;Bar;14.99\n;Anon;5\nDUP-1;Dup;1\nDUP-1;Dup again;2\nBAD-1;Has bad price;not-a-number\n";
        $path = $this->writeTempCsv($csv);

        try {
            $product = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
            $result = $service->validate(
                absolutePath: $path,
                columnMapping: ['sku' => 'sku', 'name' => 'name', 'price' => 'price'],
                target: $product,
            );

            self::assertSame(6, $result->totalRows);
            // OK-1 + first appearance of DUP-1 pass; the rest land in errors.
            self::assertSame(2, $result->successCount);
            self::assertSame(4, $result->errorCount);

            $errorTypes = array_map(static fn ($e): string => $e->errorType->value, $result->errors);
            self::assertContains(ImportErrorType::DuplicateSkuInDb->value, $errorTypes);
            self::assertContains(ImportErrorType::MissingRequired->value, $errorTypes);
            self::assertContains(ImportErrorType::DuplicateSkuInFile->value, $errorTypes);
            self::assertContains(ImportErrorType::InvalidType->value, $errorTypes);
        } finally {
            @unlink($path);
        }
    }

    private function writeTempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp-validate-unit-');
        self::assertNotFalse($path);
        $renamed = $path.'.csv';
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
    }
}

/**
 * @internal
 */
final class InMemoryAttributeRepository implements AttributeRepositoryInterface
{
    /**
     * @param array<string, Attribute> $byCode
     */
    public function __construct(private readonly array $byCode)
    {
    }

    public function findById(Uuid $id): ?Attribute
    {
        foreach ($this->byCode as $attribute) {
            if ($attribute->getId()->toRfc4122() === $id->toRfc4122()) {
                return $attribute;
            }
        }

        return null;
    }

    public function findByCode(string $code, Tenant $tenant): ?Attribute
    {
        return $this->byCode[$code] ?? null;
    }

    public function save(Attribute $attribute): void
    {
    }

    public function remove(Attribute $attribute): void
    {
    }
}

/**
 * @internal
 */
final class InMemoryCatalogObjectRepository implements CatalogObjectRepositoryInterface
{
    /**
     * @param array<string, CatalogObject> $bySku
     */
    public function __construct(private readonly array $bySku)
    {
    }

    public function findByCode(string $code, ObjectKind $kind, Tenant $tenant): ?CatalogObject
    {
        return $this->bySku[$code] ?? null;
    }

    public function findById(Uuid $id): ?CatalogObject
    {
        return null;
    }

    public function findByIds(array $idsRfc4122): array
    {
        return [];
    }

    public function findByKind(ObjectKind $kind, Tenant $tenant): array
    {
        return [];
    }

    public function save(CatalogObject $object): void
    {
    }

    public function remove(CatalogObject $object): void
    {
    }
}
