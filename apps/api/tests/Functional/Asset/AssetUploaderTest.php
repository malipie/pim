<?php

declare(strict_types=1);

namespace App\Tests\Functional\Asset;

use App\Asset\Application\AssetUploader;
use App\Asset\Domain\Entity\AssetVariant;
use App\Identity\Application\TenantContext;
use App\Identity\Domain\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\File;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AssetUploaderTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function uploadStoresBytesAndPersistsAssetWithOriginalVariant(): void
    {
        $tenant = $this->createTenant('demo');
        $this->tenantContext()->set($tenant);

        $tmpPath = $this->writeTempFile('hello asset');

        $asset = $this->uploader()->upload(new File($tmpPath), 'demo-asset-1');

        self::assertSame('demo-asset-1', $asset->getCode());
        self::assertStringStartsWith($tenant->getId()->toRfc4122(), $asset->getStoragePath());
        self::assertSame($tenant, $asset->getTenant());

        // Bytes are reachable through the storage
        self::assertSame('hello asset', $this->storage()->read($asset->getStoragePath()));

        // One `original` variant created at upload time
        self::assertCount(1, $asset->getVariants());
        $variant = $asset->getVariants()->first();
        self::assertNotFalse($variant);
        self::assertSame(AssetVariant::CODE_ORIGINAL, $variant->getVariantCode());
        self::assertSame($asset->getStoragePath(), $variant->getStoragePath());

        @unlink($tmpPath);
    }

    private function uploader(): AssetUploader
    {
        return self::getContainer()->get(AssetUploader::class);
    }

    private function storage(): FilesystemOperator
    {
        return self::getContainer()->get('assets.storage');
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }

    private function createTenant(string $code): Tenant
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $tenant = new Tenant($code, ucfirst($code).' Tenant');
        $em->persist($tenant);
        $em->flush();

        return $tenant;
    }

    private function writeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pim-asset-test-');
        \assert(false !== $path);
        file_put_contents($path, $contents);

        return $path;
    }
}
