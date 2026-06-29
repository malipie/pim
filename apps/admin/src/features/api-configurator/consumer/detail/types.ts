import type { SyncDirection } from '../../components/primitives';

/**
 * APIC-P3-12 — shared row types + tab ids for the connection-detail view.
 */
export const DETAIL_TABS = ['overview', 'endpoints', 'mapping', 'sync', 'history'] as const;
export type DetailTab = (typeof DETAIL_TABS)[number];

export function toDetailTab(value: string | null): DetailTab {
  return DETAIL_TABS.includes(value as DetailTab) ? (value as DetailTab) : 'overview';
}

export interface SyncBindingRow {
  id: string;
  connectionId: string;
  direction: SyncDirection;
  schedule: string | null;
  conflictPolicy: string;
  matchKeyMapping: string | null;
  cursor: { field?: string; type?: string; state?: unknown } | null;
  isEnabled: boolean;
  nextRun: string | null;
}

export interface SyncRunRow {
  id: string;
  bindingId: string;
  direction: SyncDirection;
  startedAt: string;
  finishedAt: string | null;
  status: string;
  createdCount: number;
  updatedCount: number;
  skippedCount: number;
  failedCount: number;
  cursorAfter: { state?: unknown } | null;
}

export interface SyncRunLogRow {
  id: string;
  runId: string;
  matchKey: string | null;
  action: string;
  message: string | null;
  createdAt: string;
}
