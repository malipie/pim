import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

export interface AssetDuplicateDialogProps {
  open: boolean;
  existingAssetId: string;
  existingCode: string;
  onOpenChange: (open: boolean) => void;
}

export function AssetDuplicateDialog({
  open,
  existingAssetId,
  existingCode,
  onOpenChange,
}: AssetDuplicateDialogProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('assets.upload.duplicate_dialog_title')}</DialogTitle>
          <DialogDescription>
            {t('assets.upload.duplicate_dialog_body', { code: existingCode })}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="ghost" onClick={() => onOpenChange(false)}>
            {t('assets.upload.cancel')}
          </Button>
          <Button
            onClick={() => {
              onOpenChange(false);
              navigate(`/assets/${existingAssetId}`);
            }}
          >
            {t('assets.upload.use_existing')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
