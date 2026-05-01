import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { ACTIVITY_30D } from '../mock-data';

/**
 * MOCK component — 30-day activity chart (added vs modified).
 * Backend: GET /api/dashboard/activity?range=30d (do dorobienia).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
export function ActivityChart() {
  const { t } = useTranslation();
  const max = useMemo(
    () => Math.max(...ACTIVITY_30D.map((p) => Math.max(p.added, p.modified))),
    [],
  );

  const width = 720;
  const height = 220;
  const padX = 24;
  const padY = 24;
  const innerW = width - padX * 2;
  const innerH = height - padY * 2;

  const buildPath = (key: 'added' | 'modified') =>
    ACTIVITY_30D.map((point, i) => {
      const x = padX + (i / (ACTIVITY_30D.length - 1)) * innerW;
      const y = padY + innerH - (point[key] / max) * innerH;
      return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
    }).join(' ');

  return (
    <div className="rounded-2xl border border-line bg-surface p-5 soft-shadow">
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
          {/* MOCK: range picker (7d/30d/90d) — wymaga GET /api/dashboard/activity?range=… (#TBD) */}
          <span className="rounded-md border border-line px-2 py-0.5 text-[11px] text-muted-foreground">
            30d
          </span>
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
