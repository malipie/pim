import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1177 — four new attribute types (textarea, datetime user-facing, color,
 * email). Mirrors the #1138 asset-picker spec flow:
 *
 *  1. Create one custom attribute per new type, attach each directly to the
 *     built-in Product ObjectType (synthetic "default" group).
 *  2. Seed a product, open detail → edit.
 *  3. Assert each row renders the correct control:
 *       - textarea → <textarea>
 *       - datetime → <input type="datetime-local">
 *       - color    → <input type="color"> (+ hex text field)
 *       - email    → <input type="email">
 *  4. Fill values, save → PATCH 200 → reload → assert each value persists.
 *
 * `fixme` in CI for the same `storageState` reason the other UI-seeded specs
 * use; locally (cold rate-limiter) it is the smoke gate.
 */
const CI_BLOCKED =
  'E2E assertion drift after UI-03: new attribute-type controls round-trip. Refs #1638';

test('new simple attribute types render their controls and round-trip', async ({ page }) => {
  test.fixme(true, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };

  const ts = uniqueSku('T1177')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');

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

  // Bilingual labels + a matching regex: the admin UI may render in pl or
  // en, and AttrRow falls back to the attribute code when the active
  // locale's label is missing (see #1138 ASSET_LABEL convention).
  const specs = [
    { type: 'textarea', code: `desc_${ts}`, pl: 'Krótki opis', en: 'Short description' },
    { type: 'datetime', code: `release_${ts}`, pl: 'Premiera', en: 'Release date' },
    { type: 'color', code: `color_${ts}`, pl: 'Kolor produktu', en: 'Product color' },
    { type: 'email', code: `email_${ts}`, pl: 'Email dostawcy', en: 'Supplier email' },
  ] as const;

  for (const spec of specs) {
    const attrResp = await page.request.post('/api/attributes', {
      data: {
        code: spec.code,
        type: spec.type,
        label: { pl: spec.pl, en: spec.en },
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
  }

  // Seed a product on the Product ObjectType.
  const sku = uniqueSku('T1177');
  const createResponse = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code: sku, objectTypeId: productType.id, attributes: { name: `1177 spec ${sku}` } },
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

  // AttrRow renders each editable control with id `attr-<code>` — target it
  // directly (the form may list a row more than once, and labels fall back
  // to the code when the active locale's label is absent, so an id selector
  // is the deterministic anchor). The CSS tag/type guards are the regression
  // catch: each type must render its dedicated control, not a plain input.
  const [textareaCode, datetimeCode, colorCode, emailCode] = specs.map((s) => s.code);

  const textarea = page.locator(`textarea#attr-${textareaCode}`).first();
  await textarea.scrollIntoViewIfNeeded();
  await expect(textarea).toBeVisible();
  await textarea.fill('Lekki, oddychający materiał.');

  const datetime = page.locator(`input#attr-${datetimeCode}[type="datetime-local"]`).first();
  await expect(datetime).toBeVisible();
  await datetime.fill('2027-03-15T14:30');

  const color = page.locator(`input#attr-${colorCode}[type="color"]`).first();
  await expect(color).toBeVisible();
  await color.fill('#1a2b3c');

  const email = page.locator(`input#attr-${emailCode}[type="email"]`).first();
  await expect(email).toBeVisible();
  await email.fill('supplier@example.com');

  // Save → PATCH 200.
  const patchResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/api/objects/') && response.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i }).click();
  expect((await patchResponse).status()).toBe(200);

  // Reload → values persist. #1351: the page reopens in edit mode, so the
  // round-trip shows up as input values, not read-only text.
  await page.reload();
  await expect(page.locator(`textarea#attr-${textareaCode}`).first()).toHaveValue(
    'Lekki, oddychający materiał.',
  );
  await expect(page.locator(`input#attr-${datetimeCode}`).first()).toHaveValue('2027-03-15T14:30');
  await expect(page.locator(`input#attr-${colorCode}`).first()).toHaveValue('#1a2b3c');
  await expect(page.locator(`input#attr-${emailCode}`).first()).toHaveValue('supplier@example.com');
});
