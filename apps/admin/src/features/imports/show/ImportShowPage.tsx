import { useApiUrl, useOne } from '@refinedev/core';
import { Download, Eye } from 'lucide-react';
import * as React from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { RollbackButton } from '@/features/imports/components/RollbackButton';
import { useImportProgress } from '@/features/imports/hooks/useImportProgress';
import { type ImportStatus, StatusBadge } from '@/features/imports/list/StatusBadge';
import {
  type ImportStage,
  ProgressBar,
  ResultBar,
  StagePipeline,
} from '@/features/imports/primitives';
import { cn } from '@/lib/utils';

interface ImportSession {
  id: string;
  status: ImportStatus;
  file_name: string;
  total_rows: number | null;
  success_count: number;
  error_count: number;
  images_downloaded: number;
  images_failed: number;
  started_at: string | null;
  completed_at: string | null;
  rollback_until: string | null;
  rolled_back_at: string | null;
  error_message: string | null;
}

/**
 * NUI-11 (#1430) — import session view v2 (design `Import-sesja.html`):
 * header with status + duration + %, StagePipeline honestly derived from
 * the session status (the backend stores no per-phase timestamps — done /
 * active / pending only; backlog: Retrofit_v2/importy-do-oprogramowania.md),
 * a Mercure-fed live log (capped buffer in `useImportProgress`) and the
 * results card with KPI counters, ResultBar, report CSV and rollback.
 */
