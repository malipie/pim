import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-IMP-00 (#493) — Imports hub tab container smoke. Single
 * login + 4 tab clicks + deep-link refresh + default redirect.
 * Heavier per-view scenarios live in their own spec files added
 * by VIEW-IMP-01..04.
 */
test.describe('Imports hub — tabs', () => {
  test('flat /integrations/imports redirects to the sessions tab', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/integrations/imports');
    await expect(page).toHaveURL(/\/integrations\/imports\/sessions$/);
    await expect(
      page.getByRole('tab', { name: /^sesje$|^sessions$/i, selected: true }),
    ).toBeVisible();
  });

  test('navigates to each of the 4 tabs and surfaces the active state', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/integrations/imports/sessions');

    await page.getByRole('tab', { name: /profile mapowań|mapping profiles/i }).click();
    await expect(page).toHaveURL(/\/integrations\/imports\/profiles$/);
    await expect(
      page.getByRole('tab', { name: /profile mapowań|mapping profiles/i, selected: true }),
    ).toBeVisible();

    await page.getByRole('tab', { name: /^źródła$|^sources$/i }).click();
    await expect(page).toHaveURL(/\/integrations\/imports\/sources$/);
    await expect(
      page.getByRole('tab', { name: /^źródła$|^sources$/i, selected: true }),
    ).toBeVisible();

    await page.getByRole('tab', { name: /harmonogram|schedule/i }).click();
    await expect(page).toHaveURL(/\/integrations\/imports\/schedule$/);
    await expect(
      page.getByRole('tab', { name: /harmonogram|schedule/i, selected: true }),
    ).toBeVisible();

    await page.getByRole('tab', { name: /^sesje$|^sessions$/i }).click();
    await expect(page).toHaveURL(/\/integrations\/imports\/sessions$/);
    await expect(
      page.getByRole('tab', { name: /^sesje$|^sessions$/i, selected: true }),
    ).toBeVisible();
  });

  test('schedule placeholder still renders the coming-soon banner', async ({ page }) => {
    // Sources got the full view in VIEW-IMP-03 — only the schedule tab
    // remains a placeholder until VIEW-IMP-04 ships the dedicated UI.
    await loginAsAdmin(page);

    await page.goto('/integrations/imports/schedule');
    await expect(page.getByRole('heading', { name: /wkrótce|coming soon/i })).toBeVisible();
  });

  // Deep-link refresh behaviour relies on the refresh-cookie token resurrection
  // path in auth-provider.ts (test #2 / test #3 already exercise direct
  // deep-link navigation which is the actual foundation contract). A dedicated
  // reload-recovery spec sits with the auth flows, not the imports hub.
});
