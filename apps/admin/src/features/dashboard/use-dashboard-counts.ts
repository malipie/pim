import { useQuery } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

interface LdCollection {
  totalItems?: number;
  'hydra:totalItems'?: number;
}

/** KPI tiles whose VALUE is live (entity totals). Deltas stay mocked — see backlog. */
export type LiveKpiKey = 'products' | 'attributes' | 'families' | 'categories';

export const LIVE_KPI_KEYS: readonly LiveKpiKey[] = [
  'products',
  'attributes',
  'families',
  'categories',
];

// "families" is the legacy KPI key — the counter reads attribute groups
// (Family is deprecated per ADR-009; the label says "Grupy atrybutów").
const ENDPOINTS: Record<LiveKpiKey, string> = {
  products: '/api/products',
  attributes: '/api/attributes',
  families: '/api/attribute_groups',
  categories: '/api/categories',
};

async function fetchTotal(path: string): Promise<number | null> {
  try {
    const data = await jsonFetch<LdCollection>(path, {
      accept: 'application/ld+json',
      query: { itemsPerPage: 1 },
    });
    return data.totalItems ?? data['hydra:totalItems'] ?? null;
  } catch {
    // A failed count degrades to the mock value — never blocks the dashboard.
    return null;
  }
}

/**
 * NUI-02 (#1421) — live entity totals for the KPI row, same cheap
 * `itemsPerPage=1 → totalItems` pattern as `use-nav-counts` / modeling tabs.
 */
export function useDashboardCounts() {
  return useQuery({
    queryKey: ['dashboard-counts'],
    staleTime: 60_000,
    refetchOnWindowFocus: false,
    queryFn: async (): Promise<Partial<Record<LiveKpiKey, number>>> => {
      const entries = await Promise.all(
        LIVE_KPI_KEYS.map(async (key) => [key, await fetchTotal(ENDPOINTS[key])] as const),
      );
      const counts: Partial<Record<LiveKpiKey, number>> = {};
      for (const [key, value] of entries) {
        if (value !== null) {
          counts[key] = value;
        }
      }
      return counts;
    },
  });
}
