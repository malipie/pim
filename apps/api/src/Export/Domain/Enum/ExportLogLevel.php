<?php

declare(strict_types=1);

namespace App\Export\Domain\Enum;

/**
 * Severity of an `export_logs` row (PRD §5.1).
 *
 * Used by the async handler (EXP-06) to record per-row issues that the
 * UI surfaces in the session detail view (EXP-13). Errors do NOT abort
 * the export — the worker collects them and finishes; status becomes
 * `done` if any rows succeeded, `error` only on whole-job failure.
 */
enum ExportLogLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
