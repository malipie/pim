import * as React from 'react';

import { cn } from '@/lib/utils';

interface ProgressProps extends React.HTMLAttributes<HTMLDivElement> {
  /** 0 ≤ value ≤ max — the progress amount. */
  value: number;
  max?: number;
  /** Forward an accessible label so screen readers announce the bar. */
  ariaLabel?: string;
}

/**
 * Plain CSS progress bar — no radix dependency. Chosen over
 * `@radix-ui/react-progress` because the wizard's needs are basic
 * (linear bar, no indeterminate state) and the bundle stays smaller
 * for the React 19 worker runtime.
 */
export const Progress = React.forwardRef<HTMLDivElement, ProgressProps>(
  ({ value, max = 100, ariaLabel, className, ...props }, ref) => {
    const clamped = Math.max(0, Math.min(value, max));
    const percent = max === 0 ? 0 : (clamped / max) * 100;

    return (
      <div
        ref={ref}
        role="progressbar"
        aria-valuenow={clamped}
        aria-valuemin={0}
        aria-valuemax={max}
        aria-label={ariaLabel}
        className={cn('relative h-2 w-full overflow-hidden rounded-full bg-muted', className)}
        {...props}
      >
        <div
          className="h-full bg-primary transition-all duration-200 ease-out"
          style={{ width: `${percent}%` }}
        />
      </div>
    );
  },
);
Progress.displayName = 'Progress';
