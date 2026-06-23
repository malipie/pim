import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * NUI-09 (#1428) — Imports hub leaves the legacy IntegrationsLayout:
 * pill tabs with counts under the v2 shell, topbar CTA, deep links and
 * the /integrations redirect keep working, api-configurator reachable.
 */
test('NUI-09 — imports hub v2: tabs, redirect, deep links', async ({ page }) => {
  await loginAsAdmin(page);

  await page.goto('/integrations/imports');
  await expect(page).toHaveURL(/\/integrations\/imports\/sessions$/);

  // Single pill tablist — no legacy double header ("Integracje" wrapper gone).
  const tabs = page.getByRole('tablist').last();
  await expect(tabs.getByRole('tab', { name: /sesje|sessions/i })).toBeVisible();
  await expect(page.getByText(/imports, exports, konektory/i)).toHaveCount(0);

  // CTA lives in the topbar now (and the empty active-sessions state mirrors it).
  await expect(page.getByRole('link', { name: /nowy import|new import/i }).first()).toBeVisible();

  // Tab navigation.
  await tabs.getByRole('tab', { name: /źródła|sources/i }).click();
  await expect(page).toHaveURL(/\/integrations\/imports\/sources$/);
  await tabs.getByRole('tab', { name: /harmonogram|schedule/i }).click();
  await expect(page).toHaveURL(/\/integrations\/imports\/schedule$/);

  // /integrations root redirect.
  await page.goto('/integrations');
  await expect(page).toHaveURL(/\/integrations\/imports\/sessions$/);

  // API configurator renders standalone under the shell.
  await page.goto('/integrations/api-configurator');
  await expect(page.getByRole('heading').first()).toBeVisible();
});
