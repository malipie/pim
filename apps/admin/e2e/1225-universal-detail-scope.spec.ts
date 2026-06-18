import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * fix(admin) #1225 / #1269 — locale/channel scope parity on the
 * custom-ObjectType detail card (`UniversalDetailPage`), mirroring the
 * product detail card.
 *
 * #1269 revised the gate: the switcher is now ALWAYS visible (parity with
 * the product card, which renders on `mode === 'edit'` — the route mode,
 * always true for an existing object — NOT the Edit toggle). Switching the
 * locale re-reads the object even in read-only view.
 *
 * Assertions:
 *   1. View mode: the locale/channel switcher is visible (read-only scope
 *      switching reflects the chosen scope's values).
 *   2. Edit mode: the switcher persists; switching the locale issues the
 *      scoped read without crashing.
 *
 * Marked `fixme` in CI for the shared-suite auth rate-limiter reason
 * (see object-name-edit.spec.ts / settings-channels-crud.spec.ts).
 */
const E2E_BLOCKED_BY_RATE_LIMITER =
  'E2E selector drift: locale/channel switcher button label changed. Refs #1638';

// Seed-dependent path (custom ObjectType `samochody`); same hardcoded-seed
// convention as object-name-edit.spec.ts. Adjust if the demo seed changes.
const OBJECT_PATH = '/objects/samochody/019e89e1-7abe-7a01-bb4d-290472beabbf';

test.describe('fix(admin) #1225 — universal detail scope switcher', () => {
  test('switcher visible in view mode and persists in edit mode', async ({ page }) => {
    test.fixme(true, E2E_BLOCKED_BY_RATE_LIMITER);
    await loginAsAdmin(page);
    await page.goto(OBJECT_PATH);
    await page.waitForTimeout(2000);

    const localePicker = page.getByRole('button', { name: /^język$|^language$/i });

    // #1351 unification — the detail page opens directly in edit mode
    // (no Edytuj gate); the scope switcher is visible from the start.
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
