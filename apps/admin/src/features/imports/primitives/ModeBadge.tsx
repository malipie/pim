import { cn } from '@/lib/utils';

export type ImportMode = 'CREATE' | 'UPDATE' | 'UPSERT';
export type ModeBadgeSize = 'sm' | 'md';

const MODE_STYLES: Record<ImportMode, { bg: string; fg: string; dot: string }> = {
  CREATE: { bg: 'bg-zinc-100', fg: 'text-zinc-700', dot: 'bg-zinc-400' },
  UPDATE: { bg: 'bg-sky-50', fg: 'text-sky-700', dot: 'bg-sky-500' },
  UPSERT: { bg: 'bg-orange-50', fg: 'text-orange-700', dot: 'bg-orange-500' },
};

const SIZE_CLASSES: Record<ModeBadgeSize, string> = {
  sm: 'text-[10.5px] font-mono px-1.5 py-0.5 rounded',
  md: 'text-[11.5px] font-mono px-2 py-0.5 rounded-md',
};

export interface ModeBadgeProps {
  mode: ImportMode;
  size?: ModeBadgeSize;
  className?: string;
}

export function ModeBadge({ mode, size = 'sm', className }: ModeBadgeProps) {
  const style = MODE_STYLES[mode];
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 font-medium',
        SIZE_CLASSES[size],
        style.bg,
        style.fg,
        className,
      )}
    >
      <span className={cn('h-1.5 w-1.5 rounded-full', style.dot)} />
      {mode}
    </span>
  );
}
