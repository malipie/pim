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
  created_at: string;
}
