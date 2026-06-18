import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1262 — a select option's label is part of the VALUE, so it must follow
 * the active value locale (the PL/EN toolbar), not the interface language.
 *
 *  1. Create a select attribute with per-locale option labels
 *     (red → Czerwony / Red), attach to Product, seed a product with color=red.
 *  2. Open detail under PL → the read-only value shows the Polish label.
 *  3. Switch the locale picker to EN → the same value now shows the English
 *     label (proving the option label re-resolves by value locale).
 *
 * `test.fixme` in CI for the shared auth-rate-limiter storageState gap
 * (same rationale as products-channel-switch.spec.ts).
 */
const CI_BLOCKED = 'E2E selector/behaviour drift on per-locale select option labels. Refs #1638';

test('select option label follows the value locale, not the UI language', async ({ page }) => {
  test.fixme(true, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);
  const refresh = await page.request.post('/api/auth/refresh');
  expect(refresh.status()).toBe(200);
  const token = ((await refresh.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${token}` };

  const ts = uniqueSku('SEL')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const attrCode = `selcolor_${ts}`;

  const otResp = await page.request.get('/api/object_types', { headers: bearer });
  const otBody = (await otResp.json()) as {
    member?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
  };
  const productType = (otBody.member ?? []).find((t) => t.kind === 'product' && t.codeImmutable);
  if (productType === undefined) {
    throw new Error('Built-in product ObjectType not found — demo seeder did not run.');
  }

  // Select attribute with per-locale option labels.
  const attrResp = await page.request.post('/api/attributes', {
    data: {
      code: attrCode,
      type: 'select',
      label: { pl: 'Kolor spec', en: 'Spec colour' },
      required: false,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
  });
  expect(attrResp.status(), await attrResp.text()).toBe(201);
  const attrId = ((await attrResp.json()) as { id: string }).id;

  const optResp = await page.request.post(`/api/attributes/${attrCode}/options`, {
    data: { code: 'red', label: { pl: 'Czerwony', en: 'Red' } },
    headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
  });
  expect([200, 201]).toContain(optResp.status());

  const attach = await page.request.post(
    `/api/object_types/${productType.id}/attributes/${attrId}`,
    { headers: bearer },
  );
  expect([200, 201, 204]).toContain(attach.status());

  const sku = uniqueSku('SEL');
  const createResponse = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: {
      code: sku,
      objectTypeId: productType.id,
      attributes: { name: `Select spec ${sku}`, [attrCode]: { option_code: 'red' } },
    },
  });
  expect(createResponse.status(), await createResponse.text()).toBe(201);
  const product = (await createResponse.json()) as { id: string };

  await page.goto(`/products/${product.id}`);

  // Read-only view under the default (PL) locale → Polish option label.
  const fieldRow = () =>
    page
      .getByText(/^(Kolor spec|Spec colour)$/)
      .first()
      .locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]');
  await fieldRow().scrollIntoViewIfNeeded();
  await expect(fieldRow().getByText('Czerwony')).toBeVisible();

  // Switch the locale picker to EN — the option label re-resolves to English.
  await page.getByRole('button', { name: /^(język|language)$/i }).click();
  await page.getByRole('menuitem', { name: /en/i }).click();

  await expect(fieldRow().getByText('Red')).toBeVisible();
  await expect(fieldRow().getByText('Czerwony')).toHaveCount(0);
});
