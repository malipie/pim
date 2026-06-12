import { cn } from '@/lib/utils';

export type TinyKpiAccent = 'emerald' | 'rose' | 'sky' | 'amber' | 'zinc' | 'violet';

const ACCENT_COLORS: Record<TinyKpiAccent, string> = {
  emerald: 'text-emerald-700',
  rose: 'text-rose-700',
  sky: 'text-sky-700',
  amber: 'text-amber-800',
  zinc: 'text-zinc-900',
  violet: 'text-orange-700',
};

export interface TinyKpiProps {
  label: string;
  value: string | number;
  unit?: string;
  trend?: number;
  accent?: TinyKpiAccent;
  className?: string;
}

export function TinyKpi({ label, value, unit, trend, accent = 'zinc', className }: TinyKpiProps) {
  return (
    <div className={cn('flex flex-col', className)}>
      <div className="text-[10.5px] uppercase tracking-wider text-zinc-500 font-medium">
        {label}
      </div>
      <div className="flex items-baseline gap-1 mt-0.5">
        <div
          className={cn(
            'font-display text-[20px] font-semibold tracking-tight num',
            ACCENT_COLORS[accent],
          )}
        >
          {value}
        </div>
        {unit ? <div className="text-[12px] text-zinc-500">{unit}</div> : null}
        {trend != null ? (
          <div
            className={cn(
              'text-[11px] num font-medium',
              trend >= 0 ? 'text-emerald-600' : 'text-rose-600',
            )}
          >
            {trend >= 0 ? '▲' : '▼'} {Math.abs(trend)}
          </div>
        ) : null}
      </div>
    </div>
  );
}
