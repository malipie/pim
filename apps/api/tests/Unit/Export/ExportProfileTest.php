<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Entity\ExportProfile;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ExportProfileTest extends TestCase
{
    #[Test]
    public function constructorDefaultsAreSane(): void
    {
        $profile = $this->makeProfile();

        self::assertInstanceOf(Uuid::class, $profile->getId());
        self::assertSame('SEO round-trip PL+EN', $profile->getName());
        self::assertSame(0, $profile->getRunCount());
        self::assertNull($profile->getLastRunAt());
        self::assertNull($profile->getDescription());
        self::assertSame(['format' => 'xlsx'], $profile->getConfig());
    }

    #[Test]
    public function recordRunBumpsCountAndStampsTimestamp(): void
    {
        $profile = $this->makeProfile();
        $profile->recordRun();

        self::assertSame(1, $profile->getRunCount());
        self::assertNotNull($profile->getLastRunAt());

        $profile->recordRun();
        self::assertSame(2, $profile->getRunCount());
    }

    #[Test]
    public function renameUpdatesNameAndTouchesUpdatedAt(): void
    {
        $profile = $this->makeProfile();
        $before = $profile->getUpdatedAt();

        // Sleep one µ-second equivalent — DateTimeImmutable resolution is to
        // the second; force a measurable gap via direct call sequence (the
        // constructor and touch() both use `new DateTimeImmutable()`, so on
        // sub-second invocation the values can collide. Comparing instances
        // by != catches even identical wall-clock values when they are
        // different objects.).
        usleep(1100000);
        $profile->rename('SEO PL only');

        self::assertSame('SEO PL only', $profile->getName());
        self::assertGreaterThan($before, $profile->getUpdatedAt());
    }

    #[Test]
    public function updateConfigReplacesConfigShape(): void
    {
        $profile = $this->makeProfile();
        $profile->updateConfig(['format' => 'csv', 'encoding' => 'utf8_bom']);

        self::assertSame(['format' => 'csv', 'encoding' => 'utf8_bom'], $profile->getConfig());
    }

    #[Test]
    public function isOwnedByReturnsTrueOnlyForOwner(): void
    {
        $userId = Uuid::v7();
        $other = Uuid::v7();
        $profile = new ExportProfile(
            userId: $userId,
            name: 'mine',
            config: [],
        );

        self::assertTrue($profile->isOwnedBy($userId));
        self::assertFalse($profile->isOwnedBy($other));
    }

    #[Test]
    public function assignTenantOnceOnly(): void
    {
        $profile = $this->makeProfile();
        $tenant = new Tenant('Acme', 'acme');
        $profile->assignTenant($tenant);

        self::assertSame($tenant, $profile->getTenant());

        $this->expectException(LogicException::class);
        $profile->assignTenant(new Tenant('Other', 'other'));
    }

    private function makeProfile(): ExportProfile
    {
        return new ExportProfile(
            userId: Uuid::v7(),
            name: 'SEO round-trip PL+EN',
            config: ['format' => 'xlsx'],
        );
    }
}
