import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * fix(admin) #1226 — variants tab is scope-aware: it follows the parent
 * product card's active locale/channel instead of the hardcoded
 * `pl`/global. Verifies the FE wiring end-to-end:
 *   - switching the header locale picker re-fetches the variant list with
 *     `?locale=` (collection overlay, #1223), and
 *   - saving a variant edit issues PATCH `/api/products/{id}?locale=`.
 *
 * The BE scope routing (localizable → locale row) is already covered by
 * #1169; here we assert the request carries the scope, which is the bug
 * #1226 fixes (previously the variants tab dropped it).
 *
 * Conditional `fixme` in CI for the shared-suite auth rate limiter (same
 * as view-07-3-products-variants.spec.ts); runs locally.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('fix(admin) #1226 — variant list + PATCH carry the active locale scope', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  // Master product + two variants via API (UI create is covered elsewhere).
  const sku = uniqueSku('SC1226');
  const typesResponse = await page.request.get('/api/object_types');
  const typesBody = (await typesResponse.json()) as {
    member?: Array<{ id: string; kind: string }>;
    'hydra:member'?: Array<{ id: string; kind: string }>;
  };
  const types = typesBody.member ?? typesBody['hydra:member'] ?? [];
  // Exactly one `kind: 'product'` type exists (the built-in); the
  // serialized `isBuiltIn` is omitted in JSON-LD, so match on kind.
  const productType = types.find((t) => t.kind === 'product');
  if (productType === undefined) {
    throw new Error('Product ObjectType not found — demo seeder did not run.');
  }

  const masterResponse = await page.request.post('/api/products', {
    headers: { 'content-type': 'application/ld+json' },
    data: { code: sku, objectTypeId: productType.id, attributes: { name: `Master ${sku}` } },
  });
  expect(masterResponse.status()).toBe(201);
  const master = (await masterResponse.json()) as { id: string };

  const generateResponse = await page.request.post(`/api/products/${master.id}/generate-variants`, {
    headers: { 'content-type': 'application/json' },
    data: { axes: { color: ['red', 'blue'] } },
  });
  expect(generateResponse.status()).toBe(201);

  await page.goto(`/products/${master.id}`);

  // Switch the header locale picker to EN, then watch the scoped variant
  // list read fire (collection overlay).
  const listScoped = page.waitForResponse(
    (r) =>
      r.url().includes('/api/products?parent_id=') &&
      r.url().includes('locale=en') &&
      r.request().method() === 'GET',
  );
  await page
    .getByRole('button', { name: /^język$|^language$/i })
    .first()
    .click();
  await page.getByRole('menuitem').filter({ hasText: /en/i }).first().click();
  await page.getByRole('tab', { name: /warianty|variants/i }).click();
  await listScoped;

  // Edit a variant attribute and save → PATCH must carry ?locale=en.
  await page.getByRole('button', { name: /^(edytuj warianty|edit variants)$/i }).click();
  await page.getByRole('button', { name: /rozwiń wszystkie|expand all/i }).click();
  const firstInput = page.locator('input, textarea').filter({ visible: true }).first();
  await firstInput.fill(`EN ${sku}`);

  const patchScoped = page.waitForResponse(
    (r) => /\/api\/products\/[^?]+\?.*locale=en/.test(r.url()) && r.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: /^(zapisz|save)$/i }).click();
  const patch = await patchScoped;
  expect(patch.status()).toBeLessThan(400);
});
