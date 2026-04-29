<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\ApiPlatform;

use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\ApiPlatform\CustomObjectTypeApiGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CustomObjectTypeApiGuardTest extends TestCase
{
    #[Test]
    public function builtInKindsAlwaysPass(): void
    {
        $guard = new CustomObjectTypeApiGuard(false);

        $guard->assertAllowed(ObjectKind::Product);
        $guard->assertAllowed(ObjectKind::Category);
        $guard->assertAllowed(ObjectKind::Asset);

        self::assertFalse($guard->isCustomKindEnabled());
    }

    #[Test]
    public function customKindIsRejectedWhenFlagOff(): void
    {
        $guard = new CustomObjectTypeApiGuard(false);

        $this->expectException(DisabledFeatureException::class);
        $this->expectExceptionMessage('disabled in MVP');
        $guard->assertAllowed(ObjectKind::Custom);
    }

    #[Test]
    public function customKindPassesWhenFlagOn(): void
    {
        $guard = new CustomObjectTypeApiGuard(true);

        $guard->assertAllowed(ObjectKind::Custom);

        self::assertTrue($guard->isCustomKindEnabled());
    }
}
