import { cn } from '@/lib/utils';

export type SourceHealth = 'ok' | 'warn' | 'error' | 'off';

const HEALTH_COLORS: Record<SourceHealth, string> = {
  ok: 'bg-emerald-500',
  warn: 'bg-amber-500',
  error: 'bg-rose-500',
  off: 'bg-zinc-300',
};

export interface HealthDotProps {
  health: SourceHealth;
  ariaLabel?: string;
  className?: string;
}

export function HealthDot({ health, ariaLabel, className }: HealthDotProps) {
  return (
    <span
      className={cn('inline-block h-2 w-2 rounded-full', HEALTH_COLORS[health], className)}
      role="img"
      aria-label={ariaLabel ?? `Health: ${health}`}
    />
  );
}
