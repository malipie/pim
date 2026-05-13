import { useEffect, useState } from 'react';

import { jsonFetch } from '@/lib/http';

import type { FilterDsl } from './filter-dsl';

/**
 * VIEW-09 (#535) — Smart Filter Presets client. CRUD + counts.
 *
 * Counts are server-side aggregates against the current tenant's
 * `catalog_objects` table (FilterDslResolver compiles DSL → SQL).
 * The endpoint is single-batched (no N+1), so passing `withCounts=true`
 * is cheap.
 */

export interface SmartFilterPreset {
  id: string;
  slug: string;
  name: { pl: string; en: string };
  icon: string;
  query: FilterDsl;
  is_built_in: boolean;
  is_system: boolean;
  sort_order: number;
  count?: number;
  created_at: string;
  updated_at: string;
}

interface ListResponse {
  data: SmartFilterPreset[];
}

export interface UseSmartPresetsOptions {
  withCounts?: boolean;
}

export interface UseSmartPresetsResult {
  presets: SmartFilterPreset[];
  isLoading: boolean;
  error: Error | null;
  refetch: () => Promise<void>;
  create: (input: {
    name: { pl: string; en: string };
    icon: string;
    query: FilterDsl;
  }) => Promise<SmartFilterPreset>;
  remove: (id: string) => Promise<void>;
}

export function useSmartPresets({
  withCounts = true,
}: UseSmartPresetsOptions = {}): UseSmartPresetsResult {
  const [presets, setPresets] = useState<SmartFilterPreset[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  const load = async (): Promise<void> => {
    setIsLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      if (withCounts) params.set('counts', 'true');
      const url = `/api/smart-filter-presets${params.toString() ? `?${params.toString()}` : ''}`;
      const body = await jsonFetch<ListResponse>(url);
      setPresets(body.data);
    } catch (err) {
      setError(err instanceof Error ? err : new Error(String(err)));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [withCounts]);

  const create: UseSmartPresetsResult['create'] = async (input) => {
    const created = await jsonFetch<SmartFilterPreset>('/api/smart-filter-presets', {
      method: 'POST',
      body: input,
    });
    await load();
    return created;
  };

  const remove: UseSmartPresetsResult['remove'] = async (id) => {
    await jsonFetch<unknown>(`/api/smart-filter-presets/${id}`, { method: 'DELETE' });
    await load();
  };

  return {
    presets,
    isLoading,
    error,
    refetch: load,
    create,
    remove,
  };
}
