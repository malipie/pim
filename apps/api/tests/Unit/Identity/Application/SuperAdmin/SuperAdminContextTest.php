<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application\SuperAdmin;

use App\Identity\Application\SuperAdmin\SuperAdminContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-014 (#677) — SuperAdminContext unit coverage of the
 * filter-toggle + finally-restore semantics.
 */
final class SuperAdminContextTest extends TestCase
{
    #[Test]
    public function startsInactive(): void
    {
        $context = new SuperAdminContext($this->createStub(EntityManagerInterface::class));

        self::assertFalse($context->isActive());
        self::assertNull($context->activeSuperAdminId());
    }

    #[Test]
    public function activationDisablesEnabledTenantFilter(): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->expects(self::once())
            ->method('isEnabled')
            ->with(SuperAdminContext::FILTER_NAME)
            ->willReturn(true);
        $filters->expects(self::once())
            ->method('disable')
            ->with(SuperAdminContext::FILTER_NAME);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $context = new SuperAdminContext($em);
        $previous = $context->useCrossTenantMode(Uuid::v7());

        self::assertTrue($previous);
        self::assertTrue($context->isActive());
    }

    #[Test]
    public function activationLeavesDisabledFilterUntouched(): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->expects(self::once())
            ->method('isEnabled')
            ->with(SuperAdminContext::FILTER_NAME)
            ->willReturn(false);
        $filters->expects(self::never())->method('disable');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $context = new SuperAdminContext($em);
        $previous = $context->useCrossTenantMode(Uuid::v7());

        self::assertFalse($previous);
        self::assertTrue($context->isActive());
    }

    #[Test]
    public function restoreReEnablesFilterWhenPreviouslyEnabled(): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturnOnConsecutiveCalls(true, false);
        $filters->expects(self::once())->method('disable');
        $filters->expects(self::once())->method('enable')->with(SuperAdminContext::FILTER_NAME);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $context = new SuperAdminContext($em);
        $previous = $context->useCrossTenantMode(Uuid::v7());
        $context->restoreTenantScope($previous);

        self::assertFalse($context->isActive());
    }

    #[Test]
    public function restoreSkipsEnableWhenFilterWasNotActiveBefore(): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);
        $filters->expects(self::never())->method('enable');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $context = new SuperAdminContext($em);
        $context->restoreTenantScope($context->useCrossTenantMode(Uuid::v7()));

        self::assertFalse($context->isActive());
    }

    #[Test]
    public function runCrossTenantRestoresScopeAfterCallback(): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturnOnConsecutiveCalls(true, false);
        $filters->expects(self::once())->method('disable');
        $filters->expects(self::once())->method('enable');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $context = new SuperAdminContext($em);
        $result = $context->runCrossTenant(Uuid::v7(), static fn (): string => 'done');

        self::assertSame('done', $result);
        self::assertFalse($context->isActive());
    }

    #[Test]
    public function runCrossTenantRestoresScopeOnException(): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturnOnConsecutiveCalls(true, false);
        $filters->expects(self::once())->method('disable');
        $filters->expects(self::once())->method('enable');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $context = new SuperAdminContext($em);

        try {
            $context->runCrossTenant(Uuid::v7(), static function (): never {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        self::assertFalse($context->isActive());
    }
}
