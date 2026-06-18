import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1126 — /modeling/categories must show the (Product) tree on a fresh visit
 * without the operator manually toggling the ObjectType selector.
 *
 * Regression: the selector emitted its auto-selected ObjectType via a
 * render-phase setSearchParams, which React applied unreliably; the list
 * `useList` is gated on that URL param, so the tree rendered empty on first
 * load. Moving the auto-select into a post-commit useEffect stamps the URL
 * reliably.
 */
test.describe('#1126 — categories first-load', () => {
  test('fresh visit auto-selects a tree and lists its categories', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/modeling/categories'); // clean URL, no targetObjectTypeId
    await page.waitForTimeout(2500);

    // The selector auto-stamps its ObjectType id into the URL post-commit.
    await expect(page).toHaveURL(/targetObjectTypeId=[0-9a-f-]{36}/);
    // The Product tree's seeded demo categories are visible without any toggle.
    await expect(page.getByText(/apparel|footwear|outdoor|odzie|obuwie/i).first()).toBeVisible();
  });
});
