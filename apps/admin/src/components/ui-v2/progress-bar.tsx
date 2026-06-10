import { cn } from '@/lib/utils';

interface ProgressBarProps {
  /** Progress in the 0..1 range; values outside are clamped. */
  value: number;
  height?: number;
  /** Shimmer animation while the job is running. */
  animated?: boolean;
  /** Accessible label announced with the progressbar role. */
  ariaLabel?: string;
  className?: string;
}

/**
 * Animated ink progress bar for async export sessions
 * (port of design primitives.jsx ProgressBar; shimmer from index.css).
 */
export function ProgressBar({
  value,
  height = 8,
  animated = true,
  ariaLabel,
  className,
}: ProgressBarProps) {
  const percent = Math.max(0, Math.min(1, value)) * 100;
  return (
    <div
      role="progressbar"
      aria-valuenow={Math.round(percent)}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-label={ariaLabel}
      className={cn('relative overflow-hidden rounded-full bg-zinc-100', className)}
      style={{ height }}
    >
      <div
        className="absolute inset-y-0 left-0 rounded-full bg-zinc-900 transition-all"
        style={{ width: `${percent}%` }}
      >
        {animated && <div className="shimmer absolute inset-0" />}
      </div>
    </div>
  );
}
