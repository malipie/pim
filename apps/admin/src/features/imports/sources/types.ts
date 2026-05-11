import type { SourceHealth, SourceType } from '../primitives';

export type SourceTypeLabel = Record<SourceType | 'webhook' | 'api', string>;

export interface ImportSourceRow {
  id: string;
  name: string;
  code: string;
  type: SourceType;
  host: string | null;
  path: string | null;
  filePattern: string | null;
  authRef: string | null;
  pollIntervalSec: number | null;
  autotrigger: boolean;
  health: SourceHealth;
  healthCheckedAt: string | null;
  healthNote: string | null;
  lastPickupAt: string | null;
  files24h: number;
  profile?: { id?: string; '@id'?: string; name?: string; code?: string } | null;
  createdAt: string;
  updatedAt: string;
}
