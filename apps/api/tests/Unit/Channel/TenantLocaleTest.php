<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Shared\Domain\Tenant;
use DomainException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TenantLocaleTest extends TestCase
{
    #[Test]
    public function defaultsAreInactiveTenantNonDefaultNonMandatory(): void
    {
        $locale = new Locale('pl_PL', 'Polski');

        $tenantLocale = new TenantLocale($locale);

        self::assertNull($tenantLocale->getTenant());
        self::assertSame($locale, $tenantLocale->getLocale());
        self::assertFalse($tenantLocale->isDefault());
        self::assertFalse($tenantLocale->isMandatory());
        self::assertNull($tenantLocale->getFallback());
        self::assertSame(0, $tenantLocale->getSortOrder());
        self::assertTrue($tenantLocale->isActive());
    }

    #[Test]
    public function defaultLocaleIsForcedMandatory(): void
    {
        $locale = new Locale('pl_PL', 'Polski');

        $tenantLocale = new TenantLocale($locale, isDefault: true);

        self::assertTrue($tenantLocale->isDefault());
        self::assertTrue($tenantLocale->isMandatory());
    }

    #[Test]
    public function assignTenantStampsAndRefusesReassignment(): void
    {
        $tenantLocale = new TenantLocale(new Locale('pl_PL', 'Polski'));
        $first = new Tenant('demo', 'Demo');
        $second = new Tenant('acme', 'Acme');

        $tenantLocale->assignTenant($first);
        self::assertSame($first, $tenantLocale->getTenant());

        $this->expectException(LogicException::class);
        $tenantLocale->assignTenant($second);
    }

    #[Test]
    public function defaultLocaleRefusesNonMandatoryDowngrade(): void
    {
        $tenantLocale = new TenantLocale(new Locale('pl_PL', 'Polski'), isDefault: true);

        $this->expectException(DomainException::class);
        $tenantLocale->setMandatory(false);
    }

    #[Test]
    public function selfFallbackIsRefused(): void
    {
        $pl = new Locale('pl_PL', 'Polski');
        $tenantLocale = new TenantLocale($pl);

        $this->expectException(DomainException::class);
        $tenantLocale->setFallback($pl);
    }

    #[Test]
    public function fallbackToDifferentLocaleSucceeds(): void
    {
        $en = new Locale('en_US', 'English');
        $de = new Locale('de_DE', 'Deutsch');
        $tenantLocale = new TenantLocale($de);

        $tenantLocale->setFallback($en);

        self::assertSame($en, $tenantLocale->getFallback());
    }

    #[Test]
    public function defaultLocaleRefusesDeactivation(): void
    {
        $tenantLocale = new TenantLocale(new Locale('pl_PL', 'Polski'), isDefault: true);

        $this->expectException(DomainException::class);
        $tenantLocale->deactivate();
    }

    #[Test]
    public function nonDefaultLocaleDeactivatesAndReactivates(): void
    {
        $tenantLocale = new TenantLocale(new Locale('de_DE', 'Deutsch'));

        $tenantLocale->deactivate();
        self::assertFalse($tenantLocale->isActive());

        $tenantLocale->reactivate();
        self::assertTrue($tenantLocale->isActive());
    }

    #[Test]
    public function markAsDefaultForcesMandatoryAndActive(): void
    {
        $tenantLocale = new TenantLocale(new Locale('de_DE', 'Deutsch'));
        $tenantLocale->deactivate();

        $tenantLocale->markAsDefault();

        self::assertTrue($tenantLocale->isDefault());
        self::assertTrue($tenantLocale->isMandatory());
        self::assertTrue($tenantLocale->isActive());
    }
}
