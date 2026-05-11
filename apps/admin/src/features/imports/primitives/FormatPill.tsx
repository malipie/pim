import { cn } from '@/lib/utils';

export type FileFormat = 'XLSX' | 'XLS' | 'CSV' | 'JSON' | 'XML';

const FORMAT_STYLES: Record<FileFormat, string> = {
  XLSX: 'bg-emerald-50 text-emerald-700',
  XLS: 'bg-emerald-50/70 text-emerald-700/80',
  CSV: 'bg-sky-50 text-sky-700',
  JSON: 'bg-violet-50 text-violet-700',
  XML: 'bg-amber-50 text-amber-700',
};

export interface FormatPillProps {
  format: FileFormat;
  className?: string;
}

export function FormatPill({ format, className }: FormatPillProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center text-[10.5px] font-mono font-semibold px-1.5 py-0.5 rounded',
        FORMAT_STYLES[format],
        className,
      )}
    >
      {format}
    </span>
  );
}
