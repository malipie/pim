import { useList } from '@refinedev/core';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { DirectionBadge, SectionLabel } from '../../components/primitives';
import { RunStatusDot, RunStatusPill, toRunStatus } from './RunStatus';
import type { SyncRunLogRow, SyncRunRow } from './types';

/**
 * APIC-P3-12 — the connection-detail History tab: the SyncRun list (P4-01)
 * with a per-run drill-down into the SyncRunLog records.
 */
export function DetailHistory({ connectionId }: { connectionId: string }) {
  const { t } = useTranslation();
  const [openRunId, setOpenRunId] = useState<string | null>(null);

  const runsQuery = useList<SyncRunRow>({
    resource: 'sync_runs',
    filters: [{ field: 'connection', operator: 'eq', value: connectionId }],
    pagination: { mode: 'off' },
    queryOptions: { enabled: connectionId !== '' },
  });
  const logsQuery = useList<SyncRunLogRow>({
    resource: 'sync_run_logs',
    filters: openRunId === null ? [] : [{ field: 'run', operator: 'eq', value: openRunId }],
    pagination: { mode: 'off' },
    queryOptions: { enabled: openRunId !== null },
  });

  const runs = runsQuery.result.data;

  return (
    <section className="soft-shadow overflow-hidden rounded-2xl border border-zinc-200 bg-white">
      <div className="border-b border-zinc-100 px-5 py-3">
        <SectionLabel>{t('api_configurator.detail.history.title')}</SectionLabel>
        {runs.length === 0 ? (
          <p className="text-[12.5px] text-zinc-500">
            {t('api_configurator.detail.history.empty')}
          </p>
        ) : null}
      </div>
      <div className="divide-y divide-zinc-50">
        {runs.map((run) => {
          const status = toRunStatus(run.status);
          const open = openRunId === run.id;
          return (
            <div key={run.id}>
              <button
                type="button"
                onClick={() => setOpenRunId(open ? null : run.id)}
                aria-expanded={open}
                className="flex w-full items-center gap-3 px-5 py-3 text-left transition hover:bg-zinc-50/70"
              >
                <RunStatusDot status={status} />
                <div className="min-w-0">
                  <div className="text-[12.5px] text-zinc-800">
                    {new Date(run.startedAt).toLocaleString()}
                  </div>
                  <div className="font-mono text-[10.5px] text-zinc-500">
                    +{run.createdCount} ~{run.updatedCount}
                    {run.skippedCount > 0 ? ` ⤳${run.skippedCount}` : ''}
                    {run.failedCount > 0 ? ` ✕${run.failedCount}` : ''}
                  </div>
                </div>
                <span className="ml-auto flex items-center gap-3">
                  <DirectionBadge dir={run.direction} label={run.direction} />
                  <RunStatusPill
                    status={status}
                    label={t(`api_configurator.detail.run_status.${status}`)}
                  />
                </span>
              </button>

              {open ? (
                <div className="bg-zinc-50/50 px-5 py-3">
                  {logsQuery.result.data.length === 0 ? (
                    <p className="text-[11.5px] text-zinc-500">
                      {t('api_configurator.detail.history.no_records')}
                    </p>
                  ) : (
                    <div className="space-y-1">
                      {logsQuery.result.data.map((log) => (
                        <div key={log.id} className="flex items-center gap-2 text-[11.5px]">
                          <span className="font-mono text-zinc-700">{log.matchKey ?? '—'}</span>
                          <span className="rounded bg-white px-1.5 py-0.5 text-[10px] text-zinc-600">
                            {log.action}
                          </span>
                          {log.message != null ? (
                            <span className="truncate text-zinc-500">{log.message}</span>
                          ) : null}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : null}
            </div>
          );
        })}
      </div>
    </section>
  );
}
