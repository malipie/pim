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

export interface AssetLibraryPickerProps {
  productId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onPicked: () => void;
}

/**
 * Full-screen modal that lets the operator browse the asset library
 * (with the same Windows-explorer folder navigation as `/assets`)
 * and pick one or more files to attach to a product (#440).
 *
 * Picked ids are POSTed to `/api/products/{id}/assets`; the backend
 * accepts both Asset.id and CatalogObject.id (Asset_Internals
 * resolves either into the canonical Asset.id before linking), so
 * the grid feeds them straight from the listing payload.
 */
export function AssetLibraryPicker({
  productId,
  open,
  onOpenChange,
  onPicked,
}: AssetLibraryPickerProps) {
  const { t } = useTranslation();
  const searchId = useId();

  const [currentFolder, setCurrentFolder] = useState<string | null>(null);
  const [folders, setFolders] = useState<FolderEntry[]>([]);
  const [assets, setAssets] = useState<AssetEntry[]>([]);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const setSearchSoon = useDebouncedCallback((value: string) => setDebouncedSearch(value), 300);

  useEffect(() => {
    setSearchSoon(search);
  }, [search, setSearchSoon]);

  // Reset state every time the modal reopens so a previous session's
  // selection / folder does not leak in.
  useEffect(() => {
    if (open) {
      setCurrentFolder(null);
      setSelected(new Set());
      setSearch('');
      setDebouncedSearch('');
      setError(null);
    }
  }, [open]);

  // Folder list — only at root.
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

  // Asset listing — narrowed by current folder + search.
  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    const query: Record<string, string> = {
      folder: currentFolder ?? 'root',
    };
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

  const toggle = (id: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const submit = async () => {
    if (selected.size === 0) return;
    setSubmitting(true);
    setError(null);
    try {
      await jsonFetch(`/api/products/${productId}/assets`, {
        method: 'POST',
        contentType: 'application/json',
        accept: 'application/json',
        body: { assetIds: [...selected] },
      });
      onPicked();
      onOpenChange(false);
    } catch {
      setError(t('products.multimedia.pick_error'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[min(1100px,95vw)] max-h-[90vh] overflow-hidden">
        <DialogHeader>
          <DialogTitle>{t('products.multimedia.picker_title')}</DialogTitle>
          <DialogDescription>{t('products.multimedia.picker_body')}</DialogDescription>
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
                  <PickerAssetTile
                    key={asset.id}
                    asset={asset}
                    isSelected={selected.has(asset.id)}
                    onToggle={() => toggle(asset.id)}
                  />
                ))}
              </ul>
            )}
          </div>

          {error ? (
            <p className="text-sm text-destructive" role="alert">
              {error}
            </p>
          ) : null}
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={submitting}>
            {t('app.cancel')}
          </Button>
          <Button onClick={submit} disabled={submitting || selected.size === 0}>
            {submitting
              ? t('app.loading')
              : t('products.multimedia.pick_confirm', { count: selected.size })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface PickerAssetTileProps {
  asset: AssetEntry;
  isSelected: boolean;
  onToggle: () => void;
}

function PickerAssetTile({ asset, isSelected, onToggle }: PickerAssetTileProps) {
  const attrs = (asset.attributesIndexed ?? {}) as Record<string, unknown>;
  const previewUrl = typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null;
  const mime = typeof attrs.mime === 'string' ? attrs.mime : null;
  const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;

  return (
    <li>
      <button
        type="button"
        onClick={onToggle}
        aria-pressed={isSelected}
        className={`group relative flex w-full flex-col overflow-hidden rounded-md border bg-card text-left transition-colors ${
          isSelected ? 'border-primary ring-2 ring-primary/40' : 'hover:border-primary'
        }`}
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
          <span
            className={`absolute right-2 top-2 flex size-5 items-center justify-center rounded-full border text-[10px] font-bold transition-colors ${
              isSelected
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-muted-foreground/40 bg-background/80 text-transparent'
            }`}
          >
            ✓
          </span>
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
