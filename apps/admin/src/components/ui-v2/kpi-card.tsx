import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';
import { Sparkline } from './sparkline';

interface KpiCardProps {
  /** Uppercase 11px label above the value (already translated). */
  label: string;
  /** Main value, 28px bold — formatted by the caller (e.g. Intl.NumberFormat). */
  value: ReactNode;
  /** Secondary line under the value (e.g. `✓0 ⚠1 ✗0`, throughput). */
  sub?: ReactNode;
  /** Optional icon rendered in the top-right corner. */
  icon?: ReactNode;
  /** Optional mini trend sparkline data. */
  trend?: number[];
  className?: string;
}

/**
 * KPI card for the exports page header strip (screen 1):
 * white card, uppercase label, large mono-spaced value, optional sub-line,
 * corner icon and trend sparkline.
 */
export function KpiCard({ label, value, sub, icon, trend, className }: KpiCardProps) {
  return (
    <div
      className={cn(
        'relative rounded-2xl border border-zinc-200 bg-surface p-5 shadow-card',
        className,
      )}
    >
      {icon && (
        <span
          aria-hidden="true"
          className="absolute top-4 right-4 grid h-8 w-8 place-items-center rounded-xl bg-zinc-100 text-zinc-500"
        >
          {icon}
        </span>
      )}
      <div className="text-[11px] font-medium tracking-wider text-zinc-500 uppercase">{label}</div>
      <div className="num font-display mt-1 text-[28px] font-semibold tracking-tight text-ink">
        {value}
      </div>
      {sub && <div className="mt-0.5 font-mono text-[11.5px] text-zinc-500">{sub}</div>}
      {trend && trend.length > 1 && (
        <div className="mt-2">
          <Sparkline data={trend} width={120} height={24} />
        </div>
      )}
    </div>
  );
}
