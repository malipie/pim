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
        $this->captured[] = $update;

        return 'urn:uuid:'.bin2hex(random_bytes(8));
    }
}
