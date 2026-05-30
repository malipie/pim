import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1142 — the Settings landing (`/settings`) must redirect to the first
 * tab (Security / Bezpieczeństwo), which every authenticated user can
 * reach (own MFA + password). Previously it pointed at `/settings/menu`.
 */
test('Settings index redirects to the Security tab', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto('/settings');

  await expect(page).toHaveURL(/\/settings\/security$/);
});
