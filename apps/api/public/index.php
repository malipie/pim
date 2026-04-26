<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    $env = $context['APP_ENV'] ?? 'prod';
    \assert(\is_string($env));

    return new Kernel($env, (bool) ($context['APP_DEBUG'] ?? false));
};
