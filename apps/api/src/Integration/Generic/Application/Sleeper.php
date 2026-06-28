<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application;

/**
 * Port over PHP's blocking sleep, so the backoff policy
 * ({@see \App\Integration\Generic\Infrastructure\Http\BackoffRestClient}) can
 * be unit-tested without real wall-clock delays.
 */
interface Sleeper
{
    public function sleep(int $seconds): void;
}
