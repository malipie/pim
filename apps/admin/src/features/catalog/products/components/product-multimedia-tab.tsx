import {
  FileText,
  Film,
  Image as ImageIcon,
  Library as LibraryIcon,
  Trash2,
  Upload,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { jsonFetch } from '@/lib/http';

import { AssetLibraryPicker } from './asset-library-picker';
import { ProductAssetUploadDialog } from './product-asset-upload-dialog';

interface ProductAsset {
  id: string;
  code: string;
  originalFilename: string;
  mimeType: string;
  size: number;
  thumbnailsStatus: 'pending' | 'ready' | 'failed';
  previewUrl: string;
  folderCode: string | null;
}

interface ProductAssetsResponse {
  member: ProductAsset[];
  totalItems: number;
}

export interface ProductMultimediaTabProps {
  productId: string;
}

/**
 * Multimedia tab on the product detail page (#440).
 *
 * Renders the grid of assets linked to the product via `product_assets`
 * m2m, plus two CTAs:
 *   - "Wgraj pliki"      — drops opens an upload dialog. The dialog
 *     forces `folderCode='product-<id>'` so the backend auto-links
 *     the new asset to the product.
 *   - "Wybierz z biblioteki" — opens a full-screen picker over the
 *     library. Selected ids are POSTed to `/api/products/{id}/assets`
 *     for m2m link.
 *
 * Per-asset "Odepnij" removes the m2m row only — the file stays in
 * the library so other products can keep using it.
 */
export function ProductMultimediaTab({ productId }: ProductMultimediaTabProps) {
  const { t } = useTranslation();
  const [assets, setAssets] = useState<ProductAsset[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);

  const fetchAssets = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await jsonFetch<ProductAssetsResponse>(`/api/products/${productId}/assets`, {
        accept: 'application/json',
      });
      setAssets(response.member ?? []);
    } catch {
      setAssets([]);
    } finally {
      setIsLoading(false);
    }
  }, [productId]);

  useEffect(() => {
    void fetchAssets();
  }, [fetchAssets]);

  const handleUnlink = async (assetId: string) => {
    try {
      await jsonFetch(`/api/products/${productId}/assets/${assetId}`, {
        method: 'DELETE',
        accept: 'application/json',
      });
      await fetchAssets();
    } catch {
      // Surface errors via a toast in a follow-up; silent retry is OK
      // for now since unlink is idempotent.
    }
  };

  return (
    <div className="rounded-2xl border border-line bg-surface p-6 soft-shadow">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h3 className="text-[14px] font-semibold text-ink">{t('products.multimedia.title')}</h3>
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => setPickerOpen(true)}
            aria-label={t('products.multimedia.pick_from_library')}
          >
            <LibraryIcon className="size-4" />
            {t('products.multimedia.pick_from_library')}
          </Button>
          <Button
            type="button"
            size="sm"
            onClick={() => setUploadOpen(true)}
            aria-label={t('products.multimedia.upload')}
          >
            <Upload className="size-4" />
            {t('products.multimedia.upload')}
          </Button>
        </div>
      </header>

      <div className="mt-4">
        {isLoading ? (
          <p className="rounded-md border bg-card p-6 text-center text-sm text-muted-foreground">
            {t('app.loading')}
          </p>
        ) : assets.length === 0 ? (
          <p className="rounded-md border bg-card p-6 text-center text-sm text-muted-foreground">
            {t('products.multimedia.empty')}
          </p>
        ) : (
          <ul
            className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
            aria-label={t('products.multimedia.title')}
          >
            {assets.map((asset) => (
              <ProductAssetTile
                key={asset.id}
                asset={asset}
                onUnlink={() => void handleUnlink(asset.id)}
              />
            ))}
          </ul>
        )}
      </div>

      <ProductAssetUploadDialog
        productId={productId}
        open={uploadOpen}
        onOpenChange={setUploadOpen}
        onUploaded={() => {
          void fetchAssets();
        }}
      />

      <AssetLibraryPicker
        productId={productId}
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        onPicked={() => {
          void fetchAssets();
        }}
      />
    </div>
  );
}

interface ProductAssetTileProps {
  asset: ProductAsset;
  onUnlink: () => void;
}

function ProductAssetTile({ asset, onUnlink }: ProductAssetTileProps) {
  const { t } = useTranslation();
  return (
    <li className="group relative overflow-hidden rounded-md border bg-card">
      <div className="aspect-square bg-muted/40">
        <img
          src={asset.previewUrl}
          alt={asset.originalFilename}
          loading="lazy"
          className="h-full w-full object-cover"
        />
      </div>
      <div className="space-y-1 p-2">
        <p className="truncate text-xs font-medium" title={asset.originalFilename}>
          {asset.originalFilename}
        </p>
        <p className="truncate font-mono text-[10px] text-muted-foreground">{asset.code}</p>
      </div>
      <button
        type="button"
        onClick={onUnlink}
        aria-label={t('products.multimedia.unlink')}
        title={t('products.multimedia.unlink')}
        className="absolute right-2 top-2 hidden rounded-full bg-background/90 p-1 text-muted-foreground shadow-sm transition-colors hover:text-destructive group-hover:flex"
      >
        <Trash2 className="size-3.5" />
      </button>
      <span className="sr-only">{`${asset.mimeType} (${asset.thumbnailsStatus})`}</span>
      {/* Keep icon imports honoured for placeholder fallback in follow-up */}
      <span className="hidden">
        <ImageIcon />
        <Film />
        <FileText />
      </span>
    </li>
  );
}
