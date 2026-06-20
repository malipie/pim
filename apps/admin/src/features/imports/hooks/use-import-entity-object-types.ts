import { useQuery } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

export interface ImportObjectTypeRow {
  id: string;
  code: string;
  kind?: string;
  builtIn?: boolean;
  label?: Record<string, string>;
}

interface ObjectTypesResponse {
  member?: ImportObjectTypeRow[];
  'hydra:member'?: ImportObjectTypeRow[];
}

/**
 * #1678 — all ObjectTypes for the import entity-tile step (StepEntityType
 * derives built-in product/category + the custom list). Kept in its own hook
 * so the transport (`jsonFetch`) lives in the data layer via `useQuery` and the
 * step component stays clear of the jsonFetch+useEffect guard (ADR-0021): the
 * cached query is mutation-reactive, and the component's own `useEffect` only
 * syncs local wizard state, never loads server data.
 */
export function useImportEntityObjectTypes() {
  return useQuery({
    queryKey: ['object-types', 'import-entity'],
    staleTime: 60_000,
    queryFn: async (): Promise<ImportObjectTypeRow[]> => {
      const response = await jsonFetch<ObjectTypesResponse>('/api/object_types?pagination=false');
      return response.member ?? response['hydra:member'] ?? [];
    },
  });
}
