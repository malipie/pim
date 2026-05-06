<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Enum\ImportImageSource;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ImportProfileTest extends TestCase
{
    #[Test]
    public function constructorDefaults(): void
    {
        $profile = $this->makeProfile();

        self::assertSame('Festo Q2 2026', $profile->getName());
        self::assertSame([], $profile->getColumnMapping());
        self::assertSame(ImportImageSource::None, $profile->getImageSource());
        self::assertNull($profile->getLastUsedAt());
        self::assertNull($profile->getLocale());
        self::assertNull($profile->getCustomValidationRules());
    }

    #[Test]
    public function renamingChangesName(): void
    {
        $profile = $this->makeProfile();
        $profile->rename('Festo Q3 2026');

        self::assertSame('Festo Q3 2026', $profile->getName());
    }

    #[Test]
    public function columnMappingRoundTrips(): void
    {
        $profile = $this->makeProfile();
        $profile->setColumnMapping([
            'Kod produktu' => 'sku',
            'Nazwa' => 'name',
            'Cena netto' => 'price',
            'Stara cena' => 'skip',
        ]);

        self::assertSame('sku', $profile->getColumnMapping()['Kod produktu']);
        self::assertSame('skip', $profile->getColumnMapping()['Stara cena']);
    }

    #[Test]
    public function imageSourceTogglesEnum(): void
    {
        $profile = $this->makeProfile();
        $profile->setImageSource(ImportImageSource::Zip);

        self::assertSame(ImportImageSource::Zip, $profile->getImageSource());
    }

    #[Test]
    public function touchLastUsedStampsTimestamp(): void
    {
        $profile = $this->makeProfile();
        self::assertNull($profile->getLastUsedAt());

        $profile->touchLastUsed();

        self::assertNotNull($profile->getLastUsedAt());
    }

    #[Test]
    public function tenantCannotBeReassigned(): void
    {
        $profile = $this->makeProfile();
        $profile->assignTenant(new Tenant('demo-1', 'Demo 1'));

        $this->expectException(LogicException::class);
        $profile->assignTenant(new Tenant('demo-2', 'Demo 2'));
    }

    private function makeProfile(): ImportProfile
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);

        return new ImportProfile(
            userId: Uuid::v7(),
            name: 'Festo Q2 2026',
            targetObjectType: $type,
        );
    }
}
