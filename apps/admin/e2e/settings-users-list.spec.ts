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
  // in #682 (sidebar permission filtering). Slow CI runners occasionally
  // miss the initial GET /api/users response when the Vite chunk lands
  // slowly; bump the timeout so the test races on the row render instead
  // of the network event itself.
  await page.goto('/settings/users');

  // Page chrome: heading + invite CTA + table.
  await expect(page.getByRole('heading', { level: 2, name: /użytkownicy|users/i })).toBeVisible({
    timeout: 30_000,
  });
  await expect(page.getByRole('button', { name: /zaproś użytkownika|invite user/i })).toBeVisible();
  await expect(page.getByRole('table')).toBeVisible();

  // The seeded admin user must appear in the list. Scope the search to the
  // table — the top-bar user menu also renders the email, so a global
  // getByText would fail Playwright's strict-mode resolution. Bumping the
  // timeout here covers the same slow-CI scenario as the heading above.
  await expect(page.getByRole('table').getByText('admin@demo.localhost')).toBeVisible({
    timeout: 30_000,
  });

  // Search → debounce → filter narrows. Wait on the empty-state copy
  // rather than the network event so the test stays decoupled from
  // request timing — the empty state only renders when the response
  // arrives with `total: 0` regardless.
  await page.getByLabel(/wyszukaj użytkowników|search users/i).fill('zzz_no_such_user');
  await expect(page.getByText(/brak użytkowników|no users/i)).toBeVisible({ timeout: 15_000 });
});
