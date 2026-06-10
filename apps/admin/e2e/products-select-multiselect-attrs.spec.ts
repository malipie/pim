import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * Operator report (2026-05-12): "atrybuty z predefiniowanymi
 * wartościami (Tagi, Kolor, Rozmiar) muszą się wyświetlać jako
 * select / multiselect w karcie produktu — aktualnie muszę wpisywać
 * wartości z palca". Spec exercises the full round-trip:
 *
 *  1. Seed a product on the built-in Product ObjectType (which already
 *     has `color` and `tags` attached via the demo seeder).
 *  2. Open the detail page, switch to edit mode.
 *  3. Assert that the Kolor row renders the searchable Combobox and
 *     that the Tagi row renders the chip-style MultiSelect — not a
 *     plain `<input>` (the regression catch).
 *  4. Pick one option in each, save, reload.
 *  5. Assert the picker remembers the selections and the read-only
 *     read-out shows the localized labels (e.g. "Nowość"), never the
 *     raw codes (`new`).
 *
 * Marked `fixme` in CI for the same `storageState` reason the other
 * UI-seeded specs use; locally (cold rate-limiter) it is the smoke
 * gate operators rely on.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('product detail renders Combobox/MultiSelect for select-like attributes', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const authHeaders = { Authorization: `Bearer ${accessToken}` };

  const sku = uniqueSku('SEL');

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

  // The seeded `color`/`tags` attributes drifted out of the product
  // ObjectType during operator UAT — create dedicated select/multiselect
  // attributes with options so the spec is self-sufficient.
  const ts = sku.toLowerCase().replace(/[^a-z0-9]/g, '_');
  const selCode = `sel_kolor_${ts}`;
  const multiCode = `sel_tagi_${ts}`;
  for (const [code, type, label] of [
    [selCode, 'select', { pl: 'Kolor SM', en: 'Color SM' }],
    [multiCode, 'multiselect', { pl: 'Tagi SM', en: 'Tags SM' }],
  ] as const) {
    const attrResp = await page.request.post('/api/attributes', {
      headers: { ...authHeaders, 'content-type': 'application/ld+json' },
      data: { code, type, label },
    });
    expect(attrResp.status(), await attrResp.text()).toBe(201);
    const createdAttr = (await attrResp.json()) as { id: string };
    const attachResp = await page.request.post(
      `/api/object_types/${productType.id}/attributes/${createdAttr.id}`,
      { headers: authHeaders },
    );
    expect([200, 201, 204]).toContain(attachResp.status());
  }
  for (const [attrCode, optCode, optLabel] of [
    [selCode, 'red', { pl: 'Czerwony', en: 'Red' }],
    [selCode, 'blue', { pl: 'Niebieski', en: 'Blue' }],
    [multiCode, 'new', { pl: 'Nowość', en: 'New' }],
    [multiCode, 'promo', { pl: 'Promocja', en: 'Promo' }],
  ] as const) {
    const optionResp = await page.request.post(`/api/attributes/${attrCode}/options`, {
      headers: { ...authHeaders, 'content-type': 'application/json' },
      data: { code: optCode, label: optLabel },
    });
    expect([200, 201]).toContain(optionResp.status());
  }

  const createResponse = await page.request.post('/api/products', {
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: {
      code: sku,
      objectTypeId: productType.id,
      attributes: { name: `Select-Multi spec ${sku}` },
    },
  });
  expect(createResponse.status()).toBe(201);
  const product = (await createResponse.json()) as { id: string };

  // Wait for the effective groups call to settle on initial mount.
  // The endpoint also ships `options` for select/multiselect attributes
  // which is the backend half of this PR.
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

  // Synthetic "default" group is appended last by the controller, so
  // expand-all is needed even though the detail page expands all groups
  // by default. Scroll the page bottom to mount the synthetic card.
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

  const kolorLabel = page.getByText(/^(Kolor SM|Color SM)$/).first();
  await kolorLabel.scrollIntoViewIfNeeded();
  await expect(kolorLabel).toBeVisible();

  // Single-select (Kolor): Combobox button shows the "Wybierz…"
  // placeholder until a value is chosen. Locate by aria-haspopup so
  // the selector stays stable after a value has been picked.
  const kolorRow = kolorLabel.locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]');
  const kolorTrigger = kolorRow.locator('button[aria-haspopup="listbox"]');
  await expect(kolorTrigger).toBeVisible();
  await kolorTrigger.click();
  await page.getByRole('button', { name: /^(Czerwony|Red)$/ }).click();
  await expect(kolorTrigger).toContainText(/Czerwony|Red/);

  // Multiselect (Tagi): trigger is a div role=button hosting chips.
  const tagiLabel = page.getByText(/^(Tagi SM|Tags SM)$/).first();
  await tagiLabel.scrollIntoViewIfNeeded();
  const tagiRow = tagiLabel.locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]');
  const tagiTrigger = tagiRow.locator('[role="button"][aria-haspopup="listbox"]');
  await expect(tagiTrigger).toBeVisible();
  await tagiTrigger.click();
  await page.getByRole('button', { name: /^(Nowość|New)$/ }).click();
  // Close popover by clicking the trigger again; chip stays inside.
  await tagiTrigger.click();
  await expect(tagiTrigger).toContainText(/Nowość|New/);

  // Save → backend persists `{attributes: {color: "red", tags: ["new"]}}`.
  const patchResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/api/objects/') && response.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i }).click();
  const patched = await patchResponse;
  expect(patched.status()).toBe(200);

  // Reload + assert read-only display now shows localized option
  // labels (not raw codes) — this is the round-trip the read path
  // was missing entirely before the fix.
  await page.reload();
  const reloadedKolorLabel = page.getByText(/^(Kolor SM|Color SM)$/).first();
  await reloadedKolorLabel.scrollIntoViewIfNeeded();
  await expect(
    reloadedKolorLabel
      .locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]')
      .getByText(/^(Czerwony|Red)$/),
  ).toBeVisible();
  const reloadedTagiLabel = page.getByText(/^(Tagi SM|Tags SM)$/).first();
  await reloadedTagiLabel.scrollIntoViewIfNeeded();
  await expect(
    reloadedTagiLabel
      .locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]')
      .getByText(/^(Nowość|New)$/),
  ).toBeVisible();
});
