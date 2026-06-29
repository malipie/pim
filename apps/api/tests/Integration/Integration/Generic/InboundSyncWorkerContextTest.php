<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Integration\Generic\Domain\Message\InboundSyncMessage;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Messenger\TenantContextRebindingMiddleware;
use App\Shared\Infrastructure\Messenger\TenantRlsGucMiddleware;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * APIC-P3-05 — the async inbound sync path propagates the tenant correctly.
 *
 * InboundSyncMessage carries its tenant as a {@see \App\Shared\Application\TenantAwareMessage}
 * (no TenantStamp, unlike the import dispatch), so this proves BOTH worker
 * middlewares resolve the tenant from that interface: the rebinding middleware
 * sets {@see TenantContext} and the RLS-GUC middleware sets (and resets)
 * `app.current_tenant`. Without this an async run would either fail to bind a
 * tenant or — worse — leak across tenants.
 */
final class InboundSyncWorkerContextTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $em = $this->em();
        $this->tenant = new Tenant('demo', 'Demo');
        $em->persist($this->tenant);
        $em->flush();
    }

    #[Test]
    public function rlsGucIsSetFromTheTenantAwareMessageAndResetAfter(): void
    {
        $connection = $this->connection();
        $middleware = new TenantRlsGucMiddleware($connection);
        $message = new InboundSyncMessage(Uuid::v7(), $this->tenant->getId());

        $envelope = new Envelope($message)->with(new ConsumedByWorkerStamp());

        $seen = new stdClass();
        $seen->value = null;
        $middleware->handle($envelope, $this->stackReadingGuc($connection, $seen));

        self::assertSame($this->tenant->getId()->toRfc4122(), $seen->value);
        self::assertSame('', $connection->fetchOne("SELECT current_setting('app.current_tenant', true)"));
    }

    #[Test]
    public function rebindingSetsTenantContextFromTheMessage(): void
    {
        $middleware = new TenantContextRebindingMiddleware(
            self::getContainer()->get(TenantRepositoryInterface::class),
            $this->tenantContext(),
            new NullLogger(),
        );
        $message = new InboundSyncMessage(Uuid::v7(), $this->tenant->getId());
        $envelope = new Envelope($message)->with(new ConsumedByWorkerStamp());

        $seen = new stdClass();
        $seen->value = null;
        $context = $this->tenantContext();
        $middleware->handle($envelope, $this->stack(static function () use ($context, $seen): void {
            $seen->value = $context->get()?->getId()->toRfc4122();
        }));

        self::assertSame($this->tenant->getId()->toRfc4122(), $seen->value);
    }

    private function connection(): Connection
    {
        // The container PHPStan extension already types this service id as
        // Connection, so an assert here is flagged always-true.
        return self::getContainer()->get('doctrine.dbal.default_connection');
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function stackReadingGuc(Connection $connection, stdClass $sink): StackInterface
    {
        return $this->stack(static function () use ($connection, $sink): void {
            $sink->value = $connection->fetchOne("SELECT current_setting('app.current_tenant', true)");
        });
    }

    /**
     * @param Closure(): void $terminal
     */
    private function stack(Closure $terminal): StackInterface
    {
        $middleware = new class($terminal) implements MiddlewareInterface {
            /**
             * @param Closure(): void $terminal
             */
            public function __construct(private Closure $terminal)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->terminal)();

                return $envelope;
            }
        };

        return new class($middleware) implements StackInterface {
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
