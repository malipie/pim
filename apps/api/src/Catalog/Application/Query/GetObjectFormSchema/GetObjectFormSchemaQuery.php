<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetObjectFormSchema;

use Symfony\Component\Uid\Uuid;

final readonly class GetObjectFormSchemaQuery
{
    public function __construct(
        public Uuid $objectId,
    ) {
    }
}
