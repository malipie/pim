<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetObjectTypeSummary;

use Symfony\Component\Uid\Uuid;

final readonly class GetObjectTypeSummaryQuery
{
    public function __construct(public Uuid $objectTypeId)
    {
    }
}
