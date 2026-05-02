import { cn } from '@/lib/utils';

interface ListSkeletonProps {
  rows?: number;
  className?: string;
}

/**
 * Skeleton placeholder for list cards (TopEditedProducts, AlertCenter,
 * RecentAgentActivity, SyncsStatusPanel, CompletenessMetrics).
 */
export function ListSkeleton({ rows = 5, className }: ListSkeletonProps) {
  return (
    <div
      className={cn(
        'animate-pulse rounded-2xl border border-line bg-surface p-5 soft-shadow',
        className,
      )}
    >
      <div className="flex items-baseline justify-between">
        <div className="h-4 w-32 rounded bg-muted" />
        <div className="h-3 w-20 rounded bg-muted/70" />
      </div>
      <div className="mt-4 space-y-3">
        {Array.from({ length: rows }).map((_, i) => (
          <div
            // biome-ignore lint/suspicious/noArrayIndexKey: static skeleton row
            key={i}
            className="flex items-center gap-3"
          >
            <div className="size-8 rounded-md bg-muted/60" />
            <div className="flex-1 space-y-1.5">
              <div className="h-3 w-3/4 rounded bg-muted" />
              <div className="h-2.5 w-1/2 rounded bg-muted/60" />
            </div>
            <div className="h-3 w-12 rounded bg-muted/60" />
          </div>
        ))}
      </div>
    </div>
  );
}
