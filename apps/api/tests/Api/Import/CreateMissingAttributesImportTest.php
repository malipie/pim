<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Import\Application\Handler\ImportRunHandler;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * #1728 — the gated `createMissingOptions` flag also mints a missing Attribute
 * (type inferred) attached to the target ObjectType, so a mapped column with no
 * attribute in the model is created instead of dropped. Driven through the run
 * handler directly (no JWT/auth layer), like the option-create test.
 */
final class CreateMissingAttributesImportTest extends CatalogApiTestCase
{
    #[Test]
    public function flagOnMintsAttributesWithInferredTypeAndWritesValues(): void
    {
        $sessionId = $this->runImport(createMissingOptions: true, sku: 'CMA-1');

        $em = $this->em();
        self::getContainer()->get(TenantContext::class)->set($this->tenant());
        $session = $em->find(ImportSession::class, $sessionId);
        \assert($session instanceof ImportSession);
        self::assertSame(ImportSessionStatus::Success, $session->getStatus());
        self::assertSame(0, $session->getErrorCount());

        $conn = $em->getConnection();
        $textType = $conn->fetchOne("SELECT type FROM attributes WHERE code = 'symbol_producenta'");
        self::assertSame('text', $textType, 'non-numeric column → text attribute');
        $numberType = $conn->fetchOne("SELECT type FROM attributes WHERE code = 'obwod_cholewki'");
        self::assertSame('number', $numberType, 'all-numeric column → number attribute (inferred)');

        // Both minted attributes are attached to the target (product) ObjectType.
        $attached = $conn->fetchOne(
            'SELECT COUNT(*) FROM object_type_attributes ota '
            .'JOIN attributes a ON a.id = ota.attribute_id '
            .'JOIN object_types ot ON ot.id = ota.object_type_id '
            ."WHERE ot.kind = 'product' AND a.code IN ('symbol_producenta', 'obwod_cholewki')",
        );
        self::assertSame(2, (int) (\is_scalar($attached) ? $attached : 0), 'minted attributes attached to the ObjectType');

        $value = $conn->fetchOne(
            'SELECT ov.value::text FROM object_values ov '
            .'JOIN objects obj ON obj.id = ov.object_id '
            .'JOIN attributes a ON a.id = ov.attribute_id '
            ."WHERE obj.code = 'CMA-1' AND a.code = 'symbol_producenta'",
        );
        self::assertIsString($value);
        self::assertStringContainsString('KOW-ZAMSZ', $value, 'value written to the minted attribute');
    }

    #[Test]
    public function flagOffDoesNotMintAttributes(): void
    {
        $this->runImport(createMissingOptions: false, sku: 'CMA-2');

        $em = $this->em();
        self::getContainer()->get(TenantContext::class)->set($this->tenant());
        $count = $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM attributes WHERE code IN ('symbol_producenta', 'obwod_cholewki')",
        );
        self::assertSame(0, (int) (\is_scalar($count) ? $count : 0), 'no attribute minted when the flag is off');
    }

    private function runImport(bool $createMissingOptions, string $sku): Uuid
    {
        $tenant = $this->tenant();
        $em = $this->em();
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $product = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($product instanceof ObjectType);

        $session = new ImportSession(
            userId: Uuid::v7(),
            targetObjectType: $product,
            fileName: 'cma.csv',
            fileSizeBytes: 64,
        );
        $session->assignTenant($tenant);
        // symbol_producenta + obwod_cholewki do not exist in the model → minted
        // when the flag is on (text + number by inference).
        $session->setColumnMapping([
            'sku' => 'sku',
            'symbol_producenta' => 'symbol_producenta',
            'obwod_cholewki' => 'obwod_cholewki',
        ]);
        $session->setCreateMissingOptions($createMissingOptions);
        $em->persist($session);
        $em->flush();
        $sessionId = $session->getId();

        self::getContainer()->get('imports.storage')->write(
            \sprintf('%s/%s/cma.csv', $tenant->getId()->toRfc4122(), $sessionId->toRfc4122()),
            \sprintf("sku;symbol_producenta;obwod_cholewki\n%s;KOW-ZAMSZ-TAUPE;28\n", $sku),
        );

        self::getContainer()->get(TenantFilterConfigurator::class)->apply();
        self::getContainer()->get(ImportRunHandler::class)->run($session);
        $em->clear();

        return $sessionId;
    }

    private function tenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }
}
