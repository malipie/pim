<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Enum;

/**
 * Wire-format an ApiProfile renders.
 *
 * `JSON_LD` is the API Platform default (Hydra context, IRIs, embedded
 * relations). `JSON` is a flat shape we hand-roll for partners that
 * cannot ingest JSON-LD — fields only, no `@context`/`@id`/`@var`.
 */
enum OutputFormat: string
{
    case JSON_LD = 'json_ld';
    case JSON = 'json';
}
