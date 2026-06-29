import { useList } from '@refinedev/core';
import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { CoverageBar, SectionLabel, SecurityNote, TypeCompat } from '../../components/primitives';
import { RunStatusDot, RunStatusPill, toRunStatus } from './RunStatus';
import type { SyncBindingRow, SyncRunRow } from './types';

interface FieldMappingRow {
  id: string;
}

/** Common PIM targets (mirrors the mapper's coverage denominator). */
const PIM_TARGET_TOTAL = 6;

/**
 * APIC-P3-12 — the connection-detail Overview tab: info tiles, recent runs,
 * mapping coverage and a static security posture card.
 */
export function DetailOverview({
  connectionId,
  binding,
  rateLimitHint,
  onOpenTab,
}: {
  connectionId: string;
  binding: SyncBindingRow | null;
  rateLimitHint: number | null;
  onOpenTab: (tab: 'mapping' | 'history') => void;
}) {
  const { t } = useTranslation();

  const runsQuery = useList<SyncRunRow>({
    resource: 'sync_runs',
    filters: [{ field: 'connection', operator: 'eq', value: connectionId }],
    pagination: { mode: 'off' },
    queryOptions: { enabled: connectionId !== '' },
  });
  const mappingsQuery = useList<FieldMappingRow>({
    resource: 'field_mappings',
    filters: [{ field: 'connection', operator: 'eq', value: connectionId }],
    pagination: { mode: 'off' },
    queryOptions: { enabled: connectionId !== '' },
  });

  const runs = runsQuery.result.data.slice(0, 4);
  const lastRun = runs[0] ?? null;
  const mappedCount = mappingsQuery.result.data.length;

  const dash = '—';
  const tiles = [
    {
      label: t('api_configurator.detail.overview.last_sync'),
      main: lastRun !== null ? new Date(lastRun.startedAt).toLocaleString() : dash,
    },
    {
      label: t('api_configurator.detail.overview.next_run'),
      main: binding?.nextRun != null ? new Date(binding.nextRun).toLocaleString() : dash,
    },
    {
      label: t('api_configurator.detail.overview.cursor'),
      main: binding?.cursor?.field ?? dash,
      mono: true,
    },
    {
      label: t('api_configurator.detail.overview.rate_limit'),
      main: rateLimitHint !== null ? String(rateLimitHint) : dash,
      sub: t('api_configurator.detail.overview.rate_limit_sub'),
    },
  ];

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.6fr_1fr]">
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {tiles.map((tile) => (
            <div
              key={tile.label}
              className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-4"
            >
              <div className="text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
                {tile.label}
              </div>
              <div className={`mt-1 text-[13px] text-zinc-900 ${tile.mono ? 'font-mono' : ''}`}>
                {tile.main}
              </div>
              {tile.sub != null ? (
                <div className="text-[10.5px] text-zinc-500">{tile.sub}</div>
              ) : null}
            </div>
          ))}
        </div>

        <section className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-5">
          <SectionLabel
            right={
              <button
                type="button"
                onClick={() => onOpenTab('history')}
                className="text-[11.5px] font-medium text-zinc-600 hover:text-zinc-900"
              >
                {t('api_configurator.detail.overview.all_history')}
              </button>
            }
          >
            {t('api_configurator.detail.overview.recent_runs')}
          </SectionLabel>
          {runs.length === 0 ? (
            <p className="text-[12.5px] text-zinc-500">
              {t('api_configurator.detail.overview.no_runs')}
            </p>
          ) : (
            <div className="space-y-1.5">
              {runs.map((run) => (
                <div key={run.id} className="flex items-center gap-2.5 text-[12.5px]">
                  <RunStatusDot status={toRunStatus(run.status)} />
                  <span className="text-zinc-800">{new Date(run.startedAt).toLocaleString()}</span>
                  <span className="font-mono text-[10.5px] text-zinc-500">
                    +{run.createdCount} ~{run.updatedCount}
                    {run.failedCount > 0 ? ` ✕${run.failedCount}` : ''}
                  </span>
                  <span className="ml-auto">
                    <RunStatusPill
                      status={toRunStatus(run.status)}
                      label={t(`api_configurator.detail.run_status.${toRunStatus(run.status)}`)}
                    />
                  </span>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      <div className="space-y-4">
        <section className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-5">
          <SectionLabel>{t('api_configurator.detail.overview.coverage')}</SectionLabel>
          <div className="flex items-center gap-3">
            <CoverageBar
              mapped={mappedCount}
              total={PIM_TARGET_TOTAL}
              width={140}
              ariaLabel={t('api_configurator.detail.overview.coverage')}
            />
            <button
              type="button"
              onClick={() => onOpenTab('mapping')}
              className="text-[11.5px] font-medium text-zinc-600 hover:text-zinc-900"
            >
              {t('api_configurator.detail.overview.edit_mapping')}
            </button>
          </div>
        </section>

        <section className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-5">
          <SectionLabel>{t('api_configurator.detail.overview.security')}</SectionLabel>
          <div className="space-y-2.5">
            {['ssrf', 'secrets', 'tenant', 'auth'].map((key) => (
              <div key={key} className="flex items-center gap-2.5">
                <TypeCompat ok title={t(`api_configurator.detail.security.${key}`)} />
                <span className="flex-1 text-[12.5px] text-zinc-700">
                  {t(`api_configurator.detail.security.${key}`)}
                </span>
              </div>
            ))}
          </div>
          <div className="mt-3">
            <SecurityNote tone="zinc" icon={<Info className="size-4" />}>
              {t('api_configurator.detail.security.note')}
            </SecurityNote>
          </div>
        </section>
      </div>
    </div>
  );
}
