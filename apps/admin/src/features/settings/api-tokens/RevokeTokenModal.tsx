import { ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

import type { ApiTokenListItem } from './types';

interface RevokeTokenModalProps {
  token: ApiTokenListItem | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

/**
 * RBAC-P5-011 (#701) — confirm modal for `DELETE /api/api-tokens/{id}`.
 *
 * Following the CLAUDE.md "hard confirm pattern" for destructive
 * actions (and PRD §5.3 spec line *„Type token name to confirm"*),
 * the operator must type the token's name verbatim before the
 * Revoke button enables. This matches the bulk-delete flow used
 * elsewhere in the admin and keeps an accidental click from
 * invalidating an integration in production.
 *
 * The endpoint is idempotent — re-revoking returns 200 without
 * mutating state, so the FE does not need to special-case the
 * race-condition where two operators click Revoke at the same
 * instant.
 */
export function RevokeTokenModal({ token, open, onOpenChange, onSuccess }: RevokeTokenModalProps) {
  const { t } = useTranslation();
  const [confirmation, setConfirmation] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const reset = () => {
    setConfirmation('');
    setSubmitting(false);
  };

  const close = (next: boolean) => {
    if (!next) reset();
    onOpenChange(next);
  };

  const canSubmit = token !== null && confirmation === token.name && !submitting;

  const handleRevoke = async () => {
    if (!token || !canSubmit) return;
    setSubmitting(true);
    try {
      await jsonFetch(`/api/api-tokens/${token.id}`, {
        method: 'DELETE',
        accept: 'application/json',
      });
      toast.success(t('settings.api_tokens.revoke.toast_success', { name: token.name }));
      onSuccess();
      close(false);
    } catch (error: unknown) {
      const status = (error as { status?: number })?.status;
      if (status === 403) {
        toast.error(t('settings.api_tokens.revoke.error_forbidden'));
      } else if (status === 404) {
        toast.error(t('settings.api_tokens.revoke.error_not_found'));
      } else {
        toast.error(t('settings.api_tokens.revoke.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-rose-100 text-rose-700">
            <ShieldOff className="size-5" aria-hidden="true" />
          </div>
          <DialogTitle>{t('settings.api_tokens.revoke.title')}</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">
          {t('settings.api_tokens.revoke.body', { name: token?.name ?? '' })}
        </p>
        <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
          {t('settings.api_tokens.revoke.warning')}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="revoke-confirmation">
            {t('settings.api_tokens.revoke.confirm_label', { name: token?.name ?? '' })}
          </Label>
          <Input
            id="revoke-confirmation"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            placeholder={token?.name ?? ''}
            autoComplete="off"
            spellCheck={false}
          />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => close(false)} disabled={submitting}>
            {t('settings.api_tokens.revoke.cancel')}
          </Button>
          <Button onClick={handleRevoke} disabled={!canSubmit} variant="destructive">
            {submitting
              ? t('settings.api_tokens.revoke.submitting')
              : t('settings.api_tokens.revoke.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
