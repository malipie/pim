import { useQuery } from '@tanstack/react-query';

import { jsonFetch } from '../http';
import {
  canEditAttributeGroup,
  canEditChannel,
  canEditLocale,
  hasAllPermissions,
  hasAnyPermission,
  hasPermission,
  hydrateIdentity,
  type Identity,
  type MeResponse,
} from './identity';

/**
 * RBAC-P4-001 (#678) — React Query hook exposing the bootstrap
 * identity to admin components.
 *
 *   - Cached under the stable key `['rbac', 'identity']` so any
 *     component can share the same fetch without duplicating the
 *     /api/auth/me round-trip. Mercure SSE invalidation (RBAC-P4-010
 *     #687) will call `queryClient.invalidateQueries({ queryKey:
 *     IDENTITY_QUERY_KEY })` when a role / permission grant changes.
 *   - `select()` hydrates the wire shape into the consumer-facing
 *     {@link Identity} so the Set-backed `hasPermission` is computed
 *     once per fetch rather than on every render.
 *   - `retry: false` because a failed /me means the session is dead;
 *     the http layer + Refine AuthProvider drive logout.
 *
 * Companion helpers (`useCanI`, `useCanEditLocale`, …) wrap the
 * hook for declarative usage — see RBAC-P4-004 (#681).
 */
export const IDENTITY_QUERY_KEY = ['rbac', 'identity'] as const;

interface UseIdentityResult {
  identity: Identity | null;
  isLoading: boolean;
  isError: boolean;
}

export function useIdentity(): UseIdentityResult {
  const query = useQuery({
    queryKey: IDENTITY_QUERY_KEY,
    queryFn: () => jsonFetch<MeResponse>('/api/auth/me', { accept: 'application/json' }),
    select: hydrateIdentity,
    retry: false,
    staleTime: 5 * 60_000,
  });

  return {
    identity: query.data ?? null,
    isLoading: query.isLoading,
    isError: query.isError,
  };
}

/**
 * Convenience hook — true when the caller holds the given PRD §3.2 code.
 * Returns false during the initial fetch so gated UI stays hidden
 * until the response lands (avoids the click-through flash).
 */
export function useCanI(code: string): boolean {
  const { identity } = useIdentity();
  return hasPermission(identity, code);
}

export function useCanIAny(codes: readonly string[]): boolean {
  const { identity } = useIdentity();
  return hasAnyPermission(identity, codes);
}

export function useCanIAll(codes: readonly string[]): boolean {
  const { identity } = useIdentity();
  return hasAllPermissions(identity, codes);
}

export function useCanEditLocale(locale: string): boolean {
  const { identity } = useIdentity();
  return canEditLocale(identity, locale);
}

export function useCanEditChannel(channel: string): boolean {
  const { identity } = useIdentity();
  return canEditChannel(identity, channel);
}

export function useCanEditAttributeGroup(groupCode: string): boolean {
  const { identity } = useIdentity();
  return canEditAttributeGroup(identity, groupCode);
}
