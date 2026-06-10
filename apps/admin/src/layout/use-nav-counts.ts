import { useQuery } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

interface LdCollection {
  totalItems?: number;
  'hydra:totalItems'?: number;
}

interface ThroughputResponse {
  active_sessions?: number;
}

export interface NavCountSource {
  /** Stable key the sidebar uses to look the count up (menu item id). */
  key: string;
  /** ObjectType UUID — counted via `/api/objects?objectType=`. */
  objectTypeId?: string;
  /** System counter kind for non-ObjectType entries. */
  system?: 'assets' | 'imports_active';
}

const COUNTS_DISABLED = import.meta.env.VITE_NAV_COUNTS === 'off';

const formatter = new Intl.NumberFormat('pl-PL');

/** `12847` → `12 847` (thin-space thousands per design). */
export function formatNavCount(value: number): string {
  return formatter.format(value);
}

async function fetchCount(source: NavCountSource): Promise<number | null> {
  try {
    if (source.objectTypeId) {
      const data = await jsonFetch<LdCollection>('/api/objects', {
        accept: 'application/ld+json',
        query: { objectType: source.objectTypeId, itemsPerPage: 1 },
      });
      return data.totalItems ?? data['hydra:totalItems'] ?? null;
    }
    if (source.system === 'assets') {
      const data = await jsonFetch<LdCollection>('/api/assets', {
        accept: 'application/ld+json',
        query: { itemsPerPage: 1 },
      });
      return data.totalItems ?? data['hydra:totalItems'] ?? null;
    }
    if (source.system === 'imports_active') {
      const data = await jsonFetch<ThroughputResponse>(
        '/api/import-sessions/throughput?windowMin=5',
        { accept: 'application/json' },
      );
      return typeof data.active_sessions === 'number' ? data.active_sessions : null;
    }
  } catch {
    // EXR-03: a failed/slow count never blocks the sidebar — the item
    // simply renders without a badge (no skeleton shift).
    return null;
  }
  return null;
}

/**
 * Sidebar item counters (EXR-03): one batched fetch for every source,
 * cached for 60 s. Disable globally with `VITE_NAV_COUNTS=off` when the
 * query cost becomes a problem.
 */
export function useNavCounts(sources: NavCountSource[]): Record<string, number> {
  const keySignature = sources.map((source) => source.key).join('|');
  const { data } = useQuery({
    queryKey: ['nav-counts', keySignature],
    enabled: !COUNTS_DISABLED && sources.length > 0,
    staleTime: 60_000,
    gcTime: 5 * 60_000,
    refetchOnWindowFocus: false,
    queryFn: async (): Promise<Record<string, number>> => {
      const results = await Promise.all(
        sources.map(async (source) => [source.key, await fetchCount(source)] as const),
      );
      const counts: Record<string, number> = {};
      for (const [key, value] of results) {
        if (value !== null) {
          counts[key] = value;
        }
      }
      return counts;
    },
  });
  return data ?? {};
}
