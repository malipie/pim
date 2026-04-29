<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CatalogObjectTest extends TestCase
{
    #[Test]
    public function constructorDefaultsAndUuidV7(): void
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $object = new CatalogObject($type, 'SKU-001');

        self::assertInstanceOf(Uuid::class, $object->getId());
        self::assertSame('SKU-001', $object->getCode());
        self::assertSame(ObjectKind::Product, $object->getKind());
        self::assertSame($type, $object->getObjectType());
        self::assertNull($object->getParent());
        self::assertNull($object->getPath());
        self::assertNull($object->getTenant());
        self::assertTrue($object->isEnabled());
        self::assertSame(CatalogObject::STATUS_DRAFT, $object->getStatus());
        self::assertSame([], $object->getCompleteness());
        self::assertSame([], $object->getAttributesIndexed());
    }

    #[Test]
    public function kindIsDenormalisedFromObjectType(): void
    {
        $categoryType = new ObjectType('category', ObjectKind::Category, ['pl' => 'Kategoria']);
        $object = new CatalogObject($categoryType, 'electronics');

        self::assertSame(ObjectKind::Category, $object->getKind());
        // Object kind tracks ObjectType kind — admins never set it directly.
    }

    #[Test]
    public function setAttributesIndexedRoundTripsJsonbPayload(): void
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $object = new CatalogObject($type, 'SKU-002');

        $object->setAttributesIndexed([
            'name' => ['pl' => 'Smartfon', 'en' => 'Smartphone'],
            'sku' => 'SKU-002',
            'color' => 'red',
            'price' => ['amount' => 1999, 'currency' => 'PLN'],
        ]);

        $indexed = $object->getAttributesIndexed();
        self::assertSame('red', $indexed['color']);
        self::assertIsArray($indexed['name']);
        self::assertSame('Smartfon', $indexed['name']['pl']);
        self::assertIsArray($indexed['price']);
        self::assertSame(1999, $indexed['price']['amount']);
    }

    #[Test]
    public function setPathStoresLtreeStringForCategory(): void
    {
        $categoryType = new ObjectType('category', ObjectKind::Category, ['pl' => 'Kategoria']);
        $object = new CatalogObject($categoryType, 'shoes');

        $object->setPath('root.men.shoes');

        self::assertSame('root.men.shoes', $object->getPath());
        // The kind = 'category' OR path IS NULL invariant + ltree validation
        // land in #33 (Doctrine listener) — entity layer just stores text.
    }

    #[Test]
    public function parentSelfReferenceWorksForVariantAndTreePatterns(): void
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $parent = new CatalogObject($type, 'PARENT-SKU');
        $variant = new CatalogObject($type, 'VARIANT-SKU');

        $variant->setParent($parent);

        self::assertSame($parent, $variant->getParent());
    }

    #[Test]
    public function assignTenantStampsAndRefusesReassignment(): void
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $object = new CatalogObject($type, 'SKU-003');
        $first = new Tenant('demo', 'Demo');
        $second = new Tenant('acme', 'Acme');

        $object->assignTenant($first);
        self::assertSame($first, $object->getTenant());

        $this->expectException(LogicException::class);
        $object->assignTenant($second);
    }

    #[Test]
    public function statusTransitionsAreFreeFormForNow(): void
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $object = new CatalogObject($type, 'SKU-004');

        $object->setStatus(CatalogObject::STATUS_PUBLISHED);
        self::assertSame('published', $object->getStatus());

        $object->setStatus(CatalogObject::STATUS_ARCHIVED);
        self::assertSame('archived', $object->getStatus());
    }
}
