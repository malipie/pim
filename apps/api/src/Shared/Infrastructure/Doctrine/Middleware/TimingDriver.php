<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Middleware;

use App\Shared\Infrastructure\Metrics\QueryDurationHistogram;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

final class TimingDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly QueryDurationHistogram $histogram,
    ) {
        parent::__construct($driver);
    }

    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        return new TimingConnection(parent::connect($params), $this->histogram);
    }
}
