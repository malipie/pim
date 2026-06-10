import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-12 (#1388) — wizard step 4: summary, save-as-profile, run with
 * sync/async routing. Backend responses are mocked; the real sync file
 * download + async session run on the live stack land in the PR smoke.
 */

test.beforeEach(async ({ page }) => {
  await page.route('**/api/exports/profiles', (route) => {
    if (route.request().method() === 'POST') {
      route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ id: 'p1', name: 'Mój profil' }),
      });
      return;
    }
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [], total: 0 }),
    });
  });
  await page.route('**/api/exports/preflight', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        count: 5,
        mode: 'sync',
        threshold: 100,
        soft_cap: 100000,
        exceeds_cap: false,
      }),
    }),
  );
  await page.route('**/api/attributes?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify([{ id: 'a2', code: 'brand', label: { pl: 'Marka' }, type: 'relation' }]),
    }),
  );
  for (const url of ['**/api/attribute_groups?*', '**/api/channels?*'] as const) {
    await page.route(url, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: '[]' }),
    );
  }
  await page.route('**/api/workspaces/current', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ enabledLocales: ['pl'], primaryLocale: 'pl' }),
    }),
  );
  await page.route('**/api/exports/sessions', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [], total: 0 }),
    }),
  );

  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/new');
  await page.waitForTimeout(500);
  for (let step = 0; step < 3; step += 1) {
    await page.getByRole('button', { name: /Dalej|Next/ }).click();
    await page.waitForTimeout(400);
  }
});

test('summary shows the configuration and sync run downloads a file', async ({ page }) => {
  await expect(page.getByText(/Podsumowanie konfiguracji|Configuration summary/)).toBeVisible();
  await expect(page.getByText(/Produkty|Products/).first()).toBeVisible();
  await expect(page.getByText('XLSX').first()).toBeVisible();
  // sync note from preflight mode
  await expect(page.getByText(/pobrany automatycznie|downloaded automatically/)).toBeVisible();

  await page.route('**/api/products/export', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      headers: { 'content-disposition': 'attachment; filename="pim-export-test.xlsx"' },
      body: 'fake-xlsx-bytes',
    }),
  );
  const downloadPromise = page.waitForEvent('download');
  await page.getByTestId('run-export').click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toBe('pim-export-test.xlsx');
  await expect(page).toHaveURL(/\/integrations\/exports\/sessions/);
});

test('async run redirects to sessions with the started toast', async ({ page }) => {
  await page.route('**/api/products/export', (route) =>
    route.fulfill({
      status: 202,
      contentType: 'application/json',
      body: JSON.stringify({ id: 'sess-1', status: 'pending', target_count: 500 }),
    }),
  );
  await page.getByTestId('run-export').click();
  await expect(page).toHaveURL(/\/integrations\/exports\/sessions/);
  await expect(page.getByText(/Eksport uruchomiony|Export started/)).toBeVisible();
});

test('save as profile posts and never runs the export', async ({ page }) => {
  let exportCalled = false;
  await page.route('**/api/products/export', (route) => {
    exportCalled = true;
    route.fulfill({ status: 500, body: '' });
  });

  await page.getByPlaceholder(/Wpisz nazwę profilu|Enter profile name/).fill('Mój profil');
  await page.getByRole('button', { name: /Zapisz jako profil|Save as profile/ }).click();
  await expect(page.getByText(/Zapisano profil|Profile .* saved/)).toBeVisible();
  await expect(page.getByText('Mój profil').first()).toBeVisible();
  expect(exportCalled).toBe(false);
});
