<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Message\RunExportMessage;
use App\Shared\Application\TenantAwareMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Regression: a RunExportMessage consumed on the async worker must carry its
 * tenant so {@see \App\Shared\Infrastructure\Messenger\TenantContextRebindingMiddleware}
 * can rebind TenantContext (HIGH-002). Without it the middleware aborts the
 * run after 5 retries and the export session hangs in `pending` forever.
 */
final class RunExportMessageTest extends TestCase
{
    #[Test]
    public function carriesTenantContextForTheAsyncWorker(): void
    {
        $sessionId = Uuid::v7();
        $tenantId = Uuid::v7();

        $message = new RunExportMessage($sessionId, $tenantId);

        self::assertInstanceOf(TenantAwareMessage::class, $message);
        self::assertTrue($message->tenantId()->equals($tenantId));
        self::assertTrue($message->exportSessionId->equals($sessionId));
    }
}
