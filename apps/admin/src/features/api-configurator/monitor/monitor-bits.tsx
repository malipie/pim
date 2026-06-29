import type { SyncDirection } from '../components/primitives';

/**
 * APIC-P4-02 — small presentational pieces + row type for the sync monitor.
 */
export interface MonitorRunRow {
  id: string;
  bindingId: string;
  direction: SyncDirection;
  startedAt: string;
  finishedAt: string | null;
  status: string;
  createdCount: number;
  updatedCount: number;
  skippedCount: number;
  failedCount: number;
  cursorAfter: { state?: unknown } | null;
}

export interface MonitorKpis {
  syncs24h: number;
  recordsIn: number;
  recordsOut: number;
  errors24h: number;
}

/**
 * Aggregate KPIs from the run list. "in" = records of inbound legs, "out" =
 * outbound legs (bidirectional counts on both). 24h windows use `now`.
 */
export function computeKpis(runs: MonitorRunRow[], nowMs: number): MonitorKpis {
  const dayAgo = nowMs - 24 * 60 * 60 * 1000;
  let syncs24h = 0;
  let recordsIn = 0;
  let recordsOut = 0;
  let errors24h = 0;

  for (const run of runs) {
    const startedMs = Date.parse(run.startedAt);
    const within24h = !Number.isNaN(startedMs) && startedMs >= dayAgo;
    if (within24h) {
      syncs24h += 1;
      if (run.failedCount > 0 || run.status === 'failed') {
        errors24h += 1;
      }
    }
    const records = run.createdCount + run.updatedCount;
    if (run.direction === 'inbound' || run.direction === 'bidirectional') {
      recordsIn += records;
    }
    if (run.direction === 'outbound' || run.direction === 'bidirectional') {
      recordsOut += records;
    }
  }

  return { syncs24h, recordsIn, recordsOut, errors24h };
}

export function KpiCell({
  label,
  value,
  tone = 'zinc',
  sub,
}: {
  label: string;
  value: string;
  tone?: 'zinc' | 'sky' | 'violet' | 'rose';
  sub?: string;
}) {
  const toneCls = {
    zinc: 'text-zinc-900',
    sky: 'text-sky-700',
    violet: 'text-violet-700',
    rose: 'text-rose-700',
  }[tone];

  return (
    <div className="soft-shadow rounded-2xl border border-zinc-200 bg-white p-4">
      <div className="text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
        {label}
      </div>
      <div className={`mt-1 text-[20px] font-semibold tabular-nums ${toneCls}`}>{value}</div>
      {sub != null ? <div className="text-[10.5px] text-zinc-500">{sub}</div> : null}
    </div>
  );
}

/** Stacked ok/warn/err proportion bar (replaces the prototype ResultBar). */
export function ResultBar({
  ok,
  warn,
  err,
  width = 110,
  label,
}: {
  ok: number;
  warn: number;
  err: number;
  width?: number;
  label: string;
}) {
  const total = Math.max(1, ok + warn + err);
  const pct = (n: number) => `${(n / total) * 100}%`;
  return (
    <div
      className="flex h-2 overflow-hidden rounded-full bg-zinc-100"
      style={{ width }}
      role="img"
      aria-label={label}
    >
      {ok > 0 ? <span className="h-full bg-emerald-500" style={{ width: pct(ok) }} /> : null}
      {warn > 0 ? <span className="h-full bg-amber-500" style={{ width: pct(warn) }} /> : null}
      {err > 0 ? <span className="h-full bg-rose-500" style={{ width: pct(err) }} /> : null}
    </div>
  );
}
