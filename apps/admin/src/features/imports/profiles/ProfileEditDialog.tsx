import { useList } from '@refinedev/core';
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

import type { ImportMode } from '../primitives';
import type { ImportProfileRow } from './types';

const MODES: ReadonlyArray<ImportMode> = [
  'ADD',
  'UPDATE',
  'UPSERT',
  'MERGE',
  'INCREMENT',
  'DELETE',
];

interface ProfileEditDialogProps {
  open: boolean;
  mode: 'create' | 'edit';
  profile?: ImportProfileRow;
  onClose: () => void;
  onSubmit: (input: ProfileFormInput) => Promise<void> | void;
}

export interface ProfileFormInput {
  name: string;
  code: string;
  mode: ImportMode;
  targetObjectTypeId: string;
  encoding: string | null;
  delimiter: string | null;
  locale: string | null;
}

interface ObjectTypeRef {
  id: string;
  code: string;
  kind: string;
  '@id'?: string;
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

export function ProfileEditDialog({
  open,
  mode,
  profile,
  onClose,
  onSubmit,
}: ProfileEditDialogProps) {
  const { t } = useTranslation();
  const isEdit = mode === 'edit';
  const [name, setName] = React.useState(profile?.name ?? '');
  const [code, setCode] = React.useState(profile?.code ?? '');
  const [importMode, setImportMode] = React.useState<ImportMode>(profile?.mode ?? 'UPDATE');
  const [targetId, setTargetId] = React.useState<string>('');
  const [encoding, setEncoding] = React.useState(profile?.encoding ?? 'utf-8');
  const [delimiter, setDelimiter] = React.useState(profile?.delimiter ?? ';');
  const [locale, setLocale] = React.useState(profile?.locale ?? '');
  const [submitting, setSubmitting] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [codeTouched, setCodeTouched] = React.useState(isEdit);

  const { result: objectTypes } = useList<ObjectTypeRef>({
    resource: 'object_types',
    pagination: { pageSize: 100 },
    queryOptions: { enabled: open },
  });

  React.useEffect(() => {
    if (!open) {
      return;
    }
    setName(profile?.name ?? '');
    setCode(profile?.code ?? '');
    setImportMode(profile?.mode ?? 'UPDATE');
    setEncoding(profile?.encoding ?? 'utf-8');
    setDelimiter(profile?.delimiter ?? ';');
    setLocale(profile?.locale ?? '');
    setError(null);
    setCodeTouched(isEdit);
  }, [open, profile, isEdit]);

  // Auto-derive code from name until the user manually edits it.
  React.useEffect(() => {
    if (!codeTouched) {
      setCode(slugify(name));
    }
  }, [name, codeTouched]);

  const products = (objectTypes.data ?? []).filter(
    (t) => t.kind === 'product' || t.kind === 'category' || t.kind === 'asset',
  );
  const fallbackTarget = profile?.targetObjectType;
  const targetIdResolved =
    targetId || (typeof fallbackTarget === 'string' ? fallbackTarget : (fallbackTarget?.id ?? ''));

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (name.trim().length === 0) {
      setError(t('imports.profiles.dialog.errors.name_required'));
      return;
    }
    if (!isEdit && !targetIdResolved) {
      setError(t('imports.profiles.dialog.errors.target_required'));
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await onSubmit({
        name: name.trim(),
        code: code.trim() || slugify(name),
        mode: importMode,
        targetObjectTypeId: targetIdResolved,
        encoding: encoding.trim() || null,
        delimiter: delimiter || null,
        locale: locale.trim() || null,
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
              ? t('imports.profiles.dialog.title_edit')
              : t('imports.profiles.dialog.title_create')}
          </DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <div className="grid gap-2">
            <Label htmlFor="profile-name">{t('imports.profiles.dialog.name')}</Label>
            <Input
              id="profile-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              maxLength={255}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="profile-code">{t('imports.profiles.dialog.code')}</Label>
            <Input
              id="profile-code"
              value={code}
              onChange={(e) => {
                setCode(e.target.value);
                setCodeTouched(true);
              }}
              maxLength={64}
              pattern="[a-z0-9-]+"
            />
            <span className="text-[11px] text-muted-foreground">
              {t('imports.profiles.dialog.code_hint')}
            </span>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="profile-mode">{t('imports.profiles.dialog.mode')}</Label>
              <Combobox
                options={MODES.map((m) => ({ value: m, label: m }))}
                value={importMode}
                onChange={(v) => setImportMode((v ?? 'UPDATE') as ImportMode)}
              />
            </div>
            {!isEdit ? (
              <div className="grid gap-2">
                <Label htmlFor="profile-target">{t('imports.profiles.dialog.target')}</Label>
                <Combobox
                  options={products.map((typeRef) => ({ value: typeRef.id, label: typeRef.code }))}
                  value={targetIdResolved}
                  onChange={(v) => setTargetId(v ?? '')}
                  placeholder={t('imports.profiles.dialog.target_placeholder')}
                />
              </div>
            ) : null}
          </div>
          <div className="grid grid-cols-3 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="profile-encoding">{t('imports.profiles.dialog.encoding')}</Label>
              <Input
                id="profile-encoding"
                value={encoding}
                onChange={(e) => setEncoding(e.target.value)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="profile-delimiter">{t('imports.profiles.dialog.delimiter')}</Label>
              <Input
                id="profile-delimiter"
                value={delimiter}
                onChange={(e) => setDelimiter(e.target.value)}
                maxLength={4}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="profile-locale">{t('imports.profiles.dialog.locale')}</Label>
              <Input
                id="profile-locale"
                value={locale}
                onChange={(e) => setLocale(e.target.value)}
                maxLength={8}
              />
            </div>
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
                  : t('imports.profiles.dialog.create')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
