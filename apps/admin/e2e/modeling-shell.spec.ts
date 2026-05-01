import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * UI-08.9 (#264) — Modeling layout shell smoke.
 *
 * Asserts that the new `/modeling/*` route tree is reachable, the 4
 * sub-tabs render with the right active highlight, and old top-level
 * URLs (e.g. `/object-types`) redirect to their `/modeling/...` twin.
 */
test.describe('Modeling layout shell', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('renders the 4-tab tablist with object-types active by default', async ({ page }) => {
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
  });

  test('switching tab updates the URL + active highlight', async ({ page }) => {
    await page.goto('/modeling/object-types');

    await page.getByRole('tab', { name: /attributes|atrybuty/i }).click();

    await expect(page).toHaveURL(/\/modeling\/attributes$/);
    await expect(page.getByRole('tab', { name: /attributes|atrybuty/i })).toHaveAttribute(
      'aria-selected',
      'true',
    );
  });

  test('legacy /object-types redirects to /modeling/object-types', async ({ page }) => {
    await page.goto('/object-types');

    await expect(page).toHaveURL(/\/modeling\/object-types$/);
    await expect(page.getByRole('tab', { name: /object types|typy obiektów/i })).toHaveAttribute(
      'aria-selected',
      'true',
    );
  });
});
