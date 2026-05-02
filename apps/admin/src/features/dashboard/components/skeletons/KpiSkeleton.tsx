import { cn } from '@/lib/utils';

/**
 * Skeleton placeholder for KpiCards block. Matches the 4-column grid +
 * card layout so the dashboard does not jump when real data arrives.
 */
export function KpiSkeleton() {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {Array.from({ length: 4 }).map((_, i) => (
        <div
          // biome-ignore lint/suspicious/noArrayIndexKey: static skeleton row
          key={i}
          className={cn('animate-pulse rounded-2xl border border-line bg-surface p-5 soft-shadow')}
        >
          <div className="flex items-start justify-between">
            <div className="h-3 w-20 rounded bg-muted" />
            <div className="size-4 rounded bg-muted" />
          </div>
          <div className="mt-3 h-7 w-24 rounded bg-muted" />
          <div className="mt-1 h-3 w-16 rounded bg-muted/70" />
        </div>
      ))}
    </div>
  );
}
