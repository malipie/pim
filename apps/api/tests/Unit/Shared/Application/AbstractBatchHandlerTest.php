<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application;

use App\Shared\Application\AbstractBatchHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractBatchHandlerTest extends TestCase
{
    #[Test]
    public function flushAndClearDelegatesToEntityManagerInOrder(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $callOrder = [];
        $em->expects(self::once())
            ->method('flush')
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'flush';
            });
        $em->expects(self::once())
            ->method('clear')
            ->willReturnCallback(static function () use (&$callOrder): void {
                $callOrder[] = 'clear';
            });

        $handler = new class($em, 200) extends AbstractBatchHandler {
            public function run(): void
            {
                $this->flushAndClear();
            }
        };

        $handler->run();

        self::assertSame(['flush', 'clear'], $callOrder, 'flush() must run before clear() — clearing first would discard pending changes.');
    }

    #[Test]
    public function shouldFlushIsTrueOnlyOnBatchBoundaries(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new class($em, 200) extends AbstractBatchHandler {
            public function check(int $processed): bool
            {
                return $this->shouldFlush($processed);
            }
        };

        self::assertFalse($handler->check(0), 'Zero must not trigger a flush — there is nothing to flush yet.');
        self::assertFalse($handler->check(1));
        self::assertFalse($handler->check(199));
        self::assertTrue($handler->check(200));
        self::assertFalse($handler->check(201));
        self::assertTrue($handler->check(400));
        self::assertTrue($handler->check(2000));
    }

    #[Test]
    public function shouldFlushHonoursCustomBatchSize(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $handler = new class($em, 50) extends AbstractBatchHandler {
            public function check(int $processed): bool
            {
                return $this->shouldFlush($processed);
            }
        };

        self::assertTrue($handler->check(50));
        self::assertTrue($handler->check(100));
        self::assertFalse($handler->check(75));
    }
}
