<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel\Infrastructure;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Channel\Infrastructure\ScopeEnumerator;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ScopeEnumeratorTest extends TestCase
{
    #[Test]
    public function localeShortCodesNormalisesBcp47ToShortAndDedupes(): void
    {
        $tenantLocales = $this->createStub(TenantLocaleRepositoryInterface::class);
        $tenantLocales->method('findActiveForTenant')->willReturn([
            new TenantLocale(new Locale('pl_PL', 'pl_PL', null, 'pl')),
            new TenantLocale(new Locale('en_US', 'en_US', null, 'en')),
            new TenantLocale(new Locale('en_GB', 'en_GB', null, 'en')), // same language → deduped
        ]);
        $enum = new ScopeEnumerator($tenantLocales, $this->createStub(ChannelRepositoryInterface::class));

        self::assertSame(['pl', 'en'], $enum->localeShortCodes(new Tenant('demo', 'Demo')));
    }

    #[Test]
    public function channelIdsByCodeMapsCodeToRfc4122Id(): void
    {
        $allegro = Uuid::v7();
        $shopify = Uuid::v7();
        $channels = $this->createStub(ChannelRepositoryInterface::class);
        $channels->method('findAllForTenant')->willReturn([
            new Channel('allegro', ['pl' => 'Allegro'], $allegro),
            new Channel('shopify', ['pl' => 'Shopify'], $shopify),
        ]);
        $enum = new ScopeEnumerator($this->createStub(TenantLocaleRepositoryInterface::class), $channels);

        self::assertSame(
            ['allegro' => $allegro->toRfc4122(), 'shopify' => $shopify->toRfc4122()],
            $enum->channelIdsByCode(new Tenant('demo', 'Demo')),
        );
    }
}
