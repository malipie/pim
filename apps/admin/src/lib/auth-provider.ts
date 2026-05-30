import type { AuthProvider } from '@refinedev/core';

import {
  clearAccessToken,
  getAccessToken,
  HttpError,
  jsonFetch,
  refreshAccessToken,
  setAccessToken,
} from './http';

interface LoginPayload {
  email: string;
  password: string;
}

interface LoginResponse {
  token: string;
}

/** #1141 — shape returned by /api/auth/login when the account has active MFA. */
interface MfaChallenge {
  mfa_required?: boolean;
  mfa_token?: string;
}

interface MeResponse {
  id: string;
  email: string;
  roles: string[];
  tenant: { id: string; code: string; name: string } | null;
  last_login_at: string | null;
}

export interface MeIdentity {
  id: string;
  /** Alias of {@link email} so existing `Identity { name }` consumers keep working. */
  name: string;
  email: string;
  roles: string[];
  tenant: { id: string; code: string; name: string } | null;
  lastLoginAt: string | null;
}

/**
 * Refine AuthProvider after #29:
 *
 *   - Login stores the access JWT in module-scoped memory only (`http.ts`).
 *     A successful login response also installs the rotating HttpOnly refresh
 *     cookie at /api/auth, which lets `check()` resurrect a session across a
 *     hard reload via silent refresh.
 *   - Logout actually calls POST /api/auth/logout so the server can revoke
 *     the refresh token + clear the cookie. Failures are swallowed because
 *     the user wanted out either way.
 *   - getIdentity hits GET /api/auth/me. We no longer decode the JWT in the
 *     browser — the server is the source of truth for roles + tenant.
 *   - onError stays as the second-line fallback. The http layer already
 *     retried the original request once after a silent refresh; if a 401
 *     still bubbles out, the session is genuinely dead.
 */
export const authProvider: AuthProvider = {
  async login(payload) {
    const { email, password, mfaToken, code } = payload as LoginPayload & {
      mfaToken?: string;
      code?: string;
    };

    // Second factor (#1141): redeem the challenge token minted after the
    // password step for the real JWT. A wrong/expired code surfaces as a
    // recoverable error so the UI can let the user retry.
    if (typeof mfaToken === 'string' && mfaToken !== '') {
      try {
        const response = await jsonFetch<LoginResponse>('/api/auth/2fa/login', {
          method: 'POST',
          body: { mfa_token: mfaToken, code },
          contentType: 'application/json',
          accept: 'application/json',
        });
        setAccessToken(response.token);
        return { success: true, redirectTo: '/dashboard' };
      } catch {
        return { success: false, error: { name: 'MfaError', message: 'auth.mfa_invalid_code' } };
      }
    }

    // Password step.
    try {
      const response = await jsonFetch<Partial<LoginResponse> & MfaChallenge>('/api/auth/login', {
        method: 'POST',
        body: { email, password },
        contentType: 'application/json',
        accept: 'application/json',
      });
      if (response.mfa_required === true && typeof response.mfa_token === 'string') {
        // Password accepted, but the account requires a second factor. Surface
        // the challenge token so the UI can collect the TOTP / backup code.
        return {
          success: false,
          error: { name: 'MfaRequired', message: 'auth.mfa_required' },
          mfaRequired: true,
          mfaToken: response.mfa_token,
        };
      }
      if (typeof response.token === 'string') {
        setAccessToken(response.token);
        return { success: true, redirectTo: '/dashboard' };
      }
      return { success: false, error: { name: 'LoginError', message: 'auth.login_failed' } };
    } catch (error) {
      const message =
        error instanceof HttpError && error.status === 401
          ? 'auth.invalid_credentials'
          : 'auth.login_failed';
      return {
        success: false,
        error: { name: 'LoginError', message },
      };
    }
  },

  async logout() {
    // Best-effort: tell the backend to revoke + clear the refresh cookie.
    // If this 401s (access token already gone) the cookie still gets cleared
    // server-side via Set-Cookie on the response; if the request itself fails
    // we don't want to block the client-side logout regardless.
    try {
      await jsonFetch('/api/auth/logout', { method: 'POST', accept: 'application/json' });
    } catch {
      // intentional: logout is idempotent and must always succeed client-side.
    }
    clearAccessToken();
    return { success: true, redirectTo: '/login' };
  },

  async check() {
    if (getAccessToken()) {
      return { authenticated: true };
    }
    // Page reload: token is gone from memory but the refresh cookie may still
    // be valid. Try once; if it fails, the user is genuinely signed out.
    try {
      await refreshAccessToken();
      return { authenticated: true };
    } catch {
      return { authenticated: false, redirectTo: '/login' };
    }
  },

  async onError(error) {
    if (error instanceof HttpError && error.status === 401) {
      clearAccessToken();
      return { logout: true, redirectTo: '/login' };
    }
    return {};
  },

  async getIdentity(): Promise<MeIdentity | null> {
    try {
      const me = await jsonFetch<MeResponse>('/api/auth/me', { accept: 'application/json' });
      return {
        id: me.id,
        name: me.email,
        email: me.email,
        roles: me.roles,
        tenant: me.tenant,
        lastLoginAt: me.last_login_at,
      };
    } catch {
      return null;
    }
  },

  async getPermissions() {
    try {
      const me = await jsonFetch<MeResponse>('/api/auth/me', { accept: 'application/json' });
      return me.roles;
    } catch {
      return [];
    }
  },
};
