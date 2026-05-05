import { useEffect, useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { jsonFetch } from '@/lib/http';

export interface AssetEditPayload {
  id: string;
  code: string;
  tags?: string[];
}

export interface AssetEditDialogProps {
  asset: AssetEditPayload | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: () => void;
}

interface AssetEditResult {
  id: string;
  code: string;
  tags: string[];
}

export function AssetEditDialog({ asset, open, onOpenChange, onSaved }: AssetEditDialogProps) {
  const { t } = useTranslation();
  const [code, setCode] = useState('');
  const [tagsInput, setTagsInput] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (asset) {
      setCode(asset.code);
      setTagsInput((asset.tags ?? []).join(', '));
      setError(null);
    }
  }, [asset]);

  const submit = async () => {
    if (!asset) return;
    setSubmitting(true);
    setError(null);
    try {
      const tags = tagsInput
        .split(/[,\n]/)
        .map((tag) => tag.trim())
        .filter((tag) => tag.length > 0);

      await jsonFetch<AssetEditResult>(`/api/assets/${asset.id}`, {
        method: 'PATCH',
        contentType: 'application/json',
        accept: 'application/json',
        body: { code, tags },
      });

      onSaved();
      onOpenChange(false);
    } catch (_e) {
      setError(t('assets.detail.save_error'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('assets.detail.edit_dialog_title')}</DialogTitle>
          <DialogDescription>{asset?.code}</DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1">
            <Label htmlFor="asset-code">{t('assets.fields.code')}</Label>
            <Input
              id="asset-code"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              autoComplete="off"
            />
            <p className="text-xs text-muted-foreground">{t('assets.detail.code_help')}</p>
          </div>
          <div className="space-y-1">
            <Label htmlFor="asset-tags">{t('assets.fields.tags')}</Label>
            <Input
              id="asset-tags"
              value={tagsInput}
              placeholder={t('assets.detail.tags_placeholder')}
              onChange={(event) => setTagsInput(event.target.value)}
              autoComplete="off"
            />
            <p className="text-xs text-muted-foreground">{t('assets.detail.tags_help')}</p>
          </div>
          {error ? (
            <p className="text-sm text-destructive" role="alert">
              {error}
            </p>
          ) : null}
        </div>
        <DialogFooter>
          <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={submitting}>
            {t('assets.upload.cancel')}
          </Button>
          <Button onClick={submit} disabled={submitting || code.trim().length === 0}>
            {submitting ? t('assets.detail.saving') : t('assets.detail.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
