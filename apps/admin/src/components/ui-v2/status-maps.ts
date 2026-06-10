/**
 * Single place mapping backend statuses onto ui-v2 visual variants.
 * Spec: Project Plan/UI/feature-exports-redesign-tickets.md §EXR-02.
 */

/** Visual variants understood by `<StatusPill>`. */
export type StatusPillVariant =
  | 'success'
  | 'warning'
  | 'partial'
  | 'error'
  | 'cancelled'
  | 'queued'
  | 'running';

/** Backend `ExportStatus` enum values (apps/api ExportStatus.php). */
export type ExportStatus = 'pending' | 'running' | 'done' | 'error';

/** Map an `ExportSession.status` onto a pill variant. */
export function exportStatusToPillVariant(status: ExportStatus | string): StatusPillVariant {
  switch (status) {
    case 'done':
      return 'success';
    case 'running':
      return 'running';
    case 'error':
      return 'error';
    case 'cancelled':
      return 'cancelled';
    default:
      return 'queued';
  }
}

/** i18n key (in the `ui_v2.status` namespace) for a pill variant. */
export function statusPillLabelKey(variant: StatusPillVariant): string {
  return `ui_v2.status.${variant}`;
}
