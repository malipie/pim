import { AlertTriangle, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

/**
 * VIEW-16 (#548) — destructive bulk delete with hard confirm typing.
 *
 * Apply button stays disabled until the operator types the exact target
 * count into the input. The endpoint validates `confirmation_count`
 * server-side too — typing the wrong number returns 400.
 */

interface BulkActionResult {
  session_id: string;
  action: string;
  target_count: number;
  success_count: number;
  skipped_count: number;
  error_count: number;
  rollback_available_until?: string;
  completed_at?: string;
}

interface HardConfirmModalProps {
  selectedIds: string[];
  onClose: () => void;
  onApplied: (result: BulkActionResult) => void;
}

export function BulkDeleteConfirmModal({ selectedIds, onClose, onApplied }: HardConfirmModalProps) {
  const { t } = useTranslation();
  const [confirmation, setConfirmation] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const expected = selectedIds.length;
  const canApply = confirmation.trim() === String(expected) && !isLoading;

  const apply = async (): Promise<void> => {
    setIsLoading(true);
    try {
      const response = await jsonFetch<BulkActionResult>('/api/products/bulk-actions/delete', {
        method: 'POST',
        body: {
          target_ids: selectedIds,
          payload: {},
          confirmation_count: expected,
        },
      });
      toast.success(
        t('products.bulk_delete.applied', {
          count: response.success_count,
          defaultValue: `Usunięto ${response.success_count} produktów`,
        }),
      );
      onApplied(response);
      onClose();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'delete failed');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-zinc-900/30 backdrop-blur-sm grid place-items-center">
      <button
        type="button"
        aria-label="Close backdrop"
        onClick={onClose}
        className="absolute inset-0 cursor-default"
      />
      <div
        className="relative bg-white rounded-3xl shadow-2xl w-[520px] max-w-[94vw] overflow-hidden flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bulk-delete-title"
      >
        <div className="px-6 h-14 flex items-center gap-3 border-b border-zinc-100">
          <span className="h-8 w-8 rounded-xl bg-rose-50 text-rose-600 grid place-items-center">
            <AlertTriangle className="size-4" />
          </span>
          <div className="leading-tight">
            <div id="bulk-delete-title" className="text-[14.5px] font-semibold tracking-tight">
              {t('products.bulk_delete.title', {
                defaultValue: 'Akcja zbiorcza · Usuń',
              })}
            </div>
            <div className="text-[11.5px] text-zinc-500 tabular-nums">
              {expected}{' '}
              {t('products.bulk_wizard.target_count_label', {
                defaultValue: 'produktów wybranych',
              })}
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="ml-auto h-8 w-8 grid place-items-center rounded-lg text-zinc-500 hover:bg-zinc-100"
          >
            <X className="size-4" />
          </button>
        </div>

        <div className="p-6 space-y-4">
          <div className="rounded-2xl border border-rose-200 bg-rose-50/60 px-4 py-3 text-[12.5px] text-rose-800">
            {t('products.bulk_delete.warning', {
              count: expected,
              defaultValue: `Operacja usunie ${expected} produktów. 24h rollback dostępny przez sticky toast.`,
            })}
          </div>
          <div>
            <label
              htmlFor="bulk-delete-confirmation"
              className="text-[11px] uppercase tracking-wider font-semibold text-zinc-500"
            >
              {t('products.bulk_delete.confirmation_label', {
                count: expected,
                defaultValue: `Wpisz dokładnie liczbę produktów do usunięcia (${expected}) aby odblokować przycisk`,
              })}
            </label>
            <Input
              id="bulk-delete-confirmation"
              value={confirmation}
              onChange={(e) => setConfirmation(e.target.value)}
              placeholder={String(expected)}
              className="mt-2 font-mono"
            />
          </div>
        </div>

        <div className="px-6 h-14 flex items-center gap-3 border-t border-zinc-100 bg-zinc-50/50">
          <span className="text-[11.5px] text-zinc-500">
            {t('products.bulk_wizard.rollback_hint', {
              defaultValue: 'Każda akcja zbiorcza ma 24h soft-rollback.',
            })}
          </span>
          <div className="ml-auto flex items-center gap-2">
            <Button variant="ghost" onClick={onClose} disabled={isLoading}>
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button onClick={() => void apply()} disabled={!canApply}>
              {t('products.bulk_delete.apply', { defaultValue: 'Usuń' })}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
