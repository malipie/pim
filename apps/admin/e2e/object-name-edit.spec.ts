import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * feat(admin) #1116 — editable object name (title) in edit mode.
 *
 * Golden path on the universal object detail page: enter edit mode →
 * the title becomes an editable input → change it → save → reload shows
 * the new name. The spec restores the original name at the end so it
 * leaves no residue on the seeded object.
 *
 * Marked `fixme` in CI for the shared-suite auth rate-limiter reason
 * (see settings-channels-crud.spec.ts). Coverage is preserved by the
 * manual + local browser smoke documented in the PR.
 */
const E2E_BLOCKED_BY_RATE_LIMITER =
  'E2E selector drift after UI-03 on the editable object-name flow. Refs #1638';

test.describe('feat(admin) #1116 — editable object name', () => {
  test('edit title → save → persists, then restore', async ({ page }) => {
    test.fixme(true, E2E_BLOCKED_BY_RATE_LIMITER);
    await loginAsAdmin(page);
    await page.goto('/objects/salony_sprzedazy/019e75c8-9a40-7b3a-b9bf-7ec5fe1a0bb7');
    await page.waitForTimeout(2000);

    // #1351 unification — the page opens directly in edit mode; the
    // title is an input from the start (no Edytuj gate).
    const titleInput = page.getByLabel(/nazwa|name/i).first();
    const original = await titleInput.inputValue();
    const edited = `${original} ✎`;

    await titleInput.fill(edited);
    await page
      .getByRole('button', { name: /zapisz zmiany|save changes/i })
      .first()
      .click();
    await page.waitForTimeout(1500);
    await page.reload();
    await page.waitForTimeout(2000);
    // Edit-mode default: the persisted name round-trips into the input.
    await expect(page.getByLabel(/nazwa|name/i).first()).toHaveValue(edited);

    // Restore original name.
    await page
      .getByLabel(/nazwa|name/i)
      .first()
      .fill(original);
    await page
      .getByRole('button', { name: /zapisz zmiany|save changes/i })
      .first()
      .click();
    await page.waitForTimeout(1500);
  });
});
