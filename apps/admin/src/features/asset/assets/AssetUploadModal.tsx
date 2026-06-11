import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { DuplicateAssetError, UploadAssetResult } from '@/lib/asset-upload';

import { AssetUploadDropzone } from './AssetUploadDropzone';

interface AssetUploadModalProps {
  open: boolean;
  onClose: () => void;
  folderCode?: string;
  onCompleted: (results: UploadAssetResult[]) => void;
  onDuplicate: (duplicate: DuplicateAssetError) => void;
}

/**
 * NUI-08 (#1427) — upload moves from an always-visible inline dropzone to a
 * modal opened by the topbar "Prześlij pliki" CTA (design UploadModal).
 * Formats/limits come from the real dropzone validation, not the mockup copy.
 */
export function AssetUploadModal({
  open,
  onClose,
  folderCode,
  onCompleted,
  onDuplicate,
}: AssetUploadModalProps) {
  const { t } = useTranslation();

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle>{t('assets.upload_cta', { defaultValue: 'Prześlij pliki' })}</DialogTitle>
        </DialogHeader>
        <AssetUploadDropzone
          onCompleted={onCompleted}
          onDuplicate={onDuplicate}
          folderCode={folderCode}
        />
        <div className="flex items-center gap-2 text-[12px] text-zinc-500">
          <Info className="size-3.5 shrink-0 text-zinc-400" aria-hidden />
          {t('assets.upload_thumbnails_note', {
            defaultValue: 'Miniatury wygenerują się automatycznie po przesłaniu.',
          })}
        </div>
      </DialogContent>
    </Dialog>
  );
}
