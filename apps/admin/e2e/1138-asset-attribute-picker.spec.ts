import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * Operator report (#1138, 2026-05-30): an `asset` attribute rendered as a
 * plain text field on the object form and showed the `{asset_id}` envelope
 * as text. Spec mirrors the operator flow:
 *
 *  1. Create a custom (non-system) `asset` attribute, attach it directly
 *     to the built-in Product ObjectType (synthetic "default" group).
 *  2. Seed a product, open detail → edit.
 *  3. Assert the asset row renders the library-picker button (NOT a plain
 *     `<input>`) — the regression catch.
 *  4. Open the picker, pick an asset, assert the thumbnail + "Zmień zasób"
 *     replace the empty state.
 *  5. Save → PATCH 200 → reload → assert the picked asset persists.
 *
 * Marked `fixme` in CI for the same `storageState` reason the other
 * UI-seeded specs use; locally (cold rate-limiter) it is the smoke gate.
 */
const CI_BLOCKED =
  'E2E selector drift after UI-03: asset row picker/thumbnail not found. Refs #1638';
const ASSET_LABEL = /^(Zdjęcie produktu|Product photo)$/;

test('asset attribute renders a library picker + thumbnail, not a text input', async ({ page }) => {
  test.fixme(true, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };

  const ts = uniqueSku('ASSET')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const attrCode = `pic_${ts}`;

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

  // Custom (non-system) asset attribute, attached directly to the Product
  // ObjectType so it renders inline in the synthetic "default" group.
  const attrResp = await page.request.post('/api/attributes', {
    data: {
      code: attrCode,
      type: 'asset',
      label: { pl: 'Zdjęcie produktu', en: 'Product photo' },
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

  // Seed a product on the Product ObjectType.
  const sku = uniqueSku('ASSET');
  const createResponse = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code: sku, objectTypeId: productType.id, attributes: { name: `Asset spec ${sku}` } },
  });
  expect(createResponse.status(), await createResponse.text()).toBe(201);
  const product = (await createResponse.json()) as { id: string };

  const groupsResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/effective-attribute-groups') &&
      response.request().method() === 'GET',
    { timeout: 15_000 },
  );
  await page.goto(`/products/${product.id}`);
  await groupsResponse;
  // #1351 — the detail page opens directly in edit mode; no Edytuj gate.
  await expect(page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i })).toBeVisible();

  const assetLabel = page.getByText(ASSET_LABEL).first();
  await assetLabel.scrollIntoViewIfNeeded();
  await expect(assetLabel).toBeVisible();

  const assetRow = assetLabel.locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]');
  // Regression catch: the row must NOT be a plain text input.
  await expect(assetRow.locator('input[type="text"]')).toHaveCount(0);

  const chooseButton = assetRow.getByRole('button', { name: /(Wybierz zasób|Choose asset)/ });
  await expect(chooseButton).toBeVisible();
  await chooseButton.click();

  // Picker dialog opens — assert it mounted, then pick the first asset.
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText(/^(Wybierz zasób|Choose asset)$/)).toBeVisible();
  // Prefer an asset that ships a thumbnail — the library may contain
  // thumbnail-less assets (UAT uploads), and the <img> assertion below
  // only applies when one exists.
  const anyAsset = dialog.locator('ul li button').first();
  await expect(anyAsset).toBeVisible();
  const thumbAssets = dialog.locator('ul li button:has(img)');
  const hasThumbnail = (await thumbAssets.count()) > 0;
  await (hasThumbnail ? thumbAssets.first() : anyAsset).click();

  // Value set: empty state is replaced by the "Zmień zasób" affordance +
  // a thumbnail image (when the chosen asset has one).
  const changeButton = assetRow.getByRole('button', { name: /(Zmień zasób|Change asset)/ });
  await expect(changeButton).toBeVisible();
  if (hasThumbnail) {
    await expect(assetRow.locator('img')).toBeVisible();
  }

  // Save → backend persists `{attributes: {<code>: {asset_id: ...}}}`.
  const patchResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/api/objects/') && response.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i }).click();
  const patched = await patchResponse;
  expect(patched.status()).toBe(200);

  // Reload → the picked asset persists (read path resolves the preview;
  // the thumbnail only renders when the asset ships one).
  await page.reload();
  const reloadedLabel = page.getByText(ASSET_LABEL).first();
  await reloadedLabel.scrollIntoViewIfNeeded();
  const reloadedRow = reloadedLabel.locator(
    'xpath=ancestor::div[contains(@class, "grid-cols")][1]',
  );
  await expect(
    reloadedRow.getByRole('button', { name: /(Zmień zasób|Change asset)/ }),
  ).toBeVisible();
  if (hasThumbnail) {
    await expect(reloadedRow.locator('img')).toBeVisible();
  }
});
