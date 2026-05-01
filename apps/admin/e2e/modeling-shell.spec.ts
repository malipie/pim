import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * UI-08.9 (#264) — Modeling layout shell smoke.
 *
 * Single test exercises the full surface (tablist render, tab switch,
 * legacy redirect) with one login. The auth-rate-limiter (5/IP/15min)
 * is shared across the whole Playwright run; splitting this into 3
 * `beforeEach`-driven tests would push the cumulative login count past
 * the limit on top of the multi-tenant-isolation suite.
 */
test('Modeling layout shell — tablist + tab switch + legacy redirect', async ({ page }) => {
  await loginAsAdmin(page);

  // 1. /modeling lands on object-types and renders the 4-tab tablist.
  await page.goto('/modeling');
  await expect(page).toHaveURL(/\/modeling\/object-types$/);

  const tablist = page.getByRole('tablist', { name: /modeling sections|sekcje modelowania/i });
  await expect(tablist).toBeVisible();

  const tabNames = [
    /object types|typy obiektów/i,
    /attributes|atrybuty/i,
    /attribute groups|grupy atrybutów/i,
    /categories|kategorie/i,
  ];
  for (const name of tabNames) {
    await expect(tablist.getByRole('tab', { name })).toBeVisible();
  }
  await expect(tablist.getByRole('tab', { name: /object types|typy obiektów/i })).toHaveAttribute(
    'aria-selected',
    'true',
  );

  // 2. Clicking the Attributes tab updates the URL + active highlight.
  await page.getByRole('tab', { name: /^attributes$|^atrybuty$/i }).click();
  await expect(page).toHaveURL(/\/modeling\/attributes$/);
  await expect(page.getByRole('tab', { name: /^attributes$|^atrybuty$/i })).toHaveAttribute(
    'aria-selected',
    'true',
  );

  // 3. Legacy top-level URL redirects to its /modeling/... twin.
  await page.goto('/object-types');
  await expect(page).toHaveURL(/\/modeling\/object-types$/);
});
