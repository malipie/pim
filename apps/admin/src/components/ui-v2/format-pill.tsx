import { cn } from '@/lib/utils';

const FORMAT_CLASSES: Record<string, string> = {
  XLSX: 'bg-emerald-50 text-emerald-700',
  XLS: 'bg-emerald-50/70 text-emerald-700/80',
  CSV: 'bg-sky-50 text-sky-700',
  JSON: 'bg-orange-50 text-orange-700',
  XML: 'bg-amber-50 text-amber-700',
};

interface FormatPillProps {
  /** File format code, e.g. `xlsx` / `CSV` — rendered uppercase. */
  format: string;
  className?: string;
}

/** Small mono chip for a file format (XLSX / CSV / ...). */
export function FormatPill({ format, className }: FormatPillProps) {
  const code = format.toUpperCase();
  return (
    <span
      className={cn(
        'inline-flex items-center rounded px-1.5 py-0.5 font-mono text-[10.5px] font-semibold',
        FORMAT_CLASSES[code] ?? 'bg-zinc-100 text-zinc-700',
        className,
      )}
    >
      {code}
    </span>
  );
}
