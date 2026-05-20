import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { LastAdminProtectionModal } from '@/components/identity';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';

import type { UserListItem } from './types';

interface DeactivateUserModalProps {
  user: UserListItem | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

/**
 * RBAC-P5-004 (#694) — confirm modal that wraps
 * `POST /api/users/{id}/deactivate`. The endpoint guards last-admin
 * removal (LastAdminGuard) and self-deactivation; a 409 with
 * `code === "last_admin"` surfaces the dedicated LastAdminProtectionModal
 * shipped in #708 instead of a generic toast.
 *
 * Reason textarea is optional UX scaffolding — the backend currently
 * does not persist the value (audit-log entry comes from the
 * AuditLogListener wired to kernel.response). It is captured client-side
 * so the operator can paste a justification into a support ticket if
 * needed; the actual audit_reason column lands with the audit-detail
 * follow-up.
 */
export function DeactivateUserModal({
  user,
  open,
  onOpenChange,
  onSuccess,
}: DeactivateUserModalProps) {
  const { t } = useTranslation();
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [lastAdminConflict, setLastAdminConflict] = useState(false);

  const handleConfirm = async () => {
    if (!user) return;
    setSubmitting(true);
    try {
      await jsonFetch(`/api/users/${user.id}/deactivate`, {
        method: 'POST',
        body: reason.trim().length > 0 ? { reason: reason.trim() } : {},
        accept: 'application/json',
        contentType: 'application/json',
      });
      toast.success(t('settings.users.deactivate.toast_success', { name: user.display_name }));
      onSuccess();
      onOpenChange(false);
      setReason('');
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: unknown })?.status;
      const body = (error as { body?: { code?: string; detail?: string } })?.body ?? {};
      if (status === 409 && body.code === 'last_admin') {
        setLastAdminConflict(true);
      } else if (status === 409) {
        toast.error(body.detail ?? t('settings.users.deactivate.error_generic'));
      } else {
        toast.error(t('settings.users.deactivate.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <>
      <Dialog open={open && !lastAdminConflict} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-rose-100 text-rose-700">
              <ShieldAlert className="size-5" aria-hidden="true" />
            </div>
            <DialogTitle>{t('settings.users.deactivate.title')}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            {t('settings.users.deactivate.body', {
              name: user?.display_name ?? '',
              email: user?.email ?? '',
            })}
          </p>
          <div className="space-y-1.5">
            <label
              htmlFor="deactivate-reason"
              className="text-xs font-medium text-muted-foreground"
            >
              {t('settings.users.deactivate.reason_label')}
            </label>
            <Textarea
              id="deactivate-reason"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={3}
              placeholder={t('settings.users.deactivate.reason_placeholder')}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => onOpenChange(false)} disabled={submitting}>
              {t('settings.users.deactivate.cancel')}
            </Button>
            <Button onClick={handleConfirm} disabled={submitting || !user}>
              {submitting
                ? t('settings.users.deactivate.submitting')
                : t('settings.users.deactivate.confirm')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <LastAdminProtectionModal
        open={lastAdminConflict}
        onOpenChange={(next) => {
          setLastAdminConflict(next);
          if (!next) {
            onOpenChange(false);
          }
        }}
        userLabel={user?.display_name ?? user?.email ?? ''}
      />
    </>
  );
}
