import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * Operator report (2026-05-12): generating ~16 variants for one product
 * filled the products list page entirely with that master's variants
 * and pushed every other product (including the master itself) off
 * the visible page. Tree mode looked broken because the variants were
 * orphaned (parent not in the same Refine page) and rendered flat at
 * the top.
 *
 * Fix: tree mode now sends `?parent_id=null` so the list only fetches
 * masters; variants load lazily on chevron expand. Spec covers:
 *   1. Seed a master + 5 variants via API.
 *   2. Open /products in tree mode (default).
 *   3. Assert master row is visible AND variant SKUs are NOT in the
 *      list (they would have been before the fix).
 *   4. Click the master's expand chevron → variants land inline.
 *
 * Marked `fixme` in CI for the same `storageState` reason the other
 * UI-seeded specs use.
 */

test('products list tree mode hides variants until expand', async ({ page }) => {
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const authHeaders = { Authorization: `Bearer ${accessToken}` };

  const sku = uniqueSku('TREE');

  const objectTypesResponse = await page.request.get('/api/object_types', {
    headers: authHeaders,
  });
  const objectTypesBody = (await objectTypesResponse.json()) as {
    member?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
    'hydra:member'?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
  };
  const types = objectTypesBody.member ?? objectTypesBody['hydra:member'] ?? [];
  const productType = types.find((t) => t.kind === 'product' && t.codeImmutable);
  if (productType === undefined) {
    throw new Error('Built-in product ObjectType not found — demo seeder did not run.');
  }

  // Master.
  const masterResponse = await page.request.post('/api/products', {
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: {
      code: sku,
      objectTypeId: productType.id,
      attributes: { name: `Tree-mode spec ${sku}` },
    },
  });
  expect(masterResponse.status()).toBe(201);
  const master = (await masterResponse.json()) as { id: string };

  // 5 variants under the master.
  const variantSkus: string[] = [];
  for (const suffix of ['red', 'green', 'blue', 'black', 'white']) {
    const variantSku = `${sku}-${suffix}`;
    variantSkus.push(variantSku);
    const variantResponse = await page.request.post('/api/products', {
      headers: { ...authHeaders, 'content-type': 'application/ld+json' },
      data: {
        code: variantSku,
        objectTypeId: productType.id,
        parentId: master.id,
        attributes: { name: `Tree-mode spec ${sku} ${suffix}` },
      },
    });
    expect(variantResponse.status()).toBe(201);
  }

  await page.goto('/products');

  // Tree mode is the default. The master must be visible and every
  // variant SKU must be absent from the page until expand. SKU cells
  // wrap on two lines for long codes, so match by `text=` substring
  // rather than exact accessible name.
  await expect(page.getByText(sku).first()).toBeVisible({ timeout: 15_000 });
  for (const variantSku of variantSkus) {
    await expect(page.getByText(variantSku)).toHaveCount(0);
  }

  // Find the master row and click its expand chevron. ProductsGrid
  // is a CSS grid (no native <tr>); rows expose `data-testid` keyed
  // by SKU and the chevron is the first <button> in the row.
  const masterRow = page.locator(`[data-testid="products-grid-row-${sku}"]`);
  await expect(masterRow).toBeVisible();
  await masterRow.locator('button').first().click();

  // Variants now load lazily and appear inline.
  for (const variantSku of variantSkus) {
    await expect(page.getByText(variantSku).first()).toBeVisible({ timeout: 10_000 });
  }
});
