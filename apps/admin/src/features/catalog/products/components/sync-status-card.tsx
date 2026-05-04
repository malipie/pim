import { Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Card } from '@/components/ui/card';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface ChannelStatus {
  product_id: string;
  aggregate: string;
  channels: Array<{
    code: string;
    status: string;
    last_sync_at: string | null;
    note?: string | null;
  }>;
}

const STATUS_DOT: Record<string, string> = {
  ok: 'bg-emerald-500',
  green: 'bg-emerald-500',
  yellow: 'bg-amber-500',
  amber: 'bg-amber-500',
  red: 'bg-rose-500',
  fail: 'bg-rose-500',
  gray: 'bg-zinc-300',
};

export interface SyncStatusCardProps {
  productId: string;
}

/**
 * VIEW-07 (#420) — sidebar "Status publikacji" card mirrored from
 * `detail-view.jsx` lines 279–300. Reads the existing `/channels-status`
 * endpoint (UI-02.5) — `Wymuś synchronizację` is intentionally a mock
 * because the real publish flow lands in Faza 1 epik 09 (Shopify).
 */
export function SyncStatusCard({ productId }: SyncStatusCardProps) {
  const { t } = useTranslation();
  const [data, setData] = useState<ChannelStatus | null>(null);

  useEffect(() => {
    let cancelled = false;
    if (productId === '') return;
    jsonFetch<ChannelStatus>(`/api/products/${productId}/channels-status`)
      .then((body) => {
        if (!cancelled) setData(body);
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [productId]);

  return (
    <Card className="rounded-2xl border-line bg-surface p-5 soft-shadow">
      <div className="mb-2.5 text-[12px] font-medium text-zinc-500">
        {t('products.detail.sidebar.publication_status', { defaultValue: 'Status publikacji' })}
      </div>
      <ul className="space-y-2.5">
        {data === null ? (
          <li className="text-[12px] text-muted-foreground">{t('app.loading')}</li>
        ) : data.channels.length === 0 ? (
          <li className="text-[12px] text-muted-foreground">
            {t('products.detail.sidebar.no_channels', {
              defaultValue: 'Brak kanałów (synchronizacja w Faza 1)',
            })}
          </li>
        ) : (
          data.channels.map((channel) => {
            const dot = STATUS_DOT[channel.status] ?? 'bg-zinc-300';
            const note =
              channel.note ??
              channel.last_sync_at ??
              t(`products.detail.sidebar.status_${channel.status}`, {
                defaultValue: channel.status,
              });
            return (
              <li key={channel.code} className="flex items-center gap-2.5">
                <span className={cn('size-2 shrink-0 rounded-full', dot)} aria-hidden />
                <span className="text-[13px] font-medium">{prettyChannelName(channel.code)}</span>
                <span className="ml-auto text-[11px] text-zinc-500">{note}</span>
              </li>
            );
          })
        )}
      </ul>
      <button
        type="button"
        onClick={() =>
          toast.info(
            t('products.detail.sidebar.force_sync.unavailable', {
              defaultValue: 'Wymuś synchronizację — follow-up Faza 1',
            }),
          )
        }
        className="mt-3 inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-xl bg-zinc-100 text-[12.5px] font-medium text-zinc-700 hover:bg-zinc-200"
      >
        <Zap className="size-3.5" aria-hidden />
        {t('products.detail.sidebar.force_sync.label', { defaultValue: 'Wymuś synchronizację' })}
      </button>
    </Card>
  );
}

function prettyChannelName(code: string): string {
  if (code === 'shopify') return 'Shopify';
  if (code === 'baselinker') return 'BaseLinker';
  if (code === 'allegro') return 'Allegro';
  return code.charAt(0).toUpperCase() + code.slice(1);
}
