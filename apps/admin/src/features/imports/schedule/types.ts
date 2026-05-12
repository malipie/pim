export type SchedulePriority = 'high' | 'normal' | 'low';
export type ScheduleRunStatus = 'pending' | 'success' | 'warning' | 'error';
export type NotifyChannel = 'slack' | 'email' | 'webhook';

export interface ImportScheduleRow {
  id: string;
  name: string;
  code: string;
  cron: string;
  cronHuman: string | null;
  priority: SchedulePriority;
  enabled: boolean;
  nextRun: string | null;
  lastRunAt: string | null;
  lastRunStatus: ScheduleRunStatus | null;
  lastRunDurationMs: number | null;
  source?: { id?: string; name?: string; code?: string } | null;
  profile?: { id?: string; name?: string; code?: string } | null;
  notifyChannels: ReadonlyArray<NotifyChannel>;
  notifyConfig?: Record<string, unknown>;
  createdAt: string;
  updatedAt: string;
}

export interface UpcomingScheduleEntry {
  id: string;
  name: string;
  code: string;
  priority: SchedulePriority;
  next_run: string;
}
