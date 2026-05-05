import { useOne } from '@refinedev/core';
import {
  ArrowLeft,
  Download,
  FileText,
  Film,
  Image as ImageIcon,
  Pencil,
  Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { jsonFetch } from '@/lib/http';

import { AssetEditDialog } from './AssetEditDialog';

interface AssetDetail {
  id: string;
  code: string;
  enabled?: boolean;
  attributesIndexed?: Record<string, unknown>;
  createdAt?: string;
}

export function AssetShowPage() {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';
  const { result, query } = useOne<AssetDetail>({
    resource: 'assets',
    id,
    queryOptions: { enabled: id.length > 0 },
  });
  const [editOpen, setEditOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const asset = result;
  const attrs = (asset.attributesIndexed ?? {}) as Record<string, unknown>;
  const previewUrl = typeof attrs.previewUrl === 'string' ? attrs.previewUrl : null;
  const mime = typeof attrs.mime === 'string' ? attrs.mime : null;
  const filename = typeof attrs.filename === 'string' ? attrs.filename : asset.code;
  const altText = resolveLocalised(attrs.alt, i18n.language) ?? filename;
  const tags = Array.isArray(attrs.tags)
    ? (attrs.tags as unknown[]).filter((entry): entry is string => typeof entry === 'string')
    : [];

  const onDelete = async () => {
    setDeleting(true);
    setDeleteError(null);
    try {
      await jsonFetch(`/api/assets/${asset.id}`, { method: 'DELETE', accept: 'application/json' });
      navigate('/assets');
    } catch {
      setDeleteError(t('assets.detail.delete_error'));
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between gap-3">
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
        <div className="flex flex-wrap items-center gap-2">
          {previewUrl ? (
            <Button asChild variant="outline" size="sm">
              <a href={previewUrl} download={filename} target="_blank" rel="noreferrer">
                <Download className="mr-2 size-4" />
                {t('assets.detail.download')}
              </a>
            </Button>
          ) : null}
          <Button variant="outline" size="sm" onClick={() => setEditOpen(true)}>
            <Pencil className="mr-2 size-4" />
            {t('assets.detail.edit')}
          </Button>
          <Button variant="destructive" size="sm" onClick={() => setDeleteOpen(true)}>
            <Trash2 className="mr-2 size-4" />
            {t('assets.detail.delete')}
          </Button>
        </div>
      </div>

      <Card>
        <CardContent className="grid gap-6 pt-6 md:grid-cols-[260px_1fr]">
          <div className="rounded-md border bg-muted/40">
            <div className="aspect-square">
              {previewUrl !== null ? (
                <img
                  src={previewUrl}
                  alt={altText}
                  className="h-full w-full rounded-md object-contain"
                />
              ) : (
                <Placeholder mime={mime} />
              )}
            </div>
          </div>

          <dl className="grid gap-3 sm:grid-cols-2">
            <Row label={t('assets.fields.alt')}>{altText}</Row>
            <Row label={t('assets.fields.mime')}>
              <span className="font-mono text-xs">{mime ?? '—'}</span>
            </Row>
            <Row label={t('assets.fields.tags')}>
              {tags.length > 0 ? (
                <span className="flex flex-wrap gap-1">
                  {tags.map((tag) => (
                    <span
                      key={tag}
                      className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                      {tag}
                    </span>
                  ))}
                </span>
              ) : (
                '—'
              )}
            </Row>
            {Object.entries(attrs)
              .filter(([code]) => !['previewUrl', 'mime', 'filename', 'alt', 'tags'].includes(code))
              .map(([code, value]) => (
                <Row key={code} label={code}>
                  {formatValue(value)}
                </Row>
              ))}
          </dl>
        </CardContent>
      </Card>

      <AssetEditDialog
        asset={editOpen ? { id: asset.id, code: asset.code, tags } : null}
        open={editOpen}
        onOpenChange={setEditOpen}
        onSaved={() => {
          void query.refetch();
        }}
      />

      <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('assets.detail.delete_confirm_title')}</DialogTitle>
            <DialogDescription>
              {t('assets.detail.delete_confirm_body', { filename })}
            </DialogDescription>
          </DialogHeader>
          {deleteError ? (
            <p className="text-sm text-destructive" role="alert">
              {deleteError}
            </p>
          ) : null}
          <DialogFooter>
            <Button variant="ghost" onClick={() => setDeleteOpen(false)} disabled={deleting}>
              {t('assets.upload.cancel')}
            </Button>
            <Button variant="destructive" onClick={onDelete} disabled={deleting}>
              {deleting ? t('assets.detail.saving') : t('assets.detail.delete_confirm_button')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
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
