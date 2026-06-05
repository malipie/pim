import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Card } from '@/components/ui/card';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface VariantRow {
  id: string;
  code: string;
  axis?: string;
  syncDot?: 'ok' | 'yellow' | 'red' | 'gray';
}

interface VariantsResponse {
  member?: VariantRow[];
  'hydra:member'?: VariantRow[];
}

export interface VariantsListCardProps {
  masterProductId: string;
  onSelectVariant?: (variantId: string) => void;
  onCreateVariant?: () => void;
  /**
   * #1274 — poly-kind base path. `/api/products` keeps the legacy product
   * sidebar unchanged; the universal object card passes `/api/objects` so
   * custom ObjectTypes list their variants the same way.
   */
  basePath?: string;
}

/**
 * VIEW-07 (#420) — sidebar "Warianty" card mirrored from
 * `detail-view.jsx` lines 303–321. Reads the master's children via the
 * standard collection filter `parent_id={master_object_id}`. Status
 * dots are derived from `syncStatusAggregate` once the variants payload
 * exposes it (today the field arrives null for fresh variants).
 */
export function VariantsListCard({
  masterProductId,
  onSelectVariant,
  onCreateVariant,
  basePath = '/api/products',
}: VariantsListCardProps) {
  const { t } = useTranslation();
  const [variants, setVariants] = useState<VariantRow[]>([]);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let cancelled = false;
    if (masterProductId === '') return;
    jsonFetch<VariantsResponse>(`${basePath}?parent_id=${masterProductId}`)
      .then((body) => {
        if (cancelled) return;
        const list = body.member ?? body['hydra:member'] ?? [];
        setVariants(Array.isArray(list) ? list : []);
        setLoaded(true);
      })
      .catch(() => {
        if (!cancelled) setLoaded(true);
      });
    return () => {
      cancelled = true;
    };
  }, [masterProductId, basePath]);

  return (
    <Card className="rounded-2xl border-line bg-surface p-5 soft-shadow">
      <div className="mb-2.5 flex items-center justify-between">
        <div className="text-[12px] font-medium text-zinc-500">
          {t('products.detail.sidebar.variants.title', { defaultValue: 'Warianty' })}
        </div>
        <span className="num text-[11px] text-zinc-500">{variants.length}</span>
      </div>
      {loaded && variants.length === 0 ? (
        <p className="text-[12px] text-muted-foreground">
          {t('products.detail.sidebar.variants.empty', {
            defaultValue: 'Brak wariantów — dodaj nowy lub wygeneruj z osi.',
          })}
        </p>
      ) : (
        <ul className="space-y-1">
          {variants.map((variant) => {
            const dot =
              variant.syncDot === 'ok'
                ? 'bg-emerald-500'
                : variant.syncDot === 'yellow'
                  ? 'bg-amber-500'
                  : variant.syncDot === 'red'
                    ? 'bg-rose-500'
                    : 'bg-zinc-300';
            return (
              <li key={variant.id}>
                <button
                  type="button"
                  onClick={() => onSelectVariant?.(variant.id)}
                  className="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left hover:bg-zinc-50"
                >
                  <span className="font-mono text-[11px] text-zinc-500">{variant.code}</span>
                  <span className="flex-1 truncate text-[12px]">{variant.axis ?? '—'}</span>
                  <span className={cn('size-1.5 rounded-full', dot)} aria-hidden />
                </button>
              </li>
            );
          })}
        </ul>
      )}
      <button
        type="button"
        onClick={onCreateVariant}
        className="mt-2.5 inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-zinc-300 text-[12px] text-zinc-500 hover:border-zinc-500 hover:text-zinc-900"
      >
        <Plus className="size-3.5" aria-hidden />
        {t('products.detail.sidebar.variants.new', { defaultValue: 'Nowy wariant' })}
      </button>
    </Card>
  );
}
