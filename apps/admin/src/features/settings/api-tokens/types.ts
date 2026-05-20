/**
 * RBAC-P5-009 (#699) — wire shape for the Settings → API tokens list.
 * Mirrors {@link ApiTokenListResponseBuilder} on the API side.
 */

export type ApiTokenStatus = 'active' | 'revoked' | 'expired';

export interface ApiTokenListItem {
  id: string;
  name: string;
  token_last4: string;
  scopes: string[];
  owner_id: string;
  owner_email: string | null;
  last_used_at: string | null;
  last_used_ip: string | null;
  expires_at: string | null;
  revoked_at: string | null;
  created_at: string;
  status: ApiTokenStatus;
}
