import { useQuery, useQueryClient } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

/**
 * VIEW-01 (#372) — read-side hook for the workspace identity strip and
 * enabled locales. Powers `<LocaleTabsField>` (which tabs to render),
 * `<LocaleAddDialog>` (which entries to filter out) and the
 * `model schema rev …` footer on the modeling Detail view.
 */
export interface CurrentWorkspace {
  id: string;
  code: string;
  name: string;
  plan: string;
  enabledLocales: string[];
  primaryLocale: string;
}

const QUERY_KEY = ['workspaces', 'current'] as const;

export function useCurrentWorkspace() {
  return useQuery<CurrentWorkspace>({
    queryKey: QUERY_KEY,
    queryFn: () =>
      jsonFetch<CurrentWorkspace>('/api/workspaces/current', { accept: 'application/json' }),
    staleTime: 5 * 60 * 1000,
  });
}

export function useInvalidateCurrentWorkspace() {
  const client = useQueryClient();
  return () => client.invalidateQueries({ queryKey: QUERY_KEY });
}
