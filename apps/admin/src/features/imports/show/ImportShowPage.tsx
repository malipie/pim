import { useApiUrl, useOne } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { RollbackButton } from '@/features/imports/components/RollbackButton';
import { useImportProgress } from '@/features/imports/hooks/useImportProgress';
import { type ImportStatus, StatusBadge } from '@/features/imports/list/StatusBadge';

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
 * Spec §5.6 + §5.7 — combined progress / results screen.
 *
 * The same page surfaces both because the wizard CTA navigates here
 * right after dispatch (status='pending'/'running'). Once Mercure /
 * polling updates the status to a terminal state, the layout
 * transitions to the results card with KPI counters + the
 * RollbackButton + report download. No router redirect needed —
 * the screen is symmetric.
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

  // Server is the source of truth; Mercure refines counters in
  // realtime when REST poll has stale data.
  const processed = Math.max(progress.processedRows, session.success_count + session.error_count);
  const total = Math.max(progress.totalRows, session.total_rows ?? 0);

  return (
    <div className="space-y-6">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{session.file_name}</h1>
          <p className="text-sm text-muted-foreground">
            <StatusBadge status={session.status} />
          </p>
        </div>
        <Button asChild variant="ghost">
          <Link to="/publications/imports">
            ← {t('imports.list.title', { defaultValue: 'Importy' })}
          </Link>
        </Button>
      </header>

      {!isTerminal && (
        <Card className="space-y-3 p-6">
          <div className="flex items-center justify-between text-sm">
            <span className="font-medium">
              {processed} / {total > 0 ? total : '?'} (
              {total > 0 ? Math.round((processed / total) * 100) : 0}%)
            </span>
            {progress.currentSku !== null && (
              <span className="font-mono text-xs text-muted-foreground">
                {t('imports.progress.current_sku', {
                  sku: progress.currentSku,
                  defaultValue: 'Aktualnie: {{sku}}',
                })}
              </span>
            )}
          </div>
          <Progress
            value={total > 0 ? (processed / total) * 100 : 0}
            ariaLabel={t('imports.progress.title', { defaultValue: 'Import w toku' })}
          />
          <p className="text-xs text-muted-foreground">
            💡{' '}
            {t('imports.progress.close_hint', {
              defaultValue: 'Możesz zamknąć tę kartę. System wyśle email po zakończeniu.',
            })}
          </p>
        </Card>
      )}

      {isTerminal && (
        <Card className="space-y-4 p-6">
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

          {session.error_message !== null && (
            <p className="rounded-md border border-destructive/40 bg-destructive/10 p-2 text-sm text-destructive">
              {session.error_message}
            </p>
          )}

          <div className="flex flex-wrap items-center gap-2">
            <Button asChild variant="outline">
              <a href={`${apiUrl}/import-sessions/${sessionId}/report.csv`}>
                📥 {t('imports.results.download_report', { defaultValue: 'Pobierz raport CSV' })}
              </a>
            </Button>
            <Button asChild variant="outline">
              <Link to={`/products?import_session_id=${sessionId}`}>
                👁{' '}
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
        </Card>
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
      ? 'border-green-500/40 bg-green-50'
      : tone === 'warn'
        ? 'border-amber-500/40 bg-amber-50'
        : 'border-muted bg-muted/40';
  return (
    <div className={`rounded-md border p-4 text-center text-base font-semibold ${palette}`}>
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
