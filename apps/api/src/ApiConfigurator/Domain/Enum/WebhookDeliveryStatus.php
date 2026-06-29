<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Enum;

/**
 * Lifecycle of a webhook delivery attempt record (APIC-P4-05).
 *
 * `pending` — enqueued, not yet attempted. `delivered` — the remote returned a
 * 2xx. `failed` — the last attempt failed; while Messenger still has retries the
 * row stays `failed` with a rising `attempts`, and once retries are exhausted
 * the envelope dead-letters to the `failed` transport (the row's terminal state
 * is `failed` with `attempts` at the retry ceiling).
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
