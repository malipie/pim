import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';
import { cn } from '@/lib/utils';

import { ACTIVITY_BY_RANGE, type ActivityRange } from '../mock-data';

const RANGES: ActivityRange[] = ['7d', '30d', '90d'];

/**
 * MOCK component — activity chart with range picker.
 * Backend: GET /api/dashboard/activity?range=7d|30d|90d (do dorobienia).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
export function ActivityChart() {
  const { t } = useTranslation();
  const [range, setRange] = useState<ActivityRange>('30d');
  const points = ACTIVITY_BY_RANGE[range];
  const max = useMemo(
    () => Math.max(...points.map((p) => Math.max(p.added, p.modified))),
    [points],
  );

  const width = 720;
  const height = 220;
  const padX = 24;
  const padY = 24;
  const innerW = width - padX * 2;
  const innerH = height - padY * 2;

  const buildPath = (key: 'added' | 'modified') =>
    points
      .map((point, i) => {
        const x = padX + (i / (points.length - 1)) * innerW;
        const y = padY + innerH - (point[key] / max) * innerH;
        return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
      })
      .join(' ');

  return (
    <div className="relative rounded-2xl border border-line bg-surface p-5 soft-shadow">
      <MockBadge variant="corner" />
      <div className="flex items-baseline justify-between">
        <h3 className="text-[15px] font-semibold text-ink">{t('dashboard.activity.title')}</h3>
        <div className="flex items-center gap-3 text-[12px] text-muted-foreground">
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2 rounded-full bg-accent-emerald" />
            {t('dashboard.activity.legend_added')}
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="size-2 rounded-full bg-accent-violet" />
            {t('dashboard.activity.legend_modified')}
          </span>
          <div
            className="flex items-center gap-0.5 rounded-md border border-line p-0.5"
            role="tablist"
            aria-label={t('dashboard.activity.range_aria', { defaultValue: 'Zakres aktywności' })}
          >
            {RANGES.map((r) => (
              <button
                key={r}
                type="button"
                role="tab"
                aria-selected={range === r}
                onClick={() => setRange(r)}
                className={cn(
                  'rounded px-2 py-0.5 text-[11px] font-medium transition-colors',
                  range === r
                    ? 'bg-accent-violet/10 text-accent-violet'
                    : 'text-muted-foreground hover:text-foreground',
                )}
              >
                {t(`dashboard.activity.range_${r}`, { defaultValue: r })}
              </button>
            ))}
          </div>
        </div>
      </div>
      <svg
        viewBox={`0 0 ${width} ${height}`}
        className="mt-4 w-full"
        role="img"
        aria-label={t('dashboard.activity.title') ?? ''}
      >
        <title>{t('dashboard.activity.title')}</title>
        <path d={buildPath('modified')} fill="none" stroke="#a855f7" strokeWidth={2} />
        <path d={buildPath('added')} fill="none" stroke="#10b981" strokeWidth={2} />
      </svg>
    </div>
  );
}
