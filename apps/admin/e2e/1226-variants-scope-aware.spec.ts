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

test('fix(admin) #1226 — variant list + PATCH carry the active locale scope', async ({ page }) => {
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  // Cookie-only page.request calls intermittently 401 behind the auth
  // rate limiter — mint a bearer like the other API-seeded specs do.
  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const authHeaders = { Authorization: `Bearer ${accessToken}` };

  // Master product + two variants via API (UI create is covered elsewhere).
  const sku = uniqueSku('SC1226');
  const typesResponse = await page.request.get('/api/object_types?itemsPerPage=200', {
    headers: authHeaders,
  });
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
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: { code: sku, objectTypeId: productType.id, attributes: { name: `Master ${sku}` } },
  });
  expect(masterResponse.status()).toBe(201);
  const master = (await masterResponse.json()) as { id: string };

  const generateResponse = await page.request.post(`/api/products/${master.id}/generate-variants`, {
    headers: { ...authHeaders, 'content-type': 'application/json' },
    data: { axes: { color: ['red', 'blue'] } },
  });
  expect(generateResponse.status()).toBe(201);

  await page.goto(`/products/${master.id}`);

  // Switch the header locale picker to EN, then watch the scoped variant
  // list read fire (collection overlay).
  // Mount the variants host first, then flip the locale — the host
  // refetches on its [locale] effect, which is the scoped read we assert.
  await page.getByRole('tab', { name: /warianty|variants/i }).click();
  const listScoped = page.waitForResponse(
    (r) =>
      r.url().includes('/api/objects?parent_id=') &&
      r.url().includes('locale=en') &&
      r.request().method() === 'GET',
  );
  // The page-level product read also re-fires for the new scope and
  // remounts the tree (loading state) — wait for BOTH before touching
  // the variants editor, or the edit-mode click lands on the old tree.
  const productScoped = page.waitForResponse(
    (r) => /\/api\/objects\/[^?]+\?.*locale=en/.test(r.url()) && r.request().method() === 'GET',
  );
  const localePicker = page.getByRole('button', { name: /^język$|^language$/i }).first();
  await localePicker.click();
  await page.getByRole('menuitem', { name: /^en\b/i }).first().click();
  await expect(localePicker).toContainText(/en/i);
  await listScoped;
  await productScoped;
  await page.waitForTimeout(500);

  // Edit a variant attribute and save → PATCH must carry ?locale=en.
  await page.getByRole('button', { name: /^(edytuj warianty|edit variants)$/i }).click();
  await page.getByRole('button', { name: /rozwiń wszystkie|expand all/i }).click();
  // #1351 — the edit-first page header carries a name <input>, so "first
  // visible input" would hit the header. Anchor to the variant card.
  const variantCard = page
    .getByText(`${sku}-red`)
    .first()
    .locator('xpath=ancestor::div[.//input[@type="text"] or .//textarea][1]');
  const firstInput = variantCard.locator('input[type="text"], textarea').first();
  await firstInput.fill(`EN ${sku}`);

  const patchScoped = page.waitForResponse(
    (r) => /\/api\/objects\/[^?]+\?.*locale=en/.test(r.url()) && r.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: /^(zapisz|save)$/i }).click();
  const patch = await patchScoped;
  expect(patch.status()).toBeLessThan(400);
});
