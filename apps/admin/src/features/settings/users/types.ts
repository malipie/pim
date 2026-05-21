/**
 * RBAC-P5-001 (#691) + UI polish #848 + UI re-align #865 — wire shape for
 * the Settings → Users list. Mirrors {@link UserListResponseBuilder} on the
 * API side.
 *
 * Pending invitations surface as `kind: 'invitation'` rows so the §5.4
 * mockup's three statuses (Active / Invited / Deactivated) all render
 * from one list. The `invitation_*` fields are populated for invitation
 * rows and null on user rows.
 *
 * Optional fields (`mfa_method`, `sso`, `scope_locale`, `scope_channel`,
 * `last_login_ip`, `last_login_country`, `is_you`, `platform_access`,
 * `deactivated_at`, `deactivated_by`, `roles[].code`, `roles[].mfa_required`)
 * are introduced by #865 — the frontend renders them when present and
 * degrades gracefully when the backend response omits them. Their wiring
 * lands together with the UserDetailPage backend hook-up.
 */

export type MfaMethod = 'app_totp' | 'email_totp';
export type SsoProvider = 'google' | 'microsoft' | 'saml';

export interface UserRoleRef {
  id: string;
  code: string;
  name: string;
  /** When true, MFA is mandated by this role (UserListView renders rose warning badge). */
  mfa_required?: boolean;
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
  /** IP address of the last successful login (when exposed by API). */
  last_login_ip?: string | null;
  /** ISO-3166 alpha-2 country code resolved from `last_login_ip` (when available). */
  last_login_country?: string | null;
  mfa_enabled: boolean;
  /** Specific TOTP method, only present when mfa_enabled is true. */
  mfa_method?: MfaMethod | null;
  /** Active SSO provider for this user, when authenticated via SSO. */
  sso?: SsoProvider | null;
  /** Per-user locale scope override (`["*"]` or empty == unrestricted). */
  scope_locale?: string[] | null;
  /** Per-user channel scope override (`["*"]` or empty == unrestricted). */
  scope_channel?: string[] | null;
  /** True when this row is the current viewer — UI shows "to ty" pill. */
  is_you?: boolean;
  /** True for Super Admins (cross-tenant operators) — shows "Platforma" pill. */
  platform_access?: boolean;
  /** Deactivation metadata — populated when `status === 'disabled'`. */
  deactivated_at?: string | null;
  deactivated_by?: string | null;
  created_at: string;
  /** Invitation uuid when `kind === 'invitation'`, else null. */
  invitation_id: string | null;
  /** ATOM timestamp of invitation expiry when applicable. */
  invitation_expires_at: string | null;
}
