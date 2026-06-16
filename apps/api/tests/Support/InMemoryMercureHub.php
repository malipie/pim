<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * In-memory Mercure hub for tests (#47 / 0.4.7).
 *
 * Captures every `Update` published during a PHPUnit run instead of
 * HTTP-posting to the dev Mercure container. Subscribers wired up
 * with `#[AsMessageHandler]` exercise the real flush → dispatch
 * pipeline — only the network leg is short-circuited.
 *
 * Tests pull captured updates via `getCapturedUpdates()` to assert
 * topics + payload shape.
 */
final class InMemoryMercureHub implements HubInterface
{
    /**
     * @var list<Update>
     */
    private array $captured = [];

    private bool $retain = true;

    /**
     * @return list<Update>
     */
    public function getCapturedUpdates(): array
    {
        return $this->captured;
    }

    public function reset(): void
    {
        $this->captured = [];
    }

    /**
     * Stop accumulating published updates — models the production hub, which
     * POSTs over HTTP and discards. Memory benchmarks must call this: retaining
     * every {@see Update} (50k+ on a bulk import) is a test-only artifact that
     * has no counterpart in the real {@see \Symfony\Component\Mercure\Hub}.
     */
    public function stopRetaining(): void
    {
        $this->retain = false;
        $this->captured = [];
    }

    public function getUrl(): string
    {
        return 'http://localhost/.well-known/mercure';
    }

    public function getPublicUrl(): string
    {
        return $this->getUrl();
    }

    public function getProvider(): TokenProviderInterface
    {
        return new StaticTokenProvider('test-jwt');
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        if ($this->retain) {
            $this->captured[] = $update;
        }

        return 'urn:uuid:'.bin2hex(random_bytes(8));
    }
}
