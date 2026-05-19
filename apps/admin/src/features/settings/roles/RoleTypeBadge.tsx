import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

import type { RoleListType } from './types';

interface RoleTypeBadgeProps {
  type: RoleListType;
}

/**
 * RBAC-P5-005 (#695) — System / Custom badge for the Settings → Roles
 * table. Visual style mirrors the status pill from the Users list so
 * the Settings surface stays coherent across pages.
 */
export function RoleTypeBadge({ type }: RoleTypeBadgeProps) {
  const { t } = useTranslation();

  const variant: Record<RoleListType, { cls: string; labelKey: string }> = {
    system: {
      cls: 'bg-violet-50 text-violet-700 ring-violet-200',
      labelKey: 'settings.roles.type.system',
    },
    custom: {
      cls: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
      labelKey: 'settings.roles.type.custom',
    },
  };

  const { cls, labelKey } = variant[type];

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide ring-1',
        cls,
      )}
    >
      {t(labelKey)}
    </span>
  );
}
