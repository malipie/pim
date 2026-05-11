export interface ResultBarProps {
  ok: number;
  warn: number;
  err: number;
  total?: number;
  width?: number;
  height?: number;
  className?: string;
}

export function ResultBar({
  ok,
  warn,
  err,
  total,
  width = 140,
  height = 8,
  className,
}: ResultBarProps) {
  const t = total ?? (ok + warn + err || 1);
  const okPct = (ok / t) * 100;
  const warnPct = (warn / t) * 100;
  const errPct = (err / t) * 100;
  return (
    <div className={`flex items-center gap-2 ${className ?? ''}`.trim()}>
      <div
        className="flex rounded-full overflow-hidden bg-zinc-100"
        style={{ width, height }}
        role="img"
        aria-label={`OK: ${ok}, ostrzeżenia: ${warn}, błędy: ${err}`}
      >
        <div className="bg-emerald-500" style={{ width: `${okPct}%` }} />
        <div className="bg-amber-400" style={{ width: `${warnPct}%` }} />
        <div className="bg-rose-500" style={{ width: `${errPct}%` }} />
      </div>
    </div>
  );
}
