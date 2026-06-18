import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1147/#1155 (channels) — selecting a channel in the object detail must
 * really switch the channel-scoped values, mirroring the locale axis.
 *
 *  1. Create a scopable attribute + a channel, attach the attr to Product.
 *  2. Seed a product, open detail → edit under "All channels" (global), save.
 *  3. Switch the picker to the channel — the field shows the global value
 *     as fallback; overwrite it with a channel value, save.
 *  4. Switch back to "All channels" — the global value is intact + distinct.
 *
 * `test.fixme` in CI for the shared auth-rate-limiter storageState gap.
 */
const CI_BLOCKED = 'E2E selector drift after UI-03 on per-channel value read/write. Refs #1638';
const FIELD_LABEL = /^(Cena kanału|Channel price)$/;

test('channel switch reads + writes per-channel values', async ({ page }) => {
  test.fixme(true, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);
  const refresh = await page.request.post('/api/auth/refresh');
  expect(refresh.status()).toBe(200);
  const token = ((await refresh.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${token}` };

  const ts = uniqueSku('CH')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const attrCode = `chprice_${ts}`;
  const channelCode = `spec_${ts}`;
  // Unique display name — earlier runs may have left same-named channels.
  const channelLabel = `Kanał Spec ${ts}`;

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

  // Scopable attribute attached to the Product ObjectType.
  const attrResp = await page.request.post('/api/attributes', {
    data: {
      code: attrCode,
      type: 'text',
      label: { pl: 'Cena kanału', en: 'Channel price' },
      scopable: true,
      required: false,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
  });
  expect(attrResp.status(), await attrResp.text()).toBe(201);
  const attrId = ((await attrResp.json()) as { id: string }).id;
  const attach = await page.request.post(
    `/api/object_types/${productType.id}/attributes/${attrId}`,
    { headers: bearer },
  );
  expect([200, 201, 204]).toContain(attach.status());

  // A tenant channel — just code + name (#1283 dropped currencies, #1318 dropped locales).
  const channelResp = await page.request.post('/api/channels', {
    data: {
      code: channelCode,
      name: channelLabel,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
  });
  expect(channelResp.status(), await channelResp.text()).toBe(201);

  const sku = uniqueSku('CH');
  const createResponse = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code: sku, objectTypeId: productType.id, attributes: { name: `Channel spec ${sku}` } },
  });
  expect(createResponse.status(), await createResponse.text()).toBe(201);
  const product = (await createResponse.json()) as { id: string };

  await page.goto(`/products/${product.id}`);
  // #1351 — the detail page opens directly in edit mode; no Edytuj gate.
  const saveButton = page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i });
  await expect(saveButton).toBeVisible();
  const waitForSave = () =>
    page.waitForResponse(
      (r) => r.url().includes(`/api/objects/${product.id}`) && r.request().method() === 'PATCH',
    );
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

  const fieldRow = () =>
    page
      .getByText(FIELD_LABEL)
      .first()
      .locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]');

  // 1. Edit under "All channels" (global) + save.
  await fieldRow().scrollIntoViewIfNeeded();
  await fieldRow().locator('input[type="text"]').fill('global-99');
  const firstSave = waitForSave();
  await saveButton.click();
  expect((await firstSave).status()).toBe(200);

  // 2. Switch the channel picker to the new channel.
  await page.getByRole('button', { name: /^(kanał|channel)$/i }).click();
  await page.getByRole('menuitem', { name: channelLabel }).click();

  // The channel scope shows the global fallback; overwrite + save.
  await fieldRow().scrollIntoViewIfNeeded();
  const channelInput = fieldRow().locator('input[type="text"]');
  await expect(channelInput).toHaveValue('global-99');
  await channelInput.fill('channel-77');
  const secondSave = waitForSave();
  await saveButton.click();
  expect((await secondSave).status()).toBe(200);
  await expect(fieldRow().locator('input[type="text"]')).toHaveValue('channel-77');

  // 3. Back to "All channels" — global value intact + distinct.
  await page.getByRole('button', { name: /^(kanał|channel)$/i }).click();
  await page.getByRole('menuitem', { name: /(Wszystkie kanały|All channels)/i }).click();
  await expect(fieldRow().locator('input[type="text"]')).toHaveValue('global-99');
});
