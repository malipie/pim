import { RolesListView } from './RolesListView';

/**
 * RBAC-P5-005 (#695) — entry point for `/settings/roles`. The view
 * itself lives next to its helpers (RoleTypeBadge) so #696/#697 can
 * extend without restructuring this module.
 */
export function RolesSettingsPage() {
  return <RolesListView />;
}
