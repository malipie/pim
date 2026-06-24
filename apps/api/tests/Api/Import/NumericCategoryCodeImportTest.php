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
 * Regression: a numeric `__category__` code (e.g. an IdoSell category id like
 * "1214553885") is coerced to an int PHP array key, which previously crashed
 * the whole run with a TypeError in CatalogObjectRepository::findByCode(int).
 * The run must instead complete — the unresolved category is a row warning.
 */
final class NumericCategoryCodeImportTest extends CatalogApiTestCase
{
    #[Test]
    public function numericCategoryCodeDoesNotCrashTheRun(): void
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
            fileName: 'numcat.csv',
            fileSizeBytes: 48,
        );
        $session->assignTenant($tenant);
        $session->setColumnMapping(['sku' => 'sku', 'kategoria' => '__category__']);
        $em->persist($session);
        $em->flush();
        $sessionId = $session->getId();

        self::getContainer()->get('imports.storage')->write(
            \sprintf('%s/%s/numcat.csv', $tenant->getId()->toRfc4122(), $sessionId->toRfc4122()),
            "sku;kategoria\nNUMCAT-1;1214553885\n",
        );

        self::getContainer()->get(TenantFilterConfigurator::class)->apply();
        self::getContainer()->get(ImportRunHandler::class)->run($session);
        $em->clear();

        self::getContainer()->get(TenantContext::class)->set($this->tenant());
        $reloaded = $em->find(ImportSession::class, $sessionId);
        \assert($reloaded instanceof ImportSession);
        self::assertSame(ImportSessionStatus::Success, $reloaded->getStatus(), 'run completes despite the numeric category code');
        self::assertSame(1, $reloaded->getSuccessCount());

        $exists = $em->getConnection()->fetchOne("SELECT COUNT(*) FROM objects WHERE code = 'NUMCAT-1'");
        self::assertSame(1, (int) (\is_scalar($exists) ? $exists : 0), 'the product imported; the unresolved category is just a warning');
    }

    private function tenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }
}
