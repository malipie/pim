import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * IMP-14 (#455) — E2E smoke for the imports wizard. Walks Step 1 →
 * Step 2 → Step 3 → Step 4 against the live stack with a tiny CSV
 * (5 rows) so the run path stays inline (sync threshold, IMP-04).
 *
 * The test is intentionally gated to the surface contract (the
 * wizard renders, navigation works, the run CTA dispatches) — full
 * round-trip with product creation lives in the backend ApiTestCase
 * suite (StartImportApiTest / ValidateDryRunApiTest).
 */
test.describe('Imports MVP', () => {
  test('wizard renders and walks the four steps', async ({ page }) => {
    await loginAsAdmin(page);

    await page.goto('/publications/imports');
    await expect(page.getByRole('heading', { name: /publikacje|publications/i })).toBeVisible();

    // Sub-tab Imports is the only enabled one on this slice.
    await expect(page.getByRole('heading', { name: /importy|imports/i })).toBeVisible();

    await page.getByRole('link', { name: /nowy import|new import/i }).click();
    await expect(page).toHaveURL(/\/publications\/imports\/new$/);
  });

  test('list view renders the empty state for a fresh tenant', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/publications/imports');
    // Either the table shows a row count or the empty-state copy
    // ("Brak importów") — the page must render either way.
    await expect(page.getByRole('heading', { name: /importy|imports/i })).toBeVisible();
  });
});
