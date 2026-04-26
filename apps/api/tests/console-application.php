<?php

declare(strict_types=1);

/*
 * PHPStan Symfony extension — console application loader.
 * Lets PHPStan resolve service IDs / argument types referenced by Symfony Commands.
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload_runtime.php';

new Dotenv()->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);

return new Application($kernel);
