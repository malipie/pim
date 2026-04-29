<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\Currency;
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
        self::assertCount(0, $channel->getCurrencies());
        self::assertNull($channel->getCategoryTreeRoot());
        self::assertNull($channel->getTenant());
    }

    #[Test]
    public function localeAndCurrencyM2mIsIdempotent(): void
    {
        $channel = new Channel('ecommerce_pl', ['pl' => 'Sklep PL']);
        $pl = new Locale('pl_PL', 'Polski');
        $pln = new Currency('PLN', 'zł', 'Polish złoty');

        $channel->addLocale($pl);
        $channel->addLocale($pl);
        $channel->addCurrency($pln);
        $channel->addCurrency($pln);

        self::assertCount(1, $channel->getLocales());
        self::assertCount(1, $channel->getCurrencies());

        $channel->removeLocale($pl);
        $channel->removeCurrency($pln);
        self::assertCount(0, $channel->getLocales());
        self::assertCount(0, $channel->getCurrencies());
    }

    #[Test]
    public function categoryTreeRootSetterStoresReference(): void
    {
        $channel = new Channel('shop', ['pl' => 'Sklep']);
        $categoryType = new ObjectType('category', ObjectKind::Category, ['pl' => 'Kategoria']);
        $root = new CatalogObject($categoryType, 'root');

        $channel->setCategoryTreeRoot($root);

        self::assertSame($root, $channel->getCategoryTreeRoot());
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
    public function localeAndCurrencyExposeBasicFields(): void
    {
        $pl = new Locale('pl_PL', 'Polski');
        $pln = new Currency('PLN', 'zł', 'Polish złoty');

        self::assertSame('pl_PL', $pl->getCode());
        self::assertSame('Polski', $pl->getLabel());
        self::assertSame('PLN', $pln->getCode());
        self::assertSame('zł', $pln->getSymbol());
        self::assertSame('Polish złoty', $pln->getLabel());
    }
}
