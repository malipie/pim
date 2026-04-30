import { useOne } from '@refinedev/core';
import { ArrowLeft, FileText, Film, Image as ImageIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface AssetDetail {
  id: string;
  code: string;
  enabled?: boolean;
  attributesIndexed?: Record<string, unknown>;
  createdAt?: string;
}

export function AssetShowPage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';
  const { result, query } = useOne<AssetDetail>({
    resource: 'assets',
    id,
    queryOptions: { enabled: id.length > 0 },
  });

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const asset = result;
  const attrs = (asset.attributesIndexed ?? {}) as Record<string, unknown>;
  const previewUrl = typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null;
  const mime = typeof attrs.mime === 'string' ? attrs.mime : null;
  const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;
  const alt = resolveLocalised(attrs.alt, i18n.language) ?? filename;

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <Button asChild variant="ghost" size="sm" className="-ml-3">
          <Link to="/assets">
            <ArrowLeft className="size-4" />
            {t('assets.back')}
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">{filename}</h1>
        <p className="font-mono text-xs text-muted-foreground">{asset.code}</p>
      </div>

      <Card>
        <CardContent className="grid gap-6 pt-6 md:grid-cols-[260px_1fr]">
          <div className="rounded-md border bg-muted/40">
            <div className="aspect-square">
              {previewUrl !== null ? (
                <img
                  src={previewUrl}
                  alt={alt}
                  className="h-full w-full rounded-md object-contain"
                />
              ) : (
                <Placeholder mime={mime} />
              )}
            </div>
          </div>

          <dl className="grid gap-3 sm:grid-cols-2">
            <Row label={t('assets.fields.alt')}>{alt}</Row>
            <Row label={t('assets.fields.mime')}>
              <span className="font-mono text-xs">{mime ?? '—'}</span>
            </Row>
            {Object.entries(attrs)
              .filter(([code]) => !['previewUrl', 'mime', 'filename', 'alt'].includes(code))
              .map(([code, value]) => (
                <Row key={code} label={code}>
                  {formatValue(value)}
                </Row>
              ))}
          </dl>
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">{t('assets.write_deferred_note')}</p>
    </div>
  );
}

function Placeholder({ mime }: { mime: string | null }) {
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
      <Icon className="size-12" />
    </div>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
      <dd className="text-sm font-medium">{children}</dd>
    </div>
  );
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return JSON.stringify(value);
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
