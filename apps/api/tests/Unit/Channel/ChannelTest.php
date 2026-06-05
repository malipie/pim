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
        $channel = new Channel('ecommerce_pl', ['pl' => 'Sklep PL', 'en' => 'PL Storefront']);

        self::assertInstanceOf(Uuid::class, $channel->getId());
        self::assertSame('ecommerce_pl', $channel->getCode());
        self::assertSame('Sklep PL', $channel->getLabel()['pl']);
        self::assertCount(0, $channel->getLocales());
        self::assertNull($channel->getCategoryTreeRootId());
        self::assertNull($channel->getTenant());
    }

    #[Test]
    public function localeM2mIsIdempotent(): void
    {
        $channel = new Channel('ecommerce_pl', ['pl' => 'Sklep PL']);
        $pl = new Locale('pl_PL', 'Polski');

        $channel->addLocale($pl);
        $channel->addLocale($pl);

        self::assertCount(1, $channel->getLocales());

        $channel->removeLocale($pl);
        self::assertCount(0, $channel->getLocales());
    }

    #[Test]
    public function categoryTreeRootStoresUuid(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $categoryType = new ObjectType('category', ObjectKind::Category, ['pl' => 'Kategoria']);
        $root = new CatalogObject($categoryType, 'root');

        $channel->attachCategoryTreeRoot($root->getId());

        self::assertSame($root->getId(), $channel->getCategoryTreeRootId());
    }

    #[Test]
    public function assignTenantStampsAndRefusesReassignment(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);
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
