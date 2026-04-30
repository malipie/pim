import { useEffect, useState } from 'react';

import { jsonFetch } from '@/lib/http';

/**
 * Resolves the built-in `kind=product` ObjectType for the current tenant.
 *
 * The CatalogObjectInput DTO from #41 requires `objectTypeId` on every
 * POST. Until the schema picker UI lands (epic 0.6 + ADR-009 follow-up),
 * we transparently auto-pick the built-in row that the seeder always
 * writes per tenant. Loading + error states are surfaced so the create
 * page can hold the submit button while we wait.
 */
interface ObjectType {
  id: string;
  code: string;
  kind: string;
  builtIn: boolean;
}

interface HydraCollection<T> {
  member: T[];
  totalItems: number;
}

interface UseDefaultObjectTypeState {
  objectTypeId: string | null;
  isLoading: boolean;
  error: Error | null;
}

export function useDefaultObjectType(
  kind: 'product' | 'category' | 'asset',
): UseDefaultObjectTypeState {
  const [objectTypeId, setObjectTypeId] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    jsonFetch<HydraCollection<ObjectType>>('/api/object_types')
      .then((response) => {
        if (cancelled) return;
        const match = (response.member ?? []).find((entry) => entry.kind === kind && entry.builtIn);
        setObjectTypeId(match?.id ?? null);
        setError(null);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setError(err instanceof Error ? err : new Error(String(err)));
        setObjectTypeId(null);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [kind]);

  return { objectTypeId, isLoading, error };
}
