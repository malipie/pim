import { AlertTriangle, CalendarDays, Gauge, Loader } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { KpiCard } from '@/components/ui-v2/kpi-card';

import type { ExportSessionRow } from '../hooks/useExportSessions';

function isActive(session: ExportSessionRow): boolean {
  return session.status === 'running' || session.status === 'pending';
}

/** Approximate rows/s over running sessions (no dedicated endpoint for exports). */
function throughputOf(sessions: ExportSessionRow[]): number {
  let total = 0;
  for (const session of sessions) {
    if (session.status !== 'running') continue;
    const elapsedSec = (Date.now() - new Date(session.started_at).getTime()) / 1000;
    if (elapsedSec > 0) {
      total += session.success_count / elapsedSec;
    }
  }
  return Math.round(total);
}

interface KpiStripProps {
  /** Sessions from the last 30 days (already filtered by the caller). */
  sessions: ExportSessionRow[];
}

/**
 * EXR-08 — KPI strip (screen 1): active, today, 30-day success rate and
 * top errors. All client-side aggregates over the 30-day session list —
 * the list endpoint returns every user session, so a stats endpoint is
 * not needed at MVP volumes (documented decision in the PR).
 */
export function KpiStrip({ sessions }: KpiStripProps) {
  const { t } = useTranslation();

  const active = sessions.filter(isActive);
  const today = sessions.filter(
    (session) => new Date(session.started_at).toDateString() === new Date().toDateString(),
  );
  const todayOk = today.filter((session) => session.status === 'done').length;
  const todayErr = today.filter((session) => session.status === 'error').length;

  const terminal = sessions.filter(
    (session) => session.status === 'done' || session.status === 'error',
  );
  const successPct =
    terminal.length === 0
      ? null
      : Math.round((terminal.filter((s) => s.status === 'done').length / terminal.length) * 100);

  const errorCounts = new Map<string, number>();
  for (const session of sessions) {
    if (session.status !== 'error') continue;
    const key = session.error_message?.slice(0, 60) ?? t('exports.kpi.unknown_error');
    errorCounts.set(key, (errorCounts.get(key) ?? 0) + 1);
  }
  const topErrors = [...errorCounts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 3);

  const dateLabel = new Intl.DateTimeFormat('pl-PL', { day: 'numeric', month: 'short' }).format(
    new Date(),
  );

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <KpiCard
        label={t('exports.kpi.active')}
        value={active.length}
        sub={t('exports.kpi.throughput', { value: throughputOf(sessions) })}
        icon={<Loader className="size-4" />}
      />
      <KpiCard
        label={t('exports.kpi.today', { date: dateLabel })}
        value={today.length}
        sub={`✓${todayOk} ⚠0 ✗${todayErr}`}
        icon={<CalendarDays className="size-4" />}
      />
      <KpiCard
        label={t('exports.kpi.success_30d')}
        value={successPct === null ? '—' : `${successPct}%`}
        sub={t('exports.kpi.terminal_sessions', { count: terminal.length })}
        icon={<Gauge className="size-4" />}
      />
      <KpiCard
        label={t('exports.kpi.top_errors')}
        value={topErrors.length === 0 ? '—' : topErrors.reduce((sum, [, n]) => sum + n, 0)}
        sub={
          topErrors.length === 0
            ? t('exports.kpi.no_errors')
            : topErrors.map(([message, count]) => `${count}× ${message}`).join(' · ')
        }
        icon={<AlertTriangle className="size-4" />}
      />
    </div>
  );
}
