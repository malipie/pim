import { FileText, Film, Image as ImageIcon, type LucideIcon } from 'lucide-react';

import { unwrapAttributesIndexed } from '@/lib/attributes-indexed';
import { cn } from '@/lib/utils';

export interface AssetEntry {
  id: string;
  code: string;
  enabled?: boolean;
  attributesIndexed?: Record<string, unknown>;
}

export type AssetKind = 'image' | 'pdf' | 'video' | 'other';

/** Normalised view-model over `attributesIndexed` used by cards, rows and the drawer. */
export interface AssetMeta {
  id: string;
  code: string;
  filename: string;
  ext: string;
  kind: AssetKind;
  typeLabel: string;
  previewUrl: string | null;
  sizeLabel: string | null;
  dims: string | null;
  folder: string | null;
  thumbnailsPending: boolean;
}

/** Design `Multimedia.html` TYPE — tinted placeholder per file kind. */
const KIND_META: Record<
  AssetKind,
  { tile: string; glyph: string; icon: LucideIcon; label: string }
> = {
  image: { tile: '#dbe6f6', glyph: '#5b78a8', icon: ImageIcon, label: 'Zdjęcie' },
  pdf: { tile: '#f6dcd4', glyph: '#b9491a', icon: FileText, label: 'PDF' },
  video: { tile: '#e3def4', glyph: '#6b5bb0', icon: Film, label: 'Wideo' },
  other: { tile: '#e9edf4', glyph: '#5b6b87', icon: FileText, label: 'Plik' },
};

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function toAssetMeta(asset: AssetEntry): AssetMeta {
  const attrs = unwrapAttributesIndexed(asset.attributesIndexed);
  const mime = typeof attrs.mime === 'string' ? attrs.mime : null;
  const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;
  const size = typeof attrs.size === 'number' ? attrs.size : null;
  const width = typeof attrs.width === 'number' ? attrs.width : null;
  const height = typeof attrs.height === 'number' ? attrs.height : null;
  const folder = typeof attrs.folder === 'string' && attrs.folder !== '' ? attrs.folder : null;
  const thumbnailsStatus =
    typeof attrs.thumbnailsStatus === 'string' ? attrs.thumbnailsStatus : 'ready';

  let kind: AssetKind = 'other';
  if (mime?.startsWith('image/')) kind = 'image';
  else if (mime === 'application/pdf') kind = 'pdf';
  else if (mime?.startsWith('video/')) kind = 'video';

  const extFromName = filename.includes('.') ? (filename.split('.').pop() ?? '') : '';
  const ext = extFromName !== '' ? extFromName : (mime?.split('/').pop() ?? 'plik');

  return {
    id: asset.id,
    code: asset.code,
    filename,
    ext,
    kind,
    typeLabel: KIND_META[kind].label,
    previewUrl: typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null,
    sizeLabel: size !== null ? formatBytes(size) : null,
    dims: width !== null && height !== null ? `${width}×${height}` : null,
    folder,
    thumbnailsPending: thumbnailsStatus === 'pending',
  };
}

interface AssetThumbProps {
  asset: AssetMeta;
  className?: string;
  iconSize?: number;
}

/** Preview image when available, otherwise the kind-tinted placeholder tile. */
export function AssetThumb({ asset, className, iconSize = 22 }: AssetThumbProps) {
  const meta = KIND_META[asset.kind];
  if (asset.previewUrl !== null && asset.kind === 'image') {
    return (
      <img
        src={asset.previewUrl}
        alt={asset.filename}
        loading="lazy"
        className={cn('h-full w-full object-cover', className)}
      />
    );
  }
  const Icon = meta.icon;
  return (
    <div
      className={cn('grid h-full w-full place-items-center', className)}
      style={{ background: meta.tile }}
    >
      <Icon style={{ color: meta.glyph, width: iconSize, height: iconSize }} />
    </div>
  );
}
