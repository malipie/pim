import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * IMP-14 (#455) — E2E smoke for the imports wizard. Single login to
 * keep the auth-throttle budget low (the suite already pays for
 * multi-tenant + catalog flows). Walks the list view → "Nowy import"
 * link → wizard route. Full round-trip with product creation lives in
 * the backend ApiTestCase suite (StartImportApiTest /
 * ValidateDryRunApiTest).
 */
test.describe('Imports MVP', () => {
  test('list renders and the wizard route is reachable', async ({ page }) => {
    await loginAsAdmin(page);

    await page.goto('/publications/imports');
    await expect(page.getByRole('heading', { name: /publikacje|publications/i })).toBeVisible();
    await expect(page.getByRole('heading', { name: /importy|imports/i })).toBeVisible();

    await page.getByRole('link', { name: /nowy import|new import/i }).click();
    await expect(page).toHaveURL(/\/publications\/imports\/new$/);
  });
});
