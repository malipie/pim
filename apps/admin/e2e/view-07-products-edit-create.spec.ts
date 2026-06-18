import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * VIEW-07 (#420) — product detail page relayout + create flow without
 * the wizard.
 *
 * Single test running the full happy path. Re-enabled in AUD-022: the
 * dev/CI `auth_login` limiter override (200/15min) means the historical
 * "rate-limiter exhausted" gating was a false root cause; this spec passes
 * against a healthy stack. Duplicate-flow coverage is also held by
 * `DuplicateProductApiTest`.
 */

test('VIEW-07 product detail + create + duplicate flow', async ({ page }) => {
  // First POST after a DB reset hydrates the attributesIndexed listener and
  // can take 12-15s; bump test budget so the full UI flow fits.
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const sku = uniqueSku('V7');
  await page.goto('/products/new');
  // Wait until /api/object_types resolves so `useDefaultObjectType`
  // populated the built-in product objectTypeId — otherwise Create
  // bails on the validation guard.
  await page.waitForResponse(
    (response) =>
      response.url().includes('/api/object_types') && response.request().method() === 'GET',
    { timeout: 30_000 },
  );

  // /products/new renders the inline form (no 3-step wizard).
  await expect(page.getByRole('button', { name: /utwórz produkt|create product/i })).toBeVisible();
  await expect(page.getByText(/krok|step/i)).toHaveCount(0);

  await page.getByPlaceholder(/^id$/i).fill(sku); // #1415 — unified ID label
  await page.getByPlaceholder(/nazwa produktu|product name/i).fill(`Playwright VIEW-07 ${sku}`);
  // #1357 — the "Marka" field was removed from the new-entry form.

  // #891 — a new product requires at least one category before POST.
  await page.getByRole('button', { name: /przypisz kategori/i }).click();
  const categoryDialog = page.getByRole('dialog');
  await categoryDialog.getByRole('checkbox').first().check();
  await categoryDialog.getByRole('button', { name: /^zapisz$/i }).click();

  const createResponse = page.waitForResponse(
    (response) =>
      // #1415 — the unified create POSTs the poly-kind /api/objects.
      response.url().endsWith('/api/objects') && response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: /utwórz produkt|create product/i }).click();
  const created = await createResponse;
  expect(created.status()).toBe(201);
  await expect(page).toHaveURL(/\/products\/[0-9a-f-]{36}$/, { timeout: 30_000 });

  // #1351 — the detail page opens directly in edit mode: "Zapisz zmiany"
  // + "Zapisz i wróć do listy" are visible immediately, there is no
  // read-only "Edytuj" toggle.
  await expect(page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i })).toBeVisible();
  await expect(page.getByRole('button', { name: /^(edytuj|edit)$/i })).toHaveCount(0);
  await expect(page.getByRole('button', { name: /^(anuluj|cancel)$/i })).toBeVisible();
  await expect(
    page.getByRole('button', { name: /zapisz i wróć do listy|save and return/i }),
  ).toBeVisible();

  // Duplicate uses the existing POST /api/products/{id}/duplicate
  // sugar endpoint and lands on the freshly minted /products/{newId}.
  const duplicateResponse = page.waitForResponse(
    (response) => response.url().includes('/duplicate') && response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: /^(duplikuj|duplicate)$/i }).click();
  const duplicate = await duplicateResponse;
  expect(duplicate.status()).toBe(201);
  const duplicateBody = (await duplicate.json()) as { id: string; code: string };
  expect(duplicateBody.code).toContain('-COPY-');

  await expect(page).toHaveURL(new RegExp(`/products/${duplicateBody.id}$`), {
    timeout: 30_000,
  });
});
