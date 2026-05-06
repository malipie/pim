import { useTranslation } from 'react-i18next';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { AssetUploadDropzone } from '@/features/asset/assets/AssetUploadDropzone';

export interface ProductAssetUploadDialogProps {
  productId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onUploaded: () => void;
}

/**
 * Inline upload dialog inside the product multimedia tab (#440).
 *
 * Reuses {@link AssetUploadDropzone} but pins `folderCode` to
 * `product-<productId>`. The backend recognises the prefix and
 * auto-links every successful upload to the product, so no extra
 * round-trip is needed after the dropzone reports completion.
 */
export function ProductAssetUploadDialog({
  productId,
  open,
  onOpenChange,
  onUploaded,
}: ProductAssetUploadDialogProps) {
  const { t } = useTranslation();
  const folderCode = `product-${productId}`;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('products.multimedia.upload_dialog_title')}</DialogTitle>
          <DialogDescription>{t('products.multimedia.upload_dialog_body')}</DialogDescription>
        </DialogHeader>
        <AssetUploadDropzone
          folderCode={folderCode}
          onCompleted={(results) => {
            if (results.length > 0) {
              onUploaded();
            }
          }}
        />
      </DialogContent>
    </Dialog>
  );
}
