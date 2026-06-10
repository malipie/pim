import type { ExportSessionRow } from '../hooks/useExportSessions';

/** Basename of the stored file (MinIO path) — `null` until the job writes it. */
export function fileNameOf(session: ExportSessionRow): string | null {
  if (!session.file_path) return null;
  const segments = session.file_path.split('/');
  return segments[segments.length - 1] ?? session.file_path;
}

/** i18n label key for an export entity type (EXR-04 enum). */
export function entityTypeLabelKey(entityType: string): string {
  switch (entityType) {
    case 'products':
      return 'exports.entity.products';
    case 'custom_module':
      return 'exports.entity.custom_module';
    case 'module_schema':
      return 'exports.entity.module_schema';
    case 'attributes':
      return 'exports.entity.attributes';
    case 'categories':
      return 'exports.entity.categories';
    default:
      return 'exports.entity.products';
  }
}

/** `1m 23s` / `850 ms` humanized duration. */
export function formatDuration(durationMs: number | null): string {
  if (durationMs === null) return '—';
  if (durationMs < 1000) return `${durationMs} ms`;
  const seconds = Math.round(durationMs / 1000);
  if (seconds < 60) return `${seconds}s`;
  return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

/** `10.06, 14:32` start timestamp for the history table. */
export function formatStartedAt(iso: string): string {
  return new Intl.DateTimeFormat('pl-PL', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso));
}
