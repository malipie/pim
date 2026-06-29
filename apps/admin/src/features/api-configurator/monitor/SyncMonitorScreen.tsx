import { useList } from '@refinedev/core';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';

import { DirectionBadge } from '../components/primitives';
import {
  type RunStatus,
  RunStatusDot,
  RunStatusPill,
  toRunStatus,
} from '../consumer/detail/RunStatus';
import { computeKpis, KpiCell, type MonitorRunRow, ResultBar } from './monitor-bits';
import { RunDrillSheet } from './RunDrillSheet';

type StatusFilter = 'all' | RunStatus;

const FILTERS: StatusFilter[] = ['all', 'success', 'partial', 'failed'];

/**
 * APIC-P4-02 — the tenant-wide sync monitor (`integracje/api-monitor.jsx`): KPI
 * strip, a status filter + search toolbar, the run history table, and a
 * per-run drill-down Sheet. Reads the P4-01 SyncRun history API; KPIs are
 * derived client-side from the run list.
 */
export function SyncMonitorScreen() {
  const { t } = useTranslation();
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [search, setSearch] = useState('');
  const [openRun, setOpenRun] = useState<MonitorRunRow | null>(null);

  const runsQuery = useList<MonitorRunRow>({
    resource: 'sync_runs',
    pagination: { mode: 'off' },
  });
  const runs = runsQuery.result.data;

  const kpis = useMemo(() => computeKpis(runs, Date.now()), [runs]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return runs.filter((run) => {
      if (statusFilter !== 'all' && toRunStatus(run.status) !== statusFilter) {
        return false;
      }
      if (q !== '' && !run.bindingId.toLowerCase().includes(q) && !run.direction.includes(q)) {
        return false;
      }
      return true;
    });
  }, [runs, statusFilter, search]);

  return (
    <div className="space-y-5">
      <div>
        <h1 className="font-display text-[22px] font-semibold tracking-tight">
          {t('api_configurator.monitor.title')}
        </h1>
        <p className="text-[12.5px] text-zinc-500">{t('api_configurator.monitor.subtitle')}</p>
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <KpiCell
          label={t('api_configurator.monitor.kpi.syncs_24h')}
          value={String(kpis.syncs24h)}
        />
        <KpiCell
          label={t('api_configurator.monitor.kpi.records_in')}
          value={kpis.recordsIn.toLocaleString()}
          tone="sky"
        />
        <KpiCell
          label={t('api_configurator.monitor.kpi.records_out')}
          value={kpis.recordsOut.toLocaleString()}
          tone="violet"
        />
        <KpiCell
          label={t('api_configurator.monitor.kpi.errors_24h')}
          value={String(kpis.errors24h)}
          tone="rose"
        />
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative">
          <Search
            className="-translate-y-1/2 absolute top-1/2 left-2.5 size-4 text-zinc-400"
            aria-hidden="true"
          />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('api_configurator.monitor.search')}
            aria-label={t('api_configurator.monitor.search')}
            className="h-9 w-64 pl-8"
          />
        </div>
        <div className="flex items-center gap-1">
          {FILTERS.map((f) => (
            <button
              key={f}
              type="button"
              onClick={() => setStatusFilter(f)}
              aria-pressed={statusFilter === f}
              className={`h-7 rounded-lg px-2.5 text-[11.5px] font-medium transition ${statusFilter === f ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100'}`}
            >
              {t(`api_configurator.monitor.filter.${f}`)}
            </button>
          ))}
        </div>
      </div>

      <div className="soft-shadow overflow-hidden rounded-2xl border border-zinc-200 bg-white">
        <div className="grid grid-cols-[16px_1.4fr_110px_1fr_110px] gap-3 border-b border-zinc-100 bg-zinc-50/40 px-5 py-2.5 text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
          <div />
          <div>{t('api_configurator.monitor.col.started')}</div>
          <div>{t('api_configurator.monitor.col.direction')}</div>
          <div>{t('api_configurator.monitor.col.result')}</div>
          <div>{t('api_configurator.monitor.col.status')}</div>
        </div>
        <div className="divide-y divide-zinc-50">
          {filtered.map((run) => {
            const status = toRunStatus(run.status);
            return (
              <button
                key={run.id}
                type="button"
                onClick={() => setOpenRun(run)}
                aria-label={t('api_configurator.monitor.open_run')}
                className="grid w-full grid-cols-[16px_1.4fr_110px_1fr_110px] items-center gap-3 px-5 py-3 text-left transition hover:bg-zinc-50/70"
              >
                <RunStatusDot status={status} />
                <div className="text-[12.5px] text-zinc-800">
                  {new Date(run.startedAt).toLocaleString()}
                </div>
                <div>
                  <DirectionBadge dir={run.direction} label={run.direction} />
                </div>
                <div className="flex items-center gap-2">
                  <ResultBar
                    ok={run.createdCount + run.updatedCount}
                    warn={run.skippedCount}
                    err={run.failedCount}
                    label={t('api_configurator.monitor.col.result')}
                  />
                  <span className="font-mono text-[10.5px] text-zinc-500">
                    +{run.createdCount} ~{run.updatedCount}
                    {run.failedCount > 0 ? ` ✕${run.failedCount}` : ''}
                  </span>
                </div>
                <div>
                  <RunStatusPill
                    status={status}
                    label={t(`api_configurator.detail.run_status.${status}`)}
                  />
                </div>
              </button>
            );
          })}
          {filtered.length === 0 ? (
            <div className="px-5 py-8 text-center text-[13px] text-zinc-400">
              {t('api_configurator.monitor.empty')}
            </div>
          ) : null}
        </div>
      </div>

      <RunDrillSheet run={openRun} onClose={() => setOpenRun(null)} />
    </div>
  );
}
