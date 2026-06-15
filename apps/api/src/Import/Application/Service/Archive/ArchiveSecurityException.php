<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Archive;

use RuntimeException;

/**
 * IMP2-2.8 (#1484) — thrown by {@see XlsxArchiveGuard} when an uploaded XLSX
 * looks like a zip bomb (too many entries, oversized after decompression, or an
 * extreme compression ratio). Presentation maps it to RFC 7807 422; the message
 * is user-facing and deliberately suggests converting to CSV rather than leaking
 * which threshold tripped.
 */
final class ArchiveSecurityException extends RuntimeException
{
}
