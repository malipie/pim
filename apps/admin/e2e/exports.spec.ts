import { expect, test } from '@playwright/test';

import { apiLogin, loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * EXP-15 (#594) + EXP-21 (#633) — Exports hub end-to-end.
 *
 * Each `test` does its own `loginAsAdmin` / `apiLogin` and folds as many
 * assertions as fit into one session to stay inside the 5/IP/15min
 * auth-rate-limiter. The three tests cover the four scenarios from
 * PRD §15.1 that don't require backend round-trip:
 *
 *   1. `hub MVP — tabs + new flow smoke` — original EXP-15 surface.
 *   2. `modal context — sync XLSX download from list + bulk + save-as-profile`
 *      — scenarios (a), (c), (d): selection toolbar opens modal,
 *      modal POSTs the export, save-as-profile lands a row in the
 *      profiles tab, "Uruchom" re-dispatches the same config.
 *   3. `new form — async path 202 redirects to sessions` — scenario
 *      (b) with the export endpoint mocked so the FE branching logic
 *      (200 → blob download, 202 → redirect) runs deterministically
 *      without a 100+ SKU fixture.
 *
 * Świadome odejście: round-trip reimport (scenario e) — EXP-22
 * follow-up blocked by IMP-16..IMP-19 (#602–#605); variants flat /
 * pipe-separated / asset URL / multi-locale columns nie są jeszcze
 * wspierane przez IMP-01..15 pipeline (EXP-02 audit).
 */

test('exports hub MVP — tabs + new flow smoke', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/integrations/exports');

  // Hub heading + tab strip.
  await expect(page.getByRole('heading', { name: /eksporty|exports/i })).toBeVisible();
  await expect(page.getByRole('tab', { name: /sessions|sesje/i }).first()).toBeVisible();
  await expect(page.getByRole('tab', { name: /profiles|profile/i }).first()).toBeVisible();

  // "Nowy eksport" CTA opens the standalone full-page form.
  await page.getByRole('link', { name: /nowy eksport|new export/i }).click();
  await expect(page).toHaveURL(/\/integrations\/exports\/new$/);
  await expect(page.getByRole('dialog')).toBeVisible();
});

test('exports modal — sync XLSX download from list + save-as-profile lifecycle', async ({
  page,
}) => {
  test.fixme(true, 'Pending #799: even after the /catalog/products → /products route fix, ACME demo seed is on tenant=acme but loginAsAdmin signs in as admin@demo.localhost — the products list reads demo and finds no ACME masters. Needs `loginAsAcmeAdmin` helper or to switch the seed home tenant.');
  await apiLogin(page);

  const profileName = `E2E-EXP-${Date.now().toString(36)}`;

  // Product list lives at `/products` (Refine resource), not `/catalog/products` —
  // see App.tsx route declarations. The legacy `/catalog/products` triggered
  // a route-not-matched fallback that left the page empty.
  await page.goto('/products');

  // Wait for at least one product row to land (seed has 3 ACME masters).
  const firstCheckbox = page.getByRole('checkbox', { name: /zaznacz acme/i }).first();
  await expect(firstCheckbox).toBeVisible({ timeout: 15_000 });
  await firstCheckbox.check();

  // BulkBar shows up with the Eksport button (EXP-11 wiring through
  // VIEW-05.4 bulk toolbar).
  const bulkExport = page.getByRole('button', { name: /^eksport$|^export$/i });
  await expect(bulkExport).toBeVisible();
  await bulkExport.click();

  // Modal opened — fill columns + format + save-as-profile.
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();

  // Save-as-profile checkbox + name (EXP-18 #630).
  await dialog.getByLabel(/zapisz jako profil|save as profile/i).check();
  await dialog.getByPlaceholder(/nazwa profilu/i).fill(profileName);

  // Submit the export. The sync path returns a binary file, so we wait
  // for a `download` event instead of a navigation.
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    dialog.getByRole('button', { name: /^eksportuj$|^export$/i }).click(),
  ]);

  expect(download.suggestedFilename()).toMatch(/\.(xlsx|csv)$/);

  // Profile landed in the Profiles tab.
  await page.goto('/integrations/exports/profiles');
  await expect(page.getByText(profileName)).toBeVisible({ timeout: 5_000 });

  // "Uruchom" re-dispatches the export — the sync path triggers another
  // download because we kept the same target_scope=selected.
  // For run-now the backend resolves selectedObjectIds = [] (we stripped
  // them out at save time per EXP-18), so the runner falls back to its
  // empty-selected branch and returns an empty file. Either way the
  // request fires; we just assert the action is wired.
  const profileRow = page.getByRole('row', { name: new RegExp(profileName) });
  await expect(profileRow).toBeVisible();
  await profileRow.getByRole('button', { name: /uruchom|run now/i }).click();

  // Run lands the user on the sessions tab via window.location.href in
  // the FE handler (EXP-14 ExportProfilesView).
  await expect(page).toHaveURL(/\/integrations\/exports\/sessions$/, { timeout: 10_000 });

  // Cleanup — delete the test profile so reruns of the suite stay clean.
  await page.goto('/integrations/exports/profiles');
  page.once('dialog', (d) => void d.accept());
  const cleanupRow = page.getByRole('row', { name: new RegExp(profileName) });
  await cleanupRow.getByRole('button', { name: /usuń|delete/i }).click();
  await expect(cleanupRow).toHaveCount(0, { timeout: 5_000 });
});

test('exports new — async 202 redirects to sessions (mocked endpoint)', async ({ page }) => {
  await apiLogin(page);

  // Intercept the export POST so the FE branching logic runs
  // deterministically regardless of how many SKUs the dev DB has.
  await page.route('**/api/products/export', async (route) => {
    if (route.request().method() !== 'POST') {
      await route.continue();
      return;
    }
    await route.fulfill({
      status: 202,
      contentType: 'application/json',
      body: JSON.stringify({
        session_id: uniqueSku('SESS').toLowerCase(),
        status: 'pending',
      }),
    });
  });

  await page.goto('/integrations/exports/new');
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();

  // Default columns and `target_scope=all` (no list context).
  await dialog.getByRole('button', { name: /^eksportuj$|^export$/i }).click();

  await expect(page).toHaveURL(/\/integrations\/exports\/sessions$/, { timeout: 10_000 });
});
