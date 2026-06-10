import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1179 — `identifier` attribute type with per-ObjectType DB-enforced
 * uniqueness. This spec is the integration proof for the trigger +
 * denormalised columns + partial unique index + application pre-check
 * chain: it runs against the live stack (real Postgres with the migration
 * applied), which the Foundry/schema-mode unit DB cannot exercise.
 *
 *  1. Create an identifier attribute, attach to the built-in Product OT.
 *  2. POST product A with identifier "X" → 201.
 *  3. POST product B with the SAME identifier "X" → 409 (a broken trigger
 *     would let this through, failing the test → guards the whole chain).
 *  4. POST product B with a different identifier "Y" → 201.
 *  5. UI: open product B, edit identifier to "X" (duplicate), save → the
 *     409 surfaces as a toast carrying the server's reason.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other
 * UI-seeded specs.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('identifier value is unique per ObjectType (DB-enforced) and surfaces a clear error', async ({
  page,
}) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };

  const ts = uniqueSku('ID')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const attrCode = `ean_${ts}`;
  const valueX = `EAN-${ts}-X`;
  const valueY = `EAN-${ts}-Y`;

  // Resolve the built-in Product ObjectType.
  const otResp = await page.request.get('/api/object_types', { headers: bearer });
  const otBody = (await otResp.json()) as {
    member?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
    'hydra:member'?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
  };
  const types = otBody.member ?? otBody['hydra:member'] ?? [];
  const productType = types.find((t) => t.kind === 'product' && t.codeImmutable);
  if (productType === undefined) {
    throw new Error('Built-in product ObjectType not found — demo seeder did not run.');
  }

  // Identifier attribute attached to the Product OT.
  const attrResp = await page.request.post('/api/attributes', {
    data: {
      code: attrCode,
      type: 'identifier',
      label: { pl: 'Kod EAN', en: 'EAN code' },
      required: false,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
  });
  expect(attrResp.status(), await attrResp.text()).toBe(201);
  const attrId = ((await attrResp.json()) as { id: string }).id;

  const attachResp = await page.request.post(
    `/api/object_types/${productType.id}/attributes/${attrId}`,
    { headers: bearer },
  );
  expect([200, 201, 204]).toContain(attachResp.status());

  // Product A with identifier X → 201.
  const skuA = uniqueSku('ID-A');
  const createA = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: {
      code: skuA,
      objectTypeId: productType.id,
      attributes: { name: `Identifier A ${skuA}`, [attrCode]: valueX },
    },
  });
  expect(createA.status(), await createA.text()).toBe(201);

  // Product B with the SAME identifier X → 409 (DB-enforced uniqueness).
  const skuB = uniqueSku('ID-B');
  const dup = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: {
      code: skuB,
      objectTypeId: productType.id,
      attributes: { name: `Identifier B ${skuB}`, [attrCode]: valueX },
    },
  });
  expect(dup.status(), await dup.text()).toBe(409);

  // Product B with a DIFFERENT identifier Y → 201.
  const createB = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: {
      code: skuB,
      objectTypeId: productType.id,
      attributes: { name: `Identifier B ${skuB}`, [attrCode]: valueY },
    },
  });
  expect(createB.status(), await createB.text()).toBe(201);
  const productB = (await createB.json()) as { id: string };

  // UI: open product B, change identifier to the duplicate X, save → the
  // 409 surfaces as a toast with the server's reason.
  const groupsResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/effective-attribute-groups') &&
      response.request().method() === 'GET',
    { timeout: 15_000 },
  );
  await page.goto(`/products/${productB.id}`);
  await groupsResponse;
  // #1351 — the detail page opens directly in edit mode; no Edytuj gate.
  await expect(page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i })).toBeVisible();

  const identifierInput = page.locator(`input#attr-${attrCode}`).first();
  await identifierInput.scrollIntoViewIfNeeded();
  await expect(identifierInput).toBeVisible();
  await identifierInput.fill(valueX);

  // Deterministic proof: the save PATCH itself is rejected with 409 (the UI
  // write path enforces uniqueness, not just the create path). The legacy
  // product detail page writes to `/api/products/{id}`; the universal page
  // writes to `/api/objects/{id}` — accept either so the spec is route-agnostic.
  const patchResponse = page.waitForResponse(
    (response) =>
      /\/api\/(products|objects)\//.test(response.url()) && response.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i }).click();
  expect((await patchResponse).status()).toBe(409);

  // The conflict detail bubbles up into the error toast (English backend
  // message), asserted right after the response so the toast is still up.
  await expect(page.getByText(/already assigned to another/i)).toBeVisible({ timeout: 4000 });
});
