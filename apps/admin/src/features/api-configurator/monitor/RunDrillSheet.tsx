import { useApiUrl, useCustomMutation, useList } from '@refinedev/core';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';

import { DirectionBadge } from '../components/primitives';
import { RunStatusPill, toRunStatus } from '../consumer/detail/RunStatus';
import type { MonitorRunRow } from './monitor-bits';

interface RunLogRow {
  id: string;
  matchKey: string | null;
  action: string;
  message: string | null;
}

/**
 * APIC-P4-02 — the run drill-down Sheet: KPI grid + meta + per-record table,
 * with footer actions (CSV export of the loaded records, pause + re-run on the
 * run's binding).
 */
export function RunDrillSheet({
  run,
  onClose,
}: {
  run: MonitorRunRow | null;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const apiUrl = useApiUrl();
  const { mutate: act } = useCustomMutation();

  const logsQuery = useList<RunLogRow>({
    resource: 'sync_run_logs',
    filters: run === null ? [] : [{ field: 'run', operator: 'eq', value: run.id }],
    pagination: { mode: 'off' },
    queryOptions: { enabled: run !== null },
  });
  const logs = logsQuery.result.data;

  function bindingAction(action: 'run' | 'pause'): void {
    if (run === null) {
      return;
    }
    act({ url: `${apiUrl}/sync_bindings/${run.bindingId}/${action}`, method: 'post', values: {} });
  }

  function downloadCsv(): void {
    if (run === null) {
      return;
    }
    const header = 'match_key,action,message';
    const rows = logs.map(
      (l) => `${l.matchKey ?? ''},${l.action},${(l.message ?? '').replace(/[\r\n,]+/g, ' ')}`,
    );
    const blob = new Blob([`${header}\n${rows.join('\n')}\n`], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `sync-run-${run.id}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  const status = run !== null ? toRunStatus(run.status) : 'running';

  return (
    <Sheet open={run !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent
        side="right"
        closeLabel={t('api_configurator.monitor.drill.close')}
        className="flex w-full max-w-2xl flex-col gap-4 overflow-y-auto"
      >
        {run !== null ? (
          <>
            <SheetTitle className="flex items-center gap-2.5">
              {t('api_configurator.monitor.drill.title')}
              <RunStatusPill
                status={status}
                label={t(`api_configurator.detail.run_status.${status}`)}
              />
              <DirectionBadge dir={run.direction} label={run.direction} />
            </SheetTitle>

            <div className="grid grid-cols-4 gap-2">
              {[
                { label: t('api_configurator.monitor.drill.created'), v: run.createdCount },
                { label: t('api_configurator.monitor.drill.updated'), v: run.updatedCount },
                { label: t('api_configurator.monitor.drill.skipped'), v: run.skippedCount },
                { label: t('api_configurator.monitor.drill.failed'), v: run.failedCount },
              ].map((c) => (
                <div
                  key={c.label}
                  className="rounded-xl border border-zinc-200 bg-zinc-50/60 p-2.5"
                >
                  <div className="text-[10px] uppercase tracking-wider text-zinc-500">
                    {c.label}
                  </div>
                  <div className="text-[16px] font-semibold tabular-nums">{c.v}</div>
                </div>
              ))}
            </div>

            <div className="text-[11.5px] text-zinc-500">
              {t('api_configurator.monitor.drill.started')}:{' '}
              {new Date(run.startedAt).toLocaleString()}
            </div>

            <div className="rounded-xl border border-zinc-200">
              <div className="border-b border-zinc-100 px-3 py-2 text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
                {t('api_configurator.monitor.drill.records', { count: logs.length })}
              </div>
              <div className="max-h-[40vh] divide-y divide-zinc-50 overflow-y-auto">
                {logs.length === 0 ? (
                  <div className="px-3 py-4 text-[12px] text-zinc-500">
                    {t('api_configurator.monitor.drill.no_records')}
                  </div>
                ) : (
                  logs.map((l) => (
                    <div key={l.id} className="flex items-center gap-2 px-3 py-2 text-[11.5px]">
                      <span className="font-mono text-zinc-700">{l.matchKey ?? '—'}</span>
                      <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] text-zinc-600">
                        {l.action}
                      </span>
                      {l.message != null ? (
                        <span className="truncate text-zinc-500">{l.message}</span>
                      ) : null}
                    </div>
                  ))
                )}
              </div>
            </div>

            <div className="mt-auto flex items-center gap-2 pt-2">
              <Button type="button" variant="outline" onClick={downloadCsv}>
                {t('api_configurator.monitor.drill.download_csv')}
              </Button>
              <div className="flex-1" />
              <Button type="button" variant="outline" onClick={() => bindingAction('pause')}>
                {t('api_configurator.monitor.drill.pause')}
              </Button>
              <Button type="button" onClick={() => bindingAction('run')}>
                {t('api_configurator.monitor.drill.rerun')}
              </Button>
            </div>
          </>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
