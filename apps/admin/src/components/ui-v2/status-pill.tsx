import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';
import { type StatusPillVariant, statusPillLabelKey } from './status-maps';

const VARIANT_CLASSES: Record<StatusPillVariant, { pill: string; dot: string }> = {
  success: { pill: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
  warning: { pill: 'bg-orange-50 text-orange-800', dot: 'bg-orange-500' },
  partial: { pill: 'bg-orange-50 text-orange-800', dot: 'bg-orange-500' },
  error: { pill: 'bg-brick-50 text-brick-700', dot: 'bg-brick-500' },
  cancelled: { pill: 'bg-zinc-100 text-zinc-500', dot: 'bg-zinc-400' },
  queued: { pill: 'bg-zinc-100 text-zinc-600', dot: 'bg-zinc-400' },
  running: { pill: 'bg-zinc-100 text-zinc-700', dot: 'bg-zinc-500' },
};

interface StatusPillProps {
  variant: StatusPillVariant;
  /** Override the default translated label (e.g. backend-provided text). */
  label?: string;
  className?: string;
}

/**
 * Dot + label status chip (ui-v2). The `running` variant pulses its dot.
 * Map backend statuses through `exportStatusToPillVariant` in status-maps.ts.
 */
export function StatusPill({ variant, label, className }: StatusPillProps) {
  const { t } = useTranslation();
  const classes = VARIANT_CLASSES[variant];
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11.5px] font-medium',
        classes.pill,
        className,
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          'h-1.5 w-1.5 rounded-full',
          classes.dot,
          variant === 'running' && 'pulse-dot',
        )}
      />
      {label ?? t(statusPillLabelKey(variant))}
    </span>
  );
}
