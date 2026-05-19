import { UsersListView } from './UsersListView';

/**
 * RBAC-P5-001 (#691) — entry point for `/settings/users`. The view itself
 * lives next to its helpers (StatusBadge, UserAvatar) so follow-ups
 * (#692–#694) can drop in without restructuring this index module.
 */
export function UsersSettingsPage() {
  return <UsersListView />;
}
