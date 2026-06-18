import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1207 — system attributes (created_at/updated_at/created_by/updated_by) are
 * treated as normal fields: the "Zablokowane" lock badge is removed from the
 * attributes library list (and the modeling dialogs / detail page lock icon).
 *
 * This spec covers the most visible, deterministic change — the list under the
 * "system" filter no longer renders the lock badge. The auto-populated values
 * (created_at/by, updated_at/by) are proven server-side by
 * CatalogObjectPolyKindGetTest::getInjectsSystemAttributeValues and verified by
 * the live-stack smoke in the PR.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other UI specs.
 */

test('system attributes have no lock badge in the attributes library', async ({ page }) => {
  test.setTimeout(60_000);

  await loginAsAdmin(page);
  await page.goto('/modeling/attributes');

  // Filter down to the system audit attributes.
  await page.getByRole('button', { name: /^system$/i }).click();

  // The system attributes are listed...
  await expect(page.getByText('created_at', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('updated_by', { exact: true }).first()).toBeVisible();

  // ...but the "Zablokowane" lock badge is gone (treated as normal fields).
  await expect(page.getByText('Zablokowane')).toHaveCount(0);
});
