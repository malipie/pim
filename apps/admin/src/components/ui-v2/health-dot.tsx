import { cn } from '@/lib/utils';

export type HealthDotHealth = 'ok' | 'warn' | 'error' | 'off';

const HEALTH_CLASSES: Record<HealthDotHealth, string> = {
  ok: 'bg-emerald-500',
  warn: 'bg-amber-500',
  error: 'bg-brick-500',
  off: 'bg-zinc-300',
};

interface HealthDotProps {
  health: HealthDotHealth;
  /** Accessible description announced by screen readers. */
  label?: string;
  className?: string;
}

/** 8px health indicator dot (port of design primitives.jsx HealthDot). */
export function HealthDot({ health, label, className }: HealthDotProps) {
  const dotClassName = cn('inline-block h-2 w-2 rounded-full', HEALTH_CLASSES[health], className);
  if (label) {
    return <span role="img" aria-label={label} className={dotClassName} />;
  }
  return <span aria-hidden="true" className={dotClassName} />;
}
