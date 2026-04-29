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

// LTREE extension: handled by Zenstruck Foundry ResetDatabase trait
// configured to `mode: migrate` (config/packages/zenstruck_foundry.yaml
// when@test) — the migration chain enables the extension as part of #33.
