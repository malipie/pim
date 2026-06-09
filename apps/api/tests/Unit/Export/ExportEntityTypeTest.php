<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Entity\ExportProfile;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportTargetScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * EXR-04 (#1380) — ExportEntityType enum semantics + entity wiring.
 */
final class ExportEntityTypeTest extends TestCase
{
    #[Test]
    public function onlyCustomModuleRequiresAnObjectType(): void
    {
        self::assertTrue(ExportEntityType::CustomModule->requiresObjectType());

        foreach ([
            ExportEntityType::Product,
            ExportEntityType::ModuleSchema,
            ExportEntityType::AttributesGroups,
            ExportEntityType::Categories,
        ] as $type) {
            self::assertFalse($type->requiresObjectType(), $type->value.' must forbid object_type_id');
        }
    }

    #[Test]
    public function catalogBackedTypesSupportScopeWhileStructuralTypesDoNot(): void
    {
        self::assertTrue(ExportEntityType::Product->supportsScopeAndFilter());
        self::assertTrue(ExportEntityType::CustomModule->supportsScopeAndFilter());
        self::assertFalse(ExportEntityType::Product->isStructural());
        self::assertFalse(ExportEntityType::CustomModule->isStructural());

        foreach ([
            ExportEntityType::ModuleSchema,
            ExportEntityType::AttributesGroups,
            ExportEntityType::Categories,
        ] as $type) {
            self::assertFalse($type->supportsScopeAndFilter(), $type->value.' must not support scope/filter');
            self::assertTrue($type->isStructural(), $type->value.' must be structural');
        }
    }

    #[Test]
    public function onlyProductIsExecutableInExr04(): void
    {
        self::assertTrue(ExportEntityType::Product->isExecutable());

        foreach ([
            ExportEntityType::CustomModule,
            ExportEntityType::ModuleSchema,
            ExportEntityType::AttributesGroups,
            ExportEntityType::Categories,
        ] as $type) {
            self::assertFalse($type->isExecutable(), $type->value.' is not runnable until EXR-05/06');
        }
    }

    #[Test]
    public function sessionDefaultsToProductAndCarriesObjectType(): void
    {
        $session = new ExportSession(
            userId: Uuid::v7(),
            source: ExportSource::ListContext,
            format: ExportFormat::Csv,
            targetScope: ExportTargetScope::All,
            selectedColumns: ['sku'],
        );
        self::assertSame(ExportEntityType::Product, $session->getEntityType());
        self::assertNull($session->getObjectTypeId());

        $objectTypeId = Uuid::v7();
        $custom = new ExportSession(
            userId: Uuid::v7(),
            source: ExportSource::CentralTab,
            format: ExportFormat::Xlsx,
            targetScope: ExportTargetScope::All,
            selectedColumns: ['sku'],
            entityType: ExportEntityType::CustomModule,
            objectTypeId: $objectTypeId,
        );
        self::assertSame(ExportEntityType::CustomModule, $custom->getEntityType());
        self::assertTrue($objectTypeId->equals($custom->getObjectTypeId()));
    }

    #[Test]
    public function profileDefaultsToProductAndReclassifies(): void
    {
        $profile = new ExportProfile(
            userId: Uuid::v7(),
            name: 'Default',
            config: ['selected_columns' => ['sku']],
        );
        self::assertSame(ExportEntityType::Product, $profile->getEntityType());
        self::assertNull($profile->getObjectTypeId());

        $objectTypeId = Uuid::v7();
        $profile->reclassify(ExportEntityType::CustomModule, $objectTypeId);
        self::assertSame(ExportEntityType::CustomModule, $profile->getEntityType());
        self::assertTrue($objectTypeId->equals($profile->getObjectTypeId()));

        $profile->reclassify(ExportEntityType::ModuleSchema, null);
        self::assertSame(ExportEntityType::ModuleSchema, $profile->getEntityType());
        self::assertNull($profile->getObjectTypeId());
    }
}
