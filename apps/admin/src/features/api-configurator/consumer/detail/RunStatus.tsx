import { cn } from '@/lib/utils';

/**
 * APIC-P3-12 — run-status indicators for the connection-detail Overview +
 * History tabs. Keyed on the SyncRunStatus enum (running / success / partial /
 * failed).
 */
export type RunStatus = 'running' | 'success' | 'partial' | 'failed';

const DOT: Record<RunStatus, string> = {
  running: 'bg-zinc-400',
  success: 'bg-emerald-500',
  partial: 'bg-amber-500',
  failed: 'bg-rose-500',
};

const PILL: Record<RunStatus, string> = {
  running: 'bg-zinc-100 text-zinc-600',
  success: 'bg-emerald-50 text-emerald-700',
  partial: 'bg-amber-50 text-amber-800',
  failed: 'bg-rose-50 text-rose-700',
};

export function RunStatusDot({ status }: { status: RunStatus }) {
  return <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full', DOT[status])} aria-hidden />;
}

export function RunStatusPill({ status, label }: { status: RunStatus; label: string }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-md px-2 py-0.5 text-[11.5px] font-medium',
        PILL[status],
      )}
    >
      {label}
    </span>
  );
}

/** Coerce an arbitrary API status string into a known RunStatus (defensive). */
export function toRunStatus(value: string): RunStatus {
  return value === 'success' || value === 'partial' || value === 'failed' ? value : 'running';
}
