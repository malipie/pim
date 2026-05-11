import type { ImportMode, SessionStatus, SourceType } from '../primitives';

export type FilterValue = 'all' | 'success' | 'warning' | 'error' | 'cancelled';

export interface ImportSessionRow {
  id: string;
  status: ApiStatus;
  file_name: string;
  file_size_bytes?: number;
  total_rows: number | null;
  success_count: number;
  error_count: number;
  started_at: string | null;
  completed_at: string | null;
  rollback_until: string | null;
  duration_sec: number | null;
  profile_name?: string | null;
  profile_id?: string | null;
  target_object_type_code?: string;
  mode?: ImportMode;
}

export interface ThroughputResponse {
  rows_per_sec: number;
  active_sessions: number;
  window_min: number;
  sampled_at: string;
}

export type ApiStatus =
  | 'pending'
  | 'running'
  | 'paused'
  | 'success'
  | 'partial'
  | 'failed'
  | 'cancelled'
  | 'rolled_back';

const STATUS_TO_PILL: Record<ApiStatus, SessionStatus> = {
  pending: 'queued',
  running: 'running',
  paused: 'paused',
  success: 'success',
  partial: 'warning',
  failed: 'error',
  cancelled: 'cancelled',
  rolled_back: 'cancelled',
};

export function pillFor(status: ApiStatus): SessionStatus {
  return STATUS_TO_PILL[status];
}

export function filterMatches(filter: FilterValue, status: ApiStatus): boolean {
  if (filter === 'all') {
    return true;
  }
  if (filter === 'success') {
    return status === 'success';
  }
  if (filter === 'warning') {
    return status === 'partial' || status === 'paused';
  }
  if (filter === 'error') {
    return status === 'failed';
  }
  return status === 'cancelled' || status === 'rolled_back';
}

export const SOURCE_FALLBACK: SourceType = 'upload';
