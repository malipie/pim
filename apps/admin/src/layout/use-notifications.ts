import { useEffect, useState } from 'react';

import { useIdentity } from '@/lib/identity';
import { ensureMercureAuthorization, mercureSubscribeUrl, mercureTenantTopic } from '@/lib/mercure';

/**
 * Mercure notifications feed for the top bar bell (#54 / 0.6.1).
 *
 * Subscribes to the tenant-scoped catalog broadcast topic published by
 * the Mercure publisher from #47 — `<base>/tenant/<tid>/objects` after
 * AUD-001 (#1573). Each `EventSource` message becomes a notification
 * entry with a stable id, the parsed payload type
 * (`object.created.product`, `object.attributes_changed.category`, …),
 * and the raw data so the UI can later route the user to the row.
 *
 * Before AUD-001 this hook listened on the un-prefixed global `/objects`
 * topic against an anonymous hub, so the bell surfaced every tenant's
 * catalog changes. The tenant prefix + the mercureAuthorization cookie
 * (minted below) confine it to the caller's tenant.
 *
 * The hook keeps the last 25 events in memory only — the bell is a
 * "is something happening right now" surface, not an audit log. Reload
 * resets the feed; durable history lives in `sync_job_logs` (Faza 1).
 *
 * Connection failure is silent: Mercure may be down in dev when the
 * operator runs only `apps/api` without the hub. Surface a toast or
 * status pill in 0.11 / #100 if production telemetry warrants it.
 */
export interface NotificationEntry {
  id: string;
  type: string;
  topic: string;
  occurredOn: string;
  data: Record<string, unknown>;
  receivedAt: number;
}

export interface UseNotificationsState {
  entries: NotificationEntry[];
  unreadCount: number;
  markAllRead: () => void;
}

const MAX_ENTRIES = 25;

export function useNotifications(): UseNotificationsState {
  const [entries, setEntries] = useState<NotificationEntry[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const { identity } = useIdentity();
  const tenantId = identity?.tenant?.id ?? null;

  useEffect(() => {
    // EventSource is window-only; SSR / unit envs (jsdom-less) skip this.
    if (typeof window === 'undefined' || typeof EventSource === 'undefined') return;
    if (tenantId === null) return;

    const broadcastTopic = mercureTenantTopic(tenantId, 'objects');
    const url = mercureSubscribeUrl(broadcastTopic);

    let source: EventSource | null = null;
    let cancelled = false;

    const onMessage = (event: MessageEvent): void => {
      try {
        const raw: unknown = JSON.parse(event.data);
        if (typeof raw !== 'object' || raw === null) return;
        const obj = raw as Record<string, unknown>;
        const type = typeof obj.type === 'string' ? obj.type : 'unknown';
        const occurredOn =
          typeof obj.occurredOn === 'string' ? obj.occurredOn : new Date().toISOString();
        const data =
          typeof obj.data === 'object' && obj.data !== null
            ? (obj.data as Record<string, unknown>)
            : {};
        const entry: NotificationEntry = {
          id: `${event.lastEventId || crypto.randomUUID()}`,
          type,
          topic: broadcastTopic,
          occurredOn,
          data,
          receivedAt: Date.now(),
        };
        setEntries((prev) => [entry, ...prev].slice(0, MAX_ENTRIES));
        setUnreadCount((prev) => prev + 1);
      } catch {
        // Malformed payload is non-fatal — Mercure is enrichment, not source of truth.
      }
    };

    // AUD-001 (#1573) — mint the tenant-scoped subscribe cookie before
    // opening the EventSource; the hub rejects an unauthorised subscription.
    ensureMercureAuthorization()
      .then(() => {
        if (cancelled) return;
        source = new EventSource(url, { withCredentials: true });
        source.addEventListener('message', onMessage);
      })
      .catch(() => {
        // Mint failed — the bell is enrichment; silently degrade.
      });

    return () => {
      cancelled = true;
      source?.close();
    };
  }, [tenantId]);

  const markAllRead = (): void => {
    setUnreadCount(0);
  };

  return { entries, unreadCount, markAllRead };
}
