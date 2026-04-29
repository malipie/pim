<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Identity\Application\TenantContext;
use App\Identity\Application\TenantScoped;
use App\Identity\Domain\Entity\Tenant;
use App\Identity\Infrastructure\Doctrine\EventListener\TenantAssignmentListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TenantAssignmentListenerTest extends TestCase
{
    private TenantContext $tenantContext;
    private TenantAssignmentListener $listener;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->tenantContext = new TenantContext();
        $this->listener = new TenantAssignmentListener($this->tenantContext);
        // The listener never calls into the EntityManager so a passive stub is enough
        // and avoids PHPUnit's "no expectations configured" notice.
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
    }

    #[Test]
    public function itStampsTenantOnAFreshlyCreatedCatalogObject(): void
    {
        $tenant = new Tenant('demo', 'Demo Tenant');
        $this->tenantContext->set($tenant);

        $object = $this->makeProductObject('SKU-001');

        $this->listener->prePersist(new PrePersistEventArgs($object, $this->entityManager));

        self::assertSame($tenant, $object->getTenant(), 'Listener must inject the current tenant on prePersist.');
    }

    #[Test]
    public function itThrowsWhenNoTenantIsSet(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('without a current tenant');

        $object = $this->makeProductObject('SKU-002');

        $this->listener->prePersist(new PrePersistEventArgs($object, $this->entityManager));
    }

    #[Test]
    public function itDoesNotOverwriteAnAlreadyAssignedTenant(): void
    {
        $existingTenant = new Tenant('demo', 'Demo Tenant');
        $object = $this->makeProductObject('SKU-003');
        $object->assignTenant($existingTenant);

        $this->tenantContext->set(new Tenant('acme', 'Acme Industries'));

        $this->listener->prePersist(new PrePersistEventArgs($object, $this->entityManager));

        self::assertSame($existingTenant, $object->getTenant(), 'Listener must respect a tenant already set by the caller.');
    }

    #[Test]
    public function itIgnoresEntitiesThatAreNotTenantScoped(): void
    {
        $this->tenantContext->set(new Tenant('demo', 'Demo Tenant'));

        $unrelated = new stdClass();

        $this->listener->prePersist(new PrePersistEventArgs($unrelated, $this->entityManager));

        // No exception, no side-effect — assertion is the lack of failure.
        self::assertTrue(true);
    }

    #[Test]
    public function itStampsAnyTenantScopedEntityNotJustCatalogObject(): void
    {
        $tenant = new Tenant('demo', 'Demo Tenant');
        $this->tenantContext->set($tenant);

        // An anonymous class implementing TenantScoped is enough to prove
        // generalisation — Sprint-0 hard-coded `instanceof Product`, #30
        // dispatches by interface, so any future domain entity (Channel,
        // Asset in epic 0.3) will be picked up the same way.
        $entity = new class implements TenantScoped {
            private ?Tenant $tenant = null;

            public function getTenant(): ?Tenant
            {
                return $this->tenant;
            }

            public function assignTenant(Tenant $tenant): void
            {
                $this->tenant = $tenant;
            }
        };

        $this->listener->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertSame($tenant, $entity->getTenant(), 'Listener must dispatch by TenantScoped interface, not FQCN.');
    }

    private function makeProductObject(string $code): CatalogObject
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);

        return new CatalogObject($type, $code);
    }
}
