import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * fix(admin) #1225 — locale/channel scope parity on the custom-ObjectType
 * detail card (`UniversalDetailPage`), mirroring the product detail card.
 *
 * Two deterministic assertions:
 *   1. View mode: the locale/channel switcher is hidden (it only makes
 *      sense while editing — parity with the product card edit-gate).
 *   2. Edit mode: the switcher appears with the Język + Kanał pickers,
 *      and switching the locale issues the scoped read without crashing.
 *
 * The per-attribute inheritance indicator (amber "Wartość z [XX]") needs a
 * localizable attribute carrying a fallback value, which is data-dependent;
 * that path is covered by the manual live-stack smoke documented in the PR.
 *
 * Marked `fixme` in CI for the shared-suite auth rate-limiter reason
 * (see object-name-edit.spec.ts / settings-channels-crud.spec.ts).
 */
const E2E_BLOCKED_BY_RATE_LIMITER =
  'Pending storageState rollout: spec lands after the 5/15min auth rate limiter is exhausted';

// Seed-dependent path (custom ObjectType `samochody`); same hardcoded-seed
// convention as object-name-edit.spec.ts. Adjust if the demo seed changes.
const OBJECT_PATH = '/objects/samochody/019e89e1-7abe-7a01-bb4d-290472beabbf';

test.describe('fix(admin) #1225 — universal detail scope switcher', () => {
  test('switcher hidden in view mode, shown in edit mode', async ({ page }) => {
    test.fixme(true, E2E_BLOCKED_BY_RATE_LIMITER);
    await loginAsAdmin(page);
    await page.goto(OBJECT_PATH);
    await page.waitForTimeout(2000);

    const localePicker = page.getByRole('button', { name: /^język$|^language$/i });

    // 1. View mode — the scope switcher must not be rendered.
    await expect(localePicker).toHaveCount(0);

    // 2. Enter edit mode — the switcher (Język + Kanał) appears.
    await page
      .getByRole('button', { name: /edytuj|^edit$/i })
      .first()
      .click();
    await page.waitForTimeout(600);
    await expect(localePicker.first()).toBeVisible();
    await expect(page.getByRole('button', { name: /^kanał$|^channel$/i }).first()).toBeVisible();

    // Switching the locale triggers the scoped read; assert no crash — the
    // switcher persists (in edit mode the title is an <input>, not an <h1>,
    // so we assert the picker rather than a heading).
    await localePicker.first().click();
    await page.waitForTimeout(300);
    const option = page.getByRole('menuitem').nth(1);
    if (await option.isVisible().catch(() => false)) {
      await option.click();
      await page.waitForTimeout(800);
    }
    await expect(localePicker.first()).toBeVisible();
  });
});
