import { ShieldAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

interface LastAdminProtectionModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * Label shown in the message, e.g. "Anna Kowalska". Passed by the
   * caller because the protection trigger ships from different flows
   * (delete user, deactivate user, change role) — each of which has the
   * subject ready as a domain object.
   */
  userLabel: string;
}

/**
 * RBAC-P5-018 (#708) — modal warning surfaced when the operator tries to
 * delete / deactivate / change-role the last user holding the
 * Administrator role on the tenant.
 *
 * The 409 response from the backend voter (RBAC-P3-005) is what guards
 * the actual mutation. This modal is the UX-side speed bump: it explains
 * what to do (assign Administrator to another user first) so the
 * operator does not have to read the API error to recover. The component
 * is wired by #694 (deactivate user) and #693 (edit user role) — for
 * #708 we only ship the reusable visual.
 */
export function LastAdminProtectionModal({
  open,
  onOpenChange,
  userLabel,
}: LastAdminProtectionModalProps) {
  const { t } = useTranslation();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-rose-100 text-rose-700">
            <ShieldAlert className="size-5" aria-hidden="true" />
          </div>
          <DialogTitle>{t('rbac.last_admin.title')}</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">
          {t('rbac.last_admin.body', { user: userLabel })}
        </p>
        <p className="text-sm text-muted-foreground">{t('rbac.last_admin.guidance')}</p>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('rbac.last_admin.close')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
