import { RoleEditorPage } from './RoleEditorPage';
import { RolesListView } from './RolesListView';

/**
 * RBAC-P5-005 (#695) — entry point for `/settings/roles`. The view
 * itself lives next to its helpers (RoleTypeBadge) so #696/#697 can
 * extend without restructuring this module.
 */
export function RolesSettingsPage() {
  return <RolesListView />;
}

/**
 * RBAC-P5-006 (#696) — entry point for both `/settings/roles/new`
 * (create) and `/settings/roles/:id/edit` (edit) since the editor
 * page handles the mode switch from `useParams().id`.
 */
export function RolesEditorRoute() {
  return <RoleEditorPage />;
}
