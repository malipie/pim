import { cn } from '@/lib/utils';

export type SessionStatus =
  | 'success'
  | 'warning'
  | 'error'
  | 'running'
  | 'queued'
  | 'cancelled'
  | 'paused';

const STATUS_STYLES: Record<SessionStatus, { bg: string; fg: string; dot: string }> = {
  success: { bg: 'bg-emerald-50', fg: 'text-emerald-700', dot: 'bg-emerald-500' },
  warning: { bg: 'bg-amber-50', fg: 'text-amber-800', dot: 'bg-amber-500' },
  error: { bg: 'bg-rose-50', fg: 'text-rose-700', dot: 'bg-rose-500' },
  running: { bg: 'bg-sky-50', fg: 'text-sky-700', dot: 'bg-sky-500' },
  queued: { bg: 'bg-zinc-100', fg: 'text-zinc-600', dot: 'bg-zinc-400' },
  cancelled: { bg: 'bg-zinc-100', fg: 'text-zinc-500', dot: 'bg-zinc-400' },
  paused: { bg: 'bg-amber-50', fg: 'text-amber-700', dot: 'bg-amber-500' },
};

export interface StatusPillProps {
  status: SessionStatus;
  label: string;
  className?: string;
}

export function StatusPill({ status, label, className }: StatusPillProps) {
  const style = STATUS_STYLES[status];
  const pulse = status === 'running';
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 text-[11.5px] font-medium px-2 py-0.5 rounded-md',
        style.bg,
        style.fg,
        className,
      )}
    >
      <span className={cn('h-1.5 w-1.5 rounded-full', style.dot, pulse && 'pulse-dot')} />
      {label}
    </span>
  );
}
