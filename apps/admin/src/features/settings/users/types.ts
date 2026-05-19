/**
 * RBAC-P5-001 (#691) — wire shape for the Settings → Users list. Mirrors
 * the projection produced by {@link UserListResponseBuilder} on the API
 * side. Lives next to the page that consumes it so the contract is easy
 * to audit when the backend evolves.
 */

export interface UserRoleRef {
  id: string;
  code: string;
  name: string;
}

export type UserStatus = 'active' | 'disabled';

export interface UserListItem {
  id: string;
  email: string;
  display_name: string;
  avatar_initial: string;
  status: UserStatus;
  roles: UserRoleRef[];
  last_login_at: string | null;
  mfa_enabled: boolean;
  created_at: string;
}
