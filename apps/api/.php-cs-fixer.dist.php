<?php

declare(strict_types=1);

/*
 * PIM API — PHP-CS-Fixer configuration.
 *
 * Symfony preset + opinionated additions. PHP 8.4 features are enabled.
 * Cache file is gitignored (.php-cs-fixer.cache).
 */

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/bin', __DIR__.'/public', __DIR__.'/src', __DIR__.'/tests'])
    ->exclude(['var', 'vendor'])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => false,
        ],
        'native_function_invocation' => false,
        'phpdoc_separation' => true,
        'phpdoc_summary' => false,
        'php_unit_test_class_requires_covers' => false,
        'single_line_throw' => false,
        'yoda_style' => false,
    ]);
