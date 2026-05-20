/**
 * RBAC-P5-014 (#704) — wire shape for `/api/sso/providers`. Mirrors
 * {@link SsoProviderResponseBuilder} on the API side.
 */

export type SsoKind = 'google_workspace' | 'microsoft_365' | 'saml';

export interface SsoProvider {
  id: string;
  kind: SsoKind;
  name: string;
  enabled: boolean;
  /**
   * Free-form JSON config — shape varies by `kind`. Secrets land here
   * masked as `'****'` on read; the FE round-trips that mask back on
   * PATCH to keep the existing value (backend merges the mask).
   */
  config: Record<string, unknown>;
  created_at: string;
  updated_at: string | null;
}

export const SSO_KINDS: readonly SsoKind[] = ['google_workspace', 'microsoft_365', 'saml'];
