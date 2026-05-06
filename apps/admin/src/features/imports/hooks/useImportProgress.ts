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

export interface ImportProgressState {
  /** True until the first SSE event lands or the connection drops. */
  connecting: boolean;
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

    source.addEventListener('message', (event) => {
      try {
        const raw = JSON.parse(event.data) as ProgressEvent;
        setState((prev) => ({
          ...prev,
          connecting: false,
          processedRows: raw.data.processed_rows ?? prev.processedRows,
          totalRows: raw.data.total_rows ?? prev.totalRows,
          successCount: raw.data.success_count ?? prev.successCount,
          errorCount: raw.data.error_count ?? prev.errorCount,
          currentSku: raw.data.current_sku ?? prev.currentSku,
          status: (raw.data.status as ImportProgressState['status']) ?? prev.status,
        }));
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
