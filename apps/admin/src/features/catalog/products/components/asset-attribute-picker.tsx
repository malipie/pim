import {
  ChevronRight,
  FileText,
  Film,
  Folder as FolderIcon,
  Image as ImageIcon,
  Search,
} from 'lucide-react';
import { useEffect, useId, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { unwrapAttributesIndexed } from '@/lib/attributes-indexed';
import { jsonFetch } from '@/lib/http';
import { useDebouncedCallback } from '@/lib/use-debounced-callback';

interface AssetEntry {
  id: string;
  code: string;
  attributesIndexed?: Record<string, unknown>;
}

interface AssetsResponse {
  member: AssetEntry[];
  totalItems: number;
}

interface FolderEntry {
  code: string;
  displayName: string;
  assetCount: number;
}

interface FoldersResponse {
  member: FolderEntry[];
  totalItems: number;
}

/**
 * Asset chosen for an `asset` attribute value (#1138). `id` is the
 * CatalogObject id from the listing — resolvable later through
 * `GET /api/assets/{id}` to refresh the preview.
 */
export interface PickedAsset {
  id: string;
  previewUrl: string | null;
  filename: string;
}

export interface AssetAttributePickerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onPick: (asset: PickedAsset) => void;
}

/**
 * Single-select asset library browser for `asset` attribute fields
 * (#1138). Mirrors {@see AssetLibraryPicker}'s folder/grid navigation but
 * returns the chosen asset (id + preview) through `onPick` instead of
 * POSTing to the product↔asset junction — the value lands in the field's
 * `{asset_id}` envelope via the detail page's dirty-fields flow.
 */
