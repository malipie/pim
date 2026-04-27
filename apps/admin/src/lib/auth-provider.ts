import type { AuthProvider } from '@refinedev/core';

import { clearStoredToken, getStoredToken, HttpError, jsonFetch, setStoredToken } from './http';

interface LoginPayload {
  email: string;
  password: string;
}

interface LoginResponse {
  token: string;
}

/**
 * Refine AuthProvider wiring `/api/auth/login` (LexikJWT) into the admin.
 *
 * The token is persisted in localStorage for now — ticket 0.0.5 calls this out
 * as the Sprint-0 shortcut; full httpOnly cookie + refresh token rotation lands
 * in 0.2.6 (#28). The `check` method is consulted by Refine on every protected
 * route to decide whether to let the user through.
 */
export const authProvider: AuthProvider = {
  async login(payload) {
    const { email, password } = payload as LoginPayload;
    try {
      const response = await jsonFetch<LoginResponse>('/api/auth/login', {
        method: 'POST',
        body: { email, password },
        contentType: 'application/json',
        accept: 'application/json',
      });
      setStoredToken(response.token);
      return { success: true, redirectTo: '/products' };
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
    clearStoredToken();
    return { success: true, redirectTo: '/login' };
  },

  async check() {
    const token = getStoredToken();
    if (!token) {
      return { authenticated: false, redirectTo: '/login' };
    }
    return { authenticated: true };
  },

  async onError(error) {
    if (error instanceof HttpError && error.status === 401) {
      clearStoredToken();
      return { logout: true, redirectTo: '/login' };
    }
    return {};
  },

  async getIdentity() {
    const token = getStoredToken();
    if (!token) return null;
    const claims = decodeJwtClaims(token);
    return {
      id: claims?.username ?? 'unknown',
      name: claims?.username ?? 'unknown',
      roles: claims?.roles ?? [],
    };
  },

  async getPermissions() {
    const token = getStoredToken();
    return decodeJwtClaims(token)?.roles ?? [];
  },
};

interface JwtClaims {
  username?: string;
  roles?: string[];
  exp?: number;
  iat?: number;
}

function decodeJwtClaims(token: string | null): JwtClaims | null {
  if (!token) return null;
  const segments = token.split('.');
  if (segments.length !== 3) return null;
  const payload = segments[1];
  try {
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
    return JSON.parse(json) as JwtClaims;
  } catch {
    return null;
  }
}
