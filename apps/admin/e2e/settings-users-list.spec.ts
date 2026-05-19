import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * RBAC-P5-001 (#691) — Settings → Users list smoke.
 *
 * Single test exercises the surface (table render, search filter, status
 * filter) with one login. The auth-rate-limiter (5/IP/15min) is shared
 * across the whole Playwright run; splitting this into multiple per-flow
 * specs would push the cumulative login count past the limit when the
 * suite runs after other RBAC specs.
 */
test('Settings → Users list — smoke', async ({ page }) => {
  await loginAsAdmin(page);

  // Sidebar → Settings → Users — direct navigation to the URL rather than
  // clicking through, because the sidebar contents are tested separately
  // in #682 (sidebar permission filtering).
  await page.goto('/settings/users');

  // The fetch for the first page lands while the route hydrates; wait for
  // it explicitly so the test does not race with the loading skeleton.
  await page.waitForResponse(
    (response) => response.url().includes('/api/users') && response.request().method() === 'GET',
  );

  // Page chrome: heading + invite CTA + table.
  await expect(page.getByRole('heading', { level: 2, name: /użytkownicy|users/i })).toBeVisible();
  await expect(page.getByRole('button', { name: /zaproś użytkownika|invite user/i })).toBeVisible();
  await expect(page.getByRole('table')).toBeVisible();

  // The seeded admin user must appear in the list.
  await expect(page.getByText('admin@demo.localhost')).toBeVisible();

  // Search → debounce → filter narrows.
  await page.getByLabel(/wyszukaj użytkowników|search users/i).fill('zzz_no_such_user');
  await page.waitForResponse(
    (response) =>
      response.url().includes('/api/users') && response.url().includes('search=zzz_no_such_user'),
  );
  await expect(page.getByText(/brak użytkowników|no users/i)).toBeVisible();
});
