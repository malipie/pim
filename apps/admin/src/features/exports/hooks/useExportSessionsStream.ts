import * as React from 'react';

interface ExportEvent {
  event: 'progress' | 'status';
  session_id: string;
  rows_done?: number;
  rows_total?: number;
  progress_pct?: number;
  estimated_seconds_remaining?: number | null;
  status?: 'pending' | 'running' | 'done' | 'error';
  success_count?: number;
  error_message?: string | null;
}

export interface UseExportSessionsStreamState {
  /** True once the EventSource connection is open. */
  connected: boolean;
  /** Latest event received; consumers can useEffect on this to refetch. */
  lastEvent: ExportEvent | null;
}

/**
 * EXP-17 (#629) — Mercure SSE subscriber for the user-level export topic.
 *
 * Topic: `exports/{user_id}` — the backend publisher (EXP-06 #585) fires
 * both `progress` and `status` events for every export the user runs.
 *
 * Returns the latest event so callers can `query.refetch()` the REST
 * endpoint (`/api/exports/sessions`). REST stays the source of truth;
 * Mercure is a "something changed" signal that avoids 5s polling when
 * the hub is reachable. Mirrors the IMP-04 publisher contract / the
 * `useImportProgress` subscriber pattern.
 *
 * Gracefully no-ops in environments without EventSource (jsdom unit
 * envs, SSR) and on connection errors — caller's polling fallback
 * keeps working when the hub is down.
 */
export function useExportSessionsStream(userId: string | null): UseExportSessionsStreamState {
  const [connected, setConnected] = React.useState(false);
  const [lastEvent, setLastEvent] = React.useState<ExportEvent | null>(null);

  React.useEffect(() => {
    if (userId === null) {
      setConnected(false);
      setLastEvent(null);
      return;
    }
    if (typeof window === 'undefined' || typeof EventSource === 'undefined') {
      return;
    }

    const topic = `https://pim.localhost/exports/${userId}`;
    const url = `${window.location.origin}/.well-known/mercure?topic=${encodeURIComponent(topic)}`;
    const source = new EventSource(url, { withCredentials: true });

    source.addEventListener('open', () => {
      setConnected(true);
    });

    source.addEventListener('message', (event) => {
      try {
        const parsed = JSON.parse(event.data) as ExportEvent;
        setLastEvent(parsed);
      } catch {
        // Malformed payload — Mercure is enrichment, not source of truth.
      }
    });

    source.addEventListener('error', () => {
      setConnected(false);
    });

    return () => {
      source.close();
    };
  }, [userId]);

  return { connected, lastEvent };
}
