import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { GatedButton } from '@/components/identity';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { jsonFetch } from '@/lib/http';

export interface AssetBulkActionsBarProps {
  selectedIds: string[];
  onDeleted: () => void;
  onClearSelection: () => void;
}

export function AssetBulkActionsBar({
  selectedIds,
  onDeleted,
  onClearSelection,
}: AssetBulkActionsBarProps) {
  const { t } = useTranslation();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (selectedIds.length === 0) {
    return null;
  }

  const submit = async () => {
    setSubmitting(true);
    setError(null);
    try {
      await jsonFetch(`/api/assets/bulk-delete`, {
        method: 'POST',
        contentType: 'application/json',
        accept: 'application/json',
        body: { ids: selectedIds },
      });
      onDeleted();
      onClearSelection();
      setConfirmOpen(false);
    } catch {
      setError(t('assets.bulk.delete_error'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <section
        className="sticky top-2 z-10 flex items-center justify-between gap-3 rounded-md border bg-card px-4 py-2 shadow-sm"
        aria-label={t('assets.bulk.delete')}
      >
        <p className="text-sm font-medium">
          {selectedIds.length === 1
            ? t('assets.bulk.selected_one')
            : t('assets.bulk.selected_other', { count: selectedIds.length })}
        </p>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" onClick={onClearSelection}>
            {t('assets.upload.cancel')}
          </Button>
          <GatedButton
            permission="asset.delete"
            variant="destructive"
            size="sm"
            onClick={() => setConfirmOpen(true)}
          >
            <Trash2 className="mr-2 size-4" />
            {t('assets.bulk.delete')}
          </GatedButton>
        </div>
      </section>

      <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('assets.bulk.delete_confirm_title')}</DialogTitle>
            <DialogDescription>
              {t('assets.bulk.delete_confirm_body', { count: selectedIds.length })}
            </DialogDescription>
          </DialogHeader>
          {error ? (
            <p className="text-sm text-destructive" role="alert">
              {error}
            </p>
          ) : null}
          <DialogFooter>
            <Button variant="ghost" onClick={() => setConfirmOpen(false)} disabled={submitting}>
              {t('assets.upload.cancel')}
            </Button>
            <Button variant="destructive" onClick={submit} disabled={submitting}>
              {submitting ? t('assets.detail.saving') : t('assets.detail.delete_confirm_button')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
