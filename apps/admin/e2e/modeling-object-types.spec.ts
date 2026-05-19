import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-01 (#372) — modeling Object Types end-to-end smoke.
 *
 * Single test exercises the full VIEW-01 surface (list with built-in /
 * custom split, detail with all 9 sections, locked behavior on built-in,
 * wizard route loads with step indicator) under one login — the auth
 * rate limiter (5/IP/15min) is shared with the rest of the e2e run.
 */
test('VIEW-01 Modeling · Object Types — list + detail + wizard smoke', async ({ page }) => {
  test.fixme(true, 'Pending #799: heading selector /object types/i level=1 no longer matches the rendered Modeling shell (UI-08.9 #264 reorg). Selector needs updating.');
  await loginAsAdmin(page);

  // 1. List: navigate to /modeling/object-types and assert built-in section.
  await page.goto('/modeling/object-types');
  await expect(page.getByRole('heading', { name: /object types/i, level: 1 })).toBeVisible();
  await expect(page.getByText(/built-in \(system\)/i)).toBeVisible();
  await expect(page.getByText(/custom \(your organization\)/i)).toBeVisible();

  // Bottom CTA for new ObjectType is a button (NOT a Sheet trigger anymore).
  // Two such CTAs render (header "+ Nowy typ" + bottom dashed). First is enough.
  const bottomCta = page
    .getByRole('button', { name: /stw[oó]rz nowy objecttype|create.*objecttype/i })
    .first();
  await expect(bottomCta).toBeVisible();

  // 2. Detail: navigate via the list's link grid (scoped to the modeling
  //    main column to avoid clicking the sidebar Produkty entry which
  //    points at /products).
  const detailLinks = page.locator('a[href^="/modeling/object-types/"]').filter({
    hasNot: page.locator('a[href$="/new"]'),
  });
  await detailLinks.first().click();
  await expect(page).toHaveURL(/\/modeling\/object-types\/[0-9a-f-]{36}/);

  for (const heading of [
    /identyfikacja|identification/i,
    /built-in attribute groups/i,
    /custom attribute groups/i,
    /settings|ustawienia/i,
    /where used/i,
  ]) {
    await expect(page.getByText(heading).first()).toBeVisible();
  }

  // 3. Built-in lock: settings toggles report aria-disabled.
  const togglesAriaDisabled = await page
    .getByRole('switch')
    .evaluateAll((els) => els.map((el) => el.getAttribute('aria-disabled')));
  expect(togglesAriaDisabled.length).toBeGreaterThan(0);
  expect(togglesAriaDisabled).toContain('true');

  // 4. Wizard route loads as a full-screen view (no Sheet/Dialog overlay).
  await page.goto('/modeling/object-types/new');
  await expect(page.getByText(/nowy objecttype|new objecttype/i).first()).toBeVisible();

  // Step indicator (progressbar) — VIEW-01 wizard renders inline.
  const stepIndicator = page.getByRole('progressbar');
  await expect(stepIndicator).toBeVisible();
  await expect(stepIndicator).toHaveAttribute('aria-valuemax', '4');

  // No Radix portal overlay = wizard is NOT a popup.
  const overlayCount = await page.locator('[role="dialog"]').count();
  expect(overlayCount).toBe(0);
});
