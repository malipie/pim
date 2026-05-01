<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroup;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers UI-08.3 (#258): system attribute flag + audit AttributeGroup
 * invariants. The migration handles seeding + listener auto-attach is
 * exercised by integration tests; this file pins the entity-level
 * contracts that block accidental mutations of system rows.
 */
final class SystemAttributesAuditGroupTest extends TestCase
{
    #[Test]
    public function freshAttributeIsNotSystem(): void
    {
        $attribute = new Attribute('sku', ['en' => 'SKU'], AttributeType::Text);

        self::assertFalse($attribute->isSystem());
    }

    #[Test]
    public function markSystemFlipsTheFlag(): void
    {
        $attribute = new Attribute('created_at', ['en' => 'Created at'], AttributeType::Datetime);
        $attribute->markSystem();

        self::assertTrue($attribute->isSystem());
    }

    #[Test]
    public function systemAttributeRefusesCodeChange(): void
    {
        $attribute = new Attribute('created_at', ['en' => 'Created at'], AttributeType::Datetime);
        $attribute->markSystem();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('System attribute code is immutable.');
        $attribute->changeCode('created_when');
    }

    #[Test]
    public function nonSystemAttributeAllowsCodeChange(): void
    {
        $attribute = new Attribute('sku', ['en' => 'SKU'], AttributeType::Text);
        $attribute->changeCode('product_sku');

        self::assertSame('product_sku', $attribute->getCode());
    }

    #[Test]
    public function datetimeAndReferenceTypesAreSystemTypes(): void
    {
        self::assertTrue(AttributeType::Datetime->isSystemType());
        self::assertTrue(AttributeType::Reference->isSystemType());
        self::assertFalse(AttributeType::Text->isSystemType());
        self::assertFalse(AttributeType::Date->isSystemType());
        self::assertFalse(AttributeType::Relation->isSystemType());
    }

    #[Test]
    public function systemTypesDoNotUseOptions(): void
    {
        self::assertFalse(AttributeType::Datetime->usesOptions());
        self::assertFalse(AttributeType::Reference->usesOptions());
    }

    #[Test]
    public function systemAttributeGroupCodeIsImmutable(): void
    {
        $audit = new AttributeGroup('audit', ['en' => 'Audit'], isSystemGroup: true, autoAttached: true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('System AttributeGroup code is immutable.');
        $audit->changeCode('audit_renamed');
    }

    #[Test]
    public function nonSystemAttributeGroupAllowsCodeChange(): void
    {
        $marketing = new AttributeGroup('marketing', ['en' => 'Marketing']);
        $marketing->changeCode('mkt');

        self::assertSame('mkt', $marketing->getCode());
    }

    #[Test]
    public function systemAttributeGroupAllowsRenameOfTranslatableLabel(): void
    {
        $audit = new AttributeGroup('audit', ['en' => 'Audit'], isSystemGroup: true, autoAttached: true);
        $audit->rename(['en' => 'Audit log', 'pl' => 'Dziennik audytu']);

        self::assertSame('Audit log', $audit->getLabel()['en']);
        self::assertSame('Dziennik audytu', $audit->getLabel()['pl']);
    }
}
