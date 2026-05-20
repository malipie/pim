import { useGetIdentity, useList } from '@refinedev/core';
import { ShieldCheck, UserCog } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/ui/toast';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

import type { RoleListItem } from '../roles/types';
import type { UserListItem } from './types';

interface EditUserModalProps {
  user: UserListItem | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

interface Identity {
  id: string;
  name: string;
  email: string;
  roles: string[];
  tenant: { id: string; code: string; name: string } | null;
  lastLoginAt: string | null;
}

/**
 * RBAC-P5-003 (#693) — edit-user modal.
 *
 * Renders a multi-select role list pre-checked with the user's current
 * assignments and PATCHes `/api/users/{id}` with the chosen `role_ids`
 * on submit. Self-edit of role assignments is hidden behind an inline
 * notice — the backend refuses self-edits with 409 `code: "self_edit"`
 * as defence in depth, but the UI shortens the round-trip.
 *
 * Profile fields (display name, avatar) are deferred per ticket body
 * because the User entity has no first_name/last_name columns yet —
 * `display_name` is still derived server-side from the email local-part.
 */
export function EditUserModal({ user, open, onOpenChange, onSuccess }: EditUserModalProps) {
  const { t } = useTranslation();
  const { data: identity } = useGetIdentity<Identity>();
  const { result: rolesResult } = useList<RoleListItem>({
    resource: 'roles',
    pagination: { mode: 'off' },
  });
  const roles: RoleListItem[] = useMemo(() => rolesResult?.data ?? [], [rolesResult?.data]);

  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [submitting, setSubmitting] = useState(false);

  // Seed the selection from the current assignments whenever the modal
  // (re)opens with a new target user. Without this, a previously edited
  // user would carry stale state into the next row's modal.
  useEffect(() => {
    if (user && open) {
      setSelectedIds(new Set(user.roles.map((r) => r.id)));
    }
  }, [user, open]);

  const isSelf = Boolean(user && identity && user.id === identity.id);

  const toggleRole = (id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const close = (next: boolean) => {
    if (!next) {
      setSubmitting(false);
    }
    onOpenChange(next);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!user || submitting || isSelf) return;
    setSubmitting(true);
    try {
      await jsonFetch(`/api/users/${user.id}`, {
        method: 'PATCH',
        body: { role_ids: Array.from(selectedIds) },
        accept: 'application/json',
        contentType: 'application/json',
      });
      toast.success(t('settings.users.edit.toast_success', { name: user.display_name }));
      onSuccess();
      close(false);
    } catch (error: unknown) {
      const status = (error as { status?: number; body?: { code?: string; detail?: string } })
        ?.status;
      const body = (error as { body?: { code?: string; detail?: string } })?.body;
      if (status === 409 && body?.code === 'last_admin') {
        toast.error(body?.detail ?? t('settings.users.edit.error_last_admin'));
      } else if (status === 409 && body?.code === 'self_edit') {
        toast.error(t('settings.users.edit.error_self_edit'));
      } else if (status === 403) {
        toast.error(t('settings.users.edit.error_forbidden'));
      } else if (status === 400) {
        toast.error(body?.detail ?? t('settings.users.edit.error_validation'));
      } else {
        toast.error(t('settings.users.edit.error_generic'));
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent className="max-w-lg">
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-accent-violet/10 text-accent-violet">
              <UserCog className="size-5" aria-hidden="true" />
            </div>
            <DialogTitle>{t('settings.users.edit.title')}</DialogTitle>
          </DialogHeader>

          {user ? (
            <div className="rounded-md border bg-muted/40 px-3 py-2 text-xs">
              <div className="font-medium text-foreground">{user.display_name}</div>
              <div className="text-muted-foreground">{user.email}</div>
            </div>
          ) : null}

          <div className="space-y-3 py-3">
            <Label>{t('settings.users.edit.section_roles')}</Label>
            {isSelf ? (
              <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {t('settings.users.edit.self_edit_notice')}
              </div>
            ) : (
              <div className="space-y-1.5">
                {roles.length === 0 ? (
                  <p className="text-xs text-muted-foreground">
                    {t('settings.users.edit.roles_loading')}
                  </p>
                ) : (
                  roles.map((role) => {
                    const checked = selectedIds.has(role.id);
                    return (
                      <label
                        key={role.id}
                        className={cn(
                          'flex cursor-pointer items-start gap-3 rounded-md border bg-background px-3 py-2 text-sm transition-colors',
                          checked
                            ? 'border-accent-violet/40 ring-1 ring-accent-violet/30'
                            : 'hover:bg-muted/40',
                        )}
                      >
                        <input
                          type="checkbox"
                          className="mt-0.5"
                          checked={checked}
                          onChange={() => toggleRole(role.id)}
                          aria-label={role.name}
                        />
                        <div className="flex-1 space-y-0.5">
                          <div className="flex items-center gap-1.5">
                            <ShieldCheck
                              className="size-3.5 text-accent-violet"
                              aria-hidden="true"
                            />
                            <span className="font-medium">{role.name}</span>
                            {role.type === 'system' ? (
                              <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                {t('settings.users.edit.role_system')}
                              </span>
                            ) : null}
                          </div>
                          <p className="text-[11px] text-muted-foreground">{role.code}</p>
                        </div>
                      </label>
                    );
                  })
                )}
              </div>
            )}
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => close(false)}
              disabled={submitting}
            >
              {t('settings.users.edit.cancel')}
            </Button>
            <Button type="submit" disabled={submitting || isSelf || !user}>
              {submitting ? t('settings.users.edit.submitting') : t('settings.users.edit.submit')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
