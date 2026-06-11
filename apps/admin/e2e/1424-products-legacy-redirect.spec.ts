import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * NUI-05 (#1424) — the legacy product list is retired after the UP-10
 * dual-maintenance window. `/products/legacy` now redirects to the
 * universal `/products` list (back-compat for operator bookmarks).
 */
test('NUI-05 — /products/legacy redirects to the universal /products list', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto('/products/legacy');
  await expect(page).toHaveURL(/\/products$/);

  // The universal list renders its toolbar search — the page is alive,
  // not a blank redirect target.
  await expect(page.getByRole('searchbox').or(page.getByPlaceholder(/szukaj|search/i))).toBeVisible(
    { timeout: 15_000 },
  );
});
