import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1209 — category assignment for custom kinds via the universal detail page.
 *
 * The CategoriesPanel used to render a read-only "UP-07 follow-up" placeholder;
 * it now wires the generalised CategoryPickerDialog against the poly-kind
 * `/api/objects/{id}/categories` endpoint with ADR-015 tree scoping.
 *
 * Full setup (the operator's actual case): a categorizable CUSTOM ObjectType +
 * a category in its tree + a custom object, then drive the picker UI.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other UI specs.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('a custom kind can assign a category via the universal detail picker', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

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
  const catName = `E2E Mycie ${ts}`;

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

  // 2. A category in the custom kind's tree (ADR-015).
  const typesResp = await page.request.get('/api/object_types', { headers: bearer });
  const typesBody = (await typesResp.json()) as {
    member?: Array<{ id: string; kind: string }>;
    'hydra:member'?: Array<{ id: string; kind: string }>;
  };
  const types = typesBody.member ?? typesBody['hydra:member'] ?? [];
  const categoryOtId = types.find((t) => t.kind === 'category')?.id;
  if (categoryOtId === undefined) throw new Error('Built-in category ObjectType not found.');

  const catResp = await page.request.post('/api/categories', {
    headers: ld,
    data: {
      code: `SVCCAT-${ts}`,
      objectTypeId: categoryOtId,
      categoryTargetObjectTypeId: otId,
      attributes: { name: catName },
    },
  });
  expect(catResp.status(), await catResp.text()).toBe(201);

  // 3. A custom-kind object.
  const objResp = await page.request.post('/api/objects', {
    headers: ld,
    data: { code: `SVC-OBJ-${ts}`, objectTypeId: otId, attributes: {} },
  });
  expect(objResp.status(), await objResp.text()).toBe(201);
  const objId = ((await objResp.json()) as { id: string }).id;

  // 4. Drive the universal detail page (custom kind → UniversalDetailPage).
  await page.goto(`/objects/${slug}/${objId}`);
  await page.getByRole('tab', { name: /kategorie/i }).click();

  // Unified CategoriesTab (#1348/#1351): empty state shows "+ Przypisz
  // kategorie", non-empty "+ Edytuj kategorie" — accept both.
  const editButton = page
    .getByRole('button', { name: /przypisz kategorie|edytuj kategorie|assign|edit categor/i })
    .first();
  await expect(editButton).toBeVisible();
  await expect(page.getByText(/UP-07 follow-upie/i)).toHaveCount(0);

  // 5. Picker opens, lists the tree-scoped category, assign it.
  await editButton.click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText(catName)).toBeVisible();

  const putResponse = page.waitForResponse(
    (r) => r.url().includes(`/api/objects/${objId}/categories`) && r.request().method() === 'PUT',
  );
  await dialog.getByRole('checkbox').first().check();
  await dialog.getByRole('button', { name: /^zapisz$/i }).click();
  expect((await putResponse).status()).toBe(200);

  // 6. The assigned category surfaces as a chip.
  await expect(page.getByText(`SVCCAT-${ts}`).first()).toBeVisible();
});
