<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Import\Application\Service\Structural\AttributeGroupImportCreator;
use App\Import\Application\Service\Structural\StructuralImportRowResult;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Structural attribute-group import — the mirror of the `attribute_groups`
 * export. Exercises {@see AttributeGroupImportCreator} directly so the core
 * upsert + object-type attachment behaviour is covered without HTTP/MinIO.
 */
final class StructuralAttributeGroupImportTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);
        foreach ([['pl_PL', 'Polski', 'pl'], ['en_US', 'English', 'en']] as [$code, $label, $lang]) {
            $locale = new Locale($code, $label, null, $lang);
            $this->em()->persist($locale);
            $this->em()->persist(new TenantLocale($locale));
        }
        $this->em()->flush();
    }

    #[Test]
    public function createsNewGroupWithMetadataAndAttachesObjectType(): void
    {
        $tenant = $this->tenant();
        $module = $this->customObjectType($tenant, 'widgets');

        $result = $this->creator()->create(2, [
            'code' => 'marketing',
            'label.pl' => 'Marketing',
            'label.en' => 'Marketing',
            'description.pl' => 'Treści',
            'icon' => 'megaphone',
            'color' => '#ff0000',
            'is_required_section' => 'true',
            'is_shared' => 'true',
            'position' => '3',
            'object_types' => 'widgets',
        ], $tenant);

        self::assertSame(StructuralImportRowResult::OUTCOME_CREATED, $result->outcome);

        $group = $this->groupRepo()->findByCode('marketing', $tenant);
        self::assertInstanceOf(AttributeGroup::class, $group);
        self::assertSame(['pl' => 'Marketing', 'en' => 'Marketing'], $group->getLabel());
        self::assertSame('megaphone', $group->getIcon());
        self::assertSame('#ff0000', $group->getColor());
        self::assertTrue($group->isRequiredSection());
        self::assertSame(3, $group->getPosition());
        self::assertSame(1, $this->junctionCount($module, $group));
    }

    #[Test]
    public function reimportIsIdempotentAndReportsUpdate(): void
    {
        $tenant = $this->tenant();
        $module = $this->customObjectType($tenant, 'widgets');

        $cells = ['code' => 'seo', 'label.en' => 'SEO', 'object_types' => 'widgets'];
        $first = $this->creator()->create(2, $cells, $tenant);
        self::assertSame(StructuralImportRowResult::OUTCOME_CREATED, $first->outcome);

        $second = $this->creator()->create(2, $cells, $tenant);
        self::assertSame(StructuralImportRowResult::OUTCOME_UPDATED, $second->outcome);

        $group = $this->groupRepo()->findByCode('seo', $tenant);
        self::assertInstanceOf(AttributeGroup::class, $group);
        self::assertSame(1, $this->junctionCount($module, $group), 're-import must not duplicate the junction');
    }

    #[Test]
    public function unknownObjectTypeWarnsButStillImportsTheGroup(): void
    {
        $tenant = $this->tenant();

        $result = $this->creator()->create(2, [
            'code' => 'logistics',
            'label.en' => 'Logistics',
            'object_types' => 'ghost_module',
        ], $tenant);

        self::assertSame(StructuralImportRowResult::OUTCOME_CREATED, $result->outcome);
        self::assertInstanceOf(AttributeGroup::class, $this->groupRepo()->findByCode('logistics', $tenant));
        self::assertNotEmpty($result->logs);
        self::assertStringContainsString('ghost_module', $result->logs[0]['message']);
    }

    #[Test]
    public function emptyCellsLeaveExistingValuesUntouchedOnUpdate(): void
    {
        $tenant = $this->tenant();

        $this->creator()->create(2, ['code' => 'specs', 'label.en' => 'Specs', 'icon' => 'ruler'], $tenant);
        // Re-import with no icon column at all — icon must be preserved.
        $this->creator()->create(2, ['code' => 'specs', 'label.en' => 'Specifications'], $tenant);

        $group = $this->groupRepo()->findByCode('specs', $tenant);
        self::assertInstanceOf(AttributeGroup::class, $group);
        self::assertSame('ruler', $group->getIcon(), 'empty/absent icon cell must not clear the value');
        self::assertSame(['en' => 'Specifications'], $group->getLabel());
    }

    private function creator(): AttributeGroupImportCreator
    {
        return self::getContainer()->get(AttributeGroupImportCreator::class);
    }

    private function groupRepo(): AttributeGroupRepositoryInterface
    {
        return self::getContainer()->get(AttributeGroupRepositoryInterface::class);
    }

    private function junctionCount(ObjectType $objectType, AttributeGroup $group): int
    {
        return (int) $this->em()->createQuery(
            'SELECT COUNT(j.position) FROM '.ObjectTypeAttributeGroup::class.' j WHERE j.objectType = :ot AND j.attributeGroup = :g',
        )->setParameter('ot', $objectType)->setParameter('g', $group)->getSingleScalarResult();
    }

    private function customObjectType(Tenant $tenant, string $code): ObjectType
    {
        $module = new ObjectType($code, ObjectKind::Custom, ['pl' => $code, 'en' => $code]);
        $module->assignTenant($tenant);
        $this->em()->persist($module);
        $this->em()->flush();

        return $module;
    }

    private function tenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        return $tenant;
    }
}
