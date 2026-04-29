<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\ApiPlatform;

use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\ApiPlatform\ObjectKindRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ObjectKindRouterTest extends TestCase
{
    private ObjectKindRouter $router;

    protected function setUp(): void
    {
        $this->router = new ObjectKindRouter();
    }

    #[Test]
    public function pathForBuiltInKindsMatchesSugarConvention(): void
    {
        self::assertSame('/api/products', $this->router->pathFor(ObjectKind::Product));
        self::assertSame('/api/categories', $this->router->pathFor(ObjectKind::Category));
        self::assertSame('/api/assets', $this->router->pathFor(ObjectKind::Asset));
    }

    #[Test]
    public function pathForCustomKindThrows(): void
    {
        $this->expectException(DisabledFeatureException::class);
        $this->router->pathFor(ObjectKind::Custom);
    }

    #[Test]
    public function groupsForBuiltInKindIncludesSharedAndPerKindGroup(): void
    {
        self::assertSame(['object:read', 'object:read:product'], $this->router->groupsFor(ObjectKind::Product));
        self::assertSame(['object:read', 'object:read:category'], $this->router->groupsFor(ObjectKind::Category));
        self::assertSame(['object:read', 'object:read:asset'], $this->router->groupsFor(ObjectKind::Asset));
    }

    #[Test]
    public function groupsForCustomKindFallsBackToSharedGroupOnly(): void
    {
        self::assertSame(['object:read'], $this->router->groupsFor(ObjectKind::Custom));
    }

    #[Test]
    public function builtInKindsListMatchesAdr009(): void
    {
        self::assertSame(
            [ObjectKind::Product, ObjectKind::Category, ObjectKind::Asset],
            ObjectKindRouter::builtInKinds(),
        );
    }
}
