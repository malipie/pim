import { useQuery } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

interface LdCollection {
  totalItems?: number;
  'hydra:totalItems'?: number;
}

/** Publish-ready threshold: a product at/above this completeness counts as ready. */
export const PUBLISH_READY_THRESHOLD = 80;

export interface CompletenessBucket {
  /** Lower bound (inclusive) of the bucket, in completeness percent. */
  gte: number;
  /** Number of products at or above {@link gte}. */
  count: number;
}

export interface DashboardCompleteness {
  /** Total product count (the `>= 0` bucket). */
  total: number;
  /** Products at/above {@link PUBLISH_READY_THRESHOLD}. */
  publishReady: number;
  /** Share of {@link publishReady} over {@link total}, 0–100 (rounded). */
  publishReadyPct: number;
  /** Cumulative "at least N%" counts for the distribution strip. */
  buckets: CompletenessBucket[];
}

/**
 * Cumulative thresholds the ring + distribution strip read. `total` is the
 * unfiltered count; the rest are `?completeness[gte]=N` cumulative counts.
 */
const THRESHOLDS = [25, 50, PUBLISH_READY_THRESHOLD, 100] as const;

async function fetchCount(query: Record<string, string | number>): Promise<number | null> {
  try {
    const data = await jsonFetch<LdCollection>('/api/products', {
      accept: 'application/ld+json',
      query: { itemsPerPage: 1, ...query },
    });
    return data.totalItems ?? data['hydra:totalItems'] ?? null;
  } catch {
    return null;
  }
}

/**
 * AUD-058 (#1610) — real overall completeness for the dashboard ring.
 *
 * The backend has no completeness aggregate endpoint, but the products
 * collection exposes the indexed `completeness[gte]=N` numeric-range filter
 * (`CompletenessFilter`, sargable on `completeness_pct`). A handful of cheap
 * `itemsPerPage=1 → totalItems` counts give a genuine publish-readiness
 * figure instead of the hard-coded mock ring.
 *
 * Channel-scoped completeness stays mock: channel-aware completeness is
 * parked until ChannelObjectTypeMapping reads land (epic 0.6), so the
 * per-channel rings remain demonstrative under the dashboard banner.
 */
export function useDashboardCompleteness() {
  return useQuery({
    queryKey: ['dashboard-completeness'],
    staleTime: 60_000,
    refetchOnWindowFocus: false,
    queryFn: async (): Promise<DashboardCompleteness | null> => {
      const [total, ...thresholdCounts] = await Promise.all([
        fetchCount({}),
        ...THRESHOLDS.map((gte) => fetchCount({ 'completeness[gte]': gte })),
      ]);

      if (total === null) {
        // Without a total we cannot compute a share — degrade to the mock.
        return null;
      }

      const buckets: CompletenessBucket[] = THRESHOLDS.map((gte, i) => ({
        gte,
        count: thresholdCounts[i] ?? 0,
      }));

      const publishReady = buckets.find((b) => b.gte === PUBLISH_READY_THRESHOLD)?.count ?? 0;
      const publishReadyPct = total > 0 ? Math.round((publishReady / total) * 100) : 0;

      return { total, publishReady, publishReadyPct, buckets };
    },
  });
}
