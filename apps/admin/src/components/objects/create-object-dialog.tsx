import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
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
import { HttpError, jsonFetch } from '@/lib/http';

/**
 * #1014 — minimal create dialog for custom-kind ObjectTypes (Bug A fix).
 *
 * Custom kinds have no dedicated sugar create route (`/products/new`
 * etc. only exist for built-in product/category/asset/brand). Pre-fix
 * `handleCreate` for custom kinds navigated to `/objects/{code}/new`
 * which does not exist — the App-level catch-all redirected to
 * `/dashboard`, completely blocking the create flow.
 *
 * This dialog ships the minimum-viable form: code + name. POST goes to
 * the poly-kind `/api/objects` (added in #981 for the relation-picker
 * `Utwórz i podepnij` flow) with body
 * `{ code, objectTypeId, attributes: { name } }`. Backend derives the
 * kind from `objectTypeId` (per `CatalogObjectProcessor::expectedKindFor`).
 *
 * Out of scope (deferred to ULV-10 wizard UI step):
 *   - Full attribute form following list-schema (relations, options, etc.).
 *   - Per-attribute validation feedback beyond the backend 422 message.
 *   - Multi-step wizard for categorizable / variant-bearing kinds.
 *
 * On success the dialog invalidates the `object-list` query for this
 * ObjectType so the populated list (or empty-state → new row) refreshes
 * immediately.
 */
export interface CreateObjectDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  objectTypeId: string;
  objectTypeCode: string;
  objectTypeLabel: string;
}

export function CreateObjectDialog({
  open,
  onOpenChange,
  objectTypeId,
  objectTypeCode,
  objectTypeLabel,
}: CreateObjectDialogProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const reset = () => {
    setCode('');
    setName('');
    setError(null);
    setBusy(false);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;

    const trimmedCode = code.trim();
    const trimmedName = name.trim();
    if (trimmedCode.length === 0) {
      setError(
        t('object_list.create_dialog.error_code_required', {
          defaultValue: 'Code is required.',
        }),
      );
      return;
    }

    setError(null);
    setBusy(true);
    try {
      await jsonFetch<{ id?: string }>('/api/objects', {
        method: 'POST',
        contentType: 'application/ld+json',
        accept: 'application/ld+json',
        body: {
          code: trimmedCode,
          objectTypeId,
          attributes: trimmedName.length > 0 ? { name: trimmedName } : {},
        },
      });
      // Refresh the list view so the newly-created row appears immediately.
      await queryClient.invalidateQueries({
        queryKey: ['object-list', objectTypeId],
      });
      reset();
      onOpenChange(false);
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        if (err.status === 409) {
          setError(
            t('object_list.create_dialog.error_duplicate_code', {
              defaultValue: 'Object with this code already exists.',
            }),
          );
        } else {
          setError(detail ?? `HTTP ${err.status}`);
        }
      } else {
        setError(
          t('object_list.create_dialog.error_generic', {
            defaultValue: 'Could not create object.',
          }),
        );
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) reset();
        onOpenChange(next);
      }}
    >
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>
            {t('object_list.create_dialog.title', {
              defaultValue: 'Create {{type}}',
              type: objectTypeLabel,
            })}
          </DialogTitle>
          <DialogDescription>
            {t('object_list.create_dialog.description', {
              defaultValue: 'Provide the unique code and an optional display name.',
            })}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor={`create-object-code-${objectTypeCode}`}>
              {t('object_list.create_dialog.code_label', { defaultValue: 'Code' })}
              <span className="text-destructive"> *</span>
            </Label>
            <Input
              id={`create-object-code-${objectTypeCode}`}
              autoFocus
              value={code}
              onChange={(e) => setCode(e.target.value)}
              placeholder={t('object_list.create_dialog.code_placeholder', {
                defaultValue: 'e.g. CAR-001',
              })}
              required
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor={`create-object-name-${objectTypeCode}`}>
              {t('object_list.create_dialog.name_label', { defaultValue: 'Name' })}
            </Label>
            <Input
              id={`create-object-name-${objectTypeCode}`}
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={t('object_list.create_dialog.name_placeholder', {
                defaultValue: 'Display name (optional)',
              })}
            />
          </div>
          {error !== null ? (
            <div
              role="alert"
              className="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive"
            >
              {error}
            </div>
          ) : null}
          <DialogFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={() => onOpenChange(false)}
              disabled={busy}
            >
              {t('object_list.create_dialog.cancel', { defaultValue: 'Cancel' })}
            </Button>
            <Button type="submit" disabled={busy || code.trim().length === 0}>
              {busy
                ? t('object_list.create_dialog.submitting', { defaultValue: 'Creating…' })
                : t('object_list.create_dialog.submit', { defaultValue: 'Create' })}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
