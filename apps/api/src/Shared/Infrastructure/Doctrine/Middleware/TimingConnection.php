<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Middleware;

use App\Shared\Infrastructure\Metrics\QueryDurationHistogram;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class TimingConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        Connection $connection,
        private readonly QueryDurationHistogram $histogram,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new TimingStatement(parent::prepare($sql), $this->histogram);
    }

    public function query(string $sql): Result
    {
        $start = hrtime(true);
        try {
            return parent::query($sql);
        } finally {
            $this->histogram->observe((hrtime(true) - $start) / 1_000_000_000);
        }
    }

    public function exec(string $sql): int|string
    {
        $start = hrtime(true);
        try {
            return parent::exec($sql);
        } finally {
            $this->histogram->observe((hrtime(true) - $start) / 1_000_000_000);
        }
    }
}
