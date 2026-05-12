<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\EventListener;

use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Idempotently enable the Postgres extensions our schema depends on.
 *
 * `objects.path` (#33) is `LTREE`. The extension is created in migration
 * `Version20260428222056`, but Zenstruck Foundry's `ResetDatabase` trait
 * recreates the test database from ORM metadata via `doctrine:schema:
 * update` — it never replays migrations, so the extension would be
 * missing on the test schema. This listener fires once per kernel boot
 * (before any test SQL flows) and pokes `CREATE EXTENSION IF NOT EXISTS`.
 *
 * Cheap: a `SELECT 1` is the same wire-cost as `CREATE EXTENSION IF NOT
 * EXISTS` against an extension that already exists. Listener registered
 * via the kernel.request event so it runs during boot of every dev/test
 * request without touching the request pipeline.
 *
 * Production deployments rely on the migration; the listener is a safety
 * net for environments that bypass migrations (test DB resets, ephemeral
 * dev volumes recreated outside the migration flow).
 */
final class PostgresExtensionLoader
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): void
    {
        // No `loaded` cache: Foundry's ResetDatabase drops + recreates the
        // database between test runs, which evicts the extension. Running
        // `CREATE EXTENSION IF NOT EXISTS` is cheap (existence check)
        // and the only correct answer once we cannot trust the DB to be
        // the same between two boots in the same process.
        try {
            // tenant-safe: infrastructure DDL — CREATE EXTENSION
            // operates on the database cluster, no row data is read
            // or written.
            $this->connection->executeStatement('CREATE EXTENSION IF NOT EXISTS ltree');
        } catch (Throwable) {
            // Best-effort: if the extension cannot be created (no
            // permission, DB not yet created in early-boot test wiring),
            // we let downstream Doctrine code surface the real error
            // instead of masking it here.
        }
    }
}
