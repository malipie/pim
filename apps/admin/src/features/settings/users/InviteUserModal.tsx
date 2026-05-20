import { useList } from '@refinedev/core';
import { Copy, Mail, UserPlus } from 'lucide-react';
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

import type { RoleListItem } from '../roles/types';

interface InviteUserModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

interface InvitationResponse {
  invitation_id: string;
  email: string;
  expires_at: string;
  token_dev_only?: string;
}

/**
 * RBAC-P5-002 (#692) — invite-user modal that POSTs to the existing
 * `/api/invitations` endpoint (Phase 2 #657).
 *
 * Form scope (intentionally minimal for #692 MVP):
 *   - email (required) — backend rejects duplicates with 400/409
 *   - role_code (required) — populated by the `/api/roles` resource,
 *     single-select today; multi-role assignment requires a backend
 *     schema change (deferred to #693 edit user since the invitation
 *     entity persists exactly one role_id per row).
 *
 * Success state surfaces the dev-mode `token_dev_only` field so the
 * operator can complete the accept flow without waiting for the
 * production mailer; the field is removed once Mailer infra is wired
 * for the demo tenant.
 */
export function InviteUserModal({ open, onOpenChange, onSuccess }: InviteUserModalProps) {
  const { t } = useTranslation();
  const [email, setEmail] = useState('');
  const [roleCode, setRoleCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState<InvitationResponse | null>(null);

  const { result: rolesResult } = useList<RoleListItem>({
    resource: 'roles',
    pagination: { mode: 'off' },
  });
  const roles: RoleListItem[] = rolesResult?.data ?? [];

  const reset = () => {
    setEmail('');
    setRoleCode('');
    setResult(null);
    setSubmitting(false);
  };

  const close = (next: boolean) => {
    if (!next) {
      reset();
    }
    onOpenChange(next);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (submitting || !email || !roleCode) return;
    setSubmitting(true);
    try {
      const response = await jsonFetch<InvitationResponse>('/api/invitations', {
        method: 'POST',
        body: { email: email.trim(), role_code: roleCode },
        accept: 'application/json',
        contentType: 'application/json',
      });
      setResult(response);
      onSuccess();
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: { detail?: string } })?.status;
      const body = (error as { body?: { detail?: string } })?.body;
      if (status === 400 || status === 409) {
        toast.error(body?.detail ?? t('settings.users.invite.error_generic'));
      } else if (status === 403) {
        toast.error(t('settings.users.invite.error_forbidden'));
      } else {
        toast.error(t('settings.users.invite.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  const copyToken = async () => {
    if (!result?.token_dev_only) return;
    await navigator.clipboard.writeText(result.token_dev_only);
    toast.success(t('settings.users.invite.token_copied'));
  };

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent className="max-w-md">
        {result ? (
          <>
            <DialogHeader>
              <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                <Mail className="size-5" aria-hidden="true" />
              </div>
              <DialogTitle>{t('settings.users.invite.success_title')}</DialogTitle>
            </DialogHeader>
            <p className="text-sm text-muted-foreground">
              {t('settings.users.invite.success_body', { email: result.email })}
            </p>
            {result.token_dev_only ? (
              <div className="space-y-2 rounded-md border border-dashed bg-muted/40 p-3">
                <Label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  {t('settings.users.invite.token_label')}
                </Label>
                <div className="flex items-center gap-2">
                  <code className="flex-1 truncate rounded bg-background px-2 py-1.5 font-mono text-[11px]">
                    {result.token_dev_only}
                  </code>
                  <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    onClick={copyToken}
                    aria-label={t('settings.users.invite.copy_token')}
                  >
                    <Copy className="size-4" />
                  </Button>
                </div>
                <p className="text-[11px] text-muted-foreground">
                  {t('settings.users.invite.token_hint')}
                </p>
              </div>
            ) : null}
            <DialogFooter>
              <Button onClick={() => close(false)}>{t('settings.users.invite.close')}</Button>
            </DialogFooter>
          </>
        ) : (
          <form onSubmit={handleSubmit}>
            <DialogHeader>
              <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-accent-violet/10 text-accent-violet">
                <UserPlus className="size-5" aria-hidden="true" />
              </div>
              <DialogTitle>{t('settings.users.invite.title')}</DialogTitle>
            </DialogHeader>
            <p className="text-sm text-muted-foreground">{t('settings.users.invite.intro')}</p>

            <div className="space-y-3 py-3">
              <div className="space-y-1.5">
                <Label htmlFor="invite-email">{t('settings.users.invite.field_email')}</Label>
                <Input
                  id="invite-email"
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="user@example.com"
                  autoComplete="email"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="invite-role">{t('settings.users.invite.field_role')}</Label>
                <select
                  id="invite-role"
                  required
                  value={roleCode}
                  onChange={(e) => setRoleCode(e.target.value)}
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <option value="">{t('settings.users.invite.field_role_placeholder')}</option>
                  {roles.map((role) => (
                    <option key={role.id} value={role.code}>
                      {role.name}
                    </option>
                  ))}
                </select>
                <p className="text-[11px] text-muted-foreground">
                  {t('settings.users.invite.field_role_hint')}
                </p>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => close(false)}
                disabled={submitting}
              >
                {t('settings.users.invite.cancel')}
              </Button>
              <Button type="submit" disabled={submitting || !email || !roleCode}>
                {submitting
                  ? t('settings.users.invite.submitting')
                  : t('settings.users.invite.submit')}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}
