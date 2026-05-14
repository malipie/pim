import { useCallback, useEffect, useState } from 'react';

import { jsonFetch } from '@/lib/http';

/**
 * VIEW-27 (#558) — per-user attribute favorites hook.
 *
 * MVP shape: load on mount, optimistic toggle (refetch on error). React
 * Query was considered but the picker only ever holds ≤10 favorites
 * per user; a lightweight cache is enough.
 */

export interface FavoriteEntry {
  attribute_id: string;
  code: string;
  label: Record<string, string> | string | null;
  sort_order: number;
}

interface FavoritesResponse {
  favorites: FavoriteEntry[];
}

export interface UseFilterFavoritesResult {
  favorites: FavoriteEntry[];
  isLoading: boolean;
  toggle: (attributeId: string) => Promise<void>;
  replace: (attributeIds: string[]) => Promise<void>;
}

export const MAX_FAVORITES = 10;

export function useFilterFavorites(): UseFilterFavoritesResult {
  const [favorites, setFavorites] = useState<FavoriteEntry[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const reload = useCallback(async (): Promise<void> => {
    setIsLoading(true);
    try {
      const response = await jsonFetch<FavoritesResponse>('/api/users/me/filter-favorites');
      setFavorites(response.favorites);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const replace = useCallback(async (attributeIds: string[]): Promise<void> => {
    const trimmed = attributeIds.slice(0, MAX_FAVORITES);
    const response = await jsonFetch<FavoritesResponse>('/api/users/me/filter-favorites', {
      method: 'PUT',
      body: { attribute_ids: trimmed },
    });
    setFavorites(response.favorites);
  }, []);

  const toggle = useCallback(
    async (attributeId: string): Promise<void> => {
      const currentIds = favorites.map((f) => f.attribute_id);
      const isFavorite = currentIds.includes(attributeId);
      const nextIds = isFavorite
        ? currentIds.filter((id) => id !== attributeId)
        : [...currentIds, attributeId].slice(0, MAX_FAVORITES);
      await replace(nextIds);
    },
    [favorites, replace],
  );

  return { favorites, isLoading, toggle, replace };
}
