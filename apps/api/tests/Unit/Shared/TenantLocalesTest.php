<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Exception\CannotDisablePrimaryLocaleException;
use App\Shared\Domain\Exception\InvalidLocaleException;
use App\Shared\Domain\Exception\LocaleNotEnabledException;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * VIEW-01 (#372) — locale lifecycle on the Tenant aggregate, mirroring
 * the WorkspaceController's invariants.
 */
final class TenantLocalesTest extends TestCase
{
    #[Test]
    public function defaultLocalesArePolishAndEnglish(): void
    {
        $tenant = new Tenant('demo', 'Demo');

        self::assertSame(['pl', 'en'], $tenant->getEnabledLocales());
        self::assertSame('pl', $tenant->getPrimaryLocale());
        self::assertTrue($tenant->isLocaleEnabled('pl'));
        self::assertTrue($tenant->isLocaleEnabled('en'));
        self::assertFalse($tenant->isLocaleEnabled('de'));
    }

    #[Test]
    public function enableLocaleAppendsAndIsIdempotent(): void
    {
        $tenant = new Tenant('demo', 'Demo');

        $tenant->enableLocale('de');
        $tenant->enableLocale('de'); // idempotent

        self::assertSame(['pl', 'en', 'de'], $tenant->getEnabledLocales());
    }

    #[Test]
    public function enableLocaleRejectsCodesOutsideLibrary(): void
    {
        $tenant = new Tenant('demo', 'Demo');

        $this->expectException(InvalidLocaleException::class);
        $tenant->enableLocale('zz');
    }

    #[Test]
    public function disableLocaleRemovesNonPrimary(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $tenant->enableLocale('de');

        $tenant->disableLocale('en');

        self::assertSame(['pl', 'de'], $tenant->getEnabledLocales());
    }

    #[Test]
    public function disableLocaleRefusesToRemovePrimary(): void
    {
        $tenant = new Tenant('demo', 'Demo');

        $this->expectException(CannotDisablePrimaryLocaleException::class);
        $tenant->disableLocale('pl');
    }

    #[Test]
    public function changePrimaryLocaleSwitchesAfterEnable(): void
    {
        $tenant = new Tenant('demo', 'Demo');

        $tenant->changePrimaryLocale('en');

        self::assertSame('en', $tenant->getPrimaryLocale());
    }

    #[Test]
    public function changePrimaryLocaleRefusesIfLocaleNotEnabled(): void
    {
        $tenant = new Tenant('demo', 'Demo');

        $this->expectException(LocaleNotEnabledException::class);
        $tenant->changePrimaryLocale('de');
    }
}