export function AssetAttributePicker({ open, onOpenChange, onPick }: AssetAttributePickerProps) {
  const { t } = useTranslation();
  const searchId = useId();

  const [currentFolder, setCurrentFolder] = useState<string | null>(null);
  const [folders, setFolders] = useState<FolderEntry[]>([]);
  const [assets, setAssets] = useState<AssetEntry[]>([]);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');

  const setSearchSoon = useDebouncedCallback((value: string) => setDebouncedSearch(value), 300);

  useEffect(() => {
    setSearchSoon(search);
  }, [search, setSearchSoon]);

  useEffect(() => {
    if (open) {
      setCurrentFolder(null);
      setSearch('');
      setDebouncedSearch('');
    }
  }, [open]);

  useEffect(() => {
    if (!open || currentFolder !== null) return;
    let cancelled = false;
    void (async () => {
      try {
        const response = await jsonFetch<FoldersResponse>('/api/asset-folders', {
          accept: 'application/json',
        });
        if (!cancelled) setFolders(response.member ?? []);
      } catch {
        if (!cancelled) setFolders([]);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [open, currentFolder]);

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    const query: Record<string, string> = { folder: currentFolder ?? 'root' };
    if (debouncedSearch) {
      query.search = debouncedSearch;
    }
    void (async () => {
      try {
        const response = await jsonFetch<AssetsResponse>('/api/assets', {
          accept: 'application/ld+json',
          query,
        });
        if (!cancelled) setAssets(response.member ?? []);
      } catch {
        if (!cancelled) setAssets([]);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [open, currentFolder, debouncedSearch]);

  const currentFolderEntry = useMemo(
    () => (currentFolder ? folders.find((entry) => entry.code === currentFolder) : null),
    [currentFolder, folders],
  );
  const showFolderTiles = currentFolder === null && folders.length > 0 && !debouncedSearch;

  const handlePick = (asset: AssetEntry) => {
    const attrs = unwrapAttributesIndexed(asset.attributesIndexed);
    const previewUrl = typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null;
    const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;
    onPick({ id: asset.id, previewUrl, filename });
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[min(1100px,95vw)] max-h-[90vh] overflow-hidden">
        <DialogHeader>
          <DialogTitle>
            {t('products.detail.asset.picker_title', { defaultValue: 'Wybierz zasób' })}
          </DialogTitle>
          <DialogDescription>
            {t('products.detail.asset.picker_body', {
              defaultValue: 'Wskaż zasób z biblioteki multimediów dla tego pola.',
            })}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-3" style={{ minHeight: 'min(500px, 60vh)' }}>
          <nav
            aria-label="Breadcrumb"
            className="flex items-center gap-1 text-sm text-muted-foreground"
          >
            <button
              type="button"
              onClick={() => setCurrentFolder(null)}
              className={`rounded px-2 py-1 transition-colors ${
                currentFolder === null ? 'font-medium text-foreground' : 'hover:bg-muted'
              }`}
            >
              {t('assets.folder.all')}
            </button>
            {currentFolderEntry !== null && currentFolderEntry !== undefined ? (
              <>
                <ChevronRight className="size-4" aria-hidden="true" />
                <span className="px-2 py-1 font-medium text-foreground">
                  {currentFolderEntry.displayName}
                </span>
              </>
            ) : null}
          </nav>

          <div className="relative flex w-full max-w-md items-center">
            <Search className="absolute left-3 size-4 text-muted-foreground" aria-hidden="true" />
            <label htmlFor={searchId} className="sr-only">
              {t('assets.filters.search_placeholder')}
            </label>
            <Input
              id={searchId}
              type="search"
              placeholder={t('assets.filters.search_placeholder')}
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="pl-9"
            />
          </div>

          <div
            className="overflow-y-auto rounded-md border bg-card p-3"
            style={{ maxHeight: '55vh' }}
          >
            {assets.length === 0 && !showFolderTiles ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                {t('products.multimedia.picker_empty')}
              </p>
            ) : (
              <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                {showFolderTiles
                  ? folders.map((folder) => (
                      <li key={folder.code}>
                        <button
                          type="button"
                          onClick={() => setCurrentFolder(folder.code)}
                          className="group flex w-full flex-col overflow-hidden rounded-md border bg-card text-left transition-colors hover:border-primary"
                        >
                          <div className="flex aspect-square items-center justify-center bg-muted/40">
                            <FolderIcon className="size-10 text-muted-foreground transition-colors group-hover:text-primary" />
                          </div>
                          <div className="space-y-0.5 p-2">
                            <p className="truncate text-xs font-medium" title={folder.displayName}>
                              {folder.displayName}
                            </p>
                            <p className="truncate text-[10px] text-muted-foreground">
                              {t('assets.folder.count', { count: folder.assetCount })}
                            </p>
                          </div>
                        </button>
                      </li>
                    ))
                  : null}
                {assets.map((asset) => (
                  <PickerAssetTile key={asset.id} asset={asset} onPick={() => handlePick(asset)} />
                ))}
              </ul>
            )}
          </div>
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={() => onOpenChange(false)}>
            {t('app.cancel')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface PickerAssetTileProps {
  asset: AssetEntry;
  onPick: () => void;
}

function PickerAssetTile({ asset, onPick }: PickerAssetTileProps) {
  const attrs = unwrapAttributesIndexed(asset.attributesIndexed);
  const previewUrl = typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null;
  const mime = typeof attrs.mime === 'string' ? attrs.mime : null;
  const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;

  return (
    <li>
      <button
        type="button"
        onClick={onPick}
        className="group relative flex w-full flex-col overflow-hidden rounded-md border bg-card text-left transition-colors hover:border-primary"
      >
        <div className="aspect-square bg-muted/40">
          {previewUrl !== null ? (
            <img
              src={previewUrl}
              alt={filename}
              loading="lazy"
              className="h-full w-full object-cover"
            />
          ) : (
            <PickerPlaceholder mime={mime} />
          )}
        </div>
        <div className="space-y-1 p-2">
          <p className="truncate text-xs font-medium" title={filename}>
            {filename}
          </p>
          <p className="truncate font-mono text-[10px] text-muted-foreground">{asset.code}</p>
        </div>
      </button>
    </li>
  );
}

function PickerPlaceholder({ mime }: { mime: string | null }) {
  const Icon =
    mime === null
      ? ImageIcon
      : mime.startsWith('image/')
        ? ImageIcon
        : mime.startsWith('video/')
          ? Film
          : FileText;
  return (
    <div className="flex h-full w-full items-center justify-center text-muted-foreground">
      <Icon className="size-8" />
    </div>
  );
}
