/**
 * RBAC-P5-005 (#695) — wire shape for the Settings → Roles list.
 * Mirrors {@link RoleListResponseBuilder} on the API side. Lives here
 * so the contract stays alongside the consumer when the role builder
 * (#696) extends the projection.
 */

export type RoleListType = 'system' | 'custom';

export interface RoleListItem {
  id: string;
  code: string;
  name: string;
  type: RoleListType;
  user_count: number;
  is_built_in: boolean;
  created_at: string;
  permissions_count: number;
}

/**
 * RBAC-P5-006 (#696) — wire shape for `GET /api/roles/{id}` consumed
 * by the custom-role builder editor. Mirrors
 * {@link RoleDetailResponseBuilder} on the API side.
 */
export interface RoleDetail {
  id: string;
  code: string;
  name: string;
  type: RoleListType;
  is_built_in: boolean;
  tenant_id: string | null;
  permission_codes: string[];
  /**
   * RBAC-P5-008 (#698) — when true, new ObjectTypes (epik 0.4) get the
   * `view + edit` permissions auto-granted to this role on creation.
   * Existing roles default to `false`; only the new-style POST/PATCH
   * /api/roles surfaces an editor.
   */
  auto_grant_new_object_types: boolean;
  created_at: string;
}
