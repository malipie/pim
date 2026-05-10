<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ObjectCategoryTest extends TestCase
{
    #[Test]
    public function freshAssignmentCarriesProductCategoryAndDefaults(): void
    {
        $product = $this->makeObject(ObjectKind::Product, 'SKU-1');
        $category = $this->makeObject(ObjectKind::Category, 'cat-1');

        $assignment = new ObjectCategory($product, $category);

        self::assertSame($product, $assignment->getProduct());
        self::assertSame($category, $assignment->getCategory());
        self::assertFalse($assignment->isPrimary());
        self::assertSame(0, $assignment->getPosition());
        self::assertInstanceOf(DateTimeImmutable::class, $assignment->getCreatedAt());
    }

    #[Test]
    public function explicitConstructorArgumentsOverrideDefaults(): void
    {
        $product = $this->makeObject(ObjectKind::Product, 'SKU-2');
        $category = $this->makeObject(ObjectKind::Category, 'cat-2');
        $stamp = new DateTimeImmutable('2026-05-10T12:00:00+00:00');

        $assignment = new ObjectCategory(
            product: $product,
            category: $category,
            isPrimary: true,
            position: 7,
            createdAt: $stamp,
        );

        self::assertTrue($assignment->isPrimary());
        self::assertSame(7, $assignment->getPosition());
        self::assertSame($stamp, $assignment->getCreatedAt());
    }

    #[Test]
    public function promoteAndDemoteToggleThePrimaryFlag(): void
    {
        $assignment = new ObjectCategory(
            $this->makeObject(ObjectKind::Product, 'SKU-3'),
            $this->makeObject(ObjectKind::Category, 'cat-3'),
        );

        $assignment->promoteToPrimary();
        self::assertTrue($assignment->isPrimary());

        $assignment->demoteFromPrimary();
        self::assertFalse($assignment->isPrimary());
    }

    #[Test]
    public function reorderUpdatesPositionInPlace(): void
    {
        $assignment = new ObjectCategory(
            $this->makeObject(ObjectKind::Product, 'SKU-4'),
            $this->makeObject(ObjectKind::Category, 'cat-4'),
            position: 0,
        );

        $assignment->reorder(42);

        self::assertSame(42, $assignment->getPosition());
    }

    private function makeObject(ObjectKind $kind, string $code): CatalogObject
    {
        $type = new ObjectType($kind->value, $kind, ['en' => ucfirst($kind->value)]);

        return new CatalogObject($type, $code);
    }
}
