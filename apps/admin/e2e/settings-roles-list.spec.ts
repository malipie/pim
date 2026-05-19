import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * RBAC-P5-005 (#695) — Settings → Roles list smoke.
 *
 * One login covers everything visible — the auth-rate-limiter is
 * shared across the suite and other RBAC specs (#691, #692, #696)
 * already chew through 4 of the 5 attempts per 15min window.
 */
test('Settings → Roles list — smoke', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/settings/roles');

  await page.waitForResponse(
    (response) => response.url().includes('/api/roles') && response.request().method() === 'GET',
  );

  // Heading + create CTA + table render. Create CTA is disabled until the
  // role builder ships (#696) so we assert it exists rather than clickable.
  await expect(page.getByRole('heading', { level: 2, name: /^role$|^roles$/i })).toBeVisible();
  await expect(
    page.getByRole('button', { name: /utwórz rolę customową|create custom role/i }),
  ).toBeVisible();
  await expect(page.getByRole('table')).toBeVisible();

  // The 4 seeded global roles must each render. Use `.first()` because
  // each role surfaces both the human-readable name ("Viewer") and the
  // monospaced code identifier ("viewer") — Playwright's strict mode
  // refuses the assertion otherwise.
  const table = page.getByRole('table');
  await expect(table.getByText(/super admin/i).first()).toBeVisible();
  await expect(table.getByText(/catalog manager/i).first()).toBeVisible();
  await expect(table.getByText(/integration manager/i).first()).toBeVisible();
  await expect(table.getByText('Viewer', { exact: true })).toBeVisible();
  // At least one System badge (capitalised) must be visible. The custom
  // path isn't seeded in dev, so we don't assert the Custom badge here.
  await expect(table.getByText(/^system$/i).first()).toBeVisible();
});
