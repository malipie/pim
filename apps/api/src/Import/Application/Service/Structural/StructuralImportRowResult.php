<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Structural;

use App\Import\Domain\Enum\ImportLogLevel;

/**
 * Per-row outcome of a structural import (attribute / attribute-group).
 *
 * The creator decides the {@see $outcome} (which session counter the row
 * bumps) and appends any number of {@see $logs} — a row may succeed yet still
 * carry warnings (e.g. an unknown object_type reference that was skipped). The
 * {@see StructuralImportRunHandler} reads this back to update the session and
 * persist the ImportLog rows.
 *
 * @phpstan-type RowLog array{level: ImportLogLevel, message: string, errorType: ?string, columnName: ?string, columnValue: ?string}
 */
final class StructuralImportRowResult
{
    public const string OUTCOME_CREATED = 'created';
    public const string OUTCOME_UPDATED = 'updated';
    public const string OUTCOME_SKIPPED = 'skipped';
    public const string OUTCOME_ERROR = 'error';

    /** @var self::OUTCOME_* */
    public string $outcome = self::OUTCOME_SKIPPED;

    /** Natural key of the row (attribute/group code), for logs + progress. */
    public ?string $code = null;

    /** @var list<RowLog> */
    public array $logs = [];

    /**
     * @param self::OUTCOME_* $outcome
     */
    public function setOutcome(string $outcome): void
    {
        $this->outcome = $outcome;
    }

    public function log(
        ImportLogLevel $level,
        string $message,
        ?string $errorType = null,
        ?string $columnName = null,
        ?string $columnValue = null,
    ): void {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'errorType' => $errorType,
            'columnName' => $columnName,
            'columnValue' => $columnValue,
        ];
    }
}
