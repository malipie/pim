<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Application\Query\GetObjectFormSchema\GetObjectFormSchemaHandler;
use App\Catalog\Application\Query\GetObjectFormSchema\GetObjectFormSchemaQuery;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\CategoryAttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration coverage for UI-08.4 (#259) — domain resolver + cached
 * form-schema query handler + Doctrine cache invalidator.
 */
final class EffectiveAttributeGroupResolverTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $em = $this->em();
        $this->tenant = new Tenant('demo', 'Demo');
        $em->persist($this->tenant);
        $em->flush();
        $this->tenantContext()->set($this->tenant);

        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($this->tenant);
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($this->tenant);
    }

    #[Test]
    public function builtInProductReturnsAuditGroupOnly(): void
    {
        $product = $this->makeProduct('SKU-001');

        $groups = $this->resolver()->resolve($product);

        self::assertCount(1, $groups);
        self::assertSame('audit', $groups[0]->getCode());
        self::assertTrue($groups[0]->isSystemGroup());
    }

    #[Test]
    public function customServiceObjectTypeWithGlobalAndCategoryGroupsResolvesUniqueSet(): void
    {
        $em = $this->em();

        $service = new ObjectType('service', ObjectKind::Custom, ['en' => 'Service']);
        $em->persist($service);
        $em->flush();

        $marketing = $this->makeGroup('marketing', 'Marketing');
        $logistics = $this->makeGroup('logistics', 'Logistics');
        $em->persist(new ObjectTypeAttributeGroup($service, $marketing, position: 1));
        $em->persist(new ObjectTypeAttributeGroup($service, $logistics, position: 2));

        $rootCat = $this->makeCategory('lekarz', 'lekarz');
        $childCat = $this->makeCategory('chirurg', 'lekarz.chirurg', parent: $rootCat);
        $leafCat = $this->makeCategory('ortopeda', 'lekarz.chirurg.ortopeda', parent: $childCat);

        $medicalReqs = $this->makeGroup('medical-requirements', 'Medical');
        $surgicalReqs = $this->makeGroup('surgical', 'Surgical');
        $orthoReqs = $this->makeGroup('orthopedics', 'Orthopedics');
        $em->persist(new CategoryAttributeGroup($rootCat->getId(), $service, $medicalReqs, 1));
        $em->persist(new CategoryAttributeGroup($childCat->getId(), $service, $surgicalReqs, 1));
        $em->persist(new CategoryAttributeGroup($leafCat->getId(), $service, $orthoReqs, 1));
        $em->flush();
        $em->clear();

        $em = $this->em();
        $reloadedLeaf = $em->find(CatalogObject::class, $leafCat->getId());
        self::assertNotNull($reloadedLeaf);

        $reloadedService = self::getContainer()->get(ObjectTypeRepositoryInterface::class)->findByCode('service', $this->tenant);
        self::assertNotNull($reloadedService);

        // Preview: groups visible when CREATING a service under the
        // ortopeda leaf — global (marketing/logistics) + ancestor chain
        // groups (medical/surgical/ortho).
        $groups = $this->resolver()->resolveForCategoryPreview($reloadedService, $reloadedLeaf);

        $codes = array_map(static fn (AttributeGroup $g): string => $g->getCode(), $groups);
        self::assertContains('marketing', $codes);
        self::assertContains('logistics', $codes);
        self::assertContains('medical-requirements', $codes);
        self::assertContains('surgical', $codes);
        self::assertContains('orthopedics', $codes);
        // No duplicates even though ortopeda is a leaf — order is global → root → leaf.
        self::assertSame(\count($codes), \count(array_unique($codes)));
    }

    #[Test]
    public function formSchemaHandlerReturnsAuditGroupWithFourSystemAttributesForBuiltInProduct(): void
    {
        $product = $this->makeProduct('SKU-002');

        $handler = self::getContainer()->get(GetObjectFormSchemaHandler::class);
        $schema = $handler(new GetObjectFormSchemaQuery($product->getId()));

        self::assertNotNull($schema);
        self::assertSame('product', $schema->objectType['kind']);
        self::assertCount(1, $schema->effectiveGroups);
        $audit = $schema->effectiveGroups[0];
        self::assertSame('audit', $audit['code']);
        self::assertTrue($audit['is_system_group']);
        $attributes = $audit['attributes'];
        self::assertIsArray($attributes);
        self::assertCount(4, $attributes);
        $codes = [];
        foreach ($attributes as $attribute) {
            self::assertIsArray($attribute);
            self::assertIsString($attribute['code']);
            $codes[] = $attribute['code'];
        }
        sort($codes);
        self::assertSame(['created_at', 'created_by', 'updated_at', 'updated_by'], $codes);
    }

    #[Test]
    public function formSchemaHandlerReturnsNullForUnknownObjectId(): void
    {
        $handler = self::getContainer()->get(GetObjectFormSchemaHandler::class);
        $schema = $handler(new GetObjectFormSchemaQuery(\Symfony\Component\Uid\Uuid::v7()));

        self::assertNull($schema);
    }

    #[Test]
    public function cacheInvalidatesWhenAttributeGroupAttachmentChanges(): void
    {
        $product = $this->makeProduct('SKU-003');
        $handler = self::getContainer()->get(GetObjectFormSchemaHandler::class);

        $first = $handler(new GetObjectFormSchemaQuery($product->getId()));
        self::assertNotNull($first);
        self::assertCount(1, $first->effectiveGroups);

        // Attach a fresh group to the product's ObjectType — the listener
        // must invalidate the cache so the next read sees the new group.
        $em = $this->em();
        $extra = $this->makeGroup('marketing', 'Marketing');
        $em->persist(new ObjectTypeAttributeGroup($product->getObjectType(), $extra, position: 5));
        $em->flush();

        $second = $handler(new GetObjectFormSchemaQuery($product->getId()));
        self::assertNotNull($second);
        self::assertCount(2, $second->effectiveGroups, 'Cache should invalidate after junction change.');
    }

    private function makeProduct(string $code): CatalogObject
    {
        $em = $this->em();
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)->findBuiltInByKind(ObjectKind::Product, $this->tenant);
        \assert(null !== $type);
        $product = new CatalogObject($type, $code);
        $em->persist($product);
        $em->flush();

        return $product;
    }

    private function makeCategory(string $code, string $path, ?CatalogObject $parent = null): CatalogObject
    {
        $em = $this->em();
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)->findBuiltInByKind(ObjectKind::Category, $this->tenant);
        \assert(null !== $type);
        $category = new CatalogObject($type, $code);
        $category->attachToPath($path);
        if (null !== $parent) {
            $category->assignParent($parent);
        }
        $em->persist($category);
        $em->flush();

        return $category;
    }

    private function makeGroup(string $code, string $label): AttributeGroup
    {
        $em = $this->em();
        $group = new AttributeGroup($code, ['en' => $label]);
        $em->persist($group);

        // Each non-system group also gets a single sample attribute so
        // form-schema projections have something to render — keeps the
        // assertions on attribute counts honest.
        $attribute = new Attribute($code.'_field', ['en' => $label.' field'], AttributeType::Text);
        $em->persist($attribute);
        $em->flush();
        $em->persist(new AttributeGroupAttribute($group, $attribute, 1));
        $em->flush();

        return $group;
    }

    private function resolver(): EffectiveAttributeGroupResolver
    {
        return self::getContainer()->get(EffectiveAttributeGroupResolver::class);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }
}
