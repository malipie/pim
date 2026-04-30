<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Entity\AssociationType;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Unit guard for {@see AssociationType} (0.11.6 — coverage gap).
 *
 * Asserts the invariants that schema-edit flows rely on:
 *   - id auto-allocated unless caller pins it (UUID v7 for time-sortable
 *     PKs)
 *   - tenant assignment is single-shot — re-stamping must throw
 *   - label / position are mutable through dedicated setters only
 */
final class AssociationTypeTest extends TestCase
{
    #[Test]
    public function generatesUuidV7WhenIdIsNotProvided(): void
    {
        $type = new AssociationType('cross_sell', ['en' => 'Cross-sell']);

        // UUID v7 is time-sortable; assert the constructor allocated one.
        self::assertInstanceOf(UuidV7::class, $type->getId());
    }

    #[Test]
    public function preservesExplicitIdWhenProvided(): void
    {
        $id = Uuid::v7();
        $type = new AssociationType('cross_sell', ['en' => 'Cross-sell'], 0, $id);

        self::assertTrue($id->equals($type->getId()));
    }

    #[Test]
    public function tenantIsNullUntilAssigned(): void
    {
        $type = new AssociationType('cross_sell', ['en' => 'Cross-sell']);
        self::assertNull($type->getTenant());
    }

    #[Test]
    public function assignTenantStampsItOnce(): void
    {
        $tenant = new Tenant('demo', 'Demo Tenant');
        $type = new AssociationType('cross_sell', ['en' => 'Cross-sell']);

        $type->assignTenant($tenant);

        self::assertSame($tenant, $type->getTenant());
    }

    #[Test]
    public function reassignTenantThrowsToProtectInvariant(): void
    {
        $type = new AssociationType('cross_sell', ['en' => 'Cross-sell']);
        $type->assignTenant(new Tenant('demo', 'Demo Tenant'));

        $this->expectException(LogicException::class);
        $type->assignTenant(new Tenant('acme', 'Acme'));
    }

    #[Test]
    public function renameReplacesLabelButLeavesCodeAndPositionAlone(): void
    {
        $type = new AssociationType('cross_sell', ['en' => 'Cross-sell'], 5);

        $type->rename(['en' => 'Recommended', 'pl' => 'Polecane']);

        self::assertSame(['en' => 'Recommended', 'pl' => 'Polecane'], $type->getLabel());
        self::assertSame('cross_sell', $type->getCode());
        self::assertSame(5, $type->getPosition());
    }

    #[Test]
    public function reorderUpdatesPosition(): void
    {
        $type = new AssociationType('cross_sell', ['en' => 'Cross-sell'], 0);

        $type->reorder(42);

        self::assertSame(42, $type->getPosition());
    }
}