export function ImportShowPage(): React.ReactElement {
  const { t } = useTranslation();
  const apiUrl = useApiUrl();
  const params = useParams<{ id: string }>();
  const sessionId = params.id ?? null;

  const { result: session, query } = useOne<ImportSession>({
    resource: 'import-sessions',
    id: sessionId ?? '',
    queryOptions: {
      enabled: sessionId !== null,
      refetchInterval: 5000,
    },
  });
  const progress = useImportProgress(sessionId);
  const logRef = React.useRef<HTMLDivElement | null>(null);

  // biome-ignore lint/correctness/useExhaustiveDependencies: scroll follows the log length
  React.useEffect(() => {
    logRef.current?.scrollTo({ top: logRef.current.scrollHeight });
  }, [progress.log.length]);

  if (sessionId === null || query.isLoading || session === undefined) {
    return (
      <p className="text-sm text-muted-foreground" aria-busy="true">
        {t('app.loading', { defaultValue: 'Ładowanie…' })}
      </p>
    );
  }

  const isTerminal =
    session.status === 'success' ||
    session.status === 'partial' ||
    session.status === 'failed' ||
    session.status === 'cancelled' ||
    session.status === 'rolled_back';

  // Server is the source of truth; Mercure refines counters in realtime.
  const processed = Math.max(progress.processedRows, session.success_count + session.error_count);
  const total = Math.max(progress.totalRows, session.total_rows ?? 0);
  const pct = total > 0 ? Math.round((processed / total) * 100) : 0;

  // Honest stage derivation — no per-phase timestamps exist on the session.
  const stage: ImportStage = isTerminal
    ? 'done'
    : processed > 0
      ? 'writing'
      : session.status === 'running'
        ? 'validating'
        : 'parsing';

  return (
    <div className="space-y-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h1 className="display truncate text-[24px] font-semibold tracking-tight">
            {session.file_name}
          </h1>
          <div className="mt-1 flex flex-wrap items-center gap-2.5 text-sm text-zinc-500">
            <StatusBadge status={session.status} />
            <span className="num font-mono text-[12px]">
              {t('imports.show.duration', {
                defaultValue: 'czas: {{value}}',
                value: formatDuration(session.started_at, session.completed_at),
              })}
            </span>
            {!isTerminal && <span className="num font-mono text-[12px]">{pct}%</span>}
          </div>
        </div>
        <Button asChild variant="ghost">
          <Link to="/integrations/imports">
            ← {t('imports.list.title', { defaultValue: 'Importy' })}
          </Link>
        </Button>
      </header>

      <StagePipeline stage={stage} />

      {!isTerminal && (
        <div className="space-y-4 rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between text-sm">
            <span className="num font-medium">
              {processed} / {total > 0 ? total : '?'} ({pct}%)
            </span>
            {progress.currentSku !== null && (
              <span className="font-mono text-xs text-zinc-500">
                {t('imports.progress.current_sku', {
                  sku: progress.currentSku,
                  defaultValue: 'Aktualnie: {{sku}}',
                })}
              </span>
            )}
          </div>
          <ProgressBar value={pct} />

          <div>
            <div className="mb-1.5 text-[11px] font-medium uppercase tracking-wider text-zinc-400">
              {t('imports.show.live_log', { defaultValue: 'Live log' })}
            </div>
            <div
              ref={logRef}
              className="max-h-56 overflow-y-auto rounded-xl bg-zinc-900 px-3.5 py-3 font-mono text-[11.5px] leading-relaxed text-zinc-200"
              aria-live="polite"
            >
              {progress.log.length === 0 ? (
                <span className="text-zinc-500">
                  {t('imports.show.log_waiting', {
                    defaultValue: '… oczekiwanie na zdarzenia (Mercure)',
                  })}
                </span>
              ) : (
                progress.log.map((entry) => (
                  <div
                    key={entry.id}
                    className={cn(entry.level === 'error' ? 'text-rose-400' : 'text-zinc-200')}
                  >
                    {entry.message}
                  </div>
                ))
              )}
            </div>
          </div>

          <p className="text-xs text-zinc-500">
            💡{' '}
            {t('imports.progress.close_hint', {
              defaultValue: 'Możesz zamknąć tę kartę. System wyśle email po zakończeniu.',
            })}
          </p>
        </div>
      )}

      {isTerminal && (
        <div className="space-y-4 rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Kpi
              label={t('imports.results.imported', {
                count: session.success_count,
                defaultValue: '{{count}} produktów zaimportowano',
              })}
              tone="ok"
            />
            <Kpi
              label={t('imports.results.skipped', {
                count: session.error_count,
                defaultValue: '{{count}} pominiętych',
              })}
              tone="warn"
            />
            <Kpi
              label={t('imports.results.duration', {
                value: formatDuration(session.started_at, session.completed_at),
                defaultValue: 'Czas: {{value}}',
              })}
              tone="muted"
            />
          </div>

          {session.total_rows !== null && session.total_rows > 0 && (
            <ResultBar
              ok={session.success_count}
              warn={0}
              err={session.error_count}
              total={session.total_rows}
            />
          )}

          {session.error_message !== null && (
            <p className="rounded-md border border-destructive/40 bg-destructive/10 p-2 text-sm text-destructive">
              {session.error_message}
            </p>
          )}

          <div className="flex flex-wrap items-center gap-2">
            <Button asChild variant="outline">
              <a href={`${apiUrl}/import-sessions/${sessionId}/report.csv`}>
                <Download className="size-4" />
                {t('imports.results.download_report', { defaultValue: 'Pobierz raport CSV' })}
              </a>
            </Button>
            <Button asChild variant="outline">
              <Link to={`/products?import_session_id=${sessionId}`}>
                <Eye className="size-4" />
                {t('imports.results.view_products', {
                  defaultValue: 'Zobacz zaimportowane produkty',
                })}
              </Link>
            </Button>
            {(session.status === 'success' || session.status === 'partial') && (
              <RollbackButton
                sessionId={sessionId}
                rollbackUntil={session.rollback_until}
                onRolledBack={() => query.refetch()}
              />
            )}
          </div>
        </div>
      )}
    </div>
  );
}

function Kpi({
  label,
  tone,
}: {
  label: string;
  tone: 'ok' | 'warn' | 'muted';
}): React.ReactElement {
  const palette =
    tone === 'ok'
      ? 'border-emerald-500/40 bg-emerald-50'
      : tone === 'warn'
        ? 'border-amber-500/40 bg-amber-50'
        : 'border-zinc-200 bg-zinc-50';
  return (
    <div className={`num rounded-xl border p-4 text-center text-base font-semibold ${palette}`}>
      {label}
    </div>
  );
}

function formatDuration(start: string | null, end: string | null): string {
  if (start === null || end === null) {
    return '—';
  }
  const diff = new Date(end).getTime() - new Date(start).getTime();
  if (Number.isNaN(diff) || diff < 0) {
    return '—';
  }
  const seconds = Math.floor(diff / 1000) % 60;
  const minutes = Math.floor(diff / (1000 * 60));
  return `${minutes}m ${seconds}s`;
}
