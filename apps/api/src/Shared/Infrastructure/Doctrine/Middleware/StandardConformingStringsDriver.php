<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use RuntimeException;
use SensitiveParameter;

/**
 * AUD-031 / W2-3 (C-2) — forces and verifies `standard_conforming_strings = on`
 * on each physical connection the moment it is opened.
 *
 * Runs once per connect (the only place a raw driver connection is created),
 * so the value is established before any query — including under FrankenPHP
 * worker mode, where a fresh physical connection is established lazily and
 * then reused across requests. See {@see StandardConformingStringsMiddleware}
 * for why the setting is load-bearing for FilterDSL escaping.
 */
final class StandardConformingStringsDriver extends AbstractDriverMiddleware
{
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        $connection = parent::connect($params);

        // Force the safe mode regardless of the server default, then read it
        // back and fail loud if the server did not honour it — a silent `off`
        // would re-open the FilterDSL injection vector.
        $connection->exec('SET standard_conforming_strings = on');

        $value = $connection->query('SHOW standard_conforming_strings')->fetchOne();
        if ('on' !== $value) {
            throw new RuntimeException(sprintf(
                'Refusing to use a Postgres connection with standard_conforming_strings=%s. '
                .'FilterDSL literal escaping (single-quote doubling) is only injection-safe when it is "on" (AUD-031 / W2-3).',
                \is_scalar($value) ? (string) $value : get_debug_type($value),
            ));
        }

        return $connection;
    }
}
