<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Middleware;

use App\Shared\Infrastructure\Metrics\QueryDurationHistogram;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class TimingStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly QueryDurationHistogram $histogram,
    ) {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        $start = hrtime(true);
        try {
            return parent::execute();
        } finally {
            $this->histogram->observe((hrtime(true) - $start) / 1_000_000_000);
        }
    }
}
