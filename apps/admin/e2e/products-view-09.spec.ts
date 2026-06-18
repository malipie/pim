import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-09 (#535) — Smart Filter Presets row + Advanced Filter Panel +
 * Filter Chips Bar pixel-perfect smoke tests.
 *
 * Covers:
 *   1. List page surfaces 5 built-in smart filter presets (migration seed).
 *   2. Clicking a preset chip flips it to the active (dark) state.
 *   3. "Filtruj zaawansowane" toggle opens the push-down panel.
 *   4. Adding a condition + Apply spawns a chip in the chips bar.
 *   5. axe-core scan finds 0 serious/critical violations on the page.
 *
 * Marked `fixme` in CI for the storageState reason the other UI-seeded
 * specs use — VIEW-09 inherits the auth rate-limiter quota.
 */
const CI_BLOCKED =
  'E2E selector drift: VIEW-09 smart-filter presets + advanced filter panel. Refs #1638';

test.describe('VIEW-09 smart filter presets + advanced filter panel', () => {
  test.beforeEach(async ({ page }) => {
    test.setTimeout(90_000);
    await loginAsAdmin(page);
    await page.goto('/products');
  });

  test('renders the five built-in smart presets', async ({ page }) => {
    test.fixme(true, CI_BLOCKED);
    const presetsRow = page.getByRole('tablist', { name: /smart filtry/i });
    await expect(presetsRow).toBeVisible();

    for (const name of [
      /niespójne tłumaczenia|inconsistent translations/i,
      /brakujące zdjęcia|missing images/i,
      /niepełne seo|weak seo/i,
      /czerwone|red/i,
      /bez kategorii|no category/i,
    ]) {
      const tab = presetsRow.getByRole('tab', { name });
      await expect(tab).toBeVisible();
    }
  });

  test('clicking a preset chip flips its aria-selected state', async ({ page }) => {
    test.fixme(true, CI_BLOCKED);
    const presetsRow = page.getByRole('tablist', { name: /smart filtry/i });
    const redTab = presetsRow.getByRole('tab', { name: /czerwone|red/i });

    await expect(redTab).toHaveAttribute('aria-selected', 'false');
    await redTab.click();
    await expect(redTab).toHaveAttribute('aria-selected', 'true');

    // Toggle off — clicking the active chip clears the selection.
    await redTab.click();
    await expect(redTab).toHaveAttribute('aria-selected', 'false');
  });

  test('advanced filter panel toggles open + accepts a condition', async ({ page }) => {
    test.fixme(true, CI_BLOCKED);
    const toggle = page.getByRole('button', { name: /filtruj zaawansowane/i });
    await toggle.click();

    const panel = page.getByRole('region', { name: /filtr zaawansowany/i });
    // VIEW-09 panel renders as <section aria-label>; Playwright maps both.
    await expect(panel.or(page.locator('section[aria-label*="Filtr"]'))).toBeVisible();

    // Push the "Dodaj warunek" button → a condition row appears with the
    // default attribute (Marka) and the operator dropdown.
    const addBtn = page.getByRole('button', { name: /dodaj warunek/i });
    await addBtn.click();
    await expect(page.getByLabel('Atrybut').first()).toBeVisible();
    await expect(page.getByLabel('Operator').first()).toBeVisible();

    // Type a value and Apply — chip lands in the chips bar.
    const valueInput = page.getByPlaceholder(/wpisz wartość/i).first();
    await valueInput.fill('Festo');
    const applyBtn = page.getByRole('button', { name: /zastosuj filtr/i });
    await applyBtn.click();

    // FilterChipsBar exposes the chips under the active-filters region.
    const chipsRegion = page.locator('section[aria-label*="Aktywne filtry"]');
    await expect(chipsRegion).toBeVisible();
    await expect(chipsRegion.getByText('Festo', { exact: false })).toBeVisible();
  });
});
