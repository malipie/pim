<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Channel\Domain\Entity\ChannelPublicationProfile;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ChannelPublicationProfileTest extends TestCase
{
    #[Test]
    public function nullPublishedCodesSignifiesPublishAll(): void
    {
        $profile = new ChannelPublicationProfile(
            channelId: Uuid::v7(),
            objectTypeId: Uuid::v7(),
        );

        self::assertTrue($profile->isPublishAll());
        self::assertNull($profile->getPublishedAttributeCodes());
    }

    #[Test]
    public function emptyArrayPublishedCodesSignifiesPublishNothing(): void
    {
        $profile = new ChannelPublicationProfile(
            channelId: Uuid::v7(),
            objectTypeId: Uuid::v7(),
            publishedAttributeCodes: [],
        );

        self::assertFalse($profile->isPublishAll());
        self::assertSame([], $profile->getPublishedAttributeCodes());
    }

    #[Test]
    public function nonNullAllowListIsPreserved(): void
    {
        $profile = new ChannelPublicationProfile(
            channelId: Uuid::v7(),
            objectTypeId: Uuid::v7(),
            publishedAttributeCodes: ['name', 'price', 'ean'],
            publishedLocales: ['pl', 'en'],
        );

        self::assertFalse($profile->isPublishAll());
        self::assertSame(['name', 'price', 'ean'], $profile->getPublishedAttributeCodes());
        self::assertSame(['pl', 'en'], $profile->getPublishedLocales());
    }

    #[Test]
    public function setPublishedAttributeCodesNullRestoresPublishAll(): void
    {
        $profile = new ChannelPublicationProfile(
            channelId: Uuid::v7(),
            objectTypeId: Uuid::v7(),
            publishedAttributeCodes: ['name'],
        );

        $profile->setPublishedAttributeCodes(null);

        self::assertTrue($profile->isPublishAll());
    }

    #[Test]
    public function defaultProfileFlagCarriedThrough(): void
    {
        $channelId = Uuid::v7();
        $objectTypeId = Uuid::v7();

        $profile = new ChannelPublicationProfile(
            channelId: $channelId,
            objectTypeId: $objectTypeId,
            isDefault: true,
        );

        self::assertTrue($profile->isDefault());
        self::assertTrue($profile->getChannelId()->equals($channelId));
        self::assertTrue($profile->getObjectTypeId()->equals($objectTypeId));
    }

    #[Test]
    public function tenantCannotBeReassigned(): void
    {
        $profile = new ChannelPublicationProfile(
            channelId: Uuid::v7(),
            objectTypeId: Uuid::v7(),
        );

        $tenant = new \App\Shared\Domain\Tenant('test-tenant', 'Test Tenant');
        $profile->assignTenant($tenant);

        $this->expectException(LogicException::class);
        $profile->assignTenant($tenant);
    }
}
