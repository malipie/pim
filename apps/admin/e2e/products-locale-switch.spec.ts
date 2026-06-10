import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * Operator report (#1146/#1150, 2026-05-30): selecting a language version
 * in the object detail did nothing. Spec exercises the end-to-end flow:
 *
 *  1. Create a localizable attribute, attach it to the Product ObjectType.
 *  2. Seed a product, open detail → edit.
 *  3. Edit the localizable field under the default locale (pl), save.
 *  4. Switch the picker to EN — the field shows the pl value as fallback;
 *     overwrite it with an EN value, save.
 *  5. Switch back to PL — the pl value is intact (distinct from EN).
 *
 * Also asserts the picker is populated from the tenant's real locales
 * (not the old hardcoded pl/en/de/cs).
 *
 * `test.fixme` in CI for the shared auth-rate-limiter storageState gap.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';
const FIELD_LABEL = /^(Tytuł lokalny|Local title)$/;

test('locale switch reads + writes per-locale values', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const token = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${token}` };

  const ts = uniqueSku('LOC')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const attrCode = `loctitle_${ts}`;

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

  // Localizable attribute, attached directly to the Product ObjectType.
  const attrResp = await page.request.post('/api/attributes', {
    data: {
      code: attrCode,
      type: 'text',
      label: { pl: 'Tytuł lokalny', en: 'Local title' },
      localizable: true,
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

  const sku = uniqueSku('LOC');
  const createResponse = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code: sku, objectTypeId: productType.id, attributes: { name: `Locale spec ${sku}` } },
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

  // 1. Edit under the default locale (pl) + save.
  await fieldRow().scrollIntoViewIfNeeded();
  const plInput = fieldRow().locator('input[type="text"]');
  await plInput.fill('Wartość PL');
  const firstSave = waitForSave();
  await saveButton.click();
  expect((await firstSave).status()).toBe(200);

  // Picker is populated from the tenant's real locales (EN present).
  await page.getByRole('button', { name: /^(język|language)$/i }).click();
  // Items render as "<code> <label>" (e.g. "en English") — match the code
  // prefix so the assertion is independent of the label language.
  const enItem = page.getByRole('menuitem', { name: /^en\b/i });
  await expect(enItem).toBeVisible();
  await enItem.click();

  // 2. EN shows the pl fallback; overwrite with an EN value + save.
  await fieldRow().scrollIntoViewIfNeeded();
  const enInput = fieldRow().locator('input[type="text"]');
  await expect(enInput).toHaveValue('Wartość PL'); // fallback to global/pl
  await enInput.fill('Value EN');
  const secondSave = waitForSave();
  await saveButton.click();
  expect((await secondSave).status()).toBe(200);
  await expect(fieldRow().locator('input[type="text"]')).toHaveValue('Value EN');

  // 3. Switch back to PL — the pl value is intact + distinct from EN.
  await page.getByRole('button', { name: /^(język|language)$/i }).click();
  await page.getByRole('menuitem', { name: /^pl\b/i }).click();
  await expect(fieldRow().locator('input[type="text"]')).toHaveValue('Wartość PL');
});
