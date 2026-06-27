import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1351 — the product detail page opens directly in edit mode (no
 * read-only "Edytuj" toggle), "Zapisz zmiany" is always visible, and a
 * new "Zapisz i wróć do listy" action saves and returns to the list
 * while plain "Zapisz zmiany" keeps the row in edit mode.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason.
 */

test('product detail opens in edit mode with save + save-and-return actions', async ({ page }) => {
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const objectTypesResponse = await page.request.get('/api/object_types?itemsPerPage=200', {
    headers: { ...bearer, accept: 'application/ld+json' },
  });
  const types =
    (
      (await objectTypesResponse.json()) as {
        member?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
      }
    ).member ?? [];
  const productType = types.find((t) => t.kind === 'product' && t.codeImmutable);
  if (productType === undefined) throw new Error('Built-in product ObjectType not seeded.');

  const sku = `ED1351-${Date.now().toString(36).toUpperCase()}`;
  const created = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    // #1350 — `sku` is a required attribute on the demo tenant; an empty
    // value would block "Zapisz i wróć do listy" by design.
    data: {
      code: sku,
      objectTypeId: productType.id,
      attributes: { name: `Edit mode ${sku}`, sku },
    },
  });
  expect(created.status()).toBe(201);
  const product = (await created.json()) as { id: string };

  // #1673 — group-required attributes (e.g. demo product's description/price)
  // now block edit-mode saves too, not just globally-required ones. Fill every
  // required field (global OR group-level) up front so the save-and-return
  // assertion exercises the navigation, not the new completeness gate.
  const groupsResp = await page.request.get(
    `/api/objects/${product.id}/effective-attribute-groups`,
    { headers: { ...bearer, accept: 'application/json' } },
  );
  const groupsBody = (await groupsResp.json()) as {
    groups: Array<{
      attributes: Array<{
        code: string;
        type: string;
        is_required?: boolean;
        is_required_in_group?: boolean;
      }>;
    }>;
  };
  const gapFill: Record<string, unknown> = {};
  for (const group of groupsBody.groups) {
    for (const attr of group.attributes) {
      const required = attr.is_required === true || attr.is_required_in_group === true;
      if (!required || attr.type === 'boolean') continue;
      if (attr.code === 'sku' || attr.code === 'name') continue; // filled at create
      gapFill[attr.code] =
        attr.type === 'price'
          ? { amount: 1, currency: 'PLN' }
          : attr.type === 'number' || attr.type === 'metric'
            ? 1
            : '1';
    }
  }
  if (Object.keys(gapFill).length > 0) {
    const fillResp = await page.request.patch(`/api/objects/${product.id}`, {
      headers: { ...bearer, 'content-type': 'application/merge-patch+json' },
      data: { attributes: gapFill },
    });
    expect(fillResp.status(), await fillResp.text()).toBe(200);
  }

  await page.goto(`/products/${product.id}`);

  // Edit mode by default — "Zapisz zmiany" visible, no "Edytuj".
  await expect(page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i })).toBeVisible({
    timeout: 15_000,
  });
  await expect(page.getByRole('button', { name: /^(edytuj|edit)$/i })).toHaveCount(0);

  // "Zapisz i wróć do listy" navigates back to the product list.
  const saveAndReturn = page.getByRole('button', {
    name: /zapisz i wróć do listy|save and return/i,
  });
  await expect(saveAndReturn).toBeVisible();
  await saveAndReturn.click();
  await expect(page).toHaveURL(/\/products$/, { timeout: 15_000 });

  await page.request.delete(`/api/products/${product.id}`, { headers: bearer });
});

/**
 * Regression: the header name <Input> is controlled by `nameValue`. In edit
 * mode `nameValue` previously read only the loaded `attrs.name` and ignored
 * `dirtyFields.name`, so keystrokes were immediately overwritten on re-render
 * and the field appeared locked. It must now reflect typed input and persist.
 */
test('product name is editable in the header and persists', async ({ page }) => {
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const objectTypesResponse = await page.request.get('/api/object_types?itemsPerPage=200', {
    headers: { ...bearer, accept: 'application/ld+json' },
  });
  const types =
    (
      (await objectTypesResponse.json()) as {
        member?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
      }
    ).member ?? [];
  const productType = types.find((t) => t.kind === 'product' && t.codeImmutable);
  if (productType === undefined) throw new Error('Built-in product ObjectType not seeded.');

  const sku = `NM1351-${Date.now().toString(36).toUpperCase()}`;
  const created = await page.request.post('/api/products', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: { code: sku, objectTypeId: productType.id, attributes: { name: `Before ${sku}`, sku } },
  });
  expect(created.status()).toBe(201);
  const product = (await created.json()) as { id: string };

  await page.goto(`/products/${product.id}`);

  const nameInput = page.getByRole('textbox', { name: /nazwa produktu|product name/i });
  await expect(nameInput).toBeVisible({ timeout: 15_000 });
  await expect(nameInput).toHaveValue(`Before ${sku}`);

  // Core regression: typed value must stick in the controlled input.
  const newName = `After ${sku}`;
  await nameInput.fill(newName);
  await expect(nameInput).toHaveValue(newName);

  // Persist via the merge-patch path and confirm it round-trips.
  const patch = await page.request.patch(`/api/objects/${product.id}`, {
    headers: { ...bearer, 'content-type': 'application/merge-patch+json' },
    data: { attributes: { name: newName } },
  });
  expect(patch.status(), await patch.text()).toBe(200);

  await page.reload();
  await expect(page.getByRole('textbox', { name: /nazwa produktu|product name/i })).toHaveValue(
    newName,
    { timeout: 15_000 },
  );

  await page.request.delete(`/api/products/${product.id}`, { headers: bearer });
});
