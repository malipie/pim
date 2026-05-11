import { Upload, Zap } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Sparkline } from '../primitives';
import type { ApiStatus, ImportSessionRow, ThroughputResponse } from './types';

interface KpiStripProps {
  sessions: ReadonlyArray<ImportSessionRow>;
  throughput?: ThroughputResponse;
}

interface DerivedKpis {
  active: number;
  todayRuns: number;
  todayOk: number;
  todayWarn: number;
  todayErr: number;
  rate30: number;
  rate30Spark: ReadonlyArray<number>;
  topErrors: ReadonlyArray<[ApiStatus, number]>;
}

function startOfToday(): number {
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  return now.getTime();
}

function deriveKpis(sessions: ReadonlyArray<ImportSessionRow>): DerivedKpis {
  let active = 0;
  let todayRuns = 0;
  let todayOk = 0;
  let todayWarn = 0;
  let todayErr = 0;
  const startToday = startOfToday();
  const last30Days: ImportSessionRow[] = [];
  const sparkBuckets = new Array<number>(30).fill(0);
  const sparkOk = new Array<number>(30).fill(0);
  const failureCounts = new Map<ApiStatus, number>();

  for (const session of sessions) {
    const startedTs = session.started_at ? new Date(session.started_at).getTime() : null;
    if (
      session.status === 'running' ||
      session.status === 'paused' ||
      session.status === 'pending'
    ) {
      active += 1;
    }
    if (startedTs !== null && startedTs >= startToday) {
      todayRuns += 1;
      if (session.status === 'success') {
        todayOk += 1;
      } else if (session.status === 'partial' || session.status === 'paused') {
        todayWarn += 1;
      } else if (session.status === 'failed') {
        todayErr += 1;
      }
    }
    if (startedTs !== null && startedTs >= Date.now() - 30 * 86_400_000) {
      last30Days.push(session);
      const daysAgo = Math.min(29, Math.floor((Date.now() - startedTs) / 86_400_000));
      const bucketIdx = 29 - daysAgo;
      sparkBuckets[bucketIdx] = (sparkBuckets[bucketIdx] ?? 0) + 1;
      if (session.status === 'success') {
        sparkOk[bucketIdx] = (sparkOk[bucketIdx] ?? 0) + 1;
      }
    }
    if (session.status === 'failed' || session.status === 'partial') {
      failureCounts.set(session.status, (failureCounts.get(session.status) ?? 0) + 1);
    }
  }

  const totalLast30 = last30Days.length;
  const okLast30 = last30Days.filter((s) => s.status === 'success').length;
  const rate30 = totalLast30 > 0 ? Math.round((okLast30 / totalLast30) * 100) : 0;

  const rate30Spark = sparkBuckets.map((total, idx) => {
    if (total === 0) {
      return 0;
    }
    return ((sparkOk[idx] ?? 0) / total) * 100;
  });

  const topErrors = [...failureCounts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 3);

  return {
    active,
    todayRuns,
    todayOk,
    todayWarn,
    todayErr,
    rate30,
    rate30Spark,
    topErrors,
  };
}

export function KpiStrip({ sessions, throughput }: KpiStripProps) {
  const { t } = useTranslation();
  const kpi = deriveKpis(sessions);
  const todayLabel = new Intl.DateTimeFormat('pl-PL', { day: 'numeric', month: 'long' }).format(
    new Date(),
  );

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
      <div className="rounded-2xl border border-zinc-100 bg-white p-4 soft-shadow">
        <div className="flex items-start justify-between">
          <div>
            <div className="text-[10.5px] uppercase tracking-wider text-zinc-400 font-medium">
              {t('imports.sessions.kpi.active')}
            </div>
            <div className="font-display text-[28px] font-semibold tracking-tight num mt-1 flex items-baseline gap-2">
              {kpi.active}
              <span className="text-[11.5px] font-normal text-zinc-500">
                {t('imports.sessions.kpi.active_unit')}
              </span>
            </div>
            <div className="text-[11px] text-zinc-500 mt-1.5 flex items-center gap-1.5">
              <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 pulse-dot" />
              {t('imports.sessions.kpi.throughput')}
              <span className="font-mono text-zinc-700">
                {throughput ? throughput.rows_per_sec.toFixed(0) : '—'}{' '}
                {t('imports.sessions.kpi.rows_per_sec')}
              </span>
            </div>
          </div>
          <div className="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-700 grid place-items-center">
            <Zap className="h-5 w-5" aria-hidden="true" />
          </div>
        </div>
      </div>

      <div className="rounded-2xl border border-zinc-100 bg-white p-4 soft-shadow">
        <div className="flex items-start justify-between">
          <div>
            <div className="text-[10.5px] uppercase tracking-wider text-zinc-400 font-medium">
              {t('imports.sessions.kpi.today', { date: todayLabel })}
            </div>
            <div className="font-display text-[28px] font-semibold tracking-tight num mt-1">
              {kpi.todayRuns}
            </div>
            <div className="flex items-center gap-2 mt-1.5 text-[11px]">
              <span className="text-emerald-700 font-medium num">✓ {kpi.todayOk}</span>
              <span className="text-amber-700 font-medium num">⚠ {kpi.todayWarn}</span>
              <span className="text-rose-700 font-medium num">✕ {kpi.todayErr}</span>
            </div>
          </div>
          <div className="h-10 w-10 rounded-xl bg-zinc-100 text-zinc-700 grid place-items-center">
            <Upload className="h-5 w-5" aria-hidden="true" />
          </div>
        </div>
      </div>

      <div className="rounded-2xl border border-zinc-100 bg-white p-4 soft-shadow">
        <div className="flex flex-col">
          <div className="text-[10.5px] uppercase tracking-wider text-zinc-400 font-medium">
            {t('imports.sessions.kpi.rate30')}
          </div>
          <div className="font-display text-[28px] font-semibold tracking-tight num mt-1 flex items-baseline gap-1">
            {kpi.rate30}
            <span className="text-[14px] text-zinc-500 font-normal">%</span>
          </div>
          <div className="mt-2 -ml-1">
            <Sparkline
              data={kpi.rate30Spark}
              width={140}
              height={24}
              stroke="#059669"
              fill="rgba(16,185,129,0.10)"
            />
          </div>
        </div>
      </div>

      <div className="rounded-2xl border border-zinc-100 bg-white p-4 soft-shadow">
        <div>
          <div className="text-[10.5px] uppercase tracking-wider text-zinc-400 font-medium">
            {t('imports.sessions.kpi.top_errors')}
          </div>
          {kpi.topErrors.length === 0 ? (
            <div className="text-[11.5px] text-zinc-400 mt-2">
              {t('imports.sessions.kpi.no_errors')}
            </div>
          ) : (
            <div className="mt-1.5 space-y-1">
              {kpi.topErrors.map(([type, count]) => (
                <div key={type} className="flex items-center gap-2">
                  <div className="text-[11.5px] text-zinc-600 truncate flex-1">
                    {t(`imports.sessions.status.${type}`)}
                  </div>
                  <div className="text-[11.5px] num font-medium text-zinc-900">{count}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
