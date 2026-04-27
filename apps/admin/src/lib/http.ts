/**
 * Single-origin fetch wrapper that attaches the stored JWT to every request and
 * surfaces auth failures as a typed exception so the AuthProvider can react.
 *
 * The admin always talks to /api/* on the same Caddy origin (CLAUDE.md →
 * "Single-origin przez Caddy w FrankenPHP — TYLKO TAK"). No CORS, no separate
 * base URL configuration in the browser.
 */

const TOKEN_STORAGE_KEY = 'pim.jwt';

export class HttpError extends Error {
  readonly status: number;
  readonly body: unknown;

  constructor(status: number, body: unknown) {
    super(`HTTP ${status}`);
    this.name = 'HttpError';
    this.status = status;
    this.body = body;
  }
}

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_STORAGE_KEY);
}

export function setStoredToken(token: string): void {
  localStorage.setItem(TOKEN_STORAGE_KEY, token);
}

export function clearStoredToken(): void {
  localStorage.removeItem(TOKEN_STORAGE_KEY);
}

export interface JsonRequestInit {
  method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
  body?: unknown;
  contentType?: string;
  accept?: string;
  query?: Record<string, string | number | undefined>;
}

export async function jsonFetch<T = unknown>(path: string, init: JsonRequestInit = {}): Promise<T> {
  const url = init.query ? appendQuery(path, init.query) : path;
  const headers = new Headers();
  headers.set('accept', init.accept ?? 'application/ld+json');

  const token = getStoredToken();
  if (token) {
    headers.set('authorization', `Bearer ${token}`);
  }

  let body: BodyInit | undefined;
  if (init.body !== undefined) {
    headers.set('content-type', init.contentType ?? 'application/ld+json');
    body = JSON.stringify(init.body);
  }

  const response = await fetch(url, {
    method: init.method ?? 'GET',
    headers,
    body,
    credentials: 'same-origin',
  });

  // 204 No Content: empty body, return as-is.
  if (response.status === 204) {
    return undefined as T;
  }

  const text = await response.text();
  const parsed = text ? safeJsonParse(text) : undefined;

  if (!response.ok) {
    throw new HttpError(response.status, parsed);
  }

  return parsed as T;
}

function appendQuery(path: string, query: Record<string, string | number | undefined>): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined) continue;
    params.set(key, String(value));
  }
  const qs = params.toString();
  if (!qs) return path;
  return path.includes('?') ? `${path}&${qs}` : `${path}?${qs}`;
}

function safeJsonParse(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}
