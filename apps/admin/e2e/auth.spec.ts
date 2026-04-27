import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, loginAsAdmin } from './helpers/auth';

test.describe('Authentication', () => {
  test('user can log in and lands on the products list', async ({ page }) => {
    await loginAsAdmin(page);
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
    await loginAsAdmin(page);
    await page.getByRole('button', { name: /wyloguj|sign out/i }).click();
    await expect(page).toHaveURL(/\/login$/);
  });

  // Sanity check that the credentials we use everywhere actually match the
  // fixtures the API ships with — if this fails, every other test is bogus.
  test('seeded credentials match the fixtures', async ({ page }) => {
    await loginAsAdmin(page, ADMIN_EMAIL, ADMIN_PASSWORD);
    await expect(page).toHaveURL(/\/products$/);
  });
});
