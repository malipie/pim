import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { jsonFetch } from '@/lib/http';

interface BulkEditModalProps {
  productIds: string[];
  onClose: () => void;
  onApplied: (job: BulkEditJobResponse) => void;
}

interface BulkEditJobResponse {
  id: string;
  status: string;
  total: number;
  processed: number;
  errors_count: number;
  first_errors: Array<{ objectId: string; message: string }>;
}

/**
 * UI-02.11 follow-up — Bulk edit attribute modal.
 *
 * Frontend-only attribute picker (no schema integration yet — text
 * input). Posts the `set_attribute_value` operation against the
 * UI-02.3 endpoint. Job result handed back to the parent for the
 * sticky toolbar's status panel.
 */
export function BulkEditModal({ productIds, onClose, onApplied }: BulkEditModalProps) {
  const { t } = useTranslation();
  const [attributeCode, setAttributeCode] = useState('');
  const [value, setValue] = useState('');
  const [isPending, setIsPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    if (attributeCode.trim() === '') return;
    setIsPending(true);
    setError(null);
    try {
      const job = await jsonFetch<BulkEditJobResponse>('/api/products/bulk-edit', {
        method: 'POST',
        body: {
          operation: 'set_attribute_value',
          product_ids: productIds,
          payload: { attribute_code: attributeCode.trim(), value: value },
        },
      });
      onApplied(job);
      onClose();
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
          {t('products.bulk.edit_modal_title', {
            defaultValue: 'Bulk edit attribute on {{count}} products',
            count: productIds.length,
          })}
        </SheetTitle>
        <form onSubmit={(e) => void handleSubmit(e)} className="mt-4 space-y-4">
          <div className="space-y-2">
            <label htmlFor="bulk-edit-attr" className="text-sm font-medium">
              {t('products.bulk.attribute_code', { defaultValue: 'Attribute code' })}
              <span className="ml-1 text-rose-600">*</span>
            </label>
            <Input
              id="bulk-edit-attr"
              value={attributeCode}
              onChange={(e) => setAttributeCode(e.target.value)}
              placeholder="brand"
            />
          </div>
          <div className="space-y-2">
            <label htmlFor="bulk-edit-value" className="text-sm font-medium">
              {t('products.bulk.new_value', { defaultValue: 'New value' })}
            </label>
            <Input id="bulk-edit-value" value={value} onChange={(e) => setValue(e.target.value)} />
          </div>
          {error !== null ? <p className="text-sm text-rose-600">{error}</p> : null}
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost" type="button" onClick={onClose} disabled={isPending}>
              {t('app.cancel', { defaultValue: 'Cancel' })}
            </Button>
            <Button type="submit" disabled={isPending || attributeCode.trim() === ''}>
              {isPending
                ? t('products.bulk.applying', { defaultValue: 'Applying…' })
                : t('products.bulk.apply', { defaultValue: 'Apply' })}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
