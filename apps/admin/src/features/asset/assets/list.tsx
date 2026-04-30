import { useList } from '@refinedev/core';
import { FileText, Film, Image as ImageIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

interface AssetEntry {
  id: string;
  code: string;
  enabled?: boolean;
  attributesIndexed?: Record<string, unknown>;
}

export function AssetsListPage() {
  const { t, i18n } = useTranslation();
  const { result, query } = useList<AssetEntry>({
    resource: 'assets',
    pagination: { mode: 'off' },
  });

  const assets = result.data;
  const isLoading = query.isLoading;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('assets.list_title')}</h1>
        <p className="text-sm text-muted-foreground">{t('assets.list_subtitle')}</p>
      </div>

      {isLoading ? (
        <p className="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground">
          {t('app.loading')}
        </p>
      ) : assets.length === 0 ? (
        <p className="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground">
          {t('assets.empty')}
        </p>
      ) : (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
          {assets.map((asset) => {
            const attrs = (asset.attributesIndexed ?? {}) as Record<string, unknown>;
            const previewUrl = typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null;
            const mime = typeof attrs.mime === 'string' ? attrs.mime : null;
            const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;

            return (
              <li key={asset.id}>
                <Link
                  to={`/assets/${asset.id}`}
                  className="group block overflow-hidden rounded-md border bg-card transition-colors hover:border-primary"
                >
                  <div className="aspect-square bg-muted/40">
                    {previewUrl !== null ? (
                      <img
                        src={previewUrl}
                        alt={filename}
                        loading="lazy"
                        className="h-full w-full object-cover transition-opacity group-hover:opacity-90"
                      />
                    ) : (
                      <PlaceholderTile mime={mime} />
                    )}
                  </div>
                  <div className="space-y-1 p-2">
                    <p
                      className="truncate text-xs font-medium"
                      title={resolveLocalised(attrs.alt, i18n.language) ?? filename}
                    >
                      {resolveLocalised(attrs.alt, i18n.language) ?? filename}
                    </p>
                    <p className="truncate font-mono text-[10px] text-muted-foreground">
                      {asset.code}
                    </p>
                  </div>
                </Link>
              </li>
            );
          })}
        </ul>
      )}

      <p className="text-xs text-muted-foreground">{t('assets.write_deferred_note')}</p>
    </div>
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
