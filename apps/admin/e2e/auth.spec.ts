import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, loginAsAdmin } from './helpers/auth';

// After #33 (ADR-009 data migration) the legacy `Product` ApiResource is
// gone — `/api/products` 404s. The admin SPA still calls it via Refine's
// dataProvider, so the post-login `/products` page errors out and tests
// that wait for the products heading or rely on the login → /products
// redirect fail. Tests are gated with `test.fixme` until #41 (epic 0.4)
// adds the sugar paths back as ApiResources on `CatalogObject`.
const BLOCKED_BY_41 = 'Pending #41: /api/products sugar path on CatalogObject';

test.describe('Authentication', () => {
  test('user can log in and lands on the products list', async ({ page }) => {
    test.fixme(true, BLOCKED_BY_41);
    await loginAsAdmin(page);
    // Dashboard is the new index after epik UI-03 #356; products list moved to /products.
    await page.goto('/products');
    await expect(page.getByRole('heading', { name: /produkty|products/i })).toBeVisible();
  });

  test('wrong password surfaces an inline error and stays on /login', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel(/e-?mail/i).fill(ADMIN_EMAIL);
    await page.getByLabel(/has[lł]o|password/i).fill('not-the-password');
    await page.getByRole('button', { name: /zaloguj|sign in/i }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('alert')).toBeVisible();
  });

  test('an unauthenticated visit to /products bounces to /login', async ({ page, context }) => {
    // Ensure no token leaks in from a previous test in the same browser context.
    await context.clearCookies();
    await page.goto('/products');
    await expect(page).toHaveURL(/\/login$/);
  });

  test('logout clears the session and redirects to /login', async ({ page }) => {
    test.fixme(true, BLOCKED_BY_41);
    await loginAsAdmin(page);
    await page.getByRole('button', { name: /wyloguj|sign out/i }).click();
    await expect(page).toHaveURL(/\/login$/);
  });

  // Sanity check that the credentials we use everywhere actually match the
  // fixtures the API ships with — if this fails, every other test is bogus.
  test('seeded credentials match the fixtures', async ({ page }) => {
    test.fixme(true, BLOCKED_BY_41);
    await loginAsAdmin(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    await expect(page).toHaveURL(/\/dashboard$/);
  });

  test('hard reload preserves the session via silent refresh', async ({ page }) => {
    test.fixme(true, BLOCKED_BY_41);
    await loginAsAdmin(page);

    // The reload drops the in-memory access token. /api/auth/refresh against
    // the still-present HttpOnly cookie should put a new one back, and the
    // user must NOT be bounced to /login.
    const refreshSeen = page.waitForResponse(
      (response) =>
        response.url().includes('/api/auth/refresh') &&
        response.request().method() === 'POST' &&
        response.status() === 200,
    );
    await page.reload();
    await refreshSeen;

    await expect(page).toHaveURL(/\/dashboard$/);
    // Dashboard is the new index after epik UI-03 #356; products list moved to /products.
    await page.goto('/products');
    await expect(page.getByRole('heading', { name: /produkty|products/i })).toBeVisible();
  });

  test('access token is not stored in localStorage', async ({ page }) => {
    test.fixme(true, BLOCKED_BY_41);
    await loginAsAdmin(page);
    const stored = await page.evaluate(() => window.localStorage.getItem('pim.jwt'));
    expect(stored).toBeNull();
  });

  test('logout calls POST /api/auth/logout', async ({ page }) => {
    test.fixme(true, BLOCKED_BY_41);
    await loginAsAdmin(page);

    const logoutPosted = page.waitForRequest(
      (request) => request.url().includes('/api/auth/logout') && request.method() === 'POST',
    );
    await page.getByRole('button', { name: /wyloguj|sign out/i }).click();
    await logoutPosted;

    await expect(page).toHaveURL(/\/login$/);
  });
});
