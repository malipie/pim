import { Copy, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

/**
 * VIEW-16 (#548) — bulk duplicate confirmation.
 *
 * Quick confirm modal — duplicate is non-destructive (creates new
 * rows under `{code}-COPY-N`) so no hard-confirm typing. Future
 * `with_assets` + `with_relations` flags from DuplicateProductController
 * stay reserved.
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

interface DuplicateModalProps {
  selectedIds: string[];
  onClose: () => void;
  onApplied: (result: BulkActionResult) => void;
}

export function BulkDuplicateModal({ selectedIds, onClose, onApplied }: DuplicateModalProps) {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);

  const apply = async (): Promise<void> => {
    setIsLoading(true);
    try {
      const response = await jsonFetch<BulkActionResult>('/api/products/bulk-actions/duplicate', {
        method: 'POST',
        body: {
          target_ids: selectedIds,
          payload: {},
        },
      });
      toast.success(
        t('products.bulk_duplicate.applied', {
          count: response.success_count,
          defaultValue: `Zduplikowano ${response.success_count} produktów`,
        }),
      );
      onApplied(response);
      onClose();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'duplicate failed');
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
        className="relative bg-white rounded-3xl shadow-2xl w-[480px] max-w-[94vw] overflow-hidden flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bulk-duplicate-title"
      >
        <div className="px-6 h-14 flex items-center gap-3 border-b border-zinc-100">
          <span className="h-8 w-8 rounded-xl bg-orange-50 text-orange-600 grid place-items-center">
            <Copy className="size-4" />
          </span>
          <div className="leading-tight">
            <div id="bulk-duplicate-title" className="text-[14.5px] font-semibold tracking-tight">
              {t('products.bulk_duplicate.title', { defaultValue: 'Akcja zbiorcza · Duplikuj' })}
            </div>
            <div className="text-[11.5px] text-zinc-500 tabular-nums">
              {selectedIds.length}{' '}
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

        <div className="p-6 text-[12.5px] text-zinc-700">
          {t('products.bulk_duplicate.body', {
            count: selectedIds.length,
            defaultValue: `Każdy z ${selectedIds.length} produktów zostanie sklonowany jako {code}-COPY-N. ObjectValues idą razem; assety + relacje pozostają out-of-scope MVP.`,
          })}
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
            <Button onClick={() => void apply()} disabled={isLoading}>
              {t('products.bulk_duplicate.apply', { defaultValue: 'Duplikuj' })}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
