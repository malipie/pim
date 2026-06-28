import { Check, TriangleAlert } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * APIC-P0-05 — mapping coverage bar + type-compatibility marker
 * (`integracje/api-primitives.jsx`).
 */
export function CoverageBar({
  mapped,
  total,
  width = 120,
  ariaLabel,
}: {
  mapped: number;
  total: number;
  width?: number;
  ariaLabel?: string;
}) {
  const pct = total > 0 ? Math.round((mapped / total) * 100) : 0;
  const tone = pct >= 80 ? 'emerald' : pct >= 50 ? 'sky' : 'amber';
  const barCls = { emerald: 'bg-emerald-500', sky: 'bg-sky-500', amber: 'bg-amber-500' }[tone];
  const txtCls = { emerald: 'text-emerald-700', sky: 'text-sky-700', amber: 'text-amber-800' }[
    tone
  ];

  return (
    <div className="flex items-center gap-2.5">
      <div
        className="overflow-hidden rounded-full bg-zinc-100"
        style={{ width, height: 7 }}
        role="progressbar"
        aria-label={ariaLabel ?? `${mapped}/${total}`}
        aria-valuenow={pct}
        aria-valuemin={0}
        aria-valuemax={100}
      >
        <div
          className={cn('h-full rounded-full transition-all', barCls)}
          style={{ width: `${pct}%` }}
        />
      </div>
      <span className={cn('num text-[11.5px] font-medium', txtCls)}>
        {mapped}/{total} · {pct}%
      </span>
    </div>
  );
}

export function TypeCompat({ ok, title }: { ok: boolean; title: string }) {
  return ok ? (
    <span
      title={title}
      className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
    >
      <Check className="size-[11px]" strokeWidth={3} aria-hidden />
      <span className="sr-only">{title}</span>
    </span>
  ) : (
    <span
      title={title}
      className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-50 text-amber-600"
    >
      <TriangleAlert className="size-[11px]" strokeWidth={2.4} aria-hidden />
      <span className="sr-only">{title}</span>
    </span>
  );
}
