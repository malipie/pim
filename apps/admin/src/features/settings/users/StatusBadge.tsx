import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import type { UserStatus } from './types';

interface StatusBadgeProps {
  status: UserStatus;
}

/**
 * RBAC-P5-001 (#691) — colour-coded status pill for the Users list.
 *
 * Today the backend exposes only `active` / `disabled` (User entity STATUS_*
 * constants); follow-ups will derive `invited` and `pending` from the
 * Invitation table (#692) and surface them through this same component
 * so the visual taxonomy stays in one file.
 */
export function StatusBadge({ status }: StatusBadgeProps) {
  const { t } = useTranslation();

  const variant: Record<UserStatus, { cls: string; dot: string; labelKey: string }> = {
    active: {
      cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
      dot: 'bg-emerald-500',
      labelKey: 'settings.users.status.active',
    },
    invited: {
      cls: 'bg-amber-50 text-amber-700 ring-amber-200',
      dot: 'bg-amber-500',
      labelKey: 'settings.users.status.invited',
    },
    disabled: {
      cls: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
      dot: 'bg-zinc-400',
      labelKey: 'settings.users.status.disabled',
    },
  };

  const { cls, dot, labelKey } = variant[status];

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-medium ring-1',
        cls,
      )}
    >
      <span className={cn('h-1.5 w-1.5 rounded-full', dot)} aria-hidden="true" />
      {t(labelKey)}
    </span>
  );
}
