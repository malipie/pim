import { useEffect, useRef, useState } from 'react';

/**
 * Mercure notifications feed for the top bar bell (#54 / 0.6.1).
 *
 * Subscribes to the broadcast topic published by the Mercure publisher
 * from #47 (`<base>/objects`). Each `EventSource` message becomes a
 * notification entry with a stable id, the parsed payload type
 * (`object.created.product`, `object.attributes_changed.category`, …),
 * and the raw data so the UI can later route the user to the row.
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
const BROADCAST_TOPIC = '/objects';

export function useNotifications(): UseNotificationsState {
  const [entries, setEntries] = useState<NotificationEntry[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const hubUrl = useRef<string | null>(null);

  useEffect(() => {
    // EventSource is window-only; SSR / unit envs (jsdom-less) skip this.
    if (typeof window === 'undefined' || typeof EventSource === 'undefined') return;

    if (hubUrl.current === null) {
      hubUrl.current = `${window.location.origin}/.well-known/mercure?topic=${encodeURIComponent(BROADCAST_TOPIC)}`;
    }

    const source = new EventSource(hubUrl.current, { withCredentials: true });
    source.addEventListener('message', (event) => {
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
          topic: BROADCAST_TOPIC,
          occurredOn,
          data,
          receivedAt: Date.now(),
        };
        setEntries((prev) => [entry, ...prev].slice(0, MAX_ENTRIES));
        setUnreadCount((prev) => prev + 1);
      } catch {
        // Malformed payload is non-fatal — Mercure is enrichment, not source of truth.
      }
    });

    return () => {
      source.close();
    };
  }, []);

  const markAllRead = (): void => {
    setUnreadCount(0);
  };

  return { entries, unreadCount, markAllRead };
}
