<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Shared\Infrastructure\Messenger\Stamp\TenantStamp;
use App\Shared\Infrastructure\Messenger\TenantRlsGucMiddleware;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-2.5 (#1481) — proves the worker middleware establishes the Postgres GUC
 * `app.current_tenant` (that RLS policies read) for the duration of an async
 * handler, and resets it afterwards so a pooled connection cannot leak the
 * tenant into the next message.
 *
 * The handler is simulated by a terminal middleware that reads
 * `current_setting('app.current_tenant')` off the SAME connection — exactly
 * what an RLS-protected query would see.
 */
final class ImportWorkerRlsGucTest extends KernelTestCase
{
    #[Test]
    public function workerMessageSetsTheGucForTheHandlerAndResetsAfter(): void
    {
        $connection = $this->connection();
        $middleware = new TenantRlsGucMiddleware($connection);
        $tenantId = Uuid::v7();

        $seen = new stdClass();
        $seen->value = null;

        $envelope = new Envelope(new stdClass())
            ->with(new ConsumedByWorkerStamp())
            ->with(new TenantStamp($tenantId));

        $middleware->handle($envelope, $this->stackReadingGuc($connection, $seen));

        self::assertSame(
            $tenantId->toRfc4122(),
            $seen->value,
            'The handler must see app.current_tenant set to the message tenant.',
        );

        $after = $connection->fetchOne("SELECT current_setting('app.current_tenant', true)");
        self::assertSame('', $after, 'GUC must be reset after the handler returns.');
    }

    #[Test]
    public function syncDispatchLeavesTheGucUntouched(): void
    {
        $connection = $this->connection();
        $middleware = new TenantRlsGucMiddleware($connection);

        // A sync dispatch carries no ConsumedByWorkerStamp; the request
        // listener owns the GUC there, so the middleware must pass through.
        $sentinel = Uuid::v7()->toRfc4122();
        $connection->executeStatement("SELECT set_config('app.current_tenant', :v, false)", ['v' => $sentinel]);

        $seen = new stdClass();
        $seen->value = null;
        $middleware->handle(new Envelope(new stdClass()), $this->stackReadingGuc($connection, $seen));

        self::assertSame($sentinel, $seen->value, 'Sync dispatch must not overwrite the request-set GUC.');

        // Cleanup so the shared connection does not carry the sentinel onward.
        $connection->executeStatement("SELECT set_config('app.current_tenant', '', false)");
    }

    private function connection(): Connection
    {
        // The container PHPStan extension already types this service id as
        // Connection (an assert here is flagged always-true), so return as-is.
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    /**
     * A one-middleware stack whose terminal handler records what
     * `current_setting('app.current_tenant')` returns while it runs.
     */
    private function stackReadingGuc(Connection $connection, stdClass $sink): StackInterface
    {
        $terminal = new class($connection, $sink) implements MiddlewareInterface {
            public function __construct(
                private readonly Connection $connection,
                private readonly stdClass $sink,
            ) {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->sink->value = $this->connection->fetchOne(
                    "SELECT current_setting('app.current_tenant', true)",
                );

                return $envelope;
            }
        };

        return new class($terminal) implements StackInterface {
            public function __construct(private readonly MiddlewareInterface $next)
            {
            }

            public function next(): MiddlewareInterface
            {
                return $this->next;
            }
        };
    }
}
