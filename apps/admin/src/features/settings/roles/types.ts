/**
 * RBAC-P5-005 (#695) — wire shape for the Settings → Roles list.
 * Mirrors {@link RoleListResponseBuilder} on the API side. Lives here
 * so the contract stays alongside the consumer when the role builder
 * (#696) extends the projection.
 */

export type RoleListType = 'system' | 'custom';

/**
 * UI re-align (#865) — scope distinction surfaced in the §5.3 mockup.
 * Backend hasn't exposed this column yet (introduced in delta Backend
 * follow-up); meanwhile the frontend resolves it from `role.code` via
 * {@link import('./scope').resolveRoleScope}.
 */
export type RoleScope = 'platform' | 'tenant';

export interface RoleListItem {
  id: string;
  code: string;
  name: string;
  type: RoleListType;
  user_count: number;
  is_built_in: boolean;
  created_at: string;
  permissions_count: number;
  /** Optional — backend exposes after #865 backend extension. */
  scope?: RoleScope;
  /** Optional — short operator description (RoleDetail.description) when included. */
  description?: string | null;
  /** Optional — persona string (`Tomasz · właściciel firmy`) once backend ships it. */
  persona?: string | null;
  /** When true, only one assignment is allowed (Tenant Owner). */
  is_unique?: boolean;
  /** When true, MFA is mandated for assignees of this role. */
  mfa_required?: boolean;
  /** Auto-grant flag (#698) — also present on RoleDetail. */
  auto_grant_new_object_types?: boolean;
  /**
   * Optional per-module permission coverage map. Each entry: { covered, total, pct }
   * keyed by module code (`platform / produkty / kategorie / multimedia / ...`).
   * Exposed by backend after the §5.3 coverage strip ships server-side.
   */
  permission_coverage?: Record<string, { covered: number; total: number; pct: number }>;
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
  /**
   * Multi-line operator note explaining why the role exists (PRD §5.3).
   * NULL on seeded system roles created before the column was added.
   */
  description: string | null;
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
  /**
   * UI re-align (#865) — optional flags backend exposes after the
   * delta-Backend follow-up. Frontend renders them when present and
   * degrades gracefully when omitted (e.g. RoleEditorPage header
   * `unique · max 1` badge).
   */
  is_unique?: boolean;
  mfa_required?: boolean;
  scope?: RoleScope;
  persona?: string | null;
  created_at: string;
}
