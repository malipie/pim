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
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('product detail opens in edit mode with save + save-and-return actions', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
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
