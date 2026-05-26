<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the built-in / immutability / icon / colour metadata introduced
 * by UI-08.2 (#257). Per ADR-014 / MOD-10 Brand is no longer a built-in
 * kind — only Product, Category, Asset get seeded with code-immutable +
 * undeletable flags. Tests here focus on the entity-level invariants.
 */
final class ObjectTypeBuiltInFlagsTest extends TestCase
{
    #[Test]
    public function freshObjectTypeIsNotBuiltInAndDeletableWithMutableCode(): void
    {
        $type = new ObjectType('subscription', ObjectKind::Custom, ['pl' => 'Subskrypcja']);

        self::assertFalse($type->isBuiltIn());
        self::assertFalse($type->isCodeImmutable());
        self::assertTrue($type->isDeletable());
        self::assertNull($type->getIcon());
        self::assertNull($type->getColor());
    }

    #[Test]
    public function lockingMakesCodeImmutableAndChangeCodeThrows(): void
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $type->markBuiltIn();
        $type->lockCode();

        self::assertTrue($type->isCodeImmutable());

        $this->expectException(LogicException::class);
        $type->changeCode('product_renamed');
    }

    #[Test]
    public function changeCodeWorksForCustomKindWithoutLock(): void
    {
        $type = new ObjectType('subscription', ObjectKind::Custom, ['pl' => 'Subskrypcja']);
        $type->changeCode('membership');

        self::assertSame('membership', $type->getCode());
    }

    #[Test]
    public function markUndeletableFlipsDeletableFlag(): void
    {
        $type = new ObjectType('asset', ObjectKind::Asset, ['pl' => 'Zasób']);
        self::assertTrue($type->isDeletable());

        $type->markUndeletable();
        self::assertFalse($type->isDeletable());
    }

    #[Test]
    public function iconAndColorAreFreelyEditable(): void
    {
        $type = new ObjectType('asset', ObjectKind::Asset, ['pl' => 'Zasób', 'en' => 'Asset']);

        $type->setIcon('Image');
        $type->setColor('#F59E0B');

        self::assertSame('Image', $type->getIcon());
        self::assertSame('#F59E0B', $type->getColor());

        $type->setIcon(null);
        self::assertNull($type->getIcon());
    }

    #[Test]
    public function builtInKindsAreFlaggedAndCustomIsNot(): void
    {
        self::assertTrue(ObjectKind::Product->isBuiltIn());
        self::assertTrue(ObjectKind::Category->isBuiltIn());
        self::assertTrue(ObjectKind::Asset->isBuiltIn());
        self::assertFalse(ObjectKind::Custom->isBuiltIn());
    }
}
