interface StatBoxProps {
  value: string | number;
  label: string;
}

/**
 * VIEW-01 (#372) — Where-used stat (object-types.jsx lines 297–302).
 * Big tabular-num value, small caption underneath, soft gray bg.
 */
export function StatBox({ value, label }: StatBoxProps) {
  const display = typeof value === 'number' ? value.toLocaleString('pl-PL') : value;
  return (
    <div className="rounded-2xl bg-zinc-50 px-4 py-3">
      <div className="num font-display text-[24px] font-semibold leading-none tracking-tight">
        {display}
      </div>
      <div className="mt-1.5 text-[11.5px] text-zinc-500">{label}</div>
    </div>
  );
}
