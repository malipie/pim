import {
  AlertTriangle,
  ArrowUpRight,
  Boxes,
  CheckCircle2,
  Clock,
  FolderTree,
  Layers,
  ShieldCheck,
  SlidersHorizontal,
  Tags,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { MockBadge } from '@/components/ui/mock-badge';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

import {
  KPI_DEFAULT_SELECTION,
  KPI_MAX_SELECTION,
  KPI_TILES,
  type KpiKey,
  type KpiTile,
} from '../mock-data';
import { LIVE_KPI_KEYS, type LiveKpiKey } from '../use-dashboard-counts';

const ICONS: Record<KpiKey, typeof Boxes> = {
  products: Boxes,
  attributes: Tags,
  families: Layers,
  categories: FolderTree,
  enabled_share: CheckCircle2,
  completeness_avg: ShieldCheck,
  last_sync_minutes: Clock,
  open_alerts: AlertTriangle,
};

const STORAGE_KEY = 'pim.dashboard.kpi.selection';

function loadSelection(): KpiKey[] {
  if (typeof window === 'undefined') {
    return KPI_DEFAULT_SELECTION;
  }
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return KPI_DEFAULT_SELECTION;
    }
    const parsed = JSON.parse(raw) as unknown;
    if (
      Array.isArray(parsed) &&
      parsed.every((k): k is KpiKey => KPI_TILES.some((tile) => tile.key === k))
    ) {
      return parsed.slice(0, KPI_MAX_SELECTION);
    }
  } catch {
    // fall through to defaults
  }
  return KPI_DEFAULT_SELECTION;
}

function persistSelection(selection: KpiKey[]) {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(selection));
  } catch {
    // ignore quota errors
  }
}

interface KpiCardsProps {
  /** NUI-02 — live entity totals; tiles in LIVE_KPI_KEYS render them without a MockBadge. */
  counts?: Partial<Record<LiveKpiKey, number>>;
  isPending?: boolean;
}

/**
 * KPI tiles, user picks 4 of 8 candidates. Entity totals
 * (products/attributes/groups/categories) are LIVE (NUI-02 #1421); the
 * remaining tiles and every delta stay mocked — backend follow-ups in
 * Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 * Selection persists in localStorage per UI-03b plan; production will move
 * to workspace_settings.
 */
