import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';

interface ChannelDeleteConfirmDialogProps {
  channelLabel: string;
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
}

export function ChannelDeleteConfirmDialog({
  channelLabel,
  open,
  onClose,
  onConfirm,
}: ChannelDeleteConfirmDialogProps) {
  const { t } = useTranslation();

  return (
    <Dialog open={open} onOpenChange={(next) => (!next ? onClose() : undefined)}>
      <DialogContent>
        <div className="space-y-2">
          <DialogTitle>{t('channels.delete.confirm_title')}</DialogTitle>
          <DialogDescription>
            {t('channels.delete.confirm_body', { name: channelLabel })}
          </DialogDescription>
        </div>
        <div className="mt-4 flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            {t('app.cancel')}
          </Button>
          <Button variant="destructive" onClick={onConfirm}>
            {t('channels.delete.confirm_submit')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
