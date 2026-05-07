import { type HttpError, useDelete, useList, useUpdate } from '@refinedev/core';
import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/ui/data-table';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';

interface ImportProfileRow {
  id: string;
  name: string;
  locale: string | null;
  encoding: string | null;
  delimiter: string | null;
  imageSource?: string;
  lastUsedAt: string | null;
  createdAt: string;
}

interface ImportProfileManagerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * Spec §5.8 — opens from the imports list toolbar. Lists every saved
 * profile owned by the current user, supports inline rename + delete.
 * Edit affects future imports only — disclaimer is rendered above
 * the table so the operator sees the constraint before clicking the
 * pencil icon.
 */
export function ImportProfileManager({
  open,
  onOpenChange,
}: ImportProfileManagerProps): React.ReactElement {
  const { t } = useTranslation();
  const [editing, setEditing] = React.useState<ImportProfileRow | null>(null);
  const [deleting, setDeleting] = React.useState<ImportProfileRow | null>(null);

  const { result, query } = useList<ImportProfileRow>({
    resource: 'import-profiles',
    pagination: { pageSize: 100 },
    queryOptions: { enabled: open },
  });
  const profiles = result.data ?? [];

  const columns: ReadonlyArray<DataTableColumn<ImportProfileRow>> = [
    {
      id: 'name',
      header: t('imports.profiles.columns.name', { defaultValue: 'Profil' }),
      cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      id: 'last_used',
      header: t('imports.profiles.columns.last_used', { defaultValue: 'Ostatnio użyty' }),
      cell: (row) => formatRelative(row.lastUsedAt),
      className: 'whitespace-nowrap text-xs text-muted-foreground',
    },
    {
      id: 'actions',
      header: t('imports.profiles.columns.actions', { defaultValue: 'Akcje' }),
      align: 'right',
      cell: (row) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="sm" onClick={() => setEditing(row)}>
            ✏️
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setDeleting(row)}
            aria-label={t('imports.profiles.delete', { defaultValue: 'Usuń' })}
          >
            🗑
          </Button>
        </div>
      ),
    },
  ];

  return (
    <>
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent className="w-full max-w-2xl">
          <SheetTitle>
            {t('imports.profiles.title', { defaultValue: 'Moje profile importu' })}
          </SheetTitle>
          <p className="rounded-md bg-muted/40 p-3 text-xs text-muted-foreground">
            {t('imports.profiles.disclaimer', {
              defaultValue:
                'Edycja profilu modyfikuje tylko przyszłe importy. Nie wpływa na poprzednie.',
            })}
          </p>
          {query.isLoading ? (
            <p className="text-sm text-muted-foreground" aria-busy="true">
              {t('app.loading', { defaultValue: 'Ładowanie…' })}
            </p>
          ) : (
            <DataTable
              data={profiles}
              columns={columns}
              rowKey={(row) => row.id}
              emptyState={
                <p className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                  Nie masz jeszcze profili. Zapisz konfigurację z wizard'a.
                </p>
              }
            />
          )}
        </SheetContent>
      </Sheet>

      {editing !== null && (
        <EditProfileDialog
          profile={editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            query.refetch();
          }}
        />
      )}

      {deleting !== null && (
        <DeleteProfileDialog
          profile={deleting}
          onClose={() => setDeleting(null)}
          onDeleted={() => {
            setDeleting(null);
            query.refetch();
          }}
        />
      )}
    </>
  );
}

interface EditProfileDialogProps {
  profile: ImportProfileRow;
  onClose: () => void;
  onSaved: () => void;
}

function EditProfileDialog({
  profile,
  onClose,
  onSaved,
}: EditProfileDialogProps): React.ReactElement {
  const { t } = useTranslation();
  const [name, setName] = React.useState(profile.name);
  const { mutate, mutation } = useUpdate<ImportProfileRow, HttpError, { name: string }>();

  const submit = (): void => {
    mutate(
      {
        resource: 'import-profiles',
        id: profile.id,
        values: { name },
        meta: { headers: { 'content-type': 'application/merge-patch+json' } },
      },
      { onSuccess: () => onSaved() },
    );
  };

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('imports.profiles.edit', { defaultValue: 'Edytuj profil' })}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <div className="space-y-1">
            <Label htmlFor="profile-name">Nazwa</Label>
            <Input
              id="profile-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="ghost" onClick={onClose} disabled={mutation.isPending}>
            {t('imports.wizard.cancel', { defaultValue: 'Anuluj' })}
          </Button>
          <Button onClick={submit} disabled={mutation.isPending || name.trim() === ''}>
            {t('app.save', { defaultValue: 'Zapisz' })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface DeleteProfileDialogProps {
  profile: ImportProfileRow;
  onClose: () => void;
  onDeleted: () => void;
}

function DeleteProfileDialog({
  profile,
  onClose,
  onDeleted,
}: DeleteProfileDialogProps): React.ReactElement {
  const { t } = useTranslation();
  const { mutate, mutation } = useDelete();

  const confirm = (): void => {
    mutate({ resource: 'import-profiles', id: profile.id }, { onSuccess: () => onDeleted() });
  };

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('imports.profiles.delete', { defaultValue: 'Usuń profil' })}</DialogTitle>
        </DialogHeader>
        <p className="text-sm">
          Usunąć profil <span className="font-mono">{profile.name}</span>? Zostanie usunięty na
          stałe.
        </p>
        <DialogFooter>
          <Button variant="ghost" onClick={onClose} disabled={mutation.isPending}>
            {t('imports.wizard.cancel', { defaultValue: 'Anuluj' })}
          </Button>
          <Button variant="destructive" onClick={confirm} disabled={mutation.isPending}>
            {t('imports.profiles.delete', { defaultValue: 'Usuń' })}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function formatRelative(value: string | null): string {
  if (value === null) {
    return '—';
  }
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }
  const diff = Date.now() - parsed.getTime();
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  if (days < 1) {
    return 'Dziś';
  }
  if (days < 2) {
    return 'Wczoraj';
  }
  if (days < 30) {
    return `${days} dni temu`;
  }
  return parsed.toLocaleDateString('pl-PL');
}
