import { cn } from '@/lib/utils';

interface ChartSkeletonProps {
  /** Optional title placeholder height/width hint; defaults look like ActivityChart. */
  className?: string;
}

/**
 * Skeleton placeholder for a chart card (e.g. ActivityChart, ChannelDistribution).
 */
export function ChartSkeleton({ className }: ChartSkeletonProps) {
  return (
    <div
      className={cn(
        'animate-pulse rounded-2xl border border-line bg-surface p-5 soft-shadow',
        className,
      )}
    >
      <div className="flex items-baseline justify-between">
        <div className="h-4 w-40 rounded bg-muted" />
        <div className="h-3 w-24 rounded bg-muted/70" />
      </div>
      <div className="mt-5 h-[180px] w-full rounded-md bg-muted/40" />
    </div>
  );
}
