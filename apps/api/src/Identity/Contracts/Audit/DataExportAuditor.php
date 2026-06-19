<?php

declare(strict_types=1);

namespace App\Identity\Contracts\Audit;

/**
 * AUD-052 (W2-11) — cross-context contract for the dedicated `data_export`
 * audit event (compliance / RODO).
 *
 * The generic {@see \App\Identity\Infrastructure\Audit\AuditLogListener} only
 * captures HTTP metadata (method + permission outcome, old/new null) — it never
 * records that a data export, i.e. full product data + PII, actually left the
 * system. This contract lets the Export bounded context (Deptrac: Export may
 * only reach `*_Contracts` + `Shared`) write a first-class audit entry without
 * importing the Identity `AuditLog` entity or its repository.
 *
 * The actor + tenant come from the Symfony security token via the adapter, so
 * callers only describe WHAT was exported, never WHO — keeping the surface
 * narrow and the question "who exported what, when?" answerable from
 * `audit_logs` with `action = 'data_export'`.
 */
interface DataExportAuditor
{
    /**
     * Records a `data_export` audit entry for the current principal.
     *
     * @param string               $sessionId export session id (RFC 4122) — the audit `resource_id`
     * @param array<string, mixed> $scope     what was exported (entity type, format, row count, scope, …) → audit `new_value`
     */
    public function recordExport(string $sessionId, array $scope): void;
}
