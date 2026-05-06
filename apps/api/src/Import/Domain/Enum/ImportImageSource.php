<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

enum ImportImageSource: string
{
    case Http = 'http';
    case Zip = 'zip';
    case None = 'none';
}
