/**
 * AUD-001 (#1573) — shared Mercure helpers for the admin SPA.
 *
 * The Mercure hub no longer runs in `anonymous` mode and every domain
 * topic is tenant-scoped + private, so before opening ANY EventSource a
 * consumer must:
 *
 *   1. mint the `mercureAuthorization` cookie via
 *      `POST /api/mercure/authorization` ({@link ensureMercureAuthorization});
 *      the cookie's JWT authorises only the caller tenant's topics;
 *   2. subscribe on a tenant-scoped topic built with
 *      {@link mercureTenantTopic} (mirrors the backend
 *      `MercureSubscribeTopics` contract).
 *
 * Without the cookie the hub answers 401 and the EventSource fails — that
 * is the mechanism that stops cross-tenant real-time leakage.
 */

import { getAccessToken } from '../http';

const AUTHORIZATION_PATH = '/api/mercure/authorization';

let inFlight: Promise<void> | null = null;
let authorizedAt = 0;

// The minted cookie lives ~1h (session.cookie_lifetime). Re-mint a couple
// of minutes early so a long-open EventSource never trips a mid-stream
// 401 when the JWT expires. A short success cache collapses the burst of
// consumers that mount together (bell + import + export + permissions).
const REAUTH_AFTER_MS = 55 * 60_000;

/**
 * Single-flight mint of the `mercureAuthorization` cookie. Idempotent:
 * concurrent callers share one POST, and a recent success short-circuits.
 *
 * Throws on a non-2xx response so callers can decide whether to fall
 * back to polling (imports/exports) or simply skip the live channel.
 */
export async function ensureMercureAuthorization(force = false): Promise<void> {
  if (
    !force &&
    inFlight === null &&
    Date.now() - authorizedAt < REAUTH_AFTER_MS &&
    authorizedAt > 0
  ) {
    return;
  }
  if (inFlight === null) {
    inFlight = mintCookie().finally(() => {
      inFlight = null;
    });
  }
  return inFlight;
}

async function mintCookie(): Promise<void> {
  const accessToken = getAccessToken();
  const response = await fetch(AUTHORIZATION_PATH, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      accept: 'application/json',
      ...(accessToken ? { authorization: `Bearer ${accessToken}` } : {}),
    },
  });
  if (!response.ok) {
    throw new Error(`Mercure authorization mint failed: ${response.status}`);
  }
  authorizedAt = Date.now();
}

/**
 * Reset the success cache — call on logout so the next login re-mints a
 * cookie for the new principal/tenant rather than reusing the stale one.
 */
export function resetMercureAuthorization(): void {
  authorizedAt = 0;
  inFlight = null;
}

/**
 * Build a tenant-scoped topic IRI: `{origin}/tenant/{tenantId}/{...segments}`.
 * Mirrors `App\Shared\Infrastructure\Mercure\MercureSubscribeTopics`.
 */
export function mercureTenantTopic(tenantId: string, ...segments: string[]): string {
  const tail = segments.join('/');
  return `${window.location.origin}/tenant/${tenantId}/${tail}`;
}

/**
 * Build the hub subscribe URL for a single topic.
 */
export function mercureSubscribeUrl(topic: string): string {
  return `${window.location.origin}/.well-known/mercure?topic=${encodeURIComponent(topic)}`;
}
