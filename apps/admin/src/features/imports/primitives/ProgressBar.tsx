export interface ProgressBarProps {
  value: number;
  height?: number;
  animated?: boolean;
  className?: string;
  ariaLabel?: string;
}

export function ProgressBar({
  value,
  height = 8,
  animated = true,
  className,
  ariaLabel,
}: ProgressBarProps) {
  const pct = Math.max(0, Math.min(1, value)) * 100;
  return (
    <div
      className={`relative rounded-full bg-zinc-100 overflow-hidden ${className ?? ''}`.trim()}
      style={{ height }}
      role="progressbar"
      aria-valuenow={Math.round(pct)}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-label={ariaLabel ?? 'Postęp importu'}
    >
      <div
        className="absolute inset-y-0 left-0 bg-zinc-900 rounded-full transition-all"
        style={{ width: `${pct}%` }}
      >
        {animated ? <div className="absolute inset-0 shimmer" aria-hidden="true" /> : null}
      </div>
    </div>
  );
}
