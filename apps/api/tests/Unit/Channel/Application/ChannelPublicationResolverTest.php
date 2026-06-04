<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel\Application;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelPublicationProfile;
use App\Channel\Domain\Repository\ChannelPublicationProfileRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Channel\Infrastructure\ChannelPublicationResolver;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[AllowMockObjectsWithoutExpectations]
final class ChannelPublicationResolverTest extends TestCase
{
    /** @var MockObject&ChannelRepositoryInterface */
    private MockObject $channels;
    /** @var MockObject&ChannelPublicationProfileRepositoryInterface */
    private MockObject $profiles;
    private ChannelPublicationResolver $resolver;
    private Tenant $tenant;
    private Uuid $objectTypeId;

    protected function setUp(): void
    {
        $this->channels = $this->createMock(ChannelRepositoryInterface::class);
        $this->profiles = $this->createMock(ChannelPublicationProfileRepositoryInterface::class);
        $this->resolver = new ChannelPublicationResolver($this->channels, $this->profiles);
        $this->tenant = new Tenant('demo', 'Demo Tenant');
        $this->objectTypeId = Uuid::v7();
    }

    #[Test]
    public function resolvePublishedCodesReturnsNullForUnknownChannel(): void
    {
        $this->channels->method('findByCode')->willReturn(null);

        $result = $this->resolver->resolvePublishedCodes('unknown', $this->objectTypeId, $this->tenant);

        self::assertNull($result);
    }

    #[Test]
    public function resolvePublishedCodesReturnsNullWhenNoProfileExists(): void
    {
        $channel = $this->makeChannel('shopify');
        $this->channels->method('findByCode')->willReturn($channel);
        $this->profiles->method('findByChannelAndObjectType')->willReturn(null);

        $result = $this->resolver->resolvePublishedCodes('shopify', $this->objectTypeId, $this->tenant);

        self::assertNull($result, 'No profile row → publish-all fallback.');
    }

    #[Test]
    public function resolvePublishedCodesReturnsNullForDefaultPublishAllProfile(): void
    {
        $channel = $this->makeChannel('shopify');
        $profile = new ChannelPublicationProfile($channel->getId(), $this->objectTypeId, null, [], [], true);
        $this->channels->method('findByCode')->willReturn($channel);
        $this->profiles->method('findByChannelAndObjectType')->willReturn($profile);

        $result = $this->resolver->resolvePublishedCodes('shopify', $this->objectTypeId, $this->tenant);

        self::assertNull($result, 'Explicit publish-all profile → null return.');
    }

    #[Test]
    public function resolvePublishedCodesReturnsAllowListWhenProfileHasCodes(): void
    {
        $channel = $this->makeChannel('shopify');
        $profile = new ChannelPublicationProfile(
            $channel->getId(),
            $this->objectTypeId,
            ['name', 'price', 'ean'],
        );
        $this->channels->method('findByCode')->willReturn($channel);
        $this->profiles->method('findByChannelAndObjectType')->willReturn($profile);

        $result = $this->resolver->resolvePublishedCodes('shopify', $this->objectTypeId, $this->tenant);

        self::assertSame(['name', 'price', 'ean'], $result);
    }

    #[Test]
    public function resolvePublishedLocalesReturnsEmptyForUnknownChannel(): void
    {
        $this->channels->method('findByCode')->willReturn(null);

        $result = $this->resolver->resolvePublishedLocales('unknown', $this->tenant);

        self::assertSame([], $result);
    }

    #[Test]
    public function resolvePublishedLocalesMergesLocalesAcrossProfiles(): void
    {
        $channel = $this->makeChannel('shopify');
        $profiles = [
            new ChannelPublicationProfile($channel->getId(), Uuid::v7(), null, ['pl', 'en']),
            new ChannelPublicationProfile($channel->getId(), Uuid::v7(), null, ['pl', 'de']),
        ];
        $this->channels->method('findByCode')->willReturn($channel);
        $this->profiles->method('findForChannel')->willReturn($profiles);

        $result = $this->resolver->resolvePublishedLocales('shopify', $this->tenant);

        sort($result);
        self::assertSame(['de', 'en', 'pl'], $result);
    }

    private function makeChannel(string $code): Channel
    {
        $channel = new Channel(code: $code, label: ['pl' => $code]);
        $channel->assignTenant($this->tenant);

        return $channel;
    }
}
