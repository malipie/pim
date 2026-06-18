<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * AUD-031 / W2-3 (C-2) — guarantees `standard_conforming_strings = on` on
 * every physical Postgres connection.
 *
 * {@see \App\Catalog\Application\Filter\FilterDslResolver} compiles the
 * product/export filter DSL to *parameter-free* SQL, escaping string literals
 * by doubling single quotes (`'` → `''`). That escaping is only injection-safe
 * while `standard_conforming_strings` is on: with the setting off, Postgres
 * treats a backslash inside a literal as an escape character, so `\'` closes
 * the literal and the doubled-quote escaping leaks — re-opening the SQLi
 * vector the audit otherwise confirmed contained (`backslash_quote=safe_encoding`
 * + `standard_conforming_strings=on`).
 *
 * Postgres defaults the setting on since 9.1, but the audit demands a
 * *guarantee*, not an assumption about server configuration. This driver
 * middleware forces it on at connect time and fails loud if the server refuses
 * (so a hostile / mis-set GUC surfaces as a connection error rather than a
 * silent escaping bypass). Full PDO parameterisation of the DSL stays deferred
 * to VIEW-10; this is the defence-in-depth floor under it.
 *
 * Tagged with `doctrine.middleware` via autoconfiguration (it implements
 * {@see Middleware}), so Symfony's Doctrine bundle slots it into the driver
 * chain without touching `doctrine.yaml` — the same wiring as
 * {@see QueryTimingMiddleware}. It applies to every connection (`default` and
 * `owner`) and re-runs on each physical connect, including FrankenPHP worker
 * reuse.
 */
final readonly class StandardConformingStringsMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new StandardConformingStringsDriver($driver);
    }
}
