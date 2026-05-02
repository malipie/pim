import { Download } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { MockBadge } from '@/components/ui/mock-badge';

import { CHANNEL_DISTRIBUTION } from '../mock-data';

/**
 * MOCK component — channel distribution stacked bar.
 * Backend: GET /api/dashboard/channel-distribution (do dorobienia).
 * Patrz Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 */
const COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#71717a'];

export function ChannelDistribution() {
  const { t } = useTranslation();
  const total = useMemo(
    () => CHANNEL_DISTRIBUTION.reduce((acc, slice) => acc + slice.count, 0),
    [],
  );
  return (
    <div className="relative rounded-2xl border border-line bg-surface p-5 soft-shadow">
      <MockBadge variant="corner" />
      <div className="flex items-baseline justify-between">
        <h3 className="text-[15px] font-semibold text-ink">{t('dashboard.channel_dist.title')}</h3>
        <div className="flex items-center gap-2">
          <span className="num text-[12px] text-muted-foreground">
            {total.toLocaleString('pl-PL')} {t('dashboard.channel_dist.unit')}
          </span>
          <button
            type="button"
            disabled
            aria-disabled="true"
            className="inline-flex cursor-not-allowed items-center gap-1 rounded-md border border-line px-2 py-0.5 text-[11px] text-muted-foreground"
          >
            <Download className="size-3" />
            {t('dashboard.channel_dist.export', { defaultValue: 'Eksportuj raport' })}
          </button>
          <MockBadge />
        </div>
      </div>
      <div
        className="mt-4 flex h-3 w-full overflow-hidden rounded-full bg-surface-2"
        role="img"
        aria-label={t('dashboard.channel_dist.title') ?? ''}
      >
        {CHANNEL_DISTRIBUTION.map((slice, i) => (
          <div
            key={slice.label}
            style={{ width: `${(slice.count / total) * 100}%`, background: COLORS[i] }}
            title={`${slice.label}: ${slice.count}`}
          />
        ))}
      </div>
      <ul className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-4">
        {CHANNEL_DISTRIBUTION.map((slice, i) => (
          <li key={slice.label} className="flex items-center gap-2 text-[12px] text-ink-2">
            <span className="size-2 rounded-full" style={{ background: COLORS[i] }} />
            <span className="flex-1 truncate">{slice.label}</span>
            <span className="num text-muted-foreground">{slice.count.toLocaleString('pl-PL')}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