export function KpiCards({ counts = {}, isPending = false }: KpiCardsProps) {
  const { t } = useTranslation();
  const [selection, setSelection] = useState<KpiKey[]>(KPI_DEFAULT_SELECTION);

  useEffect(() => {
    setSelection(loadSelection());
  }, []);

  const visibleTiles: KpiTile[] = selection
    .map((key) => KPI_TILES.find((tile) => tile.key === key))
    .filter((tile): tile is KpiTile => Boolean(tile));

  const toggle = (key: KpiKey) => {
    setSelection((prev) => {
      const next = prev.includes(key)
        ? prev.filter((k) => k !== key)
        : prev.length >= KPI_MAX_SELECTION
          ? prev
          : [...prev, key];
      persistSelection(next);
      return next;
    });
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-end">
        <Sheet>
          <SheetTrigger asChild>
            <Button
              variant="ghost"
              size="sm"
              className="text-xs text-muted-foreground hover:text-foreground"
            >
              <SlidersHorizontal className="size-3.5" />
              <span>{t('dashboard.kpi.settings_trigger', { defaultValue: 'Wybierz KPI' })}</span>
            </Button>
          </SheetTrigger>
          <SheetContent
            side="right"
            className="w-full sm:max-w-md"
            closeLabel={t('app.close', { defaultValue: 'Zamknij' })}
          >
            <div className="flex items-center justify-between border-b border-line px-4 py-3">
              <SheetTitle>
                {t('dashboard.kpi.settings_title', { defaultValue: 'Wybierz KPI' })}
              </SheetTitle>
            </div>
            <MockBadge
              variant="overlay"
              tooltip={t('dashboard.kpi.settings_mock_tooltip', {
                defaultValue: 'Konfiguracja zapisywana lokalnie (localStorage), MOCK',
              })}
            >
              <div className="mt-4 space-y-2 px-4 pb-4">
                <p className="text-sm text-muted-foreground">
                  {t('dashboard.kpi.settings_description', {
                    defaultValue:
                      'Wybierz do {{max}} kafelków na pulpicie. Selekcja zapisuje się w przeglądarce.',
                    max: KPI_MAX_SELECTION,
                  })}
                </p>
                <ul className="divide-y divide-line">
                  {KPI_TILES.map((tile) => {
                    const checked = selection.includes(tile.key);
                    const disabled = !checked && selection.length >= KPI_MAX_SELECTION;
                    return (
                      <li key={tile.key} className="py-2">
                        <label
                          className={cn(
                            'flex cursor-pointer items-center gap-3 text-sm',
                            disabled && 'cursor-not-allowed opacity-50',
                          )}
                        >
                          <input
                            type="checkbox"
                            checked={checked}
                            disabled={disabled}
                            onChange={() => toggle(tile.key)}
                            className="size-4"
                          />
                          <span className="flex-1">{t(`dashboard.kpi.${tile.key}`)}</span>
                          <span className="text-[11px] text-muted-foreground">
                            {tile.value.toLocaleString('pl-PL')}
                            {tile.unit ?? ''}
                          </span>
                        </label>
                      </li>
                    );
                  })}
                </ul>
                <p className="text-[11px] text-muted-foreground">
                  {t('dashboard.kpi.settings_count', {
                    defaultValue: '{{count}} z {{max}} wybranych',
                    count: selection.length,
                    max: KPI_MAX_SELECTION,
                  })}
                </p>
              </div>
            </MockBadge>
          </SheetContent>
        </Sheet>
      </div>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {visibleTiles.map((tile) => {
          const Icon = ICONS[tile.key];
          const isPositive = tile.delta >= 0;
          const isLive = (LIVE_KPI_KEYS as readonly string[]).includes(tile.key);
          const liveValue = isLive ? counts[tile.key as LiveKpiKey] : undefined;
          const value = liveValue ?? tile.value;
          const showAsLive = isLive && liveValue !== undefined;
          return (
            <div
              key={tile.key}
              className={cn('relative rounded-2xl border border-line bg-surface p-5 soft-shadow')}
            >
              {!showAsLive && <MockBadge variant="corner" />}
              <div className="flex items-start justify-between">
                <span className="text-[13px] font-medium text-muted-foreground">
                  {t(`dashboard.kpi.${tile.key}`)}
                </span>
                <Icon className="size-4 text-muted-foreground" />
              </div>
              <div className="mt-3 flex items-baseline gap-2">
                {isLive && isPending ? (
                  <span
                    className="inline-block h-8 w-20 animate-pulse rounded-lg bg-zinc-100"
                    aria-hidden
                  />
                ) : (
                  <span className="num display text-[28px] font-semibold text-ink">
                    {value.toLocaleString('pl-PL')}
                    {tile.unit ? (
                      <span className="ml-1 text-[16px] text-muted-foreground">{tile.unit}</span>
                    ) : null}
                  </span>
                )}
                {/* Deltas need a history aggregate the backend does not have —
                    live tiles skip them instead of faking a trend (backlog). */}
                {!showAsLive && (
                  <span
                    className={cn(
                      'num inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[11px] font-medium',
                      isPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700',
                    )}
                  >
                    <ArrowUpRight className={cn('size-3', !isPositive && 'rotate-90')} />
                    {isPositive ? '+' : ''}
                    {tile.delta}
                  </span>
                )}
              </div>
              {!showAsLive && (
                <span className="mt-1 block text-[11px] text-muted-foreground">{tile.hint}</span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
