import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Radio } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import { ChannelNodePickerDialog } from './channel-node-picker-dialog';

interface Placement {
  nodeId: string;
  nodePath: string;
  source: 'manual' | 'auto';
}

interface PlacementRow {
  channelId: string;
  channelCode: string;
  channelName: string;
  placement: Placement | null;
}

interface ListResponse {
  member: PlacementRow[];
  totalItems: number;
}

interface Props {
  productId: string;
}

const COLLAPSE_THRESHOLD = 5;

/**
 * CHC-03 (#1286) — "Gdzie trafia na kanałach" section inside the product
 * "Kategorie" tab. One row per tenant channel showing the navigation node the
 * product lands in (or a ⚠ when unmapped). Above {@link COLLAPSE_THRESHOLD}
 * channels the list collapses to just the unmapped rows.
 */
export function ChannelPlacementsSection({ productId }: Props) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [pickerChannel, setPickerChannel] = useState<{ id: string; name: string } | null>(null);
  const [busyChannelId, setBusyChannelId] = useState<string | null>(null);
  const [expanded, setExpanded] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ['products', productId, 'channel-placements'],
    queryFn: async () =>
      jsonFetch<ListResponse>(`/api/products/${productId}/channel-placements`, {
        accept: 'application/json',
      }),
    enabled: productId !== '',
  });

  const rows = useMemo(() => data?.member ?? [], [data]);
  const missingCount = rows.filter((r) => r.placement === null).length;
  const collapsing = rows.length >= COLLAPSE_THRESHOLD && !expanded;
  const visibleRows = collapsing ? rows.filter((r) => r.placement === null) : rows;

  const refresh = async (): Promise<void> => {
    await queryClient.invalidateQueries({
      queryKey: ['products', productId, 'channel-placements'],
    });
  };

  const assign = async (channelId: string, nodeId: string): Promise<void> => {
    await jsonFetch(`/api/products/${productId}/channel-placements/${channelId}`, {
      method: 'PUT',
      contentType: 'application/json',
      accept: 'application/json',
      body: { nodeId },
    });
    await refresh();
  };

  const restoreAuto = async (channelId: string): Promise<void> => {
    if (busyChannelId !== null) return;
    setBusyChannelId(channelId);
    try {
      await jsonFetch(`/api/products/${productId}/channel-placements/${channelId}`, {
        method: 'DELETE',
        accept: 'application/json',
      });
      await refresh();
    } finally {
      setBusyChannelId(null);
    }
  };

  if (!isLoading && rows.length === 0) {
    return null; // no channels configured for the tenant — nothing to place
  }

  return (
    <section className="space-y-3 border-t border-line pt-4">
      <header className="flex items-center justify-between">
        <h4 className="flex items-center gap-1.5 text-[12.5px] font-semibold text-ink">
          <Radio className="size-3.5 text-orange-600" />
          {t('products.detail.placements.title', { defaultValue: 'Gdzie trafia na kanałach' })}
        </h4>
        {collapsing && missingCount < rows.length ? (
          <button
            type="button"
            onClick={() => setExpanded(true)}
            className="text-[11.5px] font-medium text-orange-600 hover:underline"
          >
            {t('products.detail.placements.show_all', {
              defaultValue: 'Pokaż wszystkie ({{count}})',
              count: rows.length,
            })}
          </button>
        ) : null}
      </header>

      {isLoading ? (
        <p className="text-[12px] text-muted-foreground">
          {t('app.loading', { defaultValue: 'Ładowanie…' })}
        </p>
      ) : (
        <ul className="divide-y divide-line/60 rounded-xl border border-line">
          {visibleRows.map((row) => {
            const name = row.channelName || row.channelCode;
            const isBusy = busyChannelId === row.channelId;
            return (
              <li
                key={row.channelId}
                className={cn(
                  'flex items-center justify-between gap-3 px-3 py-2',
                  isBusy && 'opacity-50',
                )}
              >
                <div className="min-w-0">
                  <p className="text-[12.5px] font-medium text-ink">{name}</p>
                  {row.placement === null ? (
                    <p className="flex items-center gap-1 text-[11.5px] text-amber-600">
                      <AlertTriangle className="size-3" />
                      {t('products.detail.placements.unmapped', { defaultValue: 'brak mapowania' })}
                    </p>
                  ) : (
                    <p className="truncate text-[11.5px] text-muted-foreground">
                      {row.placement.nodePath}{' '}
                      <span className="text-zinc-400">
                        {row.placement.source === 'manual'
                          ? t('products.detail.placements.manual', { defaultValue: '(ręcznie)' })
                          : t('products.detail.placements.auto', { defaultValue: '(auto)' })}
                      </span>
                    </p>
                  )}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  {row.placement !== null ? (
                    <button
                      type="button"
                      onClick={() => void restoreAuto(row.channelId)}
                      disabled={isBusy}
                      className="text-[11.5px] text-zinc-500 hover:text-red-600 hover:underline"
                    >
                      {t('products.detail.placements.restore_auto', {
                        defaultValue: 'Przywróć automatyczne',
                      })}
                    </button>
                  ) : null}
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setPickerChannel({ id: row.channelId, name })}
                    disabled={isBusy}
                  >
                    {row.placement === null
                      ? t('products.detail.placements.assign', { defaultValue: 'Przypisz' })
                      : t('products.detail.placements.reassign', { defaultValue: 'Nadpisz' })}
                  </Button>
                </div>
              </li>
            );
          })}
        </ul>
      )}

      <ChannelNodePickerDialog
        open={pickerChannel !== null}
        onOpenChange={(next) => {
          if (!next) setPickerChannel(null);
        }}
        channelId={pickerChannel?.id ?? null}
        channelName={pickerChannel?.name ?? ''}
        onPick={async (nodeId) => {
          if (pickerChannel !== null) await assign(pickerChannel.id, nodeId);
        }}
      />
    </section>
  );
}
