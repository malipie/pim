import { cn } from '@/lib/utils';

/**
 * 13 RBAC modules per the §5.3 design coverage strip. Stable order, color
 * keyed by module identity (matches `Zrodla/.../settings/data.jsx`
 * `RBAC_MODULES`). When backend exposes per-module coverage via
 * RoleListItem.permission_coverage the strip switches to those bars; until
 * then it renders a degraded version (uniform grey-to-zinc fill at the
 * overall coverage ratio) so the visual layout matches the design even
 * without the data.
 */
const MODULES: ReadonlyArray<{
  code: string;
  short: string;
  base: string;
  full: string;
}> = [
  { code: 'platform', short: 'PLA', base: 'bg-rose-100', full: 'bg-rose-500' },
  { code: 'produkty', short: 'PRO', base: 'bg-emerald-100', full: 'bg-emerald-500' },
  { code: 'kategorie', short: 'KAT', base: 'bg-emerald-100', full: 'bg-emerald-500' },
  { code: 'multimedia', short: 'MUL', base: 'bg-violet-100', full: 'bg-violet-500' },
  { code: 'modelowanie', short: 'MOD', base: 'bg-blue-100', full: 'bg-blue-500' },
  { code: 'publikacje', short: 'PUB', base: 'bg-cyan-100', full: 'bg-cyan-500' },
  { code: 'imports', short: 'IMP', base: 'bg-amber-100', full: 'bg-amber-500' },
  { code: 'exports', short: 'EXP', base: 'bg-amber-100', full: 'bg-amber-500' },
  { code: 'workflow', short: 'WOR', base: 'bg-violet-100', full: 'bg-violet-500' },
  { code: 'cmdk', short: 'CMDK', base: 'bg-zinc-100', full: 'bg-zinc-500' },
  { code: 'settings', short: 'SET', base: 'bg-zinc-100', full: 'bg-zinc-500' },
  { code: 'tokens', short: 'TOK', base: 'bg-zinc-100', full: 'bg-zinc-500' },
  { code: 'audit', short: 'AUD', base: 'bg-zinc-100', full: 'bg-zinc-500' },
];

export interface CoverageStripProps {
  /**
   * Per-module coverage map. Falls back to an overall pct fill when omitted
   * (backend has not shipped per-module coverage yet — #865 follow-up).
   */
  perModule?: Record<string, { covered: number; total: number; pct: number }>;
  /** Overall percentage 0..100. Used when `perModule` is absent. */
  overallPct: number;
}

export function CoverageStrip({ perModule, overallPct }: CoverageStripProps) {
  const safePct = Math.min(100, Math.max(0, overallPct));
  return (
    <div
      className="grid grid-cols-13 gap-1"
      style={{ gridTemplateColumns: 'repeat(13, minmax(0, 1fr))' }}
    >
      {MODULES.map((m) => {
        const entry = perModule?.[m.code];
        const ratio = entry ? entry.pct / 100 : safePct / 100;
        const empty = ratio === 0;
        return (
          <div key={m.code} className="space-y-1" title={`${m.code} · ${Math.round(ratio * 100)}%`}>
            <div
              className={cn(
                'relative h-8 overflow-hidden rounded-md',
                empty ? 'border border-dashed border-zinc-200 bg-zinc-50' : m.base,
              )}
            >
              {!empty ? (
                <div
                  className={cn('absolute inset-0', m.full)}
                  style={{ clipPath: `inset(${(1 - ratio) * 100}% 0 0 0)` }}
                />
              ) : null}
            </div>
            <div className="truncate text-center font-mono text-[9px] uppercase text-zinc-400">
              {m.short}
            </div>
          </div>
        );
      })}
    </div>
  );
}
