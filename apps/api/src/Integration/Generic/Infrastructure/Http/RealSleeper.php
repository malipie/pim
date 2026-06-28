<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Application\Sleeper;

final class RealSleeper implements Sleeper
{
    public function sleep(int $seconds): void
    {
        if ($seconds > 0) {
            \sleep($seconds);
        }
    }
}
