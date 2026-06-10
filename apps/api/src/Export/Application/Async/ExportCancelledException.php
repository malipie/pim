<?php

declare(strict_types=1);

namespace App\Export\Application\Async;

use RuntimeException;

/**
 * EXR-15 — thrown by the per-chunk progress callback when the persisted
 * session status flipped to `cancelled` (user pressed Anuluj). The async
 * handler catches it, removes the partial temp file and exits without
 * marking the session as error.
 */
final class ExportCancelledException extends RuntimeException
{
}
