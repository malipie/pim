import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import type { SourceType } from '../primitives';
import type { ImportSourceRow } from './types';

const SOURCE_TYPES: ReadonlyArray<SourceType> = [
  'sftp',
  'ftp',
  'http',
  'folder',
  'webhook',
  'api',
  'upload',
];

interface SourceFormDialogProps {
  open: boolean;
  mode: 'create' | 'edit';
  source?: ImportSourceRow;
  onClose: () => void;
  onSubmit: (input: SourceFormInput) => Promise<void> | void;
}

export interface SourceFormInput {
  name: string;
  code: string;
  type: SourceType;
  host: string | null;
  path: string | null;
  filePattern: string | null;
  authRef: string | null;
  pollIntervalSec: number | null;
  autotrigger: boolean;
}

function slugify(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

export function SourceFormDialog({ open, mode, source, onClose, onSubmit }: SourceFormDialogProps) {
  const { t } = useTranslation();
  const isEdit = mode === 'edit';
  const [name, setName] = React.useState(source?.name ?? '');
  const [code, setCode] = React.useState(source?.code ?? '');
  const [type, setType] = React.useState<SourceType>(source?.type ?? 'folder');
  const [host, setHost] = React.useState(source?.host ?? '');
  const [path, setPath] = React.useState(source?.path ?? '');
  const [filePattern, setFilePattern] = React.useState(source?.filePattern ?? '');
  const [authRef, setAuthRef] = React.useState(source?.authRef ?? '');
  const [pollIntervalSec, setPollIntervalSec] = React.useState<string>(
    source?.pollIntervalSec?.toString() ?? '',
  );
  const [autotrigger, setAutotrigger] = React.useState(source?.autotrigger ?? false);
  const [submitting, setSubmitting] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [codeTouched, setCodeTouched] = React.useState(isEdit);

  React.useEffect(() => {
    if (!open) {
      return;
    }
    setName(source?.name ?? '');
    setCode(source?.code ?? '');
    setType(source?.type ?? 'folder');
    setHost(source?.host ?? '');
    setPath(source?.path ?? '');
    setFilePattern(source?.filePattern ?? '');
    setAuthRef(source?.authRef ?? '');
    setPollIntervalSec(source?.pollIntervalSec?.toString() ?? '');
    setAutotrigger(source?.autotrigger ?? false);
    setError(null);
    setCodeTouched(isEdit);
  }, [open, source, isEdit]);

  React.useEffect(() => {
    if (!codeTouched) {
      setCode(slugify(name));
    }
  }, [name, codeTouched]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (name.trim().length === 0) {
      setError(t('imports.sources.dialog.errors.name_required'));
      return;
    }
    const pollNum = pollIntervalSec.trim().length === 0 ? null : Number(pollIntervalSec);
    if (pollNum !== null && (Number.isNaN(pollNum) || pollNum < 30 || pollNum > 86400)) {
      setError(t('imports.sources.dialog.errors.poll_range'));
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await onSubmit({
        name: name.trim(),
        code: code.trim() || slugify(name),
        type,
        host: host.trim() || null,
        path: path.trim() || null,
        filePattern: filePattern.trim() || null,
        authRef: authRef.trim() || null,
        pollIntervalSec: pollNum,
        autotrigger,
      });
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => (!next ? onClose() : undefined)}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {isEdit
              ? t('imports.sources.dialog.title_edit')
              : t('imports.sources.dialog.title_create')}
          </DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="source-name">{t('imports.sources.dialog.name')}</Label>
              <Input
                id="source-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                maxLength={255}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="source-code">{t('imports.sources.dialog.code')}</Label>
              <Input
                id="source-code"
                value={code}
                onChange={(e) => {
                  setCode(e.target.value);
                  setCodeTouched(true);
                }}
                pattern="[a-z0-9-]+"
                maxLength={64}
              />
            </div>
          </div>
          <div className="grid gap-2">
            <Label htmlFor="source-type">{t('imports.sources.dialog.type')}</Label>
            <Combobox
              options={SOURCE_TYPES.map((t2) => ({ value: t2, label: t2.toUpperCase() }))}
              value={type}
              onChange={(v) => setType((v ?? 'folder') as SourceType)}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="source-host">{t('imports.sources.dialog.host')}</Label>
              <Input id="source-host" value={host} onChange={(e) => setHost(e.target.value)} />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="source-path">{t('imports.sources.dialog.path')}</Label>
              <Input id="source-path" value={path} onChange={(e) => setPath(e.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="source-pattern">{t('imports.sources.dialog.file_pattern')}</Label>
              <Input
                id="source-pattern"
                value={filePattern}
                onChange={(e) => setFilePattern(e.target.value)}
                placeholder="*.csv"
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="source-auth">{t('imports.sources.dialog.auth_ref')}</Label>
              <Input
                id="source-auth"
                value={authRef}
                onChange={(e) => setAuthRef(e.target.value)}
                placeholder="IMPORT_SOURCE_AUTH_<CODE>"
              />
              <span className="text-[11px] text-muted-foreground">
                {t('imports.sources.dialog.auth_hint')}
              </span>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3 items-end">
            <div className="grid gap-2">
              <Label htmlFor="source-poll">{t('imports.sources.dialog.poll')}</Label>
              <Input
                id="source-poll"
                type="number"
                value={pollIntervalSec}
                onChange={(e) => setPollIntervalSec(e.target.value)}
                placeholder="300"
                min={30}
                max={86400}
              />
            </div>
            <label className="flex items-center gap-2 text-[13px]">
              <input
                type="checkbox"
                checked={autotrigger}
                onChange={(e) => setAutotrigger(e.target.checked)}
              />
              {t('imports.sources.dialog.autotrigger')}
            </label>
          </div>
          {error ? (
            <div role="alert" className="rounded-md bg-rose-50 px-3 py-2 text-[12px] text-rose-700">
              {error}
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              {t('app.cancel')}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting
                ? t('app.loading')
                : isEdit
                  ? t('app.save')
                  : t('imports.sources.dialog.create')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
