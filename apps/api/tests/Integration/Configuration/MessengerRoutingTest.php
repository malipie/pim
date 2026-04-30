<?php

declare(strict_types=1);

namespace App\Tests\Integration\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Locks in the explicit Messenger transport routing posture (audit
 * MEDIUM-007). Every concrete message class must have an entry in
 * `config/packages/messenger.yaml` so the prod / dev / test
 * dispatchers behave identically; falling back to Messenger's
 * default behavior (in-process handling without a transport) is
 * forbidden because it diverges silently between environments once
 * an async transport is added.
 *
 * The test introspects `messenger.senders_locator` directly: for each
 * known message class it calls `getSenders()` and asserts the senders
 * list is non-empty AND points at the expected transport.
 */
final class MessengerRoutingTest extends KernelTestCase
{
    /**
     * Sources of truth — must match `messenger.yaml` routing block.
     *
     * @return iterable<string, array{class-string, string}>
     */
    public static function routedMessageProvider(): iterable
    {
        // --- Application Commands (CQRS write side) — synchronous ---
        yield 'catalog: create object' => [
            \App\Catalog\Application\Command\CreateCatalogObject\CreateCatalogObjectCommand::class,
            'sync',
        ];
        yield 'catalog: update object' => [
            \App\Catalog\Application\Command\UpdateCatalogObject\UpdateCatalogObjectCommand::class,
            'sync',
        ];
        yield 'catalog: delete object' => [
            \App\Catalog\Application\Command\DeleteCatalogObject\DeleteCatalogObjectCommand::class,
            'sync',
        ];
        yield 'api-configurator: create profile' => [
            \App\ApiConfigurator\Application\Command\CreateApiProfile\CreateApiProfileCommand::class,
            'sync',
        ];
        yield 'api-configurator: update profile' => [
            \App\ApiConfigurator\Application\Command\UpdateApiProfile\UpdateApiProfileCommand::class,
            'sync',
        ];
        yield 'api-configurator: delete profile' => [
            \App\ApiConfigurator\Application\Command\DeleteApiProfile\DeleteApiProfileCommand::class,
            'sync',
        ];

        // --- Domain events — synchronous publishers ---
        yield 'catalog: object created' => [
            \App\Catalog\Contracts\Event\ObjectCreated::class,
            'sync',
        ];
        yield 'catalog: object archived' => [
            \App\Catalog\Contracts\Event\ObjectArchived::class,
            'sync',
        ];
        yield 'catalog: object published' => [
            \App\Catalog\Contracts\Event\ObjectPublished::class,
            'sync',
        ];
        yield 'catalog: object attributes changed' => [
            \App\Catalog\Contracts\Event\ObjectAttributesChanged::class,
            'sync',
        ];
        yield 'catalog: object enabled changed' => [
            \App\Catalog\Contracts\Event\ObjectEnabledChanged::class,
            'sync',
        ];
        yield 'channel: category tree root attached' => [
            \App\Channel\Contracts\Event\CategoryTreeRootAttached::class,
            'sync',
        ];
        yield 'channel: channel created' => [
            \App\Channel\Contracts\Event\ChannelCreated::class,
            'sync',
        ];
        yield 'identity: user authenticated' => [
            \App\Identity\Contracts\Event\UserAuthenticated::class,
            'sync',
        ];
        yield 'identity: refresh token rotated' => [
            \App\Identity\Contracts\Event\RefreshTokenRotated::class,
            'sync',
        ];
        yield 'asset: uploaded' => [
            \App\Asset\Contracts\Event\AssetUploaded::class,
            'sync',
        ];
        yield 'asset: variant created' => [
            \App\Asset\Contracts\Event\AssetVariantCreated::class,
            'sync',
        ];

        // --- Background jobs — asynchronous ---
        yield 'catalog: object values changed (background reindex)' => [
            \App\Catalog\Application\Message\ObjectValuesChangedMessage::class,
            'async',
        ];
    }

    /**
     * @param class-string $messageClass
     */
    #[Test]
    #[DataProvider('routedMessageProvider')]
    public function eachKnownMessageClassRoutesToItsExplicitTransport(string $messageClass, string $expectedTransport): void
    {
        $locator = self::getContainer()->get('messenger.senders_locator');
        self::assertInstanceOf(SendersLocatorInterface::class, $locator);

        $reflection = new ReflectionClass($messageClass);
        $message = $reflection->newInstanceWithoutConstructor();
        $envelope = new Envelope($message);

        $senderAliases = [];
        foreach ($locator->getSenders($envelope) as $alias => $_sender) {
            $senderAliases[] = $alias;
        }

        self::assertNotEmpty(
            $senderAliases,
            \sprintf(
                '%s has no Messenger sender configured — every message class must be routed explicitly (audit MEDIUM-007).',
                $messageClass,
            ),
        );
        self::assertContains(
            $expectedTransport,
            $senderAliases,
            \sprintf(
                '%s should be routed to "%s" but is routed to "%s".',
                $messageClass,
                $expectedTransport,
                implode('|', $senderAliases),
            ),
        );
    }
}
