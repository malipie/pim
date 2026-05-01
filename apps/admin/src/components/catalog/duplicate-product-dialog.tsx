import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { jsonFetch } from '@/lib/http';

interface DuplicateResponse {
  id: string;
  code: string;
  source_id: string;
}

/**
 * UI-02.13 (#303) sub-component — Duplicate product dialog over the
 * UI-02.4 endpoint `POST /api/products/{id}/duplicate`.
 *
 * Slice ships SKU input + the three reserved option flags; backend
 * currently honours `with_categories` (default true) only — `assets`
 * and `relations` are reserved for the DAM / Faza 1 follow-ups but
 * appear in the dialog so the contract surface is stable.
 */
export function DuplicateProductDialog({
  productId,
  onClose,
  onDuplicated,
}: {
  productId: string;
  onClose: () => void;
  onDuplicated: () => void;
}) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [sku, setSku] = useState('');
  const [withAssets, setWithAssets] = useState(false);
  const [withRelations, setWithRelations] = useState(false);
  const [withCategories, setWithCategories] = useState(true);
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setIsPending(true);
    setError(null);
    try {
      const body: Record<string, unknown> = {
        with_assets: withAssets,
        with_relations: withRelations,
        with_categories: withCategories,
      };
      if (sku.trim() !== '') body.sku = sku.trim();
      const response = await jsonFetch<DuplicateResponse>(`/api/products/${productId}/duplicate`, {
        method: 'POST',
        body,
      });
      onDuplicated();
      onClose();
      navigate(`/products/${response.id}`);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'unknown');
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Sheet
      open
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <SheetContent side="right" className="w-[420px] p-6">
        <SheetTitle>
          {t('products.duplicate.title', { defaultValue: 'Duplicate product' })}
        </SheetTitle>
        <form onSubmit={(e) => void handleSubmit(e)} className="mt-4 space-y-4">
          <div className="space-y-2">
            <label htmlFor="duplicate-sku" className="text-sm font-medium">
              {t('products.duplicate.sku_label', {
                defaultValue: 'New SKU (optional, defaults to {src}-COPY-N)',
              })}
            </label>
            <Input
              id="duplicate-sku"
              value={sku}
              onChange={(e) => setSku(e.target.value)}
              placeholder="TST-001-COPY-1"
            />
          </div>

          <div className="space-y-2">
            <label className="inline-flex cursor-pointer items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={withCategories}
                onChange={(e) => setWithCategories(e.target.checked)}
              />
              {t('products.duplicate.with_categories', { defaultValue: 'Clone categories' })}
            </label>
            <label className="inline-flex cursor-pointer items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={withAssets}
                onChange={(e) => setWithAssets(e.target.checked)}
              />
              {t('products.duplicate.with_assets', {
                defaultValue: 'Clone assets (DAM follow-up)',
              })}
            </label>
            <label className="inline-flex cursor-pointer items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={withRelations}
                onChange={(e) => setWithRelations(e.target.checked)}
              />
              {t('products.duplicate.with_relations', {
                defaultValue: 'Clone related products (Faza 1)',
              })}
            </label>
          </div>

          {error !== null ? <p className="text-sm text-rose-600">{error}</p> : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" type="button" onClick={onClose} disabled={isPending}>
              {t('app.cancel', { defaultValue: 'Cancel' })}
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending
                ? t('products.duplicate.submitting', { defaultValue: 'Duplicating…' })
                : t('products.duplicate.submit', { defaultValue: 'Duplicate' })}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
