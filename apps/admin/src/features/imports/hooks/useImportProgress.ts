import * as React from 'react';

interface ProgressEvent {
  type: 'progress' | 'row_processed' | 'error' | 'completed';
  session_id: string;
  data: {
    processed_rows?: number;
    total_rows?: number;
    success_count?: number;
    error_count?: number;
    current_sku?: string | null;
    status?: string;
  };
}

export interface ImportLogEntry {
  id: number;
  level: 'info' | 'error';
  message: string;
}

const LOG_BUFFER_CAP = 200;

export interface ImportProgressState {
  /** True until the first SSE event lands or the connection drops. */
  connecting: boolean;
  /** NUI-11 — capped live-log buffer built from row_processed/error events. */
  log: ImportLogEntry[];
  processedRows: number;
  totalRows: number;
  successCount: number;
  errorCount: number;
  currentSku: string | null;
  status:
    | 'pending'
    | 'running'
    | 'paused'
    | 'success'
    | 'partial'
    | 'failed'
    | 'cancelled'
    | 'rolled_back';
}

const INITIAL_STATE: ImportProgressState = {
  connecting: true,
  log: [],
  processedRows: 0,
  totalRows: 0,
  successCount: 0,
  errorCount: 0,
  currentSku: null,
  status: 'pending',
};

/**
 * Mercure SSE subscriber for the import detail topic
 * (`imports/{session_id}`). Mirrors the IMP-04 publisher. The hook
 * is idempotent — re-subscribing on session id change closes the
 * previous EventSource and opens a fresh one.
 */
export function useImportProgress(sessionId: string | null): ImportProgressState {
  const [state, setState] = React.useState<ImportProgressState>(INITIAL_STATE);

  React.useEffect(() => {
    if (sessionId === null) {
      setState(INITIAL_STATE);
      return;
    }
    if (typeof window === 'undefined' || typeof EventSource === 'undefined') {
      return;
    }

    const topic = `https://pim.localhost/imports/${sessionId}`;
    const url = `${window.location.origin}/.well-known/mercure?topic=${encodeURIComponent(topic)}`;
    const source = new EventSource(url, { withCredentials: true });

    source.addEventListener('open', () => {
      setState((prev) => ({ ...prev, connecting: false }));
    });

    let logSeq = 0;
    source.addEventListener('message', (event) => {
      try {
        const raw = JSON.parse(event.data) as ProgressEvent;
        setState((prev) => {
          let log = prev.log;
          if (raw.type === 'row_processed' || raw.type === 'error') {
            const sku = raw.data.current_sku ?? null;
            const entry: ImportLogEntry = {
              id: logSeq++,
              level: raw.type === 'error' ? 'error' : 'info',
              message:
                raw.type === 'error'
                  ? `✗ ${sku ?? 'row'} — error`
                  : `✓ ${sku ?? 'row'} (${raw.data.processed_rows ?? '?'} / ${raw.data.total_rows ?? '?'})`,
            };
            log = [...prev.log.slice(-(LOG_BUFFER_CAP - 1)), entry];
          }
          return {
            ...prev,
            connecting: false,
            log,
            processedRows: raw.data.processed_rows ?? prev.processedRows,
            totalRows: raw.data.total_rows ?? prev.totalRows,
            successCount: raw.data.success_count ?? prev.successCount,
            errorCount: raw.data.error_count ?? prev.errorCount,
            currentSku: raw.data.current_sku ?? prev.currentSku,
            status: (raw.data.status as ImportProgressState['status']) ?? prev.status,
          };
        });
      } catch {
        // Malformed Mercure payload — surface a console.warn on the
        // root error handler in dev only.
      }
    });

    source.addEventListener('error', () => {
      setState((prev) => ({ ...prev, connecting: false }));
    });

    return () => {
      source.close();
    };
  }, [sessionId]);

  return state;
}
