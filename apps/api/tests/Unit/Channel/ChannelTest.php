<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\Locale;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ChannelTest extends TestCase
{
    #[Test]
    public function constructorDefaultsAreEmpty(): void
    {
        $channel = new Channel('ecommerce_pl', 'Sklep PL');

        self::assertInstanceOf(Uuid::class, $channel->getId());
        self::assertSame('ecommerce_pl', $channel->getCode());
        self::assertSame('Sklep PL', $channel->getName());
        self::assertNull($channel->getCategoryTreeRootId());
        self::assertNull($channel->getTenant());
    }

    #[Test]
    public function categoryTreeRootStoresUuid(): void
    {
        $channel = new Channel('shop', 'Sklep');
        $categoryType = new ObjectType('category', ObjectKind::Category, ['pl' => 'Kategoria']);
        $root = new CatalogObject($categoryType, 'root');

        $channel->attachCategoryTreeRoot($root->getId());

        self::assertSame($root->getId(), $channel->getCategoryTreeRootId());
    }

    #[Test]
    public function assignTenantStampsAndRefusesReassignment(): void
    {
        $channel = new Channel('shop', 'Sklep');
        $first = new Tenant('demo', 'Demo');
        $second = new Tenant('acme', 'Acme');

        $channel->assignTenant($first);
        self::assertSame($first, $channel->getTenant());

        $this->expectException(LogicException::class);
        $channel->assignTenant($second);
    }

    #[Test]
    public function localeExposesBasicFields(): void
    {
        $pl = new Locale('pl_PL', 'Polski');

        self::assertSame('pl_PL', $pl->getCode());
        self::assertSame('Polski', $pl->getLabel());
    }
}
