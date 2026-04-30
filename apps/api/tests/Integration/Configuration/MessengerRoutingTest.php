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
 * Locks in the explicit Messenger transport routing posture for
 * async-routed messages (audit MEDIUM-007).
 *
 * Async-routed message classes (those that target a non-`sync`
 * transport) MUST have an entry in `config/packages/messenger.yaml`
 * routing block — implicit/default behavior diverges silently between
 * environments once an async transport ships in production.
 *
 * Synchronous messages (Application Commands, Domain Events handled
 * by in-process subscribers) intentionally stay UNROUTED — Symfony's
 * default in-process dispatch is the established and tested behavior;
 * routing them through the `sync` transport adds a Send → Receive hop
 * that under bulk seed paths shows up as ErrorChunk accumulation
 * before GC reclaims them.
 */
final class MessengerRoutingTest extends KernelTestCase
{
    /**
     * Sources of truth — must match the `routing:` block in
     * `messenger.yaml`. Each async-routed message class is enumerated
     * here so a future deletion / typo flips this test red.
     *
     * @return iterable<string, array{class-string, string}>
     */
    public static function routedMessageProvider(): iterable
    {
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
    public function eachAsyncRoutedMessageClassPointsAtItsExplicitTransport(string $messageClass, string $expectedTransport): void
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
                '%s has no Messenger sender configured — async-routed messages must be routed explicitly (audit MEDIUM-007).',
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
