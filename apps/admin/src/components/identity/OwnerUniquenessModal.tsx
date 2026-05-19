import { Crown } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

interface OwnerUniquenessModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /**
   * Display name / email of the existing tenant Owner. Lets the modal
   * tell the operator who currently holds the role so they can target
   * the ownership transfer flow at the right user.
   */
  currentOwnerLabel?: string;
}

/**
 * RBAC-P5-018 (#708) — modal surfaced when the operator tries to assign
 * the Owner role to a second user while it's already held by someone
 * else.
 *
 * Backend voter (RBAC-P3-005) returns a 409 from such an attempt; this
 * modal explains the constraint (single Owner per tenant) and suggests
 * the transfer-ownership flow as the recovery path. The transfer flow
 * itself ships later — for #708 we only commit the visual + i18n so
 * #693 (edit user role) can drop it in.
 */
export function OwnerUniquenessModal({
  open,
  onOpenChange,
  currentOwnerLabel,
}: OwnerUniquenessModalProps) {
  const { t } = useTranslation();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <div className="mb-2 inline-grid size-10 place-items-center rounded-full bg-amber-100 text-amber-700">
            <Crown className="size-5" aria-hidden="true" />
          </div>
          <DialogTitle>{t('rbac.owner_uniqueness.title')}</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">
          {currentOwnerLabel
            ? t('rbac.owner_uniqueness.body_with_owner', { owner: currentOwnerLabel })
            : t('rbac.owner_uniqueness.body')}
        </p>
        <p className="text-sm text-muted-foreground">{t('rbac.owner_uniqueness.guidance')}</p>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('rbac.owner_uniqueness.close')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
