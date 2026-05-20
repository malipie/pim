/**
 * RBAC-P5-001 (#691) + UI polish #848 — wire shape for the Settings →
 * Users list. Mirrors {@link UserListResponseBuilder} on the API side.
 *
 * Pending invitations surface as `kind: 'invitation'` rows so the
 * §5.4 mockup's three statuses (Active / Invited / Deactivated) all
 * render from one list. The `invitation_*` fields are populated for
 * invitation rows and null on user rows.
 */

export interface UserRoleRef {
  id: string;
  code: string;
  name: string;
}

export type UserKind = 'user' | 'invitation';
export type UserStatus = 'active' | 'disabled' | 'invited';

export interface UserListItem {
  id: string;
  kind: UserKind;
  email: string;
  display_name: string;
  avatar_initial: string;
  status: UserStatus;
  roles: UserRoleRef[];
  last_login_at: string | null;
  mfa_enabled: boolean;
  created_at: string;
  /** Invitation uuid when `kind === 'invitation'`, else null. */
  invitation_id: string | null;
  /** ATOM timestamp of invitation expiry when applicable. */
  invitation_expires_at: string | null;
}
