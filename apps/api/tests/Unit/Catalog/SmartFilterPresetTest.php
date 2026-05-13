<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Entity\SmartFilterPreset;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-09 (#535) — invariants for SmartFilterPreset entity:
 *   - built-in presets cannot be mutated or assigned to a tenant.
 *   - user-defined presets can be renamed / re-iconed / re-queried.
 *   - ownership check (isOwnedBy) is identity-aware.
 */
final class SmartFilterPresetTest extends TestCase
{
    public function testConstructDefaultsToTimestampsAndSortOrderZero(): void
    {
        $preset = new SmartFilterPreset(
            slug: 'my-festo-low',
            name: ['pl' => 'Festo niski stock', 'en' => 'Festo low stock'],
            icon: '⚙️',
            query: ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
            userId: Uuid::v7(),
        );

        self::assertFalse($preset->isBuiltIn());
        self::assertSame(0, $preset->getSortOrder());
        self::assertEquals($preset->getCreatedAt(), $preset->getUpdatedAt());
        self::assertFalse($preset->isSystem());
    }

    public function testBuiltInPresetIsSystemAndImmutable(): void
    {
        $preset = new SmartFilterPreset(
            slug: 'red-low-completeness',
            name: ['pl' => 'Czerwone (<50%)', 'en' => 'Red (<50%)'],
            icon: '🔴',
            query: ['attr' => 'completeness_pct', 'op' => '<', 'value' => 50],
            userId: null,
            isBuiltIn: true,
        );

        self::assertTrue($preset->isBuiltIn());
        self::assertTrue($preset->isSystem());
        self::assertFalse($preset->isTenantShared());

        $this->expectException(LogicException::class);
        $preset->rename(['pl' => 'X', 'en' => 'X']);
    }

    public function testBuiltInPresetSilentlyIgnoresTenantAssignment(): void
    {
        // The shared TenantAssignmentListener fires for every TenantScoped
        // entity; built-in presets stay system-shipped (tenant_id NULL)
        // by silently dropping the assignment instead of throwing — so a
        // single listener works for both lanes.
        $preset = new SmartFilterPreset(
            slug: 'red',
            name: ['pl' => 'X', 'en' => 'X'],
            icon: '🔴',
            query: ['attr' => 'completeness_pct', 'op' => '<', 'value' => 50],
            userId: null,
            isBuiltIn: true,
        );
        $tenant = new Tenant('demo', 'Demo');

        $preset->assignTenant($tenant);

        self::assertNull($preset->getTenant());
        self::assertTrue($preset->isSystem());
    }

    public function testUserDefinedPresetMutators(): void
    {
        $userId = Uuid::v7();
        $preset = new SmartFilterPreset(
            slug: 'my-preset',
            name: ['pl' => 'Mój preset', 'en' => 'My preset'],
            icon: '🛠️',
            query: ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
            userId: $userId,
        );

        $createdAt = $preset->getCreatedAt();
        usleep(1_500); // ensure updatedAt clock tick on systems with µs resolution

        $preset->rename(['pl' => 'Nowy', 'en' => 'New']);
        $preset->changeIcon('⚡');
        $preset->updateQuery(['attr' => 'family', 'op' => '=', 'value' => 'Czujniki']);
        $preset->reorder(42);
        $preset->changeSlug('renamed');

        self::assertSame(['pl' => 'Nowy', 'en' => 'New'], $preset->getName());
        self::assertSame('⚡', $preset->getIcon());
        self::assertSame('renamed', $preset->getSlug());
        self::assertSame(42, $preset->getSortOrder());
        self::assertSame($createdAt, $preset->getCreatedAt());
        self::assertGreaterThanOrEqual($createdAt, $preset->getUpdatedAt());
    }

    public function testOwnershipIsIdentityAware(): void
    {
        $owner = Uuid::v7();
        $other = Uuid::v7();
        $preset = new SmartFilterPreset(
            slug: 'mine',
            name: ['pl' => 'Mój', 'en' => 'Mine'],
            icon: '🔧',
            query: ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
            userId: $owner,
        );

        self::assertTrue($preset->isOwnedBy($owner));
        self::assertFalse($preset->isOwnedBy($other));
    }

    public function testTenantSharedPresetHasTenantWithoutUser(): void
    {
        $preset = new SmartFilterPreset(
            slug: 'shared',
            name: ['pl' => 'Shared', 'en' => 'Shared'],
            icon: '👥',
            query: ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
            userId: null,
            isBuiltIn: false,
        );
        $tenant = new Tenant('demo', 'Demo');
        $preset->assignTenant($tenant);

        self::assertFalse($preset->isSystem());
        self::assertTrue($preset->isTenantShared());
        self::assertNull($preset->getUserId());
    }
}
