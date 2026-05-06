import { ClipboardList, Package, Plus, Upload } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';

/**
 * UI-02.19 (#309) — Empty state for the products list when the tenant
 * has zero products. Per `Project Plan/UI/epik-02-produkty.md` §4.8.
 *
 * Three CTAs: Add first / Clone from existing (placeholder until the
 * source-picker arrives) / Import (link to the future Publikacje epic
 * — disabled with a tooltip in this slice). Cmd+K hint hardcoded
 * because the agent flow is Beta-Demo MVP only.
 */
export function EmptyStateProducts({ onCloneFrom }: { onCloneFrom?: () => void }) {
  const { t } = useTranslation();

  return (
    <div className="mx-auto flex max-w-md flex-col items-center gap-4 rounded-lg border bg-card px-6 py-10 text-center">
      <Package className="size-12 text-muted-foreground" />
      <div className="space-y-1">
        <h2 className="text-xl font-semibold">
          {t('products.empty.title', { defaultValue: 'No products in this catalog yet' })}
        </h2>
        <p className="text-sm text-muted-foreground">
          {t('products.empty.subtitle', { defaultValue: 'Pick how you want to start:' })}
        </p>
      </div>

      <div className="flex w-full flex-col gap-2">
        <Button asChild>
          <Link to="/products/new">
            <Plus className="size-4" />
            {t('products.empty.add_first', { defaultValue: 'Add first product' })}
          </Link>
        </Button>
        {onCloneFrom !== undefined ? (
          <Button variant="outline" onClick={onCloneFrom}>
            <ClipboardList className="size-4" />
            {t('products.empty.clone_from', { defaultValue: 'Clone from existing' })}
          </Button>
        ) : null}
        <Button asChild variant="outline">
          <Link to="/publications/imports/new">
            <Upload className="size-4" />
            {t('products.empty.import_csv', { defaultValue: 'Import from Excel/CSV' })}
          </Link>
        </Button>
      </div>

      <p className="rounded bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
        {t('products.empty.cmdk_hint', {
          defaultValue:
            'Tip: open Cmd+K and ask the agent — "stwórz produkt sku=ABC123 family=Czujniki" (Beta-Demo).',
        })}
      </p>
    </div>
  );
}
