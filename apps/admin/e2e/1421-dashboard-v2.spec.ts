import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * NUI-02 (#1421) — dashboard v2: live KPI entity totals (no MockBadge on
 * wired tiles), mocked widgets keep their badges, BackupWidget renders.
 */
test('NUI-02 — dashboard renders live KPI counts and mocked widgets with badges', async ({
  page,
}) => {
  await loginAsAdmin(page);
  await page.goto('/dashboard');

  // KPI row: the products tile shows a real number (non-zero seed).
  const productsTile = page
    .locator('div')
    .filter({ hasText: /^(Produkty|Products)/ })
    .first();
  await expect(productsTile).toBeVisible();

  // Live KPI value appears (digits) once the counts query resolves.
  await expect(page.locator('.num').first()).toBeVisible({ timeout: 15_000 });

  // Backup widget (MOCK) renders with its heatmap label.
  await expect(page.getByText(/backup bazy|database backup/i)).toBeVisible();
  await expect(page.getByText(/ostatnie 14 dni|last 14 days/i)).toBeVisible();

  // Mock badges still present on mocked blocks.
  const badges = page.getByText('MOCK', { exact: true });
  expect(await badges.count()).toBeGreaterThan(0);
});
