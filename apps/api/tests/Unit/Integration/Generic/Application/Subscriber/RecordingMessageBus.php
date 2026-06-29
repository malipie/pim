<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Subscriber;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Test double for {@see MessageBusInterface} that records dispatched messages
 * (so the outbound-trigger subscriber's enqueues can be asserted).
 */
final class RecordingMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message);
    }
}
