import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * Issue #1043 — regression coverage for the not-found state on detail
 * pages. Prior to the fix, hitting `/products/{invalid-id}` left the
 * page stuck on "Ładowanie…" forever because the loading guard caught
 * the post-404 stale `undefined` data. After the fix, the operator
 * sees an explicit "Produkt nie znaleziony" + back button.
 */
const MISSING_UUID = '00000000-0000-7000-8000-000000000000';

test.describe('Detail pages — not-found state', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('legacy /products/{missing-uuid} shows product-specific not-found', async ({ page }) => {
    await page.goto(`/products/${MISSING_UUID}`);

    const heading = page.getByRole('heading', { level: 1, name: 'Produkt nie znaleziony' });
    await expect(heading).toBeVisible({ timeout: 10_000 });
    await expect(page.getByText(MISSING_UUID)).toBeVisible();

    const backLink = page.getByRole('link', { name: /Wróć do listy produktów/i });
    await expect(backLink).toBeVisible();
    await expect(backLink).toHaveAttribute('href', '/products');
  });

  test('legacy /products/{not-a-uuid} also shows not-found (no infinite loading)', async ({
    page,
  }) => {
    await page.goto('/products/123');

    await expect(
      page.getByRole('heading', { level: 1, name: 'Produkt nie znaleziony' }),
    ).toBeVisible({ timeout: 10_000 });
    await expect(page.getByText(/Ładowanie/i)).toHaveCount(0);
  });

  // NOTE: the analogous `/objects/<custom-slug>/<missing-uuid>` smoke is
  // operator-only because CI fixtures only seed built-in product / category
  // / asset ObjectTypes (per BuiltInObjectTypeSeeder). Built-in kinds
  // redirect from `/objects/:slug/:id` to their legacy detail routes
  // inside ObjectShowPage, so they cannot exercise UniversalDetailPage's
  // not-found branch in a stable spec. Coverage for the universal branch
  // is provided through the unit-level guard rewrite + operator smoke
  // (`https://pim.localhost/objects/samochody/abc` in the PR body).

  test('clicking back from product not-found navigates to /products', async ({ page }) => {
    await page.goto(`/products/${MISSING_UUID}`);
    await page.getByRole('link', { name: /Wróć do listy produktów/i }).click();
    await expect(page).toHaveURL(/\/products(\?.*)?$/);
  });
});
