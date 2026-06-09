import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1355 / #1356 — the attribute create form carried "Unique" and
 * "Indexed" toggles that were form-only (no backend column / enforcement)
 * and only misled operators. Both were removed; "Required" and
 * "Filtrowalny" stay.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('attribute create form has no Unique / Indexed toggles', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  await loginAsAdmin(page);
  await page.goto('/modeling/attributes/new');

  // Kept flags.
  await expect(page.getByText(/^required$/i).first()).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(/^filtrowalny$/i).first()).toBeVisible();

  // Removed flags.
  await expect(page.getByText(/^unique$/i)).toHaveCount(0);
  await expect(page.getByText(/^indexed$/i)).toHaveCount(0);
});
