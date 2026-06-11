import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1413 — the category picker on the universal create page listed
 * categories from EVERY ObjectType tree, so the operator could pick a
 * foreign category (e.g. a product-tree one) and the create POST failed
 * with an opaque "Nie udało się utworzyć obiektu" toast.
 *
 * CategorySelectorCard now forwards `objectTypeId` to CategoryPickerDialog
 * (ADR-015 tree scoping via `categoryTargetObjectType`), and the create
 * page surfaces the RFC 7807 `detail` when the backend rejects.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other UI specs.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('create-page category picker only lists the ObjectType tree', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(150_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };
  const json = { ...bearer, 'content-type': 'application/json' };
  const ld = { ...bearer, 'content-type': 'application/ld+json' };

  const ts = uniqueSku('SVC')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const slug = `svc_${ts}`;
  const ownCatName = `E2E Wlasna ${ts}`;
  const foreignCatName = `E2E Obca ${ts}`;

  // 1. Categorizable custom ObjectType.
  const otResp = await page.request.post('/api/object_types', {
    headers: json,
    data: { code: slug, label: { pl: `Usługi ${ts}`, en: `Services ${ts}` } },
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const otId = ((await otResp.json()) as { id: string }).id;

  const patchResp = await page.request.patch(`/api/object_types/${otId}`, {
    headers: { ...bearer, 'content-type': 'application/merge-patch+json' },
    data: { isCategorizable: true },
  });
  expect(patchResp.status()).toBe(200);

  // 2. ObjectType ids needed for the two category trees.
  const typesResp = await page.request.get('/api/object_types', { headers: bearer });
  const typesBody = (await typesResp.json()) as {
    member?: Array<{ id: string; kind: string }>;
    'hydra:member'?: Array<{ id: string; kind: string }>;
  };
  const types = typesBody.member ?? typesBody['hydra:member'] ?? [];
  const categoryOtId = types.find((t) => t.kind === 'category')?.id;
  const productOtId = types.find((t) => t.kind === 'product')?.id;
  if (categoryOtId === undefined) throw new Error('Built-in category ObjectType not found.');
  if (productOtId === undefined) throw new Error('Built-in product ObjectType not found.');

  // 3. One category in the custom kind's tree, one in the product tree.
  const ownCatResp = await page.request.post('/api/categories', {
    headers: ld,
    data: {
      code: `OWNCAT-${ts}`,
      objectTypeId: categoryOtId,
      categoryTargetObjectTypeId: otId,
      attributes: { name: ownCatName },
    },
  });
  expect(ownCatResp.status(), await ownCatResp.text()).toBe(201);

  const foreignCatResp = await page.request.post('/api/categories', {
    headers: ld,
    data: {
      code: `FORCAT-${ts}`,
      objectTypeId: categoryOtId,
      categoryTargetObjectTypeId: productOtId,
      attributes: { name: foreignCatName },
    },
  });
  expect(foreignCatResp.status(), await foreignCatResp.text()).toBe(201);

  // 4. Open the create page and the category picker.
  await page.goto(`/objects/${slug}/new`);
  const pickerButton = page.getByRole('button', {
    name: /przypisz kategorię|edytuj kategorie/i,
  });
  await expect(pickerButton).toBeVisible({ timeout: 15_000 });

  const categoriesRequest = page.waitForRequest(
    (r) => r.url().includes('/api/categories') && r.method() === 'GET',
  );
  await pickerButton.click();
  // The picker query must be tree-scoped (ADR-015).
  expect((await categoriesRequest).url()).toContain(
    `categoryTargetObjectType=${encodeURIComponent(otId)}`,
  );

  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText(ownCatName)).toBeVisible();
  await expect(dialog.getByText(foreignCatName)).toHaveCount(0);

  // 5. Assign the in-tree category and create — POST succeeds end-to-end.
  await dialog.getByRole('checkbox').first().check();
  await dialog.getByRole('button', { name: /^zapisz$/i }).click();

  // #1415 unified create: fill the code ("Kod") + name inputs.
  await page.getByPlaceholder(/^(id|kod|sku)$/i).fill(`OBJ-${ts}`);
  await page.getByPlaceholder(/^nazwa$|nazwa produktu/i).fill(`Obj ${ts}`);
  const createResponse = page.waitForResponse(
    (r) => r.url().endsWith('/api/objects') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: /^utwórz$/i }).click();
  expect((await createResponse).status()).toBe(201);

  // Cleanup OT (cascades).
  await page.request.delete(`/api/object_types/${otId}`, { headers: bearer });
});
