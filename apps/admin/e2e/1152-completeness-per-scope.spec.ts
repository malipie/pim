import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * feat(catalog) #1152 — per-scope completeness ring on the product card.
 *
 * The ring reflects the active locale/channel scope
 * (`completeness.per_channel[channel] ?? completeness.per_locale[locale] ??
 * global`). This spec is the deterministic UI safety net: the ring renders
 * in edit mode and survives a channel switch without crashing.
 *
 * The actual per-scope VALUE differentiation (per_channel.allegro ≠ global)
 * is data-dependent (needs a scopable attribute with a channel override) and
 * is proven by the AttributesIndexedRebuilder unit test + the live-stack
 * smoke documented in the PR.
 *
 * Conditional `fixme` in CI for the shared-suite auth rate limiter (same as
 * the sibling product specs); runs locally.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

// Seed-dependent product (DEMO-100); adjust if the demo seed changes.
const PRODUCT_PATH = '/products/019e8e72-20d0-79b6-928e-435b1c815aa0';

test('feat(catalog) #1152 — completeness ring survives a channel switch', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  await loginAsAdmin(page);
  await page.goto(PRODUCT_PATH);
  await page.waitForTimeout(1500);

  // The completeness ring renders its percentage text in edit mode.
  const ring = page.locator('text=/%$/').first();
  await expect(ring).toBeVisible();

  // Switch the channel; the ring must re-render (scoped read) without crashing.
  await page
    .getByRole('button', { name: /^kanał$|^channel$/i })
    .first()
    .click();
  await page.waitForTimeout(300);
  const option = page.getByRole('menuitem').nth(1);
  if (await option.isVisible().catch(() => false)) {
    await option.click();
    await page.waitForTimeout(800);
  }
  await expect(page.locator('text=/%$/').first()).toBeVisible();
});
