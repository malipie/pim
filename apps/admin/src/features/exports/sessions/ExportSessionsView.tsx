import { useApiUrl, useCustom, useCustomMutation } from '@refinedev/core';
import { useTranslation } from 'react-i18next';

interface ExportSessionRow {
  id: string;
  format: 'xlsx' | 'csv';
  target_scope: string;
  target_count: number;
  success_count: number;
  status: 'pending' | 'running' | 'done' | 'error';
  source: string;
  started_at: string;
  completed_at: string | null;
}

interface SessionsResponse {
  items: ExportSessionRow[];
  total: number;
}

const STATUS_STYLES: Record<ExportSessionRow['status'], string> = {
  pending: 'bg-amber-100 text-amber-900',
  running: 'bg-sky-100 text-sky-900',
  done: 'bg-emerald-100 text-emerald-900',
  error: 'bg-rose-100 text-rose-900',
};

/**
 * EXP-13 (#592) — Recent exports grid.
 *
 * Renders `GET /api/exports/sessions` (self-audit only, PRD §8.5)
 * with per-row actions:
 *   - Download (when status=done) → opens `/download` endpoint in a new tab.
 *   - Rerun → POST `/rerun`, refreshes the grid.
 *   - Delete → DELETE, refreshes the grid.
 *
 * 5-second polling refresh keeps the running rows alive without
 * Mercure SSE wiring (PRD §11.5; native SSE subscription is a
 * follow-up — `exports/{user_id}` topic publishes are already running
 * on the backend EXP-06 handler, so flipping the FE switch is just an
 * EventSource hookup).
 */
export function ExportSessionsView(): React.ReactElement {
  const { t } = useTranslation();
  const apiUrl = useApiUrl();

  const { result, query } = useCustom<SessionsResponse>({
    url: `${apiUrl}/exports/sessions`,
    method: 'get',
    queryOptions: { refetchInterval: 5000, staleTime: 2000 },
  });

  const { mutate: rerun } = useCustomMutation();
  const { mutate: del } = useCustomMutation();

  const sessions = result?.data?.items ?? [];

  const onDownload = (id: string) => {
    window.open(`${apiUrl}/exports/sessions/${id}/download`, '_blank');
  };

  const onRerun = (id: string) => {
    rerun(
      {
        url: `${apiUrl}/exports/sessions/${id}/rerun`,
        method: 'post',
        values: {},
      },
      {
        onSuccess: () => {
          void query.refetch();
        },
      },
    );
  };

  const onDelete = (id: string) => {
    if (
      !window.confirm(
        t('exports.sessions.confirm_delete', {
          defaultValue: 'Usunąć ten eksport? Plik z MinIO zostanie również usunięty.',
        }),
      )
    ) {
      return;
    }
    del(
      {
        url: `${apiUrl}/exports/sessions/${id}`,
        method: 'delete',
        values: {},
      },
      {
        onSuccess: () => {
          void query.refetch();
        },
      },
    );
  };

  if (sessions.length === 0) {
    return (
      <div className="rounded-md border border-dashed bg-muted/30 p-8 text-center">
        <h2 className="text-lg font-medium">
          {t('exports.sessions.empty_title', { defaultValue: 'Nie masz jeszcze eksportów' })}
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          {t('exports.sessions.empty_subtitle', {
            defaultValue: 'Otwórz [Nowy eksport →] albo uruchom modal z listy produktów.',
          })}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
          <tr>
            <th className="px-3 py-2 text-left">
              {t('exports.sessions.col_started', { defaultValue: 'Rozpoczęte' })}
            </th>
            <th className="px-3 py-2 text-left">
              {t('exports.sessions.col_format', { defaultValue: 'Format' })}
            </th>
            <th className="px-3 py-2 text-right">
              {t('exports.sessions.col_rows', { defaultValue: 'Wiersze' })}
            </th>
            <th className="px-3 py-2 text-left">
              {t('exports.sessions.col_status', { defaultValue: 'Status' })}
            </th>
            <th className="px-3 py-2 text-right">
              {t('exports.sessions.col_actions', { defaultValue: 'Akcje' })}
            </th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {sessions.map((session) => (
            <tr key={session.id} className="hover:bg-muted/30">
              <td className="px-3 py-2 font-mono text-xs">
                {new Date(session.started_at).toLocaleString()}
              </td>
              <td className="px-3 py-2">
                <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-medium uppercase">
                  {session.format}
                </span>
              </td>
              <td className="px-3 py-2 text-right font-mono text-xs">
                {session.success_count}/{session.target_count}
              </td>
              <td className="px-3 py-2">
                <span
                  className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[session.status]}`}
                >
                  {t(`exports.status.${session.status}`, { defaultValue: session.status })}
                </span>
              </td>
              <td className="px-3 py-2 text-right">
                <div className="inline-flex gap-1">
                  {session.status === 'done' && (
                    <button
                      type="button"
                      onClick={() => onDownload(session.id)}
                      className="rounded border border-input bg-background px-2 py-1 text-xs hover:bg-muted"
                    >
                      {t('exports.sessions.action_download', { defaultValue: 'Pobierz' })}
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => onRerun(session.id)}
                    className="rounded border border-input bg-background px-2 py-1 text-xs hover:bg-muted"
                  >
                    {t('exports.sessions.action_rerun', { defaultValue: 'Powtórz' })}
                  </button>
                  <button
                    type="button"
                    onClick={() => onDelete(session.id)}
                    className="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-900 hover:bg-rose-100"
                  >
                    {t('exports.sessions.action_delete', { defaultValue: 'Usuń' })}
                  </button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default ExportSessionsView;
