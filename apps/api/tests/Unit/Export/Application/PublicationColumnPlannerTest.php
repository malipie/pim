<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Application;

use App\Channel\Contracts\ChannelPublicationResolverInterface;
use App\Export\Application\Builder\PublicationColumnPlanner;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[AllowMockObjectsWithoutExpectations]
final class PublicationColumnPlannerTest extends TestCase
{
    /** @var MockObject&ChannelPublicationResolverInterface */
    private MockObject $resolver;
    private PublicationColumnPlanner $planner;
    private Tenant $tenant;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(ChannelPublicationResolverInterface::class);
        $this->planner = new PublicationColumnPlanner($this->resolver);
        $this->tenant = new Tenant('demo', 'Demo Tenant');
    }

    #[Test]
    public function returnsNullWhenNoChannelInSession(): void
    {
        $session = $this->makeSession(channels: [], locales: ['pl']);

        $result = $this->planner->plan($session, [Uuid::v7()->toRfc4122()]);

        self::assertNull($result);
    }

    #[Test]
    public function returnsNullForPublishAllProfile(): void
    {
        $this->resolver->method('resolvePublishedCodes')->willReturn(null);
        $session = $this->makeSession(channels: ['shopify'], locales: ['pl']);

        $result = $this->planner->plan($session, [Uuid::v7()->toRfc4122()]);

        self::assertNull($result, 'Publish-all profile cannot generate a finite column list.');
    }

    #[Test]
    public function generatesBareCodeAndLocaleSuffixedColumns(): void
    {
        $this->resolver->method('resolvePublishedCodes')->willReturn(['name', 'price']);
        $session = $this->makeSession(channels: ['shopify'], locales: ['pl', 'en']);

        $result = $this->planner->plan($session, [Uuid::v7()->toRfc4122()]);

        self::assertNotNull($result);
        self::assertContains('name', $result);
        self::assertContains('name.pl', $result);
        self::assertContains('name.en', $result);
        self::assertContains('price', $result);
        self::assertContains('price.shopify', $result);
    }

    #[Test]
    public function generatesCombinedLocaleChannelColumns(): void
    {
        // IMP2-1.6 (#1469) — attributes scoped to both a locale and a channel
        // need the combined `code.locale.channel` notation in the plan.
        $this->resolver->method('resolvePublishedCodes')->willReturn(['name']);
        $session = $this->makeSession(channels: ['shopify', 'allegro'], locales: ['pl', 'en']);

        $result = $this->planner->plan($session, [Uuid::v7()->toRfc4122()]);

        self::assertNotNull($result);
        self::assertContains('name.pl.shopify', $result);
        self::assertContains('name.en.shopify', $result);
        self::assertContains('name.pl.allegro', $result);
        self::assertContains('name.en.allegro', $result);
    }

    #[Test]
    public function deduplicatesColumnsAcrossMultipleObjectTypes(): void
    {
        $this->resolver->method('resolvePublishedCodes')->willReturn(['name', 'ean']);
        $session = $this->makeSession(channels: ['shopify'], locales: ['pl']);

        $ot1 = Uuid::v7()->toRfc4122();
        $ot2 = Uuid::v7()->toRfc4122();

        $result = $this->planner->plan($session, [$ot1, $ot2]);

        self::assertNotNull($result);
        self::assertCount(\count(array_unique($result)), $result, 'No duplicates in result.');
    }

    #[Test]
    public function returnsNullWhenObjectTypeIdsIsEmpty(): void
    {
        $session = $this->makeSession(channels: ['shopify'], locales: ['pl']);

        $result = $this->planner->plan($session, []);

        self::assertNull($result);
    }

    /**
     * @param list<string> $channels
     * @param list<string> $locales
     */
    private function makeSession(array $channels, array $locales): ExportSession
    {
        $session = new ExportSession(
            userId: Uuid::v7(),
            source: ExportSource::ListContext,
            format: ExportFormat::Csv,
            targetScope: ExportTargetScope::All,
            selectedColumns: [],
            locales: $locales,
            channels: $channels,
        );
        $session->assignTenant($this->tenant);

        return $session;
    }
}
