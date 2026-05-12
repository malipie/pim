import { expect, type Page } from '@playwright/test';

export const ADMIN_EMAIL = 'admin@demo.localhost';
export const ADMIN_PASSWORD = 'changeme';

const MAX_LOGIN_ATTEMPTS = 5;
const RATE_LIMIT_BACKOFF_MS = 2_000;
const LOGIN_RESPONSE_TIMEOUT_MS = 10_000;

/**
 * Log in through the actual /login form and wait for the dashboard (new index
 * after epik UI-03 #356) to render. Single happy-path login helper used by
 * every protected-route test.
 *
 * HARD-09 — modeling-shell flake root cause: when this helper runs late in
 * the Playwright suite the dev `auth_login` rate-limit (5/IP/15min) had
 * already been spent by earlier specs. The submit POST returned 429, the
 * authProvider catch block silently emitted `success: false`, the form
 * stayed on /login with a generic error toast, and the assertion
 * `expect(page).toHaveURL(/\/dashboard$/)` failed with the diagnostic
 * "received string `https://pim.localhost/login`" — exactly the error in
 * the modeling-shell flake reports. The previous helper had no visibility
 * into the response status; if a 429 happened it just timed out without
 * surfacing the cause.
 *
 * The new flow watches the POST /api/auth/login response directly:
 *   - 200 → assert /dashboard URL and return,
 *   - 429 → exponential backoff + retry up to MAX_LOGIN_ATTEMPTS,
 *   - any other status → throw with the actual code so the next debugger
 *     does not chase ghosts.
 *
 * For specs that do not need to exercise the login UX itself, prefer
 * {@link apiLogin} (HARD-10) — it skips the form interaction and saves
 * roughly 1.5 seconds per spec, plus dodges the React render flakiness
 * that comes with form submit + redirect.
 */
export async function loginAsAdmin(
  page: Page,
  email: string = ADMIN_EMAIL,
  password: string = ADMIN_PASSWORD,
): Promise<void> {
  await page.goto('/login');
  await page.getByLabel(/e-?mail/i).fill(email);
  await page.getByLabel(/has[lł]o|password/i).fill(password);

  for (let attempt = 0; attempt < MAX_LOGIN_ATTEMPTS; attempt += 1) {
    const responsePromise = page.waitForResponse(
      (response) =>
        response.url().includes('/api/auth/login') && response.request().method() === 'POST',
      { timeout: LOGIN_RESPONSE_TIMEOUT_MS },
    );
    await page.getByRole('button', { name: /zaloguj|sign in/i }).click();

    const response = await responsePromise;
    const status = response.status();

    if (status === 200) {
      await expect(page).toHaveURL(/\/dashboard$/);
      return;
    }

    if (status === 429) {
      // Rate-limited. Retry-After is sometimes absent on dev — back off
      // an exponentially-growing window and try again.
      const backoff = RATE_LIMIT_BACKOFF_MS * (attempt + 1);
      await page.waitForTimeout(backoff);
      continue;
    }

    throw new Error(`Login failed with HTTP ${status} (attempt ${attempt + 1}).`);
  }

  throw new Error(
    `Login still rate-limited after ${MAX_LOGIN_ATTEMPTS} attempts — bump the dev override or migrate this spec to apiLogin.`,
  );
}

/**
 * HARD-10 — fast path login via API request (no form interaction).
 *
 * Form-based {@link loginAsAdmin} waits for React render + form submit +
 * redirect → adds ~1.5 seconds per spec on a warm cache, more on cold.
 * The API call is a single round-trip; the refresh-token cookie lands in
 * the browser context exactly as it would after a form submit, so the
 * subsequent `page.goto('/dashboard')` runs the same auth check pipeline
 * as a real user (AuthedRoute → check() → refresh() with cookie → JWT in
 * memory).
 *
 * Same retry-on-429 strategy as `loginAsAdmin` so the helper survives
 * accumulated rate-limit buckets without operator intervention.
 *
 * Use for specs that need authenticated state but do NOT exercise the
 * login UX itself. `auth.spec.ts` keeps the form path because it
 * explicitly asserts on form behaviour.
 */
export async function apiLogin(
  page: Page,
  email: string = ADMIN_EMAIL,
  password: string = ADMIN_PASSWORD,
): Promise<void> {
  for (let attempt = 0; attempt < MAX_LOGIN_ATTEMPTS; attempt += 1) {
    const response = await page.request.post('/api/auth/login', {
      data: { email, password },
      headers: { accept: 'application/json' },
    });
    const status = response.status();

    if (status === 200) {
      // Cookie is now in the browser context. Land on /dashboard so
      // AuthedRoute runs check() → refresh() → JWT installs in
      // module-scope memory, identical to the real user flow.
      await page.goto('/dashboard');
      await expect(page).toHaveURL(/\/dashboard$/);
      return;
    }

    if (status === 429) {
      const backoff = RATE_LIMIT_BACKOFF_MS * (attempt + 1);
      await page.waitForTimeout(backoff);
      continue;
    }

    throw new Error(`apiLogin failed with HTTP ${status} (attempt ${attempt + 1}).`);
  }

  throw new Error(`apiLogin still rate-limited after ${MAX_LOGIN_ATTEMPTS} attempts.`);
}

/**
 * Random SKU — the API enforces a unique (tenant_id, sku) constraint and the
 * dev DB is not reset between runs locally; suffix with timestamp + random
 * so successive runs of the create-product test never collide.
 */
export function uniqueSku(prefix = 'E2E'): string {
  const stamp = Date.now().toString(36).toUpperCase();
  const random = Math.floor(Math.random() * 1000)
    .toString()
    .padStart(3, '0');
  return `${prefix}-${stamp}-${random}`;
}
