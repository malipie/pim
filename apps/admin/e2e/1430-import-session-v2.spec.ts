import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * NUI-11 (#1430) — import session view v2: header (status + duration),
 * stage pipeline, results card with KPIs / ResultBar / report download.
 * Uses the newest session from the history table; skips when the
 * environment has no import sessions yet.
 */
test('NUI-11 — session view renders pipeline, summary and report link', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/integrations/imports/sessions');

  // History rows are grid divs with an onClick navigate — click the
  // filename cell and let the event bubble to the row handler.
  const fileCell = page.getByText(/\.csv|\.xlsx/i).first();
  const hasSession = await fileCell
    .waitFor({ state: 'visible', timeout: 10_000 })
    .then(() => true)
    .catch(() => false);
  test.skip(!hasSession, 'No import sessions in this environment');

  await fileCell.click();
  await expect(page).toHaveURL(/\/integrations\/imports\/[0-9a-f-]{8,}/);

  // Stage pipeline renders its stages.
  await expect(page.getByText(/parsing/i).first()).toBeVisible();
  await expect(page.getByText(/zapis|writing/i).first()).toBeVisible();

  // Terminal session shows the results card with the report download.
  await expect(page.getByText(/zaimportowano|imported/i).first()).toBeVisible({ timeout: 15_000 });
  await expect(page.getByRole('link', { name: /raport|report/i })).toBeVisible();
});
