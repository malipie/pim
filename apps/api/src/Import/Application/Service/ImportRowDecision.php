<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

/**
 * IMP2-1.3 (#1465) — per-row outcome of ObjectResolver::decide().
 */
enum ImportRowDecision
{
    case Create;
    case Update;
    case SkipExists;
    case SkipNoMatch;
}
