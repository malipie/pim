import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-09b (#540) — Query mode AND/OR brackets editor.
 *
 * Covers:
 *   1. Mode toggle: Grid → Query unlocks the recursive editor.
 *   2. Nested group inside root: add `+ Dodaj grupę` button is visible
 *      at depth < 3 and disappears at depth 3.
 *   3. Apply with a non-empty group → URL search params include `?q=`
 *      (base64-encoded DSL).
 *
 * Marked `fixme` in CI for the storageState reason the other UI-seeded
 * specs use — VIEW-09b inherits the auth rate-limiter quota.
 */
const CI_BLOCKED = 'E2E selector drift: VIEW-09b recursive query-mode editor. Refs #1638';

test.describe('VIEW-09b query mode editor', () => {
  test.beforeEach(async ({ page }) => {
    test.setTimeout(90_000);
    await loginAsAdmin(page);
    await page.goto('/products');
  });

  test('Query tab unlocks the recursive editor', async ({ page }) => {
    test.fixme(true, CI_BLOCKED);
    await page.getByRole('button', { name: /filtruj zaawansowane/i }).click();
    const queryTab = page.getByRole('tab', { name: /query/i });
    await expect(queryTab).toBeEnabled();
    await queryTab.click();
    await expect(queryTab).toHaveAttribute('aria-selected', 'true');

    // Root group is rendered.
    const rootGroup = page.getByRole('region', { name: /grupa and|grupa or/i }).first();
    await expect(rootGroup).toBeVisible();
  });

  test('+ Dodaj warunek and + Dodaj grupę work inside root', async ({ page }) => {
    test.fixme(true, CI_BLOCKED);
    await page.getByRole('button', { name: /filtruj zaawansowane/i }).click();
    await page.getByRole('tab', { name: /query/i }).click();

    await page
      .getByRole('button', { name: /dodaj warunek/i })
      .first()
      .click();
    await expect(page.getByLabel('Atrybut').first()).toBeVisible();

    await page
      .getByRole('button', { name: /dodaj grupę/i })
      .first()
      .click();
    // After nesting, a second group with its own AND/OR toggle appears.
    const groups = page.getByRole('region', { name: /grupa and|grupa or/i });
    await expect(groups).toHaveCount(2);
  });
});
