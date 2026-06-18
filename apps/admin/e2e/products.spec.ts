import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

// This suite drove the legacy ProductListPage / ProductCreatePage CRUD
// (`/products/new` form with SKU/Nazwa/Marka inputs, `/products/:id/edit`).
// NUI-05 (#1424) retired those pages — `/products` is now the UniversalListPage
// and `/products/:id/edit` only redirects, so the labelled-input + add-product
// flows here no longer exist. The equivalent coverage moved to
// `view-07-products-edit-create.spec.ts` (universal create/edit, now enabled).
// Kept fixme until this legacy suite is rewritten or removed.
const BLOCKED_BY_41 =
  'Legacy product CRUD UI retired (NUI-05 #1424); coverage moved to view-07-products-edit-create. Refs #1638';

test.describe('Products CRUD', () => {
  test.beforeEach(async ({ page }) => {
    test.fixme(true, BLOCKED_BY_41);
    await loginAsAdmin(page);
  });

  test('list shows seeded products for the current tenant', async ({ page }) => {
    // Demo tenant fixtures persist DEMO-001..003 — list the page and assert
    // at least one of them is rendered. Other tests may add E2E-* rows but
    // those are filtered to the same tenant by Doctrine TenantFilter.
    await expect(page.getByRole('cell', { name: /^DEMO-/ }).first()).toBeVisible();
  });

  test('creates a new product and surfaces it in the list', async ({ page }) => {
    const sku = uniqueSku();
    const name = `Playwright created ${sku}`;

    await page.getByRole('link', { name: /dodaj produkt|add product/i }).click();
    await expect(page).toHaveURL(/\/products\/new$/);

    await page.getByLabel(/^SKU$/).fill(sku);
    await page.getByLabel(/nazwa|name/i).fill(name);
    await page.getByLabel(/marka|brand/i).fill('Playwright Inc.');
    await page.getByLabel(/opis|description/i).fill('Created by E2E test.');

    await page.getByRole('button', { name: /^zapisz$|^save$/i }).click();

    // The form redirects to /products on success; the new row appears in the table.
    await expect(page).toHaveURL(/\/products$/);
    // `exact: true` because `name` happens to start with `sku`, so a non-exact
    // match would resolve to two cells in strict mode.
    await expect(page.getByRole('cell', { name: sku, exact: true })).toBeVisible();
    await expect(page.getByRole('cell', { name, exact: true })).toBeVisible();
  });

  test('PATCH updates the name but leaves the SKU immutable on the form', async ({ page }) => {
    const sku = uniqueSku('PATCH');
    const initialName = `Initial ${sku}`;
    const renamed = `Renamed ${sku}`;

    // Seed via the UI — fewer moving parts than calling the API from Playwright.
    await page.getByRole('link', { name: /dodaj produkt|add product/i }).click();
    await page.getByLabel(/^SKU$/).fill(sku);
    await page.getByLabel(/nazwa|name/i).fill(initialName);
    await page.getByRole('button', { name: /^zapisz$|^save$/i }).click();
    await expect(page).toHaveURL(/\/products$/);

    // Click the edit button on the new row and rename it.
    const row = page.getByRole('row').filter({ hasText: sku });
    await row.getByRole('link').click();
    await expect(page).toHaveURL(/\/products\/[0-9a-f-]{36}\/edit$/);

    // SKU must stay locked — disabled per ticket #3 (product:patch group).
    await expect(page.getByLabel(/^SKU$/)).toBeDisabled();

    const nameField = page.getByLabel(/nazwa|name/i);
    await nameField.fill(renamed);
    await page.getByRole('button', { name: /^zapisz$|^save$/i }).click();

    await expect(page).toHaveURL(/\/products$/);
    const updatedRow = page.getByRole('row').filter({ hasText: sku });
    await expect(updatedRow.getByRole('cell', { name: renamed, exact: true })).toBeVisible();
  });

  test('blank SKU + name on create surfaces a validation error and keeps user on the form', async ({
    page,
  }) => {
    await page.getByRole('link', { name: /dodaj produkt|add product/i }).click();
    await expect(page).toHaveURL(/\/products\/new$/);

    // Leave both required fields empty and submit.
    await page.getByRole('button', { name: /^zapisz$|^save$/i }).click();

    // react-hook-form keeps us on the form; the SKU input is flagged invalid.
    await expect(page).toHaveURL(/\/products\/new$/);
    await expect(page.getByLabel(/^SKU$/)).toHaveAttribute('aria-invalid', 'true');
  });
});
