<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Entity\Association;
use App\Catalog\Domain\Entity\AssociationType;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Identity\Domain\Entity\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AssociationTest extends TestCase
{
    #[Test]
    public function constructorWiresUpReferences(): void
    {
        $type = $this->associationType();
        [$a, $b] = $this->twoProducts();

        $assoc = new Association($a, $b, $type, position: 5);

        self::assertInstanceOf(Uuid::class, $assoc->getId());
        self::assertSame($a, $assoc->getSource());
        self::assertSame($b, $assoc->getTarget());
        self::assertSame($type, $assoc->getType());
        self::assertSame(5, $assoc->getPosition());
        self::assertNull($assoc->getTenant());
    }

    #[Test]
    public function selfLoopIsRejected(): void
    {
        $type = $this->associationType();
        [$a, $_b] = $this->twoProducts();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('itself');
        new Association($a, $a, $type);
    }

    #[Test]
    public function assignTenantStampsAndRefusesReassignment(): void
    {
        $type = $this->associationType();
        [$a, $b] = $this->twoProducts();
        $assoc = new Association($a, $b, $type);

        $first = new Tenant('demo', 'Demo');
        $second = new Tenant('acme', 'Acme');

        $assoc->assignTenant($first);
        self::assertSame($first, $assoc->getTenant());

        $this->expectException(LogicException::class);
        $assoc->assignTenant($second);
    }

    #[Test]
    public function associationTypeDefaultsAreSensible(): void
    {
        $type = new AssociationType('cross_sell', ['pl' => 'Sprzedaż krzyżowa', 'en' => 'Cross-sell'], 10);

        self::assertSame('cross_sell', $type->getCode());
        self::assertSame('Cross-sell', $type->getLabel()['en']);
        self::assertSame(10, $type->getPosition());
        self::assertNull($type->getTenant());
    }

    /**
     * @return array{0: CatalogObject, 1: CatalogObject}
     */
    private function twoProducts(): array
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);

        return [new CatalogObject($type, 'SKU-A'), new CatalogObject($type, 'SKU-B')];
    }

    private function associationType(): AssociationType
    {
        return new AssociationType('cross_sell', ['pl' => 'Cross-sell'], 10);
    }
}
