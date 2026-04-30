import { CheckCircle2, MinusCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { jsonFetch } from '@/lib/http';

interface ProductBulkBarProps {
  ids: string[];
  onCleared: () => void;
}

/**
 * Multi-select action bar for the products list (#55 / 0.6.2).
 *
 * Operates on the selected ids by issuing the matching API call per row
 * — the API exposes per-row PATCH/DELETE only, so we sequence the
 * requests with a small concurrency cap. A future backend endpoint
 * `/api/products/bulk` (epic 0.7 schema-add) would let us flip this to
 * a single round trip, but the per-row fan-out is fine at MVP scale.
 *
 * The list page refetches after the bar reports completion so the row
 * states reflect the new status without a full page reload.
 */
export function ProductBulkBar({ ids, onCleared }: ProductBulkBarProps) {
  const { t } = useTranslation();
  const [isPending, setIsPending] = useState(false);

  const run = async (action: 'enable' | 'disable' | 'delete'): Promise<void> => {
    if (ids.length === 0 || isPending) return;
    setIsPending(true);
    try {
      await applyBulk(ids, action);
      onCleared();
    } finally {
      setIsPending(false);
    }
  };

  return (
    <div
      className="flex flex-wrap items-center gap-2 rounded-md border bg-card px-3 py-2"
      data-testid="product-bulk-bar"
    >
      <span className="text-sm font-medium">
        {t('products.bulk.selected', {
          count: ids.length,
          defaultValue: '{{count}} selected',
        })}
      </span>
      <div className="ml-auto flex gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => run('enable')}
          disabled={isPending}
        >
          <CheckCircle2 className="size-4" />
          {t('products.bulk.enable', { defaultValue: 'Enable' })}
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => run('disable')}
          disabled={isPending}
        >
          <MinusCircle className="size-4" />
          {t('products.bulk.disable', { defaultValue: 'Disable' })}
        </Button>
        <Button
          type="button"
          variant="destructive"
          size="sm"
          onClick={() => run('delete')}
          disabled={isPending}
        >
          <Trash2 className="size-4" />
          {t('products.bulk.delete', { defaultValue: 'Delete' })}
        </Button>
      </div>
    </div>
  );
}

async function applyBulk(ids: string[], action: 'enable' | 'disable' | 'delete'): Promise<void> {
  // Sequential — at MVP scale (<200 selected rows) this avoids hammering
  // the backend in parallel. If we ever need higher throughput we'd
  // switch to a small p-limit pool keyed off the row count.
  for (const id of ids) {
    if (action === 'delete') {
      await jsonFetch(`/api/products/${id}`, { method: 'DELETE' });
      continue;
    }
    await jsonFetch(`/api/products/${id}`, {
      method: 'PATCH',
      body: { enabled: action === 'enable' },
      contentType: 'application/merge-patch+json',
    });
  }
}
