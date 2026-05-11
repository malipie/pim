import type { ImportMode } from '../primitives';

export type ProfileViewMode = 'grid' | 'list';

export interface ImportProfileRow {
  id: string;
  name: string;
  code: string;
  mode: ImportMode;
  targetObjectType?: { code?: string; id?: string } | string;
  columnMapping?: Record<string, string>;
  locale: string | null;
  encoding: string | null;
  delimiter: string | null;
  lastUsedAt: string | null;
  createdAt: string;
}

export function columnsCount(row: ImportProfileRow): number {
  return Object.keys(row.columnMapping ?? {}).length;
}

export function detectFormat(row: ImportProfileRow): 'XLSX' | 'XLS' | 'CSV' | 'JSON' | 'XML' {
  const delim = (row.delimiter ?? '').trim();
  // CSV is the wizard default when no XLSX-specific hint is present.
  if (delim === ',' || delim === ';' || delim === '\t' || delim === '|') {
    return 'CSV';
  }
  return 'XLSX';
}

export function targetCodeOf(row: ImportProfileRow): string {
  if (typeof row.targetObjectType === 'string') {
    return row.targetObjectType;
  }
  return row.targetObjectType?.code ?? '—';
}
