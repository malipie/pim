import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1357 — the new-product form (/products/new) carried a "Marka" input
 * and a non-functional "Dodaj grupę atrybutów ad-hoc" stub. Both were
 * removed per operator request; SKU + name stay.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason.
 */

test('new product form has no Marka input and no ad-hoc group adder', async ({ page }) => {
  test.setTimeout(120_000);

  await loginAsAdmin(page);
  await page.goto('/products/new');

  // SKU + name inputs stay.
  // #1415 — the identifier is labelled "ID" for every ObjectType.
  await expect(page.getByPlaceholder(/^id$/i)).toBeVisible({ timeout: 15_000 });
  await expect(page.getByPlaceholder(/nazwa produktu|product name/i)).toBeVisible();

  // "Marka" input is gone.
  await expect(page.getByPlaceholder(/^marka$/i)).toHaveCount(0);
  // The ad-hoc group adder is gone.
  await expect(
    page.getByRole('button', { name: /dodaj grupę atrybutów ad-hoc|ad-hoc/i }),
  ).toHaveCount(0);
});
