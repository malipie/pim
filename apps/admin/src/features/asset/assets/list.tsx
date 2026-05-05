import { useList } from '@refinedev/core';
import { FileText, Film, Image as ImageIcon, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import type { DuplicateAssetError, UploadAssetResult } from '@/lib/asset-upload';
import { useDebouncedCallback } from '@/lib/use-debounced-callback';

import { AssetBulkActionsBar } from './AssetBulkActionsBar';
import { AssetDuplicateDialog } from './AssetDuplicateDialog';
import { AssetFilterBar, type AssetFilters } from './AssetFilterBar';
import { AssetUploadDropzone } from './AssetUploadDropzone';

interface AssetEntry {
  id: string;
  code: string;
  enabled?: boolean;
  attributesIndexed?: Record<string, unknown>;
}

interface DuplicateState {
  open: boolean;
  existingAssetId: string;
  existingCode: string;
}

const POLL_INTERVAL_MS = 3500;

export function AssetsListPage() {
  const { t, i18n } = useTranslation();
  const [filters, setFilters] = useState<AssetFilters>({ search: '', mimeGroup: 'all' });
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [duplicate, setDuplicate] = useState<DuplicateState | null>(null);
  const [refreshTick, setRefreshTick] = useState(0);

  const setSearchSoon = useDebouncedCallback((value: string) => setDebouncedSearch(value), 300);

  useEffect(() => {
    setSearchSoon(filters.search);
  }, [filters.search, setSearchSoon]);

  const filterParams = useMemo(() => {
    const params: Array<{ field: string; operator: 'eq'; value: string }> = [];
    if (debouncedSearch) params.push({ field: 'search', operator: 'eq', value: debouncedSearch });
    if (filters.mimeGroup !== 'all')
      params.push({ field: 'mimeGroup', operator: 'eq', value: filters.mimeGroup });
    return params;
  }, [debouncedSearch, filters.mimeGroup]);

  const { result, query } = useList<AssetEntry>({
    resource: 'assets',
    pagination: { mode: 'off' },
    filters: filterParams,
    queryOptions: {
      refetchInterval: (q) => {
        const data = q.state.data?.data as AssetEntry[] | undefined;
        return hasPendingThumbnails(data) ? POLL_INTERVAL_MS : false;
      },
    },
    meta: { refreshTick },
  });

  const assets: AssetEntry[] = result?.data ?? [];
  const isLoading = query.isLoading;

  const onCompleted = (results: UploadAssetResult[]) => {
    if (results.length > 0) {
      setRefreshTick((tick) => tick + 1);
      void query.refetch();
    }
  };

  const onDuplicate = (error: DuplicateAssetError) => {
    setDuplicate({
      open: true,
      existingAssetId: error.existingAssetId,
      existingCode: error.existingCode,
    });
  };

  const toggleSelect = (id: string, additive: boolean) => {
    setSelected((prev) => {
      const next = new Set(additive ? prev : []);
      if (prev.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const clearSelection = () => setSelected(new Set());

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('assets.list_title')}</h1>
        <p className="text-sm text-muted-foreground">{t('assets.list_subtitle')}</p>
      </div>

      <AssetUploadDropzone onCompleted={onCompleted} onDuplicate={onDuplicate} />

      <AssetFilterBar filters={filters} onChange={setFilters} />

      <AssetBulkActionsBar
        selectedIds={[...selected]}
        onDeleted={() => {
          setRefreshTick((tick) => tick + 1);
          void query.refetch();
        }}
        onClearSelection={clearSelection}
      />

      {isLoading ? (
        <p className="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground">
          {t('app.loading')}
        </p>
      ) : assets.length === 0 ? (
        <p className="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground">
          {t('assets.empty')}
        </p>
      ) : (
        <ul
          className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
          aria-label={t('assets.list_title')}
        >
          {assets.map((asset: AssetEntry) => (
            <AssetTile
              key={asset.id}
              asset={asset}
              locale={i18n.language}
              isSelected={selected.has(asset.id)}
              onToggle={(additive) => toggleSelect(asset.id, additive)}
            />
          ))}
        </ul>
      )}

      {duplicate ? (
        <AssetDuplicateDialog
          open={duplicate.open}
          existingAssetId={duplicate.existingAssetId}
          existingCode={duplicate.existingCode}
          onOpenChange={(open) => setDuplicate(open ? duplicate : null)}
        />
      ) : null}
    </div>
  );
}

interface AssetTileProps {
  asset: AssetEntry;
  locale: string;
  isSelected: boolean;
  onToggle: (additive: boolean) => void;
}

function AssetTile({ asset, locale, isSelected, onToggle }: AssetTileProps) {
  const { t } = useTranslation();
  const attrs = (asset.attributesIndexed ?? {}) as Record<string, unknown>;
  const previewUrl = typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null;
  const mime = typeof attrs.mime === 'string' ? attrs.mime : null;
  const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;
  const thumbnailsStatus =
    typeof attrs.thumbnailsStatus === 'string' ? attrs.thumbnailsStatus : 'ready';
  const altText = resolveLocalised(attrs.alt, locale) ?? filename;

  return (
    <li>
      <div
        className={`group relative overflow-hidden rounded-md border bg-card transition-colors ${
          isSelected ? 'border-primary ring-2 ring-primary/40' : 'hover:border-primary'
        }`}
      >
        <input
          type="checkbox"
          checked={isSelected}
          onChange={() => onToggle(false)}
          onClick={(event) => {
            event.stopPropagation();
            if (event.shiftKey || event.metaKey || event.ctrlKey) {
              onToggle(true);
            }
          }}
          className="absolute left-2 top-2 z-10 size-4 cursor-pointer rounded border-muted-foreground/40"
          aria-label={t('assets.fields.code')}
        />
        <Link to={`/assets/${asset.id}`} className="block">
          <div className="aspect-square bg-muted/40">
            {previewUrl !== null ? (
              <img
                src={previewUrl}
                alt={altText}
                loading="lazy"
                className="h-full w-full object-cover transition-opacity group-hover:opacity-90"
              />
            ) : (
              <PlaceholderTile mime={mime} />
            )}
            {thumbnailsStatus === 'pending' ? (
              <span
                className="absolute right-2 top-2 flex items-center gap-1 rounded bg-background/90 px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                title={t('assets.thumbnails.pending')}
              >
                <Loader2 className="size-3 animate-spin" />
                <span className="sr-only">{t('assets.thumbnails.pending')}</span>
              </span>
            ) : null}
          </div>
          <div className="space-y-1 p-2">
            <p className="truncate text-xs font-medium" title={altText}>
              {altText}
            </p>
            <p className="truncate font-mono text-[10px] text-muted-foreground">{asset.code}</p>
          </div>
        </Link>
      </div>
    </li>
  );
}

function PlaceholderTile({ mime }: { mime: string | null }) {
  const Icon = pickIcon(mime);
  return (
    <div className="flex h-full w-full items-center justify-center text-muted-foreground">
      <Icon className="size-8" />
    </div>
  );
}

function pickIcon(mime: string | null) {
  if (mime === null) return ImageIcon;
  if (mime.startsWith('image/')) return ImageIcon;
  if (mime.startsWith('video/')) return Film;
  return FileText;
}

function resolveLocalised(value: unknown, locale: string): string | null {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object') {
    const map = value as Record<string, string>;
    const lang = locale.split('-')[0] ?? locale;
    return map[lang] ?? map.en ?? map.pl ?? Object.values(map)[0] ?? null;
  }
  return null;
}

function hasPendingThumbnails(items: AssetEntry[] | undefined): boolean {
  if (!items) return false;
  return items.some(
    (item) =>
      typeof item.attributesIndexed?.thumbnailsStatus === 'string' &&
      item.attributesIndexed.thumbnailsStatus === 'pending',
  );
}
