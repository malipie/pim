import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-13 (#1389) — Export Profiles tab: v2 table, run/edit/delete.
 * Backend mocked; the full save → run → edit → delete cycle runs in
 * the live smoke.
 */

const PROFILE = {
  id: '22222222-2222-2222-2222-222222222222',
  name: 'SEO PL+EN',
  description: 'Cotygodniowy zrzut SEO',
  entity_type: 'product',
  object_type_id: null,
  config: {
    format: 'csv',
    selected_columns: ['sku', 'meta_title.pl', 'meta_title.en'],
    locales: ['pl', 'en'],
    channels: null,
    filter: { attr: 'brand', op: '=', value: 'Festo' },
  },
  last_run_at: '2026-06-09T10:00:00+00:00',
  run_count: 4,
  created_at: '2026-06-01T10:00:00+00:00',
  updated_at: '2026-06-09T10:00:00+00:00',
};

test.beforeEach(async ({ page }) => {
  await page.route('**/api/exports/sessions', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [], total: 0 }),
    }),
  );
});

test('profiles table renders config summary and runs a profile', async ({ page }) => {
  await page.route('**/api/exports/profiles', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [PROFILE], total: 1 }),
    }),
  );
  await page.route(`**/api/exports/profiles/${PROFILE.id}/run`, (route) =>
    route.fulfill({
      status: 202,
      contentType: 'application/json',
      body: JSON.stringify({ id: 'sess-9', status: 'pending' }),
    }),
  );

  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/profiles', { waitUntil: 'commit' });
  await page.waitForTimeout(1000);

  await expect(page.getByText('SEO PL+EN')).toBeVisible();
  await expect(page.getByText('CSV')).toBeVisible();
  await expect(page.getByText('brand')).toBeVisible();
  await expect(page.getByText('3', { exact: true })).toBeVisible(); // columns count
  // tab counter wired to the real total
  await expect(page.getByRole('tab', { name: /Profile/ })).toContainText('1');

  await page.getByRole('button', { name: /Uruchom teraz|Run now/ }).click();
  await expect(page).toHaveURL(/\/integrations\/exports\/sessions/);
  await expect(page.getByText(/eksport uruchomiony|export started/)).toBeVisible();
});

test('edit opens the wizard prefilled from the profile', async ({ page }) => {
  await page.route('**/api/exports/profiles', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [PROFILE], total: 1 }),
    }),
  );
  await page.route(`**/api/exports/profiles/${PROFILE.id}`, (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(PROFILE),
    }),
  );
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

  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto(`/integrations/exports/new?profile=${PROFILE.id}`, { waitUntil: 'commit' });
  await page.waitForTimeout(1000);

  // step 1 prefilled: product selected
  await expect(page.getByRole('radio', { name: /Produkty|Products/ })).toHaveAttribute(
    'aria-checked',
    'true',
  );
  // step 2 shows CSV selected
  await page.getByRole('button', { name: /Dalej|Next/ }).click();
  await expect(page.getByRole('radio', { name: /^CSV/ })).toHaveAttribute('aria-checked', 'true');
});

test('delete confirms with the profile name and refetches', async ({ page }) => {
  let deleted = false;
  await page.route('**/api/exports/profiles', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(deleted ? { items: [], total: 0 } : { items: [PROFILE], total: 1 }),
    }),
  );
  await page.route(`**/api/exports/profiles/${PROFILE.id}`, (route) => {
    deleted = true;
    route.fulfill({ status: 204, body: '' });
  });

  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/profiles', { waitUntil: 'commit' });
  await page.waitForTimeout(1000);

  page.once('dialog', (dialog) => {
    expect(dialog.message()).toContain('SEO PL+EN');
    void dialog.accept();
  });
  await page.getByRole('button', { name: /Usuń profil|Delete profile/ }).click();
  await expect(page.getByText(/Brak zapisanych profili|No saved profiles/)).toBeVisible();
});
