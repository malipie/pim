<?php

declare(strict_types=1);

/*
 * PHPStan Doctrine extension — ObjectManager loader.
 * Allows PHPStan to type-check query builder return types against entity metadata.
 */

use App\Kernel;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload_runtime.php';

new Dotenv()->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

/** @var Registry $registry */
$registry = $kernel->getContainer()->get('doctrine');

return $registry->getManager();
