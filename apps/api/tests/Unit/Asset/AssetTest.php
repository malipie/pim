<?php

declare(strict_types=1);

namespace App\Tests\Unit\Asset;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Entity\AssetVariant;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AssetTest extends TestCase
{
    #[Test]
    public function constructorSetsAllFields(): void
    {
        $asset = $this->makeAsset();

        self::assertInstanceOf(Uuid::class, $asset->getId());
        self::assertSame('hero-image', $asset->getCode());
        self::assertSame('hero.jpg', $asset->getOriginalFilename());
        self::assertSame('image/jpeg', $asset->getMimeType());
        self::assertSame(123_456, $asset->getSize());
        self::assertSame('demo/hero/original.jpg', $asset->getStoragePath());
        self::assertSame([], $asset->getMetadata());
        self::assertNull($asset->getObjectId());
        self::assertCount(0, $asset->getVariants());
    }

    #[Test]
    public function metadataRoundTripsJsonbPayload(): void
    {
        $asset = $this->makeAsset();
        $asset->updateMetadata([
            'width' => 1920,
            'height' => 1080,
            'exif' => ['camera' => 'Sony A7'],
        ]);

        self::assertSame(1920, $asset->getMetadata()['width']);
        self::assertIsArray($asset->getMetadata()['exif']);
        self::assertSame('Sony A7', $asset->getMetadata()['exif']['camera']);
    }

    #[Test]
    public function linkToObjectStoresUuid(): void
    {
        $type = new ObjectType('asset', ObjectKind::Asset, ['pl' => 'Zasób']);
        $object = new CatalogObject($type, 'hero-image');
        $asset = $this->makeAsset();

        $asset->linkToObject($object->getId());

        self::assertSame($object->getId(), $asset->getObjectId());
    }

    #[Test]
    public function variantsCollectionIsIdempotent(): void
    {
        $asset = $this->makeAsset();
        $variant = new AssetVariant($asset, AssetVariant::CODE_ORIGINAL, 'demo/hero/original.jpg', 'image/jpeg', 123_456);

        $asset->addVariant($variant);
        $asset->addVariant($variant);

        self::assertCount(1, $asset->getVariants());
    }

    #[Test]
    public function assignTenantStampsAndRefusesReassignment(): void
    {
        $asset = $this->makeAsset();
        $first = new Tenant('demo', 'Demo');
        $second = new Tenant('acme', 'Acme');

        $asset->assignTenant($first);
        self::assertSame($first, $asset->getTenant());

        $this->expectException(LogicException::class);
        $asset->assignTenant($second);
    }

    private function makeAsset(): Asset
    {
        return new Asset(
            code: 'hero-image',
            originalFilename: 'hero.jpg',
            mimeType: 'image/jpeg',
            size: 123_456,
            storagePath: 'demo/hero/original.jpg',
        );
    }
}
