import { cn } from '@/lib/utils';

export type ModeBadgeMode = 'ADD' | 'UPDATE' | 'UPSERT' | 'MERGE' | 'INCREMENT' | 'DELETE';

const MODE_CLASSES: Record<ModeBadgeMode, { badge: string; dot: string }> = {
  ADD: { badge: 'bg-zinc-100 text-zinc-700', dot: 'bg-zinc-400' },
  UPDATE: { badge: 'bg-sky-50 text-sky-700', dot: 'bg-sky-500' },
  UPSERT: { badge: 'bg-orange-50 text-orange-700', dot: 'bg-orange-500' },
  MERGE: { badge: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
  INCREMENT: { badge: 'bg-amber-50 text-amber-800', dot: 'bg-amber-500' },
  DELETE: { badge: 'bg-brick-50 text-brick-700', dot: 'bg-brick-500' },
};

interface ModeBadgeProps {
  /** Operation mode; the label renders verbatim (uppercase technical code). */
  mode: ModeBadgeMode | string;
  size?: 'sm' | 'md';
  className?: string;
}

/**
 * Uppercase operation-mode chip with a colored dot (UPDATE / CREATE / ...).
 * Unknown modes fall back to the neutral zinc tint.
 */
export function ModeBadge({ mode, size = 'sm', className }: ModeBadgeProps) {
  const classes = MODE_CLASSES[mode as ModeBadgeMode] ?? MODE_CLASSES.ADD;
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 font-mono font-medium uppercase',
        size === 'sm'
          ? 'rounded px-1.5 py-0.5 text-[10.5px]'
          : 'rounded-md px-2 py-0.5 text-[11.5px]',
        classes.badge,
        className,
      )}
    >
      <span aria-hidden="true" className={cn('h-1.5 w-1.5 rounded-full', classes.dot)} />
      {mode}
    </span>
  );
}
