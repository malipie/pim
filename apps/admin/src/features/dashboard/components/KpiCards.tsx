import { ArrowUpRight, Boxes, FolderTree, Layers, Tags } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import { KPI_TILES, type KpiTile } from '../mock-data';

/**
 * MOCK component — 4 KPI tiles z deltą.
 * Backend: GET /api/dashboard/kpis (do dorobienia).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
const ICONS: Record<KpiTile['key'], typeof Boxes> = {
  products: Boxes,
  attributes: Tags,
  families: Layers,
  categories: FolderTree,
};

export function KpiCards() {
  const { t } = useTranslation();

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {KPI_TILES.map((tile) => {
        const Icon = ICONS[tile.key];
        return (
          <div
            key={tile.key}
            className={cn('rounded-2xl border border-line bg-surface p-5 soft-shadow')}
          >
            <div className="flex items-start justify-between">
              <span className="text-[13px] font-medium text-muted-foreground">
                {t(`dashboard.kpi.${tile.key}`)}
              </span>
              <Icon className="size-4 text-muted-foreground" />
            </div>
            <div className="mt-3 flex items-baseline gap-2">
              <span className="num display text-[28px] font-semibold text-ink">
                {tile.value.toLocaleString('pl-PL')}
              </span>
              <span className="num inline-flex items-center gap-0.5 rounded-md bg-accent-emerald/10 px-1.5 py-0.5 text-[11px] font-medium text-accent-emerald">
                <ArrowUpRight className="size-3" />+{tile.delta}
              </span>
            </div>
            <span className="mt-1 block text-[11px] text-muted-foreground">{tile.hint}</span>
          </div>
        );
      })}
    </div>
  );
}
