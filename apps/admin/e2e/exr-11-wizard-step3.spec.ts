import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-11 (#1387) — wizard step 3: two-pane column picker. The attribute
 * catalog endpoints are mocked for determinism; column-order-in-file
 * assertions land with EXR-12's sync export e2e.
 */

test.beforeEach(async ({ page }) => {
  await page.route('**/api/exports/profiles', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [], total: 0 }),
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
  await page.route('**/api/attributes?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify([
        { id: 'a1', code: 'name', label: { pl: 'Nazwa' }, type: 'text', localizable: true },
        { id: 'a2', code: 'brand', label: { pl: 'Marka' }, type: 'relation' },
      ]),
    }),
  );
  await page.route('**/api/attribute_groups?*', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: '[]' }),
  );
  await page.route('**/api/workspaces/current', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ enabledLocales: ['pl'], primaryLocale: 'pl' }),
    }),
  );
  await page.route('**/api/channels?*', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: '[]' }),
  );

  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/new');
  await page.waitForTimeout(500);
  await page.getByRole('button', { name: /Dalej|Next/ }).click(); // → step 2
  await page.waitForTimeout(300);
  await page.getByRole('button', { name: /Dalej|Next/ }).click(); // → step 3
  await page.waitForTimeout(500);
});

test('sku is locked first, selecting adds columns, counter updates', async ({ page }) => {
  await expect(page.getByText(/klucz|key/).first()).toBeVisible();
  await expect(page.getByText(/Wybrane atrybuty \(1\)|Selected attributes \(1\)/)).toBeVisible();

  await page.getByRole('checkbox', { name: /Marka/ }).check();
  await expect(page.getByText(/Wybrane atrybuty \(2\)|Selected attributes \(2\)/)).toBeVisible();

  // locked sku cannot be removed — no remove button on its row
  await expect(
    page.getByRole('button', { name: /Usuń kolumnę SKU|Remove column SKU/ }),
  ).toHaveCount(0);
});

test('search narrows, group checkbox toggles all, clear keeps the key', async ({ page }) => {
  const search = page.getByRole('searchbox');
  await search.fill('marka');
  await expect(page.getByRole('checkbox', { name: /Marka/ })).toBeVisible();
  await expect(page.getByRole('checkbox', { name: /Nazwa \[pl\]/ })).toHaveCount(0);
  await search.fill('');

  // group "Inne atrybuty" checkbox selects name.pl + brand
  await page
    .getByRole('checkbox', { name: /Zaznacz całą grupę|Toggle whole group/ })
    .last()
    .check();
  await expect(page.getByText(/Wybrane atrybuty \(3\)|Selected attributes \(3\)/)).toBeVisible();

  await page.getByRole('button', { name: /^Wyczyść$|^Clear$/ }).click();
  await expect(page.getByText(/Wybrane atrybuty \(1\)|Selected attributes \(1\)/)).toBeVisible();
  // min 1 column satisfied by the locked key → Dalej enabled
  await expect(page.getByRole('button', { name: /Dalej|Next/ })).toBeEnabled();
});

test('selection survives Wstecz/Dalej navigation', async ({ page }) => {
  await page.getByRole('checkbox', { name: /Marka/ }).check();
  await expect(page.getByText(/Wybrane atrybuty \(2\)|Selected attributes \(2\)/)).toBeVisible();

  await page.getByRole('button', { name: /Wstecz|Back/ }).click();
  await page.getByRole('button', { name: /Dalej|Next/ }).click();
  await expect(page.getByText(/Wybrane atrybuty \(2\)|Selected attributes \(2\)/)).toBeVisible();
});
