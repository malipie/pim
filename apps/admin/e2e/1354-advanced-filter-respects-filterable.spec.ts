import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1354 — the advanced filter panel must offer ONLY attributes flagged
 * `is_filterable=true`. Previously the AttributePicker fetched the full
 * tenant attribute list, so an operator could build a condition on a
 * non-filterable attribute (e.g. `description`, `short_description`) that
 * the search index silently ignored — the list just emptied with no
 * explanation.
 *
 * The demo seeder marks a sensible product subset filterable
 * (brand/color/size/tags/price/weight/height/in_stock/release_date); the
 * remaining product attributes (name/sku/description/short_description/
 * main_image/related_to) stay non-filterable and MUST NOT appear in the
 * picker.
 *
 * Marked `fixme` in CI for the shared `storageState` auth-quota reason
 * the other UI-seeded specs use.
 */

test('advanced filter picker lists only filterable attributes', async ({ page }) => {
  test.setTimeout(90_000);

  await loginAsAdmin(page);
  await page.goto('/products');

  // Open the push-down advanced filter panel.
  await page.getByRole('button', { name: /filtruj zaawansowane/i }).click();
  const panel = page.locator('section[aria-label*="Filtr"]');
  await expect(panel).toBeVisible();

  // Add a condition row → its AttributePicker trigger appears.
  await page.getByRole('button', { name: /dodaj warunek/i }).click();
  const pickerTrigger = panel.locator('button[aria-haspopup="listbox"]').first();
  await expect(pickerTrigger).toBeVisible();
  await pickerTrigger.click();

  // The portal dropdown is searchable; the option rows render label + code.
  const search = page.getByPlaceholder(/szukaj atrybutu/i);
  await expect(search).toBeVisible();

  // Filterable attributes MUST be offered.
  await expect(page.getByText('brand', { exact: true })).toBeVisible();
  await expect(page.getByText('color', { exact: true })).toBeVisible();
  await expect(page.getByText('price', { exact: true })).toBeVisible();

  // Non-filterable product attributes MUST NOT appear in the picker.
  await expect(page.getByText('short_description', { exact: true })).toHaveCount(0);
  await expect(page.getByText('description_html', { exact: true })).toHaveCount(0);
  await expect(page.getByText('related_to', { exact: true })).toHaveCount(0);

  // Narrowing the search to a non-filterable code yields the empty state,
  // proving the gate runs before the text filter.
  await search.fill('short_description');
  await expect(page.getByText(/brak atrybutów/i)).toBeVisible();
});
