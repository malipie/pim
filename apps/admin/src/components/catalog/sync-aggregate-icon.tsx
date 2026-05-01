import { Circle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export type SyncAggregate = 'green' | 'yellow' | 'red' | 'gray';

const TONE: Record<SyncAggregate, string> = {
  green: 'fill-emerald-500 text-emerald-500',
  yellow: 'fill-amber-500 text-amber-500',
  red: 'fill-rose-500 text-rose-500',
  gray: 'fill-muted-foreground text-muted-foreground',
};

/**
 * UI-02.10 (#300) — single-icon channel sync aggregate badge.
 *
 * Per `Project Plan/UI/epik-02-produkty.md` §4.5 / §11.5. Tooltip
 * surfaces the per-channel breakdown once UI-02.5 channels-status is
 * wired through (placeholder text in MVP).
 */
export function SyncAggregateIcon({ status }: { status: SyncAggregate }) {
  const { t } = useTranslation();
  const tone = TONE[status];

  return (
    <div className="inline-flex items-center" title={t(`products.sync.${status}`, status)}>
      <Circle className={`size-3 ${tone}`} aria-label={status} />
    </div>
  );
}
