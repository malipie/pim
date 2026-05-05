<?php

declare(strict_types=1);

namespace App\Asset\Domain;

/**
 * Lifecycle of the derivative pipeline (`thumb` 200×200 + `medium` 800×800).
 *
 * `pending` is the row's default after `POST /api/assets`; the worker
 * transitions it to `ready` once both Imagick variants land in storage,
 * or to `failed` if the source is unreadable / unsupported. The grid
 * polls until `pending` resolves.
 */
enum ThumbnailsStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
