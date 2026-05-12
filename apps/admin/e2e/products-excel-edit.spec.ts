import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * Regression for the bug operator hit on 2026-05-12: the products list
 * Excel grid surfaced no change after editing the "Nazwa" cell, so it
 * looked like edits did not persist.
 *
 * Real cause: the read path in `buildRow` (apps/admin/src/features/
 * catalog/products/list.tsx) treated `attributesIndexed` as a flat map,
 * but the API ships every reading wrapped as `{ value: ... }` (per
 * AttributesIndexedRebuilder). The PATCH did persist, but the row
 * reader fell back to `entry.code` (SKU) and the cell snapped back.
 *
 * The spec exercises the full round-trip:
 *  1. seed a product with a known name,
 *  2. switch to Excel mode,
 *  3. commit a new name in the Nazwa cell,
 *  4. wait for the PATCH to land (server actually persists),
 *  5. reload the page so the in-memory row is refetched from `/api/products`,
 *  6. assert the cell renders the new name (this is what was broken).
 */
test('Excel grid commit on Nazwa is reflected after refetch', async ({ page }) => {
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const sku = uniqueSku('XLS');
  const initialName = `Excel initial ${sku}`;
  const renamedName = `Excel renamed ${sku}`;

  // Seed via the regular create flow so the new product carries a real
  // attributesIndexed payload (the listener fires on save).
  await page.goto('/products/new');
  await page.waitForResponse(
    (response) =>
      response.url().includes('/api/object_types') && response.request().method() === 'GET',
    { timeout: 30_000 },
  );

  await page.getByPlaceholder('SKU').fill(sku);
  await page.getByPlaceholder(/nazwa produktu|product name/i).fill(initialName);

  const createResponse = page.waitForResponse(
    (response) =>
      response.url().endsWith('/api/products') && response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: /utwórz produkt|create product/i }).click();
  const created = await createResponse;
  expect(created.status()).toBe(201);
  await expect(page).toHaveURL(/\/products\/[0-9a-f-]{36}$/, { timeout: 30_000 });

  // Land on the list and switch to Excel mode.
  await page.goto('/products');
  await page.getByRole('tab', { name: /^excel$/i }).click();

  // Find the row by SKU. The Excel grid renders one cell per column;
  // the SKU column is read-only and stable.
  const skuCell = page.getByRole('cell', { name: sku, exact: true }).first();
  await expect(skuCell).toBeVisible();

  // Before fix: the Nazwa cell would show the SKU because buildRow
  // fell back to `entry.code` for every row. Confirm we actually see
  // the seeded name (this on its own catches the read-path regression).
  const initialNameCell = page.getByRole('cell', { name: initialName, exact: true }).first();
  await expect(initialNameCell).toBeVisible();

  // Single-click into the Nazwa cell, type the new value, and commit
  // by pressing Enter. The grid issues a PATCH with
  // `{ attributes: { name: ... } }`. Scope the editor lookup to the
  // grid table so we don't accidentally grab the page-level search bar.
  await initialNameCell.click();
  const editor = page.locator('table td input').first();
  await expect(editor).toBeFocused();

  const patchResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/api/products/') && response.request().method() === 'PATCH',
  );
  await editor.fill(renamedName);
  await editor.press('Enter');
  const patched = await patchResponse;
  expect(patched.status()).toBe(200);

  // Reload — refetch from /api/products. With the buildRow fix the
  // Nazwa cell now reads `attributesIndexed.name.value` instead of
  // falling back to SKU.
  await page.reload();
  await page.getByRole('tab', { name: /^excel$/i }).click();

  await expect(page.getByRole('cell', { name: renamedName, exact: true }).first()).toBeVisible({
    timeout: 15_000,
  });
});
