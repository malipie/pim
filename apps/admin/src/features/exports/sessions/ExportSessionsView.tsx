import { useGetIdentity } from '@refinedev/core';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { toast } from '@/components/ui/toast';
import { getAccessToken, jsonFetch } from '@/lib/http';

import { useExportSessions, useInvalidateExportSessions } from '../hooks/useExportSessions';
import { useExportSessionsStream } from '../hooks/useExportSessionsStream';
import { ActiveSessions } from './ActiveSessions';
import { HistoryTable } from './HistoryTable';
import { KpiStrip } from './KpiStrip';

const THIRTY_DAYS_MS = 30 * 24 * 60 * 60 * 1000;

interface Identity {
  id: string;
  name?: string;
  email?: string;
}

/**
 * EXR-08 (#1384) — Sessions tab in the v2 design (screen 1):
 * KPI strip → "W toku" (active sessions with progress) → "Historia"
 * (v2 table with search/segments/pagination). Data: one shared
 * sessions query; Mercure SSE triggers refetch (EXP-17 publisher),
 * 30 s polling fallback until EXR-15.
 */
export function ExportSessionsView(): React.ReactElement {
  const { t } = useTranslation();
  const { data: identity } = useGetIdentity<Identity>();

  const sessionsQuery = useExportSessions();
  const invalidate = useInvalidateExportSessions();

  const { lastEvent } = useExportSessionsStream(identity?.id ?? null);
  useEffect(() => {
    if (lastEvent === null) return;
    invalidate();
  }, [lastEvent, invalidate]);

  const all = sessionsQuery.data?.items ?? [];
  const cutoff = Date.now() - THIRTY_DAYS_MS;
  const sessions = all.filter((session) => new Date(session.started_at).getTime() >= cutoff);
  const active = sessions.filter(
    (session) => session.status === 'running' || session.status === 'pending',
  );
  const history = sessions.filter(
    (session) => session.status === 'done' || session.status === 'error',
  );

  const onDownload = async (id: string) => {
    // window.open() drops the Authorization header (JWT-guarded endpoint
    // would 401 in the new tab) — fetch with Bearer + blob anchor instead.
    try {
      const token = getAccessToken();
      const headers: Record<string, string> = {
        accept:
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv, application/json',
      };
      if (token !== null) {
        headers.authorization = `Bearer ${token}`;
      }
      const response = await fetch(`/api/exports/sessions/${id}/download`, {
        method: 'GET',
        headers,
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error(`Download failed: HTTP ${response.status}`);
      }
      const blob = await response.blob();
      const filename =
        parseFilename(response.headers.get('content-disposition')) ?? `pim-export-${id}`;
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = filename;
      document.body.appendChild(anchor);
      anchor.click();
      document.body.removeChild(anchor);
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch {
      toast.error(t('exports.history.download_failed'));
    }
  };

  const onRerun = async (id: string) => {
    try {
      await jsonFetch(`/api/exports/sessions/${id}/rerun`, {
        method: 'POST',
        accept: 'application/json',
      });
      invalidate();
    } catch {
      toast.error(t('exports.history.rerun_failed'));
    }
  };

  const onDelete = async (id: string) => {
    if (!window.confirm(t('exports.sessions.confirm_delete'))) {
      return;
    }
    try {
      await jsonFetch(`/api/exports/sessions/${id}`, {
        method: 'DELETE',
        accept: 'application/json',
      });
      invalidate();
    } catch {
      toast.error(t('exports.history.delete_failed'));
    }
  };

  if (sessionsQuery.isLoading) {
    return (
      <div className="space-y-4" aria-busy="true">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {[0, 1, 2, 3].map((index) => (
            <div key={index} className="h-28 animate-pulse rounded-2xl bg-zinc-100" />
          ))}
        </div>
        <div className="h-40 animate-pulse rounded-2xl bg-zinc-100" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <KpiStrip sessions={sessions} />
      <ActiveSessions sessions={active} />
      <HistoryTable
        sessions={history}
        userName={identity?.name ?? identity?.email ?? '—'}
        onDownload={(id) => void onDownload(id)}
        onRerun={(id) => void onRerun(id)}
        onDelete={(id) => void onDelete(id)}
      />
    </div>
  );
}

function parseFilename(header: string | null): string | null {
  if (header === null) return null;
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header);
  return match?.[1] !== undefined ? decodeURIComponent(match[1]) : null;
}

export default ExportSessionsView;
