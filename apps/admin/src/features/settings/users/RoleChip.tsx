import { resolveRoleColor } from '@/features/settings/roles/colors';
import { cn } from '@/lib/utils';

export interface RoleChipProps {
  code: string;
  name: string;
  size?: 'sm' | 'md';
}

/**
 * Colored role chip matching `Zrodla/.../settings/users.jsx` §RoleChip.
 * Color identity is resolved from the role's `code` field via
 * {@link resolveRoleColor} — custom roles fall through to pink.
 */
export function RoleChip({ code, name, size = 'md' }: RoleChipProps) {
  const color = resolveRoleColor(code);
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-md font-medium ring-1',
        size === 'sm' ? 'px-1.5 py-0.5 text-[10.5px]' : 'px-2 py-0.5 text-[11px]',
        color.chip,
      )}
    >
      <span className={cn('size-1.5 rounded-full', color.dot)} aria-hidden />
      {name}
    </span>
  );
}
