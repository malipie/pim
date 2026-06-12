import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';

import { TOP_EDITED } from '../mock-data';

/**
 * MOCK component — top 10 edited products.
 * Backend: GET /api/dashboard/top-edited?limit=10 (do dorobienia).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
export function TopEditedProducts() {
  const { t } = useTranslation();
  return (
    <div className="relative rounded-2xl border border-line bg-surface soft-shadow">
      <MockBadge variant="corner" />
      <div className="flex items-center justify-between border-b border-line px-5 py-4">
        <h3 className="text-[15px] font-semibold text-ink">{t('dashboard.top_edited.title')}</h3>
        <span className="text-[12px] text-muted-foreground">
          {t('dashboard.top_edited.subtitle')}
        </span>
      </div>
      <ol className="divide-y divide-line">
        {TOP_EDITED.map((p, idx) => (
          <li key={p.sku} className="flex items-center gap-4 px-5 py-3 text-[13.5px]">
            <span className="num w-5 text-right text-muted-foreground">{idx + 1}</span>
            <span className="font-mono text-[12px] text-ink-2">{p.sku}</span>
            <span className="flex-1 truncate text-ink">{p.name}</span>
            <span className="hidden text-muted-foreground sm:inline">{p.family}</span>
            <span className="num inline-flex items-center gap-1 rounded-md bg-orange-500/10 px-2 py-0.5 text-[12px] font-medium text-orange-700">
              {p.edits}×
            </span>
            <span className="num hidden w-10 text-right text-[12px] tabular-nums text-ink-2 lg:inline">
              {p.completeness}%
            </span>
          </li>
        ))}
      </ol>
    </div>
  );
}
