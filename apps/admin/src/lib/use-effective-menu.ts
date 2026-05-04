import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { jsonFetch } from '@/lib/http';

/**
 * VIEW-08 (#427) — render-ready main menu data, resolved per-locale by
 * `GET /api/menu_configuration/effective`. The sidebar consumes
 * `visible[]`; the settings/menu page additionally renders
 * `available[]` (object_types flagged exposeToMainMenu but not yet
 * promoted to the sidebar).
 */
export interface EffectiveMenuItem {
  id: string;
  kind: 'system' | 'object_type';
  ref: string;
  /** Resolved locale string for `object_type` entries; `null` for `system` (FE uses `labelKey`). */
  label: string | null;
  /** i18n key for `system` entries (e.g. `nav.dashboard`). */
  labelKey?: string;
  icon: string;
  route: string | null;
  comingSoon: boolean;
  protected: boolean;
  position?: number;
  /** Only present on `kind=object_type`. */
  objectTypeKind?: string;
  objectTypeCode?: string;
}

export interface EffectiveMenu {
  visible: EffectiveMenuItem[];
  available: EffectiveMenuItem[];
}

export interface MenuConfigurationItem {
  kind: 'system' | 'object_type';
  ref: string;
  position: number;
  visible: boolean;
}

export interface MenuConfiguration {
  id: string;
  items: MenuConfigurationItem[];
  updatedAt: string;
}

const EFFECTIVE_KEY = ['menu', 'effective'] as const;
const CONFIG_KEY = ['menu', 'configuration'] as const;

export function useEffectiveMenu() {
  return useQuery<EffectiveMenu>({
    queryKey: EFFECTIVE_KEY,
    queryFn: () =>
      jsonFetch<EffectiveMenu>('/api/menu_configuration/effective', {
        accept: 'application/json',
      }),
    staleTime: 30_000,
  });
}

export function useMenuConfiguration() {
  return useQuery<MenuConfiguration>({
    queryKey: CONFIG_KEY,
    queryFn: () =>
      jsonFetch<MenuConfiguration>('/api/menu_configuration', {
        accept: 'application/json',
      }),
    staleTime: 30_000,
  });
}

export function useReplaceMenuConfiguration() {
  const queryClient = useQueryClient();

  return useMutation<MenuConfiguration, Error, MenuConfigurationItem[]>({
    mutationFn: (items) =>
      jsonFetch<MenuConfiguration>('/api/menu_configuration', {
        method: 'PUT',
        accept: 'application/json',
        contentType: 'application/json',
        body: { items },
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: EFFECTIVE_KEY });
      queryClient.invalidateQueries({ queryKey: CONFIG_KEY });
    },
  });
}

export function useInvalidateEffectiveMenu() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: EFFECTIVE_KEY });
    queryClient.invalidateQueries({ queryKey: CONFIG_KEY });
  };
}
