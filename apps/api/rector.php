<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/bin',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/var',
        __DIR__.'/vendor',
        __DIR__.'/tools',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SymfonySetList::SYMFONY_74,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_ORM_30,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
    ])
    ->withImportNames(importShortClasses: false)
    ->withParallel();
