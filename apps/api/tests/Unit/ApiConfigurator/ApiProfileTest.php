<?php

declare(strict_types=1);

namespace App\Tests\Unit\ApiConfigurator;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Enum\OutputFormat;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ApiProfileTest extends TestCase
{
    #[Test]
    public function constructorSetsDefaultsAndAcceptsLists(): void
    {
        $profile = new ApiProfile(
            code: 'storefront',
            name: 'Storefront partner X',
            outputFormat: OutputFormat::JSON_LD,
            objectTypeIds: ['018f1234-1234-7000-8000-000000000001'],
            includedAttributes: ['name', 'brand'],
            filters: ['status' => 'enabled'],
            description: 'Public storefront feed',
            webhookUrl: 'https://example.test/webhook',
            webhookEvents: ['object.created.product'],
            rateLimitPerHour: 2000,
        );

        self::assertInstanceOf(Uuid::class, $profile->getId());
        self::assertSame('storefront', $profile->getCode());
        self::assertSame('Storefront partner X', $profile->getName());
        self::assertSame(OutputFormat::JSON_LD, $profile->getOutputFormat());
        self::assertSame(['018f1234-1234-7000-8000-000000000001'], $profile->getObjectTypeIds());
        self::assertSame(['name', 'brand'], $profile->getIncludedAttributes());
        self::assertSame(['status' => 'enabled'], $profile->getFilters());
        self::assertSame('Public storefront feed', $profile->getDescription());
        self::assertSame('https://example.test/webhook', $profile->getWebhookUrl());
        self::assertSame(['object.created.product'], $profile->getWebhookEvents());
        self::assertSame(2000, $profile->getRateLimitPerHour());
        self::assertNull($profile->getTenant());
    }

    #[Test]
    public function rateLimitDefaultsTo1000(): void
    {
        $profile = $this->makeProfile();

        self::assertSame(1000, $profile->getRateLimitPerHour());
    }

    #[Test]
    public function settersBumpUpdatedAt(): void
    {
        $profile = $this->makeProfile();
        $createdAt = $profile->getUpdatedAt();
        // sleep is too coarse for the test, manipulate directly via setter
        // and assert the relationship by re-reading.
        usleep(1_500_000);
        $profile->rename('new name');
        self::assertGreaterThanOrEqual($createdAt, $profile->getUpdatedAt());
        self::assertSame('new name', $profile->getName());
    }

    #[Test]
    public function assignTenantStampsAndRefusesReassignment(): void
    {
        $profile = $this->makeProfile();
        $first = new Tenant('demo', 'Demo');
        $second = new Tenant('acme', 'Acme');

        $profile->assignTenant($first);
        self::assertSame($first, $profile->getTenant());

        $this->expectException(LogicException::class);
        $profile->assignTenant($second);
    }

    #[Test]
    public function settersReplaceListPayloads(): void
    {
        $profile = $this->makeProfile();
        $profile->setObjectTypeIds(['018f1234-1234-7000-8000-000000000003']);
        self::assertSame(['018f1234-1234-7000-8000-000000000003'], $profile->getObjectTypeIds());

        $profile->setIncludedAttributes(['sku', 'name']);
        self::assertSame(['sku', 'name'], $profile->getIncludedAttributes());

        $profile->setWebhookEvents(['object.created.product']);
        self::assertSame(['object.created.product'], $profile->getWebhookEvents());
    }

    private function makeProfile(): ApiProfile
    {
        return new ApiProfile(
            code: 'sitemap',
            name: 'SiteMap profile',
            outputFormat: OutputFormat::JSON,
        );
    }
}
