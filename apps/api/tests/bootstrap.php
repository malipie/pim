<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

// Test DB isolation guard — refuse to boot the test suite against a DB
// whose name does not end with `_test`. Foundry's ResetDatabase trait
// drops and re-creates the schema on first boot; without this guard a
// stale dev container (APP_ENV=dev cached) lets Foundry wipe the live
// dev DB. See apps/api/.env.test multi-line warning + feedback memory
// `feedback_phpunit_dev_db_collision.md` for the incident history.
$dsn = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
if ($dsn !== '' && preg_match('#/([^/?]+)(\?|$)#', (string) $dsn, $m) === 1) {
    $dbName = $m[1];
    if (!str_ends_with($dbName, '_test')) {
        fwrite(\STDERR, sprintf(
            "[bootstrap] ABORT: PHPUnit booted with DATABASE_URL pointing at '%s' (no '_test' suffix).\n".
            "Foundry's ResetDatabase would wipe the dev DB. Run:\n".
            "    docker compose exec api php bin/console cache:clear --env=test\n".
            "and retry. See apps/api/.env.test for the long-form explanation.\n",
            $dbName,
        ));
        exit(1);
    }
}

// LTREE extension: handled by Zenstruck Foundry ResetDatabase trait
// configured to `mode: migrate` (config/packages/zenstruck_foundry.yaml
// when@test) — the migration chain enables the extension as part of #33.
