import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin, uniqueSku } from './helpers/auth';

/**
 * #975 — Relation picker modal ("Wybierz obiekt") used to render
 * "Brak dopasowań" for every relation attribute because the FE called
 * `GET /api/objects` which had no AP4 GetCollection operation. After
 * the fix (CatalogObject.xml + relations-tab.tsx `?code=` → `?sku=`)
 * the picker fetches a real list of poly-kind candidates and the
 * operator can pick a target without leaving the modal.
 */
test('relation picker lists candidates and narrows on `?sku=` filter', async ({ page }) => {
  // First POST after DB reset is slow (attributesIndexed listener warm-up).
  test.setTimeout(180_000);

  // Direct API login first — we need the JWT for `page.request` calls
  // below; `apiLogin` only lands the refresh cookie, which is not
  // attached to ad-hoc requests. We still call `apiLogin` afterwards
  // so the browser context owns an in-memory token for the UI flow.
  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  // Mint two products via API — one becomes the "source" we edit, the
  // other has to appear in the candidate modal. Both share the demo
  // tenant so the tenant filter does not hide them.
  const sourceSku = uniqueSku('975SRC');
  const targetSku = uniqueSku('975TGT');

  const objectTypesResponse = await page.request.get('/api/object_types?itemsPerPage=200', {
    headers: { ...bearer, accept: 'application/ld+json' },
  });
  expect(objectTypesResponse.status()).toBe(200);
  const objectTypesBody = (await objectTypesResponse.json()) as {
    member?: Array<{ id: string; kind: string }>;
  };
  const productType = (objectTypesBody.member ?? []).find((row) => row.kind === 'product');
  if (productType === undefined) {
    throw new Error('Built-in product ObjectType not seeded for demo tenant.');
  }
  const productTypeId = productType.id;

  const sourceResponse = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code: sourceSku, objectTypeId: productTypeId },
  });
  expect(sourceResponse.status()).toBe(201);
  const source = (await sourceResponse.json()) as { id: string };

  const targetResponse = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code: targetSku, objectTypeId: productTypeId },
  });
  expect(targetResponse.status()).toBe(201);

  // Navigate straight to the source product in edit mode — relations
  // tab only renders when `mode === 'edit'`.
  await page.goto(`/products/${source.id}/edit`);
  await expect(page).toHaveURL(/\/products\/[0-9a-f-]{36}\/edit$/);

  // Switch to the "Powiązania"/"Relations" tab. Seeded via
  // BuiltInProductRelationAttributesSeeder so every product detail
  // page has this tab. Match both Polish + English labels + optional
  // badge count.
  await page.getByRole('tab', { name: /(powi[ąa]zania|relations)\s*\d*/i }).click();

  // Every built-in relation attribute renders its own "Dodaj
  // powiązanie" button — pick the first one (cross_sell, position 10
  // in the seeder). We don't bind to a specific card heading because
  // multiple cards share the same surrounding `div` structure; the
  // first button is the cross-sell entry point.
  const addLinkButton = page
    .getByRole('button', { name: /^(dodaj powi[ąa]zanie|add link|add relation)$/i })
    .first();
  await expect(addLinkButton).toBeVisible();

  // Open the picker modal. Capture the candidate fetch so we can
  // prove the FE talks to `/api/objects?…sku=…` (the bug was
  // `?code=`, silently ignored by AP4).
  const candidatesResponsePromise = page.waitForResponse(
    (response) => response.url().includes('/api/objects?') && response.request().method() === 'GET',
    { timeout: 15_000 },
  );
  await addLinkButton.click();

  // Modal title + the unfiltered candidate list arrives.
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.getByText(/wybierz obiekt|pick object|select object/i)).toBeVisible();

  const initialResponse = await candidatesResponsePromise;
  expect(initialResponse.status()).toBe(200);

  // Candidate list contains at least the second product we seeded.
  // The picker excludes the source product (`excludedObjectIds`)
  // automatically.
  await expect(page.getByRole('button', { name: targetSku })).toBeVisible({ timeout: 15_000 });

  // Type a substring → the FE rebuilds the query with `?sku=<query>`;
  // assert the request URL really uses `sku` (not `code`) and that
  // the list still contains a row matching the substring.
  const filteredResponsePromise = page.waitForResponse(
    (response) =>
      response.url().includes('/api/objects?') &&
      response.url().includes('sku=') &&
      response.request().method() === 'GET',
    { timeout: 15_000 },
  );
  await page.getByPlaceholder(/szukaj po kodzie|search by code/i).fill(targetSku);
  const filteredResponse = await filteredResponsePromise;
  expect(filteredResponse.status()).toBe(200);
  expect(filteredResponse.url()).toContain(`sku=${encodeURIComponent(targetSku)}`);

  await expect(page.getByRole('button', { name: targetSku })).toBeVisible();
});
