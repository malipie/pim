import { useQuery } from '@tanstack/react-query';
import { Image as ImageIcon, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { unwrapAttributesIndexed } from '@/lib/attributes-indexed';
import { jsonFetch } from '@/lib/http';

import { AssetAttributePicker, type PickedAsset } from './asset-attribute-picker';

interface AssetResource {
  id: string;
  code: string;
  attributesIndexed?: Record<string, unknown>;
}

interface ResolvedAsset {
  previewUrl: string | null;
  filename: string;
}

export interface AssetFieldProps {
  /** The attribute value envelope — `{asset_id}` (or a bare id string for legacy values). */
  value: unknown;
  isEditing: boolean;
  onChange: (next: { asset_id: string } | null) => void;
  ariaLabel: string;
}

/**
 * Editor + read-only renderer for `asset` AttributeType fields (#1138).
 *
 * Before this, an asset attribute fell through to a plain text input and
 * rendered the `{asset_id}` envelope as `[object Object]`. Now it opens
 * the asset library picker, stores the chosen `{asset_id}` (CatalogObject
 * id), and shows the thumbnail + filename — resolved through
 * `GET /api/assets/{id}` so the preview stays fresh.
 */
export function AssetField({ value, isEditing, onChange, ariaLabel }: AssetFieldProps) {
  const { t } = useTranslation();
  const [pickerOpen, setPickerOpen] = useState(false);

  const assetId = extractAssetId(value);

  // Resolve the chosen asset's preview + filename. The picker also hands
  // these over on pick, but resolving by id keeps the thumbnail correct
  // on page reload (the stored envelope only carries the id).
  const { data: resolved } = useQuery<ResolvedAsset | null>({
    queryKey: ['assets', assetId, 'preview-meta'],
    queryFn: async () => {
      if (assetId === null) return null;
      const asset = await jsonFetch<AssetResource>(`/api/assets/${assetId}`, {
        accept: 'application/json',
      });
      const attrs = unwrapAttributesIndexed(asset.attributesIndexed);
      return {
        previewUrl: typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null,
        filename: typeof attrs.filename === 'string' ? attrs.filename : asset.code,
      };
    },
    enabled: assetId !== null,
    staleTime: 60_000,
  });

  const previewUrl = resolved?.previewUrl ?? null;
  const filename = resolved?.filename ?? null;

  const handlePicked = (asset: PickedAsset) => {
    onChange({ asset_id: asset.id });
  };

  if (!isEditing) {
    if (assetId === null) {
      return (
        <span className="italic text-zinc-500">
          {t('products.detail.field.empty', { defaultValue: '—' })}
        </span>
      );
    }
    return (
      <div className="flex items-center gap-3">
        <AssetThumb previewUrl={previewUrl} alt={filename ?? assetId} />
        <span className="truncate text-[13.5px] text-ink" title={filename ?? assetId}>
          {filename ?? assetId}
        </span>
      </div>
    );
  }

  return (
    <div className="flex items-center gap-3">
      <AssetThumb previewUrl={previewUrl} alt={filename ?? ''} />
      <div className="flex min-w-0 flex-col gap-1">
        {assetId !== null ? (
          <span className="truncate text-[13px] text-zinc-600" title={filename ?? assetId}>
            {filename ?? assetId}
          </span>
        ) : (
          <span className="text-[13px] italic text-zinc-500">
            {t('products.detail.asset.none', { defaultValue: 'Brak wybranego zasobu' })}
          </span>
        )}
        <div className="flex items-center gap-2">
          {(() => {
            const action =
              assetId !== null
                ? t('products.detail.asset.change', { defaultValue: 'Zmień zasób' })
                : t('products.detail.asset.choose', { defaultValue: 'Wybierz zasób' });
            return (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setPickerOpen(true)}
                aria-label={`${action} — ${ariaLabel}`}
              >
                {action}
              </Button>
            );
          })()}
          {assetId !== null ? (
            <button
              type="button"
              onClick={() => onChange(null)}
              className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700"
            >
              <X className="size-3" aria-hidden />
              {t('products.detail.asset.clear', { defaultValue: 'Usuń' })}
            </button>
          ) : null}
        </div>
      </div>

      <AssetAttributePicker open={pickerOpen} onOpenChange={setPickerOpen} onPick={handlePicked} />
    </div>
  );
}

function AssetThumb({ previewUrl, alt }: { previewUrl: string | null; alt: string }) {
  return (
    <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
      {previewUrl !== null ? (
        <img src={previewUrl} alt={alt} loading="lazy" className="h-full w-full object-cover" />
      ) : (
        <ImageIcon className="size-6 text-zinc-300" aria-hidden />
      )}
    </div>
  );
}

/**
 * Pulls the asset id out of the stored value. Canonical shape is the
 * `{asset_id}` envelope; a bare id string is tolerated for older data.
 */
function extractAssetId(value: unknown): string | null {
  if (typeof value === 'string') {
    return value === '' ? null : value;
  }
  if (value !== null && typeof value === 'object' && 'asset_id' in value) {
    const raw = (value as { asset_id: unknown }).asset_id;
    return typeof raw === 'string' && raw !== '' ? raw : null;
  }
  return null;
}
