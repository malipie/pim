import { useQuery } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

export interface SidebarObjectType {
  id: string;
  code: string;
  kind: string;
  label: Record<string, string>;
  icon: string | null;
  color: string | null;
  builtIn: boolean;
  menuPosition: number;
  hierarchical: boolean;
  hasVariants: boolean;
}

export const SIDEBAR_OBJECT_TYPES_QUERY_KEY = ['object_types', 'menu'] as const;

/**
 * VIEW-01c (#414) — Reads `GET /api/object_types/menu`, the lean payload
 * the dynamic sidebar consumes. Filtered to `display_in_menu = true`,
 * sorted by `menu_position ASC, code ASC`. Cached for 60s — sidebar
 * layout is sticky between renders, no need to revalidate per route.
 */
export function useObjectTypesMenu() {
  return useQuery<SidebarObjectType[]>({
    queryKey: SIDEBAR_OBJECT_TYPES_QUERY_KEY,
    queryFn: () =>
      jsonFetch<SidebarObjectType[]>('/api/object_types/menu', {
        accept: 'application/json',
      }),
    staleTime: 60_000,
  });
}
