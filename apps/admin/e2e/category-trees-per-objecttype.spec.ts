import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * ADR-015 PR-C (#1122) — per-ObjectType category trees.
 *
 * The /modeling/categories ObjectType selector now lists every
 * is_categorizable ObjectType (built-in + custom) and emits its id; the
 * tree is filtered to that ObjectType's tree via
 * ?categoryTargetObjectType=<id>. Switching the selector swaps trees;
 * "+ Nowa kategoria" creates inside the selected tree.
 *
 * Marked `fixme` in CI for the shared-suite auth rate-limiter reason
 * (see settings-channels-crud.spec.ts). Coverage is preserved by
 * CategoriesApiTest (listFilterByCategoryTreeReturnsOnlyThatTree),
 * CatalogObjectPolyKindPostTest (scope create + per-tree uniqueness) and
 * the local browser smoke documented in the PR.
 */
const E2E_BLOCKED_BY_RATE_LIMITER =
  'Pending storageState rollout: spec lands after the 5/15min auth rate limiter is exhausted';

test.describe('ADR-015 — per-ObjectType category trees', () => {
  test('selector switches trees; create lands in the selected tree', async ({ page }) => {
    test.fixme(true, E2E_BLOCKED_BY_RATE_LIMITER);
    await loginAsAdmin(page);
    await page.goto('/modeling/categories');
    await page.waitForTimeout(2000);

    // The ObjectType selector lists categorizable ObjectTypes.
    const select = page.locator('select').first();
    await expect(select).toBeVisible();
    const options = await select.locator('option').allInnerTexts();
    expect(options.some((o) => /product|produkt/i.test(o))).toBeTruthy();

    // Product tree shows the seeded demo categories.
    await select.selectOption({ label: /product|produkt/i });
    await page.waitForTimeout(1200);
    await expect(page.getByText(/apparel|footwear|outdoor/i).first()).toBeVisible();
  });
});
