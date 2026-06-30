<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Integration\Generic\Application\Handler\OutboundSyncHandler;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Message\OutboundSyncMessage;
use App\Integration\Generic\Domain\Repository\SyncRunRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Messenger\TenantContextRebindingMiddleware;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * #1885 — proves the async OUTBOUND worker path end to end, split into the two
 * steps a worker run performs (mirrors {@see InboundSyncWorkerContextTest}):
 *
 *  1. the rebinding middleware restores {@see TenantContext} from the
 *     {@see OutboundSyncMessage} (a `TenantAwareMessage`) on a worker run;
 *  2. with the tenant bound, {@see OutboundSyncHandler} resolves the binding
 *     and the runner records a {@see \App\Integration\Generic\Domain\Entity\SyncRun}.
 *
 * Together they show the worker creates a run; the live "no run" seen while
 * smoke-testing was a worker restart-cycling / expired-token artifact, not a
 * code defect (#1885). No catalog objects are seeded, so the reader yields none
 * and no remote call is made — the run is created regardless.
 */
final class OutboundSyncWorkerContextTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private Tenant $tenant;
    private ObjectType $productType;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $em = $this->em();
        $this->tenant = new Tenant('demo', 'Demo');
        $em->persist($this->tenant);
        $em->flush();
        $this->tenantContext()->set($this->tenant);

        $this->productType = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);
        $em->persist($this->productType);
        $em->flush();
    }

    #[Test]
    public function rebindingSetsTenantFromOutboundMessage(): void
    {
        $middleware = new TenantContextRebindingMiddleware(
            self::getContainer()->get(TenantRepositoryInterface::class),
            $this->tenantContext(),
            new NullLogger(),
        );
        $envelope = new Envelope(
            new OutboundSyncMessage(Uuid::v7(), $this->tenant->getId()),
            [new ConsumedByWorkerStamp()],
        );

        $seen = null;
        $context = $this->tenantContext();
        $middleware->handle($envelope, $this->stack(static function () use ($context, &$seen): void {
            $seen = $context->get()?->getId()->toRfc4122();
        }));

        self::assertSame($this->tenant->getId()->toRfc4122(), $seen);
    }

    #[Test]
    public function handlerCreatesSyncRunUnderBoundTenant(): void
    {
        $binding = $this->seedOutboundBinding();
        $bindingId = $binding->getId();

        // The worker state after the middlewares run: TenantContext bound + the
        // RLS GUC set for the same tenant.
        $this->tenantContext()->set($this->tenant);
        $this->em()->getConnection()->executeStatement(
            "SELECT set_config('app.current_tenant', :t, false)",
            ['t' => $this->tenant->getId()->toRfc4122()],
        );

        self::getContainer()->get(OutboundSyncHandler::class)(
            new OutboundSyncMessage($bindingId, $this->tenant->getId()),
        );

        $reloaded = $this->em()->find(SyncBinding::class, $bindingId->toRfc4122());
        self::assertInstanceOf(SyncBinding::class, $reloaded);
        $runs = self::getContainer()->get(SyncRunRepositoryInterface::class)->findByBinding($reloaded);

        self::assertCount(1, $runs, 'the worker handler must create exactly one SyncRun');
        self::assertSame(SyncDirection::Outbound, $runs[0]->getDirection());
    }

    private function seedOutboundBinding(): SyncBinding
    {
        $em = $this->em();
        $connection = new Connection('idosell', 'IdoSell', 'https://api.example.test');
        $connection->assignTenant($this->tenant);
        $em->persist($connection);

        $endpoint = new RemoteEndpoint($connection, RemoteEndpointRole::WriteUpdate, 'PUT', '/products/products');
        $endpoint->assignTenant($this->tenant);
        $em->persist($endpoint);

        $binding = new SyncBinding($connection, $this->productType->getId(), SyncDirection::Outbound);
        $binding->assignTenant($this->tenant);
        $binding->setWriteEndpoint($endpoint);
        $em->persist($binding);
        $em->flush();

        return $binding;
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
            public function __construct(private MiddlewareInterface $middleware)
            {
            }

            public function next(): MiddlewareInterface
            {
                return $this->middleware;
            }
        };
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
}
