<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Integration;

/**
 * Outcome of an {@see InboundRecordWriter} upsert (APIC-P3-04, ADR-0022).
 *
 * `action` is one of `created|updated|skipped|failed` — the Integration sync
 * loop maps it to a `SyncRecordAction` for the run log without importing any
 * Catalog domain type. `issues` carries per-value validation messages (a value
 * that failed validation is skipped, the rest of the record still writes).
 */
final readonly class InboundUpsertResult
{
    /**
     * @param list<string> $issues
     */
    public function __construct(
        public string $action,
        public ?string $objectId,
        public array $issues = [],
    ) {
    }

    public static function failed(string $message): self
    {
        return new self('failed', null, [$message]);
    }
}
