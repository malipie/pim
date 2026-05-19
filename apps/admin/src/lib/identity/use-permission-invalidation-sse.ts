import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';

import { IDENTITY_QUERY_KEY } from './use-identity';

/**
 * RBAC-P4-010 (#687) — Mercure EventSource subscription that
 * invalidates the `useIdentity()` cache the moment the backend
 * publishes a `permission.invalidated` event.
 *
 * Pairs with the backend publisher
 * `App\Identity\Infrastructure\Mercure\PermissionInvalidationPublisher`
 * which emits on two topics:
 *
 *   - `{base}/identity/user/{userId}`   — per-user invalidation,
 *   - `{base}/identity/tenant/{tenantId}` — tenant-wide (macierz changes).
 *
 * The hook subscribes to the per-user topic for the active session.
 * Tenant-wide invalidation is handled by the same listener once the
 * tenant scope arrives — backend currently emits the user-level event
 * for every user inside an affected tenant rather than relying on the
 * topic broadcast (matches PermissionResolver's per-user cache key).
 *
 * Mercure URL comes from `VITE_MERCURE_PUBLIC_URL`; falls back to
 * `${origin}/.well-known/mercure` so the dev stack works without
 * extra env wiring (the Caddy proxy in the dev stack routes that
 * path to the hub).
 *
 * The EventSource is torn down on unmount / when the user logs out
 * (userId becomes null). Reconnection is the browser's job — the
 * native EventSource handles exponential back-off automatically.
 */
export function usePermissionInvalidationSse(userId: string | null): void {
  const queryClient = useQueryClient();

  useEffect(() => {
    if (!userId) {
      return;
    }

    const baseUrl =
      // Vite env access intentionally guarded — fall back to same-origin
      // path when the env var is missing so dev works out of the box.
      (typeof import.meta !== 'undefined' &&
        (import.meta as { env?: { VITE_MERCURE_PUBLIC_URL?: string } }).env
          ?.VITE_MERCURE_PUBLIC_URL) ||
      `${window.location.origin}/.well-known/mercure`;

    const topic = `${window.location.origin}/identity/user/${userId}`;
    const url = new URL(baseUrl);
    url.searchParams.append('topic', topic);

    const source = new EventSource(url.toString(), { withCredentials: true });
    source.onmessage = () => {
      queryClient.invalidateQueries({ queryKey: IDENTITY_QUERY_KEY });
    };
    source.onerror = () => {
      // Native EventSource auto-reconnects; we keep the source live and
      // let the browser back off. If the hub is permanently down, the
      // 5-min query staleTime keeps a fresh /api/auth/me on the
      // next refocus.
    };

    return () => {
      source.close();
    };
  }, [userId, queryClient]);
}
